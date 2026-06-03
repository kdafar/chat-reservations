<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('User')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                        ->dehydrated(fn ($state) => filled($state)) // don’t overwrite on edit if empty
                        ->required(fn (string $context) => $context === 'create')
                        ->minLength(8),
                ])
                ->columns(3),

            Forms\Components\Section::make('Roles & Scope')
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->label('Roles')
                        ->relationship('roles', 'name') // Spatie relation on User via HasRoles
                        ->multiple()
                        ->preload()
                        ->searchable(),
                    Forms\Components\Select::make('branchLinks')
                        ->label('Branches (scope)')
                        ->relationship('branchLinks', 'name') // requires User::branchLinks() belongsToMany
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->helperText('Assign branches for partner/branch staff scoping. Admins can ignore.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('roles.name')
                    ->label('Roles')
                    ->separator(', ')
                    ->colors([
                        'primary',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, User $record) {
                        if ($record->doctorProfile()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('user_resource.delete_blocked.title'))
                                ->body(__('user_resource.delete_blocked.body'))
                                ->danger()
                                ->persistent()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->before(function (Tables\Actions\DeleteBulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                        $linked = $records->filter(fn (User $u) => $u->doctorProfile()->exists());
                        if ($linked->isNotEmpty()) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('user_resource.delete_blocked.title'))
                                ->body(__('user_resource.delete_blocked.bulk_body', ['count' => $linked->count()]))
                                ->danger()
                                ->persistent()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\UserResource\RelationManagers\LeavesRelationManager::class,
            \App\Filament\Resources\UserResource\RelationManagers\AttendanceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
