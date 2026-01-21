<?php

namespace App\Filament\Resources;

use App\Enums\PromotionType;
use App\Filament\Resources\PromotionResource\Pages;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Promotion;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Resources\Concerns\Translatable as FilamentTranslatable;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromotionResource extends Resource
{
    use FilamentTranslatable;

    protected static ?string $model = Promotion::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Marketing';

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Section::make(__('Details'))->schema([
                // Translatable fields (single keys, like StateResource)
                TextInput::make('title')
                    ->label(__('Title'))
                    ->required()
                    ->maxLength(180)
                    ->helperText(__('Shown on badges & offers listing')),

                Textarea::make('summary')
                    ->label(__('Summary'))
                    ->rows(2)
                    ->maxLength(500),

                Grid::make(12)->schema([
                    Select::make('type')
                        ->label(__('Type'))
                        ->options([
                            PromotionType::ITEM => 'Item / Line-level',
                            PromotionType::CART => 'Cart-level',
                            PromotionType::BUNDLE => 'Bundle / Combo',
                        ])->required()->native(false)->preload()->searchable()
                        ->columnSpan(3),

                    Select::make('status')
                        ->label(__('Status'))
                        ->options([
                            'draft' => 'Draft',
                            'active' => 'Active',
                            'archived' => 'Archived',
                        ])->default('draft')
                        ->native(false)->preload()
                        ->columnSpan(3),

                    TextInput::make('priority')
                        ->label(__('Priority (lower = higher)'))
                        ->numeric()->default(100)
                        ->columnSpan(2),

                    Select::make('stack_behavior')
                        ->label(__('Stacking'))
                        ->options([
                            'stack' => 'Stack with others',
                            'exclusive' => 'Exclusive (don’t stack on same items)',
                        ])->default('exclusive')
                        ->native(false)->preload()
                        ->columnSpan(4),
                ])->columnSpan(12),

            ])->columns(1),

            Section::make(__('Scope (optional)'))->schema([
                // SERVICE
                Select::make('service_id')
                    ->label(__('Service'))
                    ->relationship(
                        name: 'service',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $q) {
                            $loc = app()->getLocale();
                            $q->orderBy("name->{$loc}");
                        }
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record?->getTranslation('name', app()->getLocale()))
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('branch_id', null))
                    ->native(false)->searchable()->preload(),

                // PARTNER
                Select::make('partner_id')
                    ->label(__('Partner'))
                    ->relationship(
                        name: 'partner',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $q) {
                            $loc = app()->getLocale();
                            $q->orderBy("name->{$loc}");
                        }
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record?->getTranslation('name', app()->getLocale()))
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('branch_id', null))
                    ->native(false)->searchable()->preload(),

                // BRANCH (dependent on service/partner)
                Select::make('branch_id')
                    ->label(__('Branch'))
                    ->options(function (Get $get) {
                        $loc = app()->getLocale();
                        $sid = (int) ($get('service_id') ?? 0);
                        $pid = (int) ($get('partner_id') ?? 0);
                        $current = $get('branch_id');

                        $q = \App\Models\Branch::query();

                        if ($sid) {
                            $q->forService($sid);
                        }
                        if ($pid) {
                            $q->where('partner_id', $pid);
                        }

                        // Editing case: if no filters but a branch is already selected, show it
                        if (! $sid && ! $pid && $current) {
                            $b = \App\Models\Branch::find($current);

                            return $b ? [$b->id => $b->getTranslation('name', $loc)] : [];
                        }

                        return $q->orderBy("name->{$loc}")
                            ->limit(100) // guard against huge lists; tweak as needed
                            ->get()
                            ->mapWithKeys(fn ($b) => [$b->id => $b->getTranslation('name', $loc)])
                            ->all();
                    })
                    // Ensure label renders even if not in current options (e.g., editing)
                    ->getOptionLabelUsing(function ($value) {
                        if (! $value) {
                            return null;
                        }
                        $loc = app()->getLocale();
                        $b = \App\Models\Branch::find($value);

                        return $b?->getTranslation('name', $loc);
                    })
                    ->reactive()
                    ->searchable()
                    ->native(false)
                    ->placeholder(__('Select a branch'))
                    ->disabled(fn (Get $get) => ! ($get('service_id') || $get('partner_id'))),
            ])->columns(3),

            Section::make(__('Channels & Media'))->schema([
                Select::make('channels')
                    ->label(__('Channels'))
                    ->multiple()
                    ->options(['web' => 'Web', 'whatsapp' => 'WhatsApp'])
                    ->default(['web'])
                    ->native(false)->preload()
                    ->helperText(__('Select where this promotion is active')),

                FileUpload::make('image_path')
                    ->label(__('Cover Image'))
                    ->image()->directory('promotions'),
            ])->columns(2),

            Section::make(__('Timing & Limits'))->schema([
                Forms\Components\Toggle::make('auto_apply')->label(__('Auto apply'))->default(true),
                Forms\Components\Toggle::make('once_per_order')->label(__('Once per order'))->default(false),

                Forms\Components\DateTimePicker::make('starts_at')->label(__('Starts at')),
                Forms\Components\DateTimePicker::make('ends_at')->label(__('Ends at')),

                TextInput::make('max_redemptions')->label(__('Max redemptions'))->numeric()->nullable(),
                TextInput::make('max_per_user')->label(__('Max per user'))->numeric()->nullable(),
            ])->columns(3)->collapsed(false),

            Section::make(__('Conditions'))->schema([
                Repeater::make('conditions')
                    ->relationship()
                    ->orderColumn('sort')
                    ->schema([
                        Select::make('condition_type')
                            ->label(__('Condition type'))
                            ->options([
                                'cart_min_subtotal' => 'Cart: Min Subtotal',
                                'bxgy_same_item' => 'Buy X Get Y (same item)',
                                'has_items_set' => 'Has Items Set (bundle)',
                                'in_section' => 'Item in Section (category)',
                                'order_type' => 'Order Type (delivery/pickup)',
                            ])
                            ->required()
                            ->native(false)->preload()->searchable()
                            ->live(),

                        // cart_min_subtotal
                        Group::make()->schema([
                            TextInput::make('payload.amount')
                                ->label(__('Minimum subtotal (KD)'))
                                ->numeric()->minValue(0)->required()
                                ->helperText(__('Applies when cart subtotal ≥ amount')),
                        ])->visible(fn (Get $get) => $get('condition_type') === 'cart_min_subtotal')
                            ->columns(2),

                        // bxgy_same_item
                        Group::make()->schema([
                            Select::make('payload.item_id')
                                ->label(__('Item'))
                                ->options(function (Get $get) {
                                    $loc = app()->getLocale();
                                    $q = MenuItem::query();

                                    // Filter by scope selections from the same form
                                    if ($pid = $get('../../partner_id')) {
                                        $q->whereHas('branch', fn ($b) => $b->where('partner_id', $pid));
                                    }
                                    if ($sid = $get('../../service_id')) {
                                        $q->whereHas('branch', fn ($b) => method_exists($b, 'scopeForService') ? $b->forService($sid) : $b);
                                    }
                                    if ($bid = $get('../../branch_id')) {
                                        $q->where('branch_id', $bid);
                                    }

                                    return $q->orderBy("name->{$loc}")
                                        ->get()
                                        ->mapWithKeys(fn ($i) => [$i->id => $i->getTranslation('name', $loc)])
                                        ->toArray();
                                })
                                ->searchable()->preload()->native(false)->required(),

                            TextInput::make('payload.buy_qty')->label(__('Buy qty'))->numeric()->default(2)->minValue(1)->required(),
                            TextInput::make('payload.get_qty')->label(__('Get qty (free)'))->numeric()->default(1)->minValue(1)->required(),
                            Toggle::make('payload.repeat')->label(__('Repeat for multiples'))->default(true),
                        ])->visible(fn (Get $get) => $get('condition_type') === 'bxgy_same_item')
                            ->columns(4),

                        // has_items_set (bundle requires these items)
                        Group::make()->schema([
                            Repeater::make('payload.items')
                                ->label(__('Required items'))
                                ->schema([
                                    Select::make('item_id')
                                        ->label(__('Item'))
                                        ->options(function (Get $get) {
                                            $loc = app()->getLocale();
                                            $q = MenuItem::query();
                                            if ($pid = $get('../../../../partner_id')) {
                                                $q->whereHas('branch', fn ($b) => $b->where('partner_id', $pid));
                                            }
                                            if ($sid = $get('../../../../service_id')) {
                                                $q->whereHas('branch', fn ($b) => method_exists($b, 'scopeForService') ? $b->forService($sid) : $b);
                                            }
                                            if ($bid = $get('../../../../branch_id')) {
                                                $q->where('branch_id', $bid);
                                            }

                                            return $q->orderBy("name->{$loc}")
                                                ->get()->mapWithKeys(fn ($i) => [$i->id => $i->getTranslation('name', $loc)])
                                                ->toArray();
                                        })
                                        ->searchable()->preload()->native(false)->required(),
                                    TextInput::make('qty')->label(__('Qty'))->numeric()->default(1)->minValue(1)->required(),
                                ])
                                ->default([])
                                ->reorderable()
                                ->addActionLabel(__('Add item'))
                                ->columns(2),
                        ])->visible(fn (Get $get) => $get('condition_type') === 'has_items_set'),

                        // in_section (category-based condition)
                        Group::make()->schema([
                            Select::make('payload.section_id')
                                ->label(__('Section (category)'))
                                ->options(function (Get $get) {
                                    $loc = app()->getLocale();
                                    $q = MenuSection::query()
                                        ->with('menu');

                                    // Filter sections by the selected branch (via related menu)
                                    if ($bid = $get('../../branch_id')) {
                                        $q->whereHas('menu', fn ($m) => $m->where('branch_id', $bid));
                                    }

                                    // Partner / Service via menu->branch
                                    if ($pid = $get('../../partner_id')) {
                                        $q->whereHas('menu.branch', fn ($b) => $b->where('partner_id', $pid));
                                    }
                                    if ($sid = $get('../../service_id')) {
                                        $q->whereHas('menu.branch', fn ($b) => method_exists($b, 'scopeForService') ? $b->forService($sid) : $b);
                                    }

                                    return $q->orderBy("name->{$loc}")
                                        ->get()->mapWithKeys(fn ($s) => [$s->id => $s->getTranslation('name', $loc)])
                                        ->toArray();
                                })
                                ->searchable()->preload()->native(false)->required(),

                            TextInput::make('payload.min_qty')
                                ->label(__('Min items from section'))
                                ->numeric()->minValue(1)->default(1)
                                ->helperText(__('Require at least N items from this section')),
                        ])->visible(fn (Get $get) => $get('condition_type') === 'in_section')
                            ->columns(2),

                        // order_type
                        Group::make()->schema([
                            CheckboxList::make('payload.allowed')
                                ->label(__('Allowed order types'))
                                ->options([
                                    'delivery' => __('Delivery'),
                                    'pickup' => __('Pickup'),
                                ])->columns(2),
                        ])->visible(fn (Get $get) => $get('condition_type') === 'order_type'),
                    ])
                    ->collapsed()
                    ->addActionLabel(__('Add condition')),
            ]),

            Section::make(__('Actions'))->schema([
                Repeater::make('actions')
                    ->relationship()
                    ->orderColumn('sort')
                    ->schema([
                        Select::make('action_type')
                            ->label(__('Action type'))
                            ->options([
                                'money_off_cart' => 'KD Off Cart',
                                'free_delivery' => 'Free Delivery',
                                'bundle_price' => 'Bundle Fixed Price',
                                'give_free_item' => 'Give Free Item (Y part of BXGY)',
                            ])->required()->native(false)->preload()->searchable()
                            ->live(),

                        // money_off_cart
                        Group::make()->schema([
                            TextInput::make('payload.amount')
                                ->label(__('Amount (KD)'))
                                ->numeric()->minValue(0.01)->required(),
                        ])->visible(fn (Get $get) => $get('action_type') === 'money_off_cart'),

                        // free_delivery (no config)
                        Group::make()->schema([
                            TextInput::make('payload._note')
                                ->label(__('No configuration needed'))
                                ->disabled()->dehydrated(false)->default(''),
                        ])->visible(fn (Get $get) => $get('action_type') === 'free_delivery'),

                        // bundle_price
                        Group::make()->schema([
                            TextInput::make('payload.price')
                                ->label(__('Bundle price (KD)'))
                                ->numeric()->minValue(0.01)->required(),
                        ])->visible(fn (Get $get) => $get('action_type') === 'bundle_price'),

                        // give_free_item
                        Group::make()->schema([
                            Select::make('payload.item_id')
                                ->label(__('Free item'))
                                ->options(function (Get $get) {
                                    $loc = app()->getLocale();
                                    $q = MenuItem::query();
                                    if ($pid = $get('../../partner_id')) {
                                        $q->whereHas('branch', fn ($b) => $b->where('partner_id', $pid));
                                    }
                                    if ($sid = $get('../../service_id')) {
                                        $q->whereHas('branch', fn ($b) => method_exists($b, 'scopeForService') ? $b->forService($sid) : $b);
                                    }
                                    if ($bid = $get('../../branch_id')) {
                                        $q->where('branch_id', $bid);
                                    }

                                    return $q->orderBy("name->{$loc}")
                                        ->get()->mapWithKeys(fn ($i) => [$i->id => $i->getTranslation('name', $loc)])
                                        ->toArray();
                                })
                                ->searchable()->preload()->native(false)->required(),
                            TextInput::make('payload.qty')->label(__('Qty'))->numeric()->default(1)->minValue(1)->required(),
                        ])->visible(fn (Get $get) => $get('action_type') === 'give_free_item')
                            ->columns(3),
                    ])
                    ->collapsed()
                    ->addActionLabel(__('Add action')),
            ]),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),

                // FIX: Changed $r to $record
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->state(fn (Promotion $record) => $record->getTranslation('title', app()->getLocale()))
                    ->searchable(query: function (Builder $q, string $search): Builder {
                        return $q->where('title->'.app()->getLocale(), 'like', "%{$search}%");
                    })
                    ->wrap(),

                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('priority')->sortable(),

                // FIX: Changed $r to $record
                TextColumn::make('service.name')
                    ->label(__('Service'))
                    ->state(fn (Promotion $record) => optional($record->service)?->getTranslation('name', app()->getLocale()))
                    ->toggleable(),

                // FIX: Changed $r to $record
                TextColumn::make('partner.name')
                    ->label(__('Partner'))
                    ->state(fn (Promotion $record) => optional($record->partner)?->getTranslation('name', app()->getLocale()))
                    ->toggleable(),

                // FIX: Changed $r to $record
                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->state(fn (Promotion $record) => optional($record->branch)?->getTranslation('name', app()->getLocale()))
                    ->toggleable(),

                TextColumn::make('starts_at')->dateTime()->toggleable(),
                TextColumn::make('ends_at')->dateTime()->toggleable(),
            ])
            ->defaultSort('priority')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived']),
                Tables\Filters\TernaryFilter::make('has_scope')
                    ->label(__('Scoped'))
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('service_id')->orWhereNotNull('partner_id')->orWhereNotNull('branch_id'),
                        false: fn ($q) => $q->whereNull('service_id')->whereNull('partner_id')->whereNull('branch_id'),
                        blank: fn ($q) => $q
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()->label(__('Duplicate')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }
}
