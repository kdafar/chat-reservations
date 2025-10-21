<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchIntegrationResource\Pages;
use App\Models\Branch;
use App\Models\BranchIntegration;
use App\Models\Partner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BranchIntegrationResource extends Resource
{
    protected static ?string $model = BranchIntegration::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 50;

    protected static ?string $label = 'Branch Integration';

    protected static ?string $pluralLabel = 'Branch Integrations';

    public static function form(Form $form): Form
    {
        $locale = app()->getLocale();

        return $form->schema([
            Forms\Components\Section::make(__('Connection'))
                ->schema([
                    // Virtual Partner selector (not stored) to filter Branch list
                    Forms\Components\Select::make('partner_id')
                        ->label(__('Partner'))
                        ->options(
                            Partner::query()
                                ->orderBy("name->$locale")
                                ->get(['id', 'name'])
                                ->mapWithKeys(fn (Partner $p) => [$p->id => $p->name])
                                ->toArray()
                        )
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(function (Set $set) {
                            // reset branch when partner changes
                            $set('branch_id', null);
                        }),

                    Forms\Components\Select::make('branch_id')
                        ->label(__('Branch'))
                        ->required()
                        ->options(fn (Get $get) => Branch::query()
                            ->when($get('partner_id'), fn (Builder $q, $pid) => $q->where('partner_id', (int) $pid))
                            ->orderBy("name->$locale")
                            ->get(['id', 'name'])
                            ->mapWithKeys(fn (Branch $b) => [$b->id => $b->name])
                            ->toArray()
                        )
                        ->searchable(),

                    Forms\Components\Select::make('provider')
                        ->label(__('Provider'))
                        ->required()
                        ->options([
                            'generic_json' => 'Generic JSON',
                            // add more (e.g. 'light_speed' => 'LightSpeed', ...)
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
                        ->nullable(),
                ]),
        ]);
    }

    public static function fillForm(Model $record): Form
    {
        $form = parent::fillForm($record);

        $data = $record->toArray();

        // If a branch is set, find its partner and set the partner_id in the form
        if ($record->branch) {
            $data['partner_id'] = $record->branch->partner_id;
        }

        return $form->state($data);
    }

    public static function table(Table $table): Table
    {
        $locale = app()->getLocale();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('partner') // Use a virtual, non-nested name
                    ->label(__('Partner'))
                    ->formatStateUsing(fn (BranchIntegration $r) => $r->partner?->name ?? '—')
                    ->sortable(query: function (Builder $q, string $direction) use ($locale) {
                        $q->leftJoin('branches as b', 'b.id', '=', 'branch_integrations.branch_id')
                            ->leftJoin('partners as p', 'p.id', '=', 'b.partner_id')
                            ->orderBy("p.name->$locale", $direction)
                            ->select('branch_integrations.*'); // restore base columns
                    })
                    ->searchable(query: function (Builder $query, string $search) use ($locale): Builder {
                        // Provide a custom query for searching the nested relationship
                        return $query->whereHas('branch.partner', fn ($q) => $q->where("name->$locale", 'like', "%{$search}%")
                        );
                    }),

                // CORRECTED BRANCH COLUMN
                Tables\Columns\TextColumn::make('branch') // Use a virtual, non-nested name
                    ->label(__('Branch'))
                    ->formatStateUsing(fn (BranchIntegration $record) => $record->branch?->name ?? '—'),

                Tables\Columns\TextColumn::make('provider')->label(__('Provider'))->badge(),
                Tables\Columns\TextColumn::make('api_base_url')->label(__('Base URL'))->limit(40)->tooltip(fn (?string $state): ?string => $state),
                Tables\Columns\IconColumn::make('enabled')->label(__('Enabled'))->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->since()->label(__('Updated')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('partner')
                    ->label(__('Partner'))
                    ->options(
                        Partner::query()
                            ->orderBy("name->$locale")
                            ->get(['id', 'name'])
                            ->mapWithKeys(fn (Partner $p) => [$p->id => $p->name])
                            ->toArray()
                    )
                    ->query(function (Builder $q, array $data) {
                        if (! empty($data['value'])) {
                            $q->whereHas('branch', fn (Builder $b) => $b->where('partner_id', (int) $data['value'])
                            );
                        }
                    }),

                Tables\Filters\SelectFilter::make('provider')
                    ->options([
                        'generic_json' => 'Generic JSON',
                    ]),

                Tables\Filters\TernaryFilter::make('enabled')->label(__('Enabled')),
            ])
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

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\BranchIntegrationResource\RelationManagers\LogsRelationManager::class,
        ];
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
