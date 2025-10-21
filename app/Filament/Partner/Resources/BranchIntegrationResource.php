<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Concerns\ScopesToActivePartner;
use App\Filament\Partner\Resources\BranchIntegrationResource\Pages;
use App\Models\Branch;
use App\Models\BranchIntegration;
use App\Models\Partner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BranchIntegrationResource extends Resource
{
    use ScopesToActivePartner;

    protected static ?string $model = BranchIntegration::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';

    protected static ?string $navigationGroup = 'Integrations';

    protected static ?int $navigationSort = 50;

    protected static ?string $label = 'Integration';

    protected static ?string $pluralLabel = 'Integrations';

    public static function form(Form $form): Form
    {
        $locale = app()->getLocale();

        return $form->schema([
            Forms\Components\Section::make(__('Connection'))
                ->schema([
                    // Shows the current partner's name, but is disabled
                    Forms\Components\Placeholder::make('partner_name')
                        ->label(__('Partner'))
                        ->content(function (?BranchIntegration $record): string {
                            // On the edit page, get the partner from the record
                            if ($record) {
                                return $record->partner?->name ?? '—';
                            }

                            // On the create page, get the active partner from the session
                            return Partner::find(session('active_partner_id'))?->name ?? '—';
                        }),

                    // The branch dropdown is already filtered to the active partner's branches
                    Forms\Components\Select::make('branch_id')
                        ->label(__('Branch'))
                        ->required()
                        ->options(
                            Branch::query()
                                ->where('partner_id', (int) session('active_partner_id'))
                                ->orderBy("name->$locale")
                                ->pluck('name', 'id')
                        )
                        ->searchable(),

                    Forms\Components\Select::make('provider')
                        ->label(__('Provider'))
                        ->required()
                        ->options([
                            'generic_json' => 'Generic JSON',
                        ])
                        ->native(false),

                    Forms\Components\TextInput::make('api_base_url')
                        ->label(__('API Base URL'))
                        ->required()
                        ->url()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('api_key')
                        ->label(__('API Key'))
                        ->password()
                        ->revealable()
                        ->maxLength(255),

                    Forms\Components\Toggle::make('enabled')
                        ->label(__('Enabled'))
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Settings'))
                ->schema([
                    Forms\Components\KeyValue::make('settings')
                        ->label(__('Extra Settings'))
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->reorderable()
                        ->addButtonLabel(__('Add setting'))
                        ->nullable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->formatStateUsing(fn (BranchIntegration $record) => $record->branch?->name ?? '—'),

                Tables\Columns\TextColumn::make('provider')->label(__('Provider'))->badge(),

                Tables\Columns\TextColumn::make('api_base_url')
                    ->label(__('Base URL'))
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state),

                Tables\Columns\IconColumn::make('enabled')->label(__('Enabled'))->boolean(),

                Tables\Columns\TextColumn::make('updated_at')->since()->label(__('Updated')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\Action::make('syncNow')
                    ->label(__('Sync now'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (BranchIntegration $record) {
                        \App\Jobs\SyncBranchIntegration::dispatch($record->id);
                        \Filament\Notifications\Notification::make()
                            ->title(__('Sync started'))
                            ->body(__('We will import menus for this branch in the background.'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('test')
                    ->label(__('Test connection'))
                    ->icon('heroicon-o-wifi')
                    ->action(function (BranchIntegration $record) {
                        try {
                            $provider = \App\Services\MenuSync\ProviderFactory::make($record->provider);
                            $data = $provider->fetch($record->api_base_url, $record->api_key, 'en');
                            $count = is_array($data) ? count($data) : 0;
                            \Filament\Notifications\Notification::make()
                                ->title(__('Connection OK'))
                                ->body(__('Fetched :n categories (EN).', ['n' => $count]))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('Connection failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulkSync')
                    ->label(__('Sync selected'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(function ($records) {
                        foreach ($records as $r) {
                            \App\Jobs\SyncBranchIntegration::dispatch($r->id);
                        }
                        \Filament\Notifications\Notification::make()
                            ->title(__('Sync queued for :n integrations', ['n' => $records->count()]))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranchIntegrations::route('/'),
            'create' => Pages\CreateBranchIntegration::route('/create'),
            'edit' => Pages\EditBranchIntegration::route('/{record}/edit'),
        ];
    }
}
