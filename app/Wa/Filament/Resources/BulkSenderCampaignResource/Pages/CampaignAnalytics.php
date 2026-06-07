<?php

namespace App\Wa\Filament\Resources\BulkSenderCampaignResource\Pages;

use App\Wa\Filament\Resources\BulkSenderCampaignResource;
use App\Wa\Hub\Models\PromotionalCampaignRecipient;
use App\Wa\Models\PointUsage;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CampaignAnalytics extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = BulkSenderCampaignResource::class;

    protected static string $view = 'filament.resources.bulk-sender-campaign-resource.pages.campaign-analytics';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    public function getTitle(): string|Htmlable
    {
        return 'Analytics: '.$this->record->name;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PromotionalCampaignRecipient::query()
                    ->where('promotional_campaign_id', $this->record->id)
            )
            ->columns([
                // COLUMN 1: Identity (Phone & Name)
                Tables\Columns\TextColumn::make('msisdn')
                    ->label('Recipient')
                    ->searchable(['msisdn', 'name'])
                    ->weight('bold')
                    ->description(fn (PromotionalCampaignRecipient $record) => $record->name ?: '-')
                    ->copyable(),

                // COLUMN 2: Status & Errors (The most important part)
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'slate' => 'pending',
                        'info' => 'sent',
                        'primary' => 'delivered',
                        'success' => 'read',
                        'danger' => ['failed', 'undeliverable', 'experiment_blocked'],
                        'warning' => 'limited',
                    ])
                    ->searchable(['status', 'wa_error_code', 'wa_error_title', 'error_message'])
                    // SHOW ERROR DETAILS DIRECTLY HERE
                    ->description(function (PromotionalCampaignRecipient $record) {
                        if (! in_array($record->status, ['failed', 'undeliverable', 'experiment_blocked'])) {
                            return null;
                        }

                        $parts = [];

                        // 1. Error Code
                        if ($record->wa_error_code) {
                            $parts[] = 'Code: '.$record->wa_error_code;
                        }

                        // 2. Error Title (Category)
                        if ($record->wa_error_title) {
                            $parts[] = $record->wa_error_title;
                        }

                        // 3. Detailed Error Message (Truncated but present)
                        if ($record->error_message) {
                            // If we already have a title, show the message in parens or separated
                            $msg = Str::limit($record->error_message, 80);
                            if ($record->wa_error_title && $record->wa_error_title !== $msg) {
                                $parts[] = "($msg)";
                            } elseif (! $record->wa_error_title) {
                                $parts[] = $msg;
                            }
                        }

                        return implode(' • ', $parts);
                    })
                    ->wrap(),

                // COLUMN 3: The Journey (Timeline in one view)
                Tables\Columns\TextColumn::make('timeline')
                    ->label('Timeline')
                    ->html()
                    ->state(function (PromotionalCampaignRecipient $record) {
                        $lines = [];

                        // Sent
                        if ($record->sent_at) {
                            $lines[] = '<span class="text-xs text-gray-500">📤 '.$record->sent_at->format('H:i:s').'</span>';
                        }

                        // Delivered (with diff)
                        if ($record->delivered_at) {
                            $diff = $record->sent_at ? $record->sent_at->diffForHumans($record->delivered_at, true, true) : '';
                            $lines[] = '<span class="text-xs text-emerald-600 font-medium">📬 '.$record->delivered_at->format('H:i:s').' (+'.$diff.')</span>';
                        }

                        // Read (with diff)
                        if ($record->read_at) {
                            $diff = $record->delivered_at ? $record->delivered_at->diffForHumans($record->read_at, true, true) : '';
                            $lines[] = '<span class="text-xs text-blue-600 font-medium">👁 '.$record->read_at->format('H:i:s').' (+'.$diff.')</span>';
                        }

                        return implode('<br>', $lines);
                    }),

                // COLUMN 4: Technical Meta Data
                Tables\Columns\TextColumn::make('meta_info')
                    ->label('Meta Info')
                    ->html()
                    ->state(function (PromotionalCampaignRecipient $record) {
                        $out = '';
                        if ($record->wa_pricing_model) {
                            $out .= '<span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 mb-1">'.$record->wa_pricing_model.'</span><br>';
                        }
                        if ($record->wa_message_id) {
                            // Truncate ID for display, copy full
                            $short = Str::limit($record->wa_message_id, 15);
                            $out .= '<span class="text-xs font-mono text-gray-400" title="'.$record->wa_message_id.'">ID: '.$short.'</span>';
                        }

                        return $out;
                    })
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'read' => 'Read',
                        'failed' => 'Failed',
                        'undeliverable' => 'Undeliverable',
                        'limited' => 'Limited',
                    ]),

                Tables\Filters\TernaryFilter::make('has_error')
                    ->label('Issues')
                    ->queries(
                        true: fn ($query) => $query->whereIn('status', ['failed', 'undeliverable', 'experiment_blocked']),
                        false: fn ($query) => $query->whereIn('status', ['sent', 'delivered', 'read']),
                    ),

                // NEW: Specific Error Reason Filter
                Tables\Filters\SelectFilter::make('error_reason')
                    ->label('Error Reason')
                    ->options(function () {
                        // Dynamically fetch error reasons present in this campaign
                        return $this->record->recipients()
                            ->whereIn('status', ['failed', 'undeliverable'])
                            ->select(DB::raw('COALESCE(wa_error_title, LEFT(error_message, 50), "Unknown") as reason'))
                            ->distinct()
                            ->pluck('reason', 'reason')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        if (filled($data['value'])) {
                            $val = $data['value'];
                            // Filter by either the title or the raw message start
                            $query->where(function ($q) use ($val) {
                                $q->where('wa_error_title', $val)
                                    ->orWhere('error_message', 'like', $val.'%');
                            });
                        }
                    }),
            ])
            ->defaultSort('id', 'asc')
            ->poll('10s');
    }

    // --- Stats Calculation Helpers ---

    public function getStatusStatsProperty(): array
    {
        $stats = $this->record->recipients()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $total = array_sum($stats);

        $statuses = ['pending', 'sent', 'delivered', 'read', 'failed', 'undeliverable', 'limited', 'experiment_blocked'];
        foreach ($statuses as $status) {
            if (! isset($stats[$status])) {
                $stats[$status] = 0;
            }
        }

        return [
            'raw' => $stats,
            'total' => $total,
            'delivered_rate' => $total > 0 ? round(($stats['delivered'] ?? 0) / $total * 100, 1) : 0,
            'read_rate' => ($stats['delivered'] ?? 0) > 0 ? round(($stats['read'] ?? 0) / ($stats['delivered'] ?? 0) * 100, 1) : 0,
            'failed_rate' => $total > 0 ? round((($stats['failed'] ?? 0) + ($stats['undeliverable'] ?? 0)) / $total * 100, 1) : 0,
        ];
    }

    public function getLatencyMetricsProperty(): array
    {
        // Limit to 2000 for performance
        $rows = $this->record->recipients()
            ->whereIn('status', ['delivered', 'read'])
            ->whereNotNull('sent_at')
            ->whereNotNull('delivered_at')
            ->select('sent_at', 'delivered_at', 'read_at')
            ->limit(2000)
            ->get();

        if ($rows->isEmpty()) {
            return ['delivery' => 'N/A', 'read' => 'N/A'];
        }

        $deliveryTimes = [];
        $readTimes = [];

        foreach ($rows as $row) {
            if ($row->sent_at && $row->delivered_at) {
                $deliveryTimes[] = $row->sent_at->diffInSeconds($row->delivered_at);
            }
            if ($row->delivered_at && $row->read_at) {
                $readTimes[] = $row->delivered_at->diffInSeconds($row->read_at);
            }
        }

        $avgDelivery = count($deliveryTimes) > 0 ? array_sum($deliveryTimes) / count($deliveryTimes) : 0;
        $avgRead = count($readTimes) > 0 ? array_sum($readTimes) / count($readTimes) : 0;

        return [
            'delivery' => $this->humanizeDuration($avgDelivery),
            'read' => $this->humanizeDuration($avgRead),
        ];
    }

    protected function humanizeDuration($seconds): string
    {
        if ($seconds < 1) {
            return '< 1s';
        }
        if ($seconds < 60) {
            return round($seconds, 1).'s';
        }
        if ($seconds < 3600) {
            return round($seconds / 60, 1).'m';
        }

        return round($seconds / 3600, 1).'h';
    }

    public function getFailureAnalysisProperty()
    {
        return $this->record->recipients()
            ->select(
                DB::raw('COALESCE(wa_error_title, error_message, "Unknown Error") as reason'),
                DB::raw('count(*) as count'),
                DB::raw('MAX(wa_error_code) as code')
            )
            ->whereIn('status', ['failed', 'undeliverable'])
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit(20)
            ->get();
    }

    public function getFunnelDataProperty(): array
    {
        $stats = $this->statusStats['raw'];

        $sent = ($stats['sent'] ?? 0) + ($stats['delivered'] ?? 0) + ($stats['read'] ?? 0);
        $delivered = ($stats['delivered'] ?? 0) + ($stats['read'] ?? 0);
        $read = $stats['read'] ?? 0;

        return [
            'sent' => $sent,
            'delivered' => $delivered,
            'read' => $read,
            'drop_off_sent_delivered' => $sent > 0 ? round(($sent - $delivered) / $sent * 100, 1) : 0,
            'drop_off_delivered_read' => $delivered > 0 ? round(($delivered - $read) / $delivered * 100, 1) : 0,
        ];
    }

    public function getPointsStatsProperty(): array
    {
        // HYBRID MAPPING LOGIC:
        // 1. Direct Match: If 'campaign_id' exists in meta (Future-proof)
        // 2. Heuristic Match: Fallback to template name + timestamp if ID is missing (Legacy)

        $totalPoints = PointUsage::query()
            ->where(function (Builder $query) {
                // Future-proof: Match exact campaign ID if stored
                $query->where('meta->campaign_id', $this->record->id)
                    ->orWhere(function (Builder $subQuery) {
                        // Legacy/Fallback: Match by template & time
                        $subQuery->where('meta->template', $this->record->template_name)
                            ->where('created_at', '>=', $this->record->created_at->subMinutes(1))
                            // Important: Only use heuristic if campaign_id is NOT set
                            // This ensures that once we start saving IDs, we rely solely on ID matching
                            // and don't accidentally pick up manual sends or other campaigns.
                            ->whereNull('meta->campaign_id');
                    });
            })
            ->sum('points');

        // Efficiency Metric (Cost per Delivered Message)
        $deliveredCount = $this->statusStats['raw']['delivered'] ?? 0;

        // Avoid division by zero
        $costPerMsg = $deliveredCount > 0
            ? round($totalPoints / $deliveredCount, 2)
            : 0;

        return [
            'total' => (int) $totalPoints,
            'avg_cost' => $costPerMsg,
        ];
    }
}
