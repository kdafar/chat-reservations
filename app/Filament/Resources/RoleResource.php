<?php

namespace App\Filament\Resources;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        // 1. Get all available permissions, grouped by their prefix
        $groupedPermissions = Permission::all()->groupBy(fn ($permission) => Str::before($permission->name, '.'));

        // 2. Create a Fieldset with a CheckboxList for each group
        $permissionFieldsets = $groupedPermissions->map(function ($permissions, $groupName) {
            return Fieldset::make(Str::title($groupName))
                ->schema([
                    CheckboxList::make($groupName.'_permissions')
                        ->label('')
                        ->options($permissions->mapWithKeys(fn ($p) => [$p->id => Str::after($p->name, '.')])->toArray())

                        // 👇 ADD THIS LINE TO GET A "SELECT ALL" CHECKBOX 👇
                        ->bulkToggleable()

                        ->columns(4)
                        ->gridDirection('row'),
                ]);
        })->values()->toArray();

        // The form schema ONLY defines the UI components. No data logic here.
        return $form->schema([
            TextInput::make('name')
                ->label('Role Name')
                ->required()
                ->unique(ignoreRecord: true)
                ->columnSpanFull(),
            ...$permissionFieldsets,
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Role')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('permissions_count')->counts('permissions')->label('Permissions')->sortable(),
            ])
            ->defaultSort('name')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => RoleResource\Pages\ListRoles::route('/'),
            'create' => RoleResource\Pages\CreateRole::route('/create'),
            'edit' => RoleResource\Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
