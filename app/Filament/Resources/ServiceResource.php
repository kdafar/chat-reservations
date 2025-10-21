<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ServiceResource extends Resource
{
    use Translatable;

    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(120)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),

            TextInput::make('slug')
                ->required()
                ->maxLength(160)
                ->unique(ignoreRecord: true),

            FileUpload::make('icon')
                ->label('Icon')
                ->directory('services/icons')
                ->visibility('public')
                ->image()
                ->imageEditor()
                ->nullable(),

            Toggle::make('is_active')->label('Active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('icon')->label('Icon')->square()->toggleable(),

                // 2. Removed hardcoded locale and simplified the column.
                // It will now automatically display the content for the selected locale.
                TextColumn::make('name')
                    ->label('Name')
                    // 3. Updated search to use the dynamic locale.
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('name->'.app()->getLocale(), 'like', "%{$search}%");
                    })
                    ->sortable(),

                TextColumn::make('slug')->searchable()->toggleable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    // 4. Added the required getTranslatableLocales() method.
    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
