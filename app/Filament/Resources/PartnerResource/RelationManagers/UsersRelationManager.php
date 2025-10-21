<?php

namespace App\Filament\Resources\PartnerResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Users';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->rule(fn () => Rule::unique('users', 'email')),
            Forms\Components\TextInput::make('phone')->tel()->maxLength(32),
            Forms\Components\TextInput::make('password')
                ->password()
                ->required()
                ->minLength(8)
                ->dehydrateStateUsing(fn ($state) => Hash::make($state)),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\AttachAction::make()
                    ->recordSelectSearchColumns(['name', 'email', 'phone'])
                    ->preloadRecordSelect(),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
                // (Optional) allow editing the user record here:
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
            ]);
    }
}
