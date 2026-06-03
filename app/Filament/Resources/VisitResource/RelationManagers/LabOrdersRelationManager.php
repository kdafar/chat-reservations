<?php

namespace App\Filament\Resources\VisitResource\RelationManagers;

use App\Models\Lab\LabOrder;
use App\Models\Lab\LabOrderItem;
use App\Models\Lab\LabTest;
use App\Models\Visit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Per-visit lab orders. Doctor opens the visit → "Order Tests" creates
 * a LabOrder with the chosen tests; lab tech later opens the same visit
 * → drills into the order → enters results for each line + marks
 * completed when all rows are filled.
 */
class LabOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'labOrders';

    protected static ?string $title = 'Lab Orders';

    protected static ?string $icon = 'heroicon-o-beaker';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Order')
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make('order_code')
                        ->label('Code')
                        ->content(fn (?LabOrder $record) => $record?->order_code ?? 'auto-generated'),

                    Forms\Components\Select::make('status')
                        ->options([
                            LabOrder::STATUS_ORDERED => 'Ordered',
                            LabOrder::STATUS_SAMPLE_COLLECTED => 'Sample Collected',
                            LabOrder::STATUS_IN_PROGRESS => 'In Progress',
                            LabOrder::STATUS_COMPLETED => 'Completed',
                            LabOrder::STATUS_CANCELLED => 'Cancelled',
                        ])
                        ->default(LabOrder::STATUS_ORDERED)
                        ->required(),

                    Forms\Components\Textarea::make('notes')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Tests on this order')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->label('')
                        ->relationship('items')
                        ->columns(4)
                        ->defaultItems(1)
                        ->collapsible()
                        ->itemLabel(fn (array $state) => self::repeaterItemLabel($state))
                        ->schema([
                            Forms\Components\Select::make('lab_test_id')
                                ->label('Test')
                                ->required()
                                ->options(fn () => LabTest::query()
                                    ->where('is_active', true)
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn (LabTest $t) => [$t->id => "{$t->code} — {$t->name}"])
                                    ->all())
                                ->searchable()
                                ->reactive()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if (! $state) return;
                                    $test = LabTest::query()->find($state);
                                    if (! $test) return;
                                    $set('result_unit', $test->unit);
                                    $set('reference_range_snapshot', $test->reference_range);
                                    $set('price_snapshot', (string) $test->default_price);
                                })
                                ->columnSpan(2),

                            Forms\Components\Select::make('status')
                                ->options([
                                    LabOrderItem::STATUS_PENDING => 'Pending',
                                    LabOrderItem::STATUS_IN_PROGRESS => 'In Progress',
                                    LabOrderItem::STATUS_COMPLETED => 'Completed',
                                    LabOrderItem::STATUS_CANCELLED => 'Cancelled',
                                ])
                                ->default(LabOrderItem::STATUS_PENDING),

                            Forms\Components\TextInput::make('price_snapshot')
                                ->label('Price (KWD)')
                                ->numeric()
                                ->step('0.001')
                                ->default(0),

                            Forms\Components\TextInput::make('result_value')
                                ->label('Result')
                                ->maxLength(191)
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('result_unit')
                                ->label('Unit')
                                ->maxLength(32),

                            Forms\Components\Select::make('flag')
                                ->options([
                                    LabOrderItem::FLAG_NORMAL => 'Normal',
                                    LabOrderItem::FLAG_LOW => 'Low',
                                    LabOrderItem::FLAG_HIGH => 'High',
                                    LabOrderItem::FLAG_CRITICAL => 'Critical',
                                ])
                                ->nullable()
                                ->placeholder('—'),

                            Forms\Components\TextInput::make('reference_range_snapshot')
                                ->label('Reference')
                                ->maxLength(191)
                                ->columnSpan(2),

                            Forms\Components\Textarea::make('notes')
                                ->rows(1)
                                ->columnSpan(2),
                        ])
                        ->addActionLabel('Add another test'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order_code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $s) => match ($s) {
                        'ordered' => 'gray',
                        'sample_collected' => 'info',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Tests')
                    ->counts('items'),

                Tables\Columns\TextColumn::make('items_completed')
                    ->label('Done')
                    ->state(fn (LabOrder $r) => $r->items()->where('status', LabOrderItem::STATUS_COMPLETED)->count()),

                Tables\Columns\TextColumn::make('ordered_at')
                    ->label('Ordered')
                    ->dateTime('Y-m-d H:i'),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Order Tests')
                    ->mutateFormDataUsing(function (array $data): array {
                        $visit = $this->getOwnerRecord();
                        $data['visit_id'] = $visit->id;
                        $data['patient_id'] = $visit->patient_id;
                        $data['branch_id'] = $visit->branch_id;
                        $data['doctor_id'] = $visit->doctor_id;
                        $data['ordered_by_user_id'] = (int) (auth()->id() ?? 0) ?: null;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Enter results'),

                Tables\Actions\Action::make('markCompleted')
                    ->label('Mark order completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (LabOrder $r) => $r->status !== LabOrder::STATUS_COMPLETED && $r->status !== LabOrder::STATUS_CANCELLED)
                    ->requiresConfirmation()
                    ->action(function (LabOrder $r) {
                        $pendingCount = $r->items()
                            ->whereIn('status', [LabOrderItem::STATUS_PENDING, LabOrderItem::STATUS_IN_PROGRESS])
                            ->count();
                        if ($pendingCount > 0) {
                            Notification::make()
                                ->title("Cannot complete — {$pendingCount} test(s) still pending")
                                ->warning()->send();
                            return;
                        }
                        $r->forceFill([
                            'status' => LabOrder::STATUS_COMPLETED,
                            'completed_at' => now(),
                        ])->save();
                        Notification::make()->title('Lab order completed')->success()->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No lab orders for this visit')
            ->emptyStateDescription('Order tests for the patient.')
            ->emptyStateIcon('heroicon-o-beaker');
    }

    protected static function repeaterItemLabel(array $state): ?string
    {
        $testId = $state['lab_test_id'] ?? null;
        if (! $testId) {
            return 'New test';
        }
        $test = LabTest::query()->find($testId);
        if (! $test) {
            return "Test #{$testId}";
        }
        $resultBit = ($state['result_value'] ?? null)
            ? ' = '.$state['result_value'].($state['result_unit'] ?? '')
            : '';
        return $test->code.' — '.$test->name.$resultBit;
    }
}
