<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Services\CheckInService;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

class CheckInScanner extends Page
{
    use HasHelpAction;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    // UI rename only
    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 10;

    protected static ?string $title = null;

    // We’ll render via a custom blade
    protected static string $view = 'filament.pages.check-in-scanner';

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_tools');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.check_in_scanner.nav_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('pages.check_in_scanner.title');
    }

    // Livewire state
    public ?array $result = null;

    public ?string $error = null;

    public ?string $lastScanned = null;

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_check-in-scanner');
    }

    protected function getHeaderActions(): array
    {
        return $this->withHelp([]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.check_in_scanner.what.heading'), 'body' => __('help.pages.check_in_scanner.what.body')],
            ['heading' => __('help.pages.check_in_scanner.how.heading'), 'items' => (array) trans('help.pages.check_in_scanner.how.items')],
            ['heading' => __('help.pages.check_in_scanner.faq.heading'), 'items' => (array) trans('help.pages.check_in_scanner.faq.items')],
        ];
    }

    /**
     * Called from JS/Alpine after a code is scanned.
     * $raw may be the full URL (e.g. https://.../c/{token}) or just the token.
     */
    public function checkIn(string $raw): void
    {
        $this->resetFeedback();

        $token = $this->extractToken($raw);
        if (! $token) {
            $this->error = 'Invalid QR code: token not found.';

            return;
        }

        /** @var CheckInService $svc */
        $svc = app(CheckInService::class);

        try {
            $booking = $svc->checkInByToken($token);

            // Refresh relations for display
            $booking->load(['branch', 'table']);

            // UI keys renamed (DB same)
            $this->result = [
                'appointment_code' => $booking->booking_code,       // DB: booking_code
                'patient_count' => $booking->party_size,         // DB: party_size
                'clinic' => $booking->branch?->localized_name ?? ('#'.$booking->branch_id), // DB: branch_id
                'room' => $booking->table?->only(['id', 'name', 'capacity']), // DB: table_id relation
                'status' => $booking->status,             // DB: status
                'checked_in_at' => optional($booking->checked_in_at)->format('Y-m-d H:i'), // DB: checked_in_at
            ];

            $this->lastScanned = $raw;
        } catch (ValidationException $e) {
            $this->error = collect($e->errors())->flatten()->first() ?? 'Check-in validation failed.';
        } catch (\Throwable $e) {
            // keep it safe for production; don’t leak exception
            $this->error = 'Unexpected error during clinic check-in.';
        }
    }

    public function resetFeedback(): void
    {
        $this->result = null;
        $this->error = null;
    }

    private function extractToken(string $raw): ?string
    {
        $raw = trim($raw);

        // 1) If it's a full URL containing /c/{token}
        if (preg_match('~/(?:c)/([A-Za-z0-9]+)~', $raw, $m)) {
            return $m[1];
        }

        // 2) If it looks like a bare token
        if (preg_match('~^[A-Za-z0-9]{8,}$~', $raw)) {
            return $raw;
        }

        return null;
    }
}
