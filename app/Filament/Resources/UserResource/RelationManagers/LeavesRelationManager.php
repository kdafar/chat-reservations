<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\StaffLeave;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LeavesRelationManager extends RelationManager
{
    protected static string $relationship = 'leaves';

    protected static ?string $title = 'Leaves';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->options([
                    StaffLeave::TYPE_ANNUAL => 'Annual',
                    StaffLeave::TYPE_SICK => 'Sick',
                    StaffLeave::TYPE_MATERNITY => 'Maternity',
                    StaffLeave::TYPE_UNPAID => 'Unpaid',
                    StaffLeave::TYPE_EMERGENCY => 'Emergency',
                    StaffLeave::TYPE_OTHER => 'Other',
                ])
                ->default(StaffLeave::TYPE_ANNUAL)
                ->required(),
            Forms\Components\DatePicker::make('starts_on')->native(false)->required()->default(now()),
            Forms\Components\DatePicker::make('ends_on')->native(false)->required()->default(now())->afterOrEqual('starts_on'),
            Forms\Components\Textarea::make('reason')->rows(2)->maxLength(500)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge()->formatStateUsing(fn (string $s) => ucfirst($s)),
                Tables\Columns\TextColumn::make('starts_on')->date('Y-m-d')->label('From'),
                Tables\Columns\TextColumn::make('ends_on')->date('Y-m-d')->label('To'),
                Tables\Columns\TextColumn::make('days_count')->label('Days')->numeric(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $s) => match ($s) {
                    'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', default => 'gray',
                }),
                Tables\Columns\TextColumn::make('decidedBy.name')->label('Decided by')->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = $this->getOwnerRecord()->id;
                        $data['requested_by_user_id'] = (int) (auth()->id() ?? 0) ?: null;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (StaffLeave $r) => $r->status === StaffLeave::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (StaffLeave $r) {
                        $r->forceFill([
                            'status' => StaffLeave::STATUS_APPROVED,
                            'decided_at' => now(),
                            'decided_by_user_id' => auth()->id(),
                        ])->save();
                        Notification::make()->title('Leave approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (StaffLeave $r) => $r->status === StaffLeave::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('decision_notes')->rows(2)->required()])
                    ->action(function (StaffLeave $r, array $data) {
                        $r->forceFill([
                            'status' => StaffLeave::STATUS_REJECTED,
                            'decision_notes' => $data['decision_notes'],
                            'decided_at' => now(),
                            'decided_by_user_id' => auth()->id(),
                        ])->save();
                        Notification::make()->title('Leave rejected')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
