<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchAvailabilityRuleResource\Pages;
use App\Models\Branch;
use App\Models\BranchAvailabilityRule;
use App\Models\Partner; // Added
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BranchAvailabilityRuleResource extends Resource
{
    protected static ?string $model = BranchAvailabilityRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    // UI rename only
    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $slug = 'branch-availability';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_scheduling');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.branch_availability_rule.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.branch_availability_rule.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.branch_availability_rule.label_plural');
    }

    public static function form(Form $form): Form
    {
        $days = [
            0 => __('clinic_misc.branch_availability_rule.days.sun'),
            1 => __('clinic_misc.branch_availability_rule.days.mon'),
            2 => __('clinic_misc.branch_availability_rule.days.tue'),
            3 => __('clinic_misc.branch_availability_rule.days.wed'),
            4 => __('clinic_misc.branch_availability_rule.days.thu'),
            5 => __('clinic_misc.branch_availability_rule.days.fri'),
            6 => __('clinic_misc.branch_availability_rule.days.sat'),
        ];

        return $form->schema([
            Forms\Components\Section::make(__('clinic_misc.branch_availability_rule.section_hours'))
                ->description(__('clinic_misc.branch_availability_rule.section_hours_desc'))
                ->columns(3)
                ->schema([
                    // NEW: Partner Selection to filter Branches
                    Forms\Components\Select::make('partner_id')
                        ->label(__('clinic_misc.branch_availability_rule.partner'))
                        ->options(Partner::all()->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('branch_id', null))
                        ->dehydrated(false) // Don't save to DB (helper field)
                        ->default(fn ($record) => $record?->branch?->partner_id),

                    Forms\Components\Select::make('branch_id')
                        ->label(__('clinic_misc.branch_availability_rule.branch'))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(function (Get $get) {
                            $partnerId = $get('partner_id');
                            $query = Branch::query()->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"'))");

                            if ($partnerId) {
                                $query->where('partner_id', $partnerId);
                            }

                            return $query->get()->mapWithKeys(fn ($b) => [$b->id => $b->localized_name])->toArray();
                        })
                        ->getOptionLabelFromRecordUsing(fn (Branch $b) => $b->localized_name),

                    Forms\Components\Select::make('day_of_week')
                        ->label(__('clinic_misc.branch_availability_rule.day_of_week'))->required()
                        ->options($days)->native(false),

                    Forms\Components\Toggle::make('is_open')
                        ->label(__('clinic_misc.branch_availability_rule.is_open'))
                        ->default(true),

                    Forms\Components\TimePicker::make('open_at')
                        ->label(__('clinic_misc.branch_availability_rule.open_at'))->seconds(false)
                        ->required(fn (Get $get) => (bool) $get('is_open'))
                        ->visible(fn (Get $get) => (bool) $get('is_open')),

                    Forms\Components\TimePicker::make('close_at')
                        ->label(__('clinic_misc.branch_availability_rule.close_at'))->seconds(false)
                        ->helperText(__('clinic_misc.branch_availability_rule.close_at_help'))
                        ->required(fn (Get $get) => (bool) $get('is_open'))
                        ->visible(fn (Get $get) => (bool) $get('is_open')),

                    Forms\Components\TextInput::make('slot_length_minutes')
                        ->label(__('clinic_misc.branch_availability_rule.slot_length_minutes'))->suffix('min')
                        ->required()
                        ->integer()
                        ->minValue(15)->step(15)
                        ->default(90),

                    Forms\Components\TextInput::make('slot_step_minutes')
                        ->label(__('clinic_misc.branch_availability_rule.slot_step_minutes'))->suffix('min')
                        ->required()
                        ->integer()
                        ->minValue(5)->step(5)
                        ->default(30)
                        ->maxValue(fn (Get $get) => (int) ($get('slot_length_minutes') ?: 9999))
                        ->rule(function (Get $get) {
                            $len = (int) $get('slot_length_minutes');

                            return function (string $attribute, $value, \Closure $fail) use ($len) {
                                $step = (int) $value;
                                if ($len > 0 && $step > 0 && $len % $step !== 0) {
                                    $fail(__('clinic_misc.branch_availability_rule.slot_step_validation'));
                                }
                            };
                        }),

                    Forms\Components\TextInput::make('max_party_size')
                        ->label(__('clinic_misc.branch_availability_rule.max_party_size'))->numeric()->minValue(1)->maxValue(20)
                        ->default(6)->required(),

                    Forms\Components\TextInput::make('lead_time_minutes')
                        ->label(__('clinic_misc.branch_availability_rule.lead_time_minutes'))->numeric()->minValue(0)->suffix('min')
                        ->default(60)->required(),

                    Forms\Components\KeyValue::make('capacity_map')
                        ->label(__('clinic_misc.branch_availability_rule.capacity_map'))
                        ->keyLabel(__('clinic_misc.branch_availability_rule.capacity_map_key'))
                        ->valueLabel(__('clinic_misc.branch_availability_rule.capacity_map_value'))
                        ->addButtonLabel(__('clinic_misc.branch_availability_rule.capacity_map_add'))
                        ->reorderable()
                        ->helperText(__('clinic_misc.branch_availability_rule.capacity_map_help')),

                    Forms\Components\CheckboxList::make('apply_to_days')
                        ->label(__('clinic_misc.branch_availability_rule.apply_to_days'))
                        ->options($days)
                        ->columns(3)
                        ->helperText(__('clinic_misc.branch_availability_rule.apply_to_days_help'))
                        ->dehydrated(false)
                        ->visible(fn ($livewire) => $livewire instanceof \App\Filament\Resources\BranchAvailabilityRuleResource\Pages\CreateBranchAvailabilityRule),
                ]),

            Forms\Components\Section::make(__('clinic_misc.branch_availability_rule.section_ui_images'))
                ->description(__('clinic_misc.branch_availability_rule.section_ui_images_desc'))
                ->collapsible()
                ->collapsed() //  default collapsed
                ->columns(12)
                ->schema([
                    //  helper toggle (not stored)
                    Forms\Components\Toggle::make('ui_images_enabled')
                        ->label(__('clinic_misc.branch_availability_rule.enable_ui_images'))
                        ->dehydrated(false)
                        ->default(function ($record) {
                            $party = $record?->ui_party_images;
                            $time = $record?->ui_time_image;

                            return ! empty($party) || ! empty($time);
                        })
                        ->columnSpan(12),

                    // ───────── PARTY IMAGES (per size) ─────────
                    Forms\Components\Repeater::make('ui_party_images')
                        ->label(__('clinic_misc.branch_availability_rule.party_images'))
                        ->columns(12)
                        ->addActionLabel(__('clinic_misc.branch_availability_rule.add_party_image'))
                        ->visible(fn (Get $get) => (bool) $get('ui_images_enabled')) //  optional
                        // ... keep your existing afterStateHydrated + schema ...
                        ->mutateDehydratedStateUsing(function (?array $state) {
                            $out = [];
                            foreach (($state ?? []) as $row) {
                                $size = (string) ($row['size'] ?? '');
                                $src = trim((string) ($row['src'] ?? ''));
                                if ($size === '' || $src === '') {
                                    continue;
                                }

                                $file = $row['file'] ?? null;
                                if (is_array($file)) {
                                    $file = $file[0] ?? null;
                                }

                                $out[$size] = [
                                    'src' => $src,
                                    'file' => $file ? (string) $file : null,
                                    'width' => isset($row['width']) ? (int) $row['width'] : null,
                                    'height' => isset($row['height']) ? (int) $row['height'] : null,
                                    'scale_type' => in_array(($row['scale_type'] ?? 'contain'), ['contain', 'cover'], true)
                                        ? $row['scale_type'] : 'contain',
                                    'aspect_ratio' => isset($row['aspect_ratio']) ? (float) $row['aspect_ratio'] : null,
                                    'alt_text' => (string) ($row['alt_text'] ?? ''),
                                ];
                            }

                            //  store NULL if empty (so it stays truly optional)
                            return ! empty($out) ? $out : null;
                        })
                        ->columnSpan(12),

                    // ───────── TIME IMAGE (single) ─────────
                    Forms\Components\Group::make([
                        // keep your existing upload/text fields...
                    ])
                        ->visible(fn (Get $get) => (bool) $get('ui_images_enabled')) //  optional
                        ->columns(6)
                        ->columnSpan(12),

                    // ───────── TIME IMAGE (single) ─────────
                    Forms\Components\Group::make([
                        Forms\Components\FileUpload::make('time_src_file')
                            ->label(__('clinic_misc.branch_availability_rule.time_image_upload'))
                            ->disk('public')
                            ->directory('wa/ui/time')
                            ->image()->imageEditor()
                            ->openable()->downloadable()
                            ->multiple(false)
                            ->maxFiles(1)
                            ->dehydrated(false)

                            ->afterStateHydrated(function (Forms\Components\FileUpload $component, $state, Get $get) {
                                if (is_array($state)) {
                                    $normalized = array_values(array_filter($state, fn ($p) => is_string($p) && $p !== ''));
                                    if (! empty($normalized)) {
                                        $component->state($normalized);

                                        return;
                                    }
                                }

                                $img = (array) ($get('ui_time_image') ?? []);
                                $file = (string) ($img['file'] ?? '');
                                if ($file !== '' && Storage::disk('public')->exists($file)) {
                                    $component->state([$file]);

                                    return;
                                }
                                $src = (string) ($img['src'] ?? '');
                                if ($src !== '') {
                                    $raw = str_starts_with($src, 'data:image') ? (explode(',', $src, 2)[1] ?? '') : $src;
                                    if ($raw !== '') {
                                        $bin = base64_decode($raw, true);
                                        if ($bin !== false) {
                                            $hash = substr(sha1($bin), 0, 16);
                                            $rel = "wa/ui/time/{$hash}.png";
                                            if (! Storage::disk('public')->exists($rel)) {
                                                Storage::disk('public')->put($rel, $bin);
                                            }
                                            $component->state([$rel]);
                                        }
                                    }
                                }
                            })

                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! ($state instanceof TemporaryUploadedFile)) {
                                    return;
                                }

                                $storedPath = $state->store('wa/ui/time', ['disk' => 'public']);

                                $curr = (array) ($get('ui_time_image') ?? []);
                                if ($storedPath) {
                                    $curr['file'] = $storedPath;
                                }

                                $abs = Storage::disk('public')->path($storedPath);
                                $b64 = base64_encode(file_get_contents($abs));
                                $curr['src'] = $b64;

                                [$w, $h] = @getimagesize($abs) ?: [null, null];
                                if ($w && empty($curr['width'])) {
                                    $curr['width'] = (int) $w;
                                }
                                if ($h && empty($curr['height'])) {
                                    $curr['height'] = (int) $h;
                                }
                                if ($w && $h && $h != 0 && empty($curr['aspect_ratio'])) {
                                    $curr['aspect_ratio'] = round($w / $h, 3);
                                }

                                $set('ui_time_image', $curr);
                            }),

                        Forms\Components\Textarea::make('ui_time_image.src')
                            ->label(__('clinic_misc.branch_availability_rule.time_image_base64'))
                            ->rows(3)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (is_string($state) && str_starts_with($state, 'data:image')) {
                                    $curr = (array) ($get('ui_time_image') ?? []);
                                    $curr['src'] = explode(',', $state, 2)[1] ?? $state;
                                    $set('ui_time_image', $curr);
                                }
                            }),

                        Forms\Components\Select::make('ui_time_image.scale_type')
                            ->options(['contain' => 'contain', 'cover' => 'cover'])
                            ->default('contain')
                            ->label(__('clinic_misc.branch_availability_rule.time_image_scale')),

                        Forms\Components\TextInput::make('ui_time_image.width')->numeric()->label(__('clinic_misc.branch_availability_rule.width')),
                        Forms\Components\TextInput::make('ui_time_image.height')->numeric()->label(__('clinic_misc.branch_availability_rule.height')),
                        Forms\Components\TextInput::make('ui_time_image.aspect_ratio')->numeric()->label(__('clinic_misc.branch_availability_rule.aspect_ratio')),
                        Forms\Components\TextInput::make('ui_time_image.alt_text')->label(__('clinic_misc.branch_availability_rule.alt_text')),
                    ])->columns(6)->columnSpan(12),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $days = [
            0 => __('clinic_misc.branch_availability_rule.days.sun'),
            1 => __('clinic_misc.branch_availability_rule.days.mon'),
            2 => __('clinic_misc.branch_availability_rule.days.tue'),
            3 => __('clinic_misc.branch_availability_rule.days.wed'),
            4 => __('clinic_misc.branch_availability_rule.days.thu'),
            5 => __('clinic_misc.branch_availability_rule.days.fri'),
            6 => __('clinic_misc.branch_availability_rule.days.sat'),
        ];

        return $table
            ->defaultSort('branch_id')
            ->columns([
                Tables\Columns\TextColumn::make('branch_id')
                    ->label(__('clinic_misc.branch_availability_rule.branch'))
                    ->formatStateUsing(fn ($state, $record) => $record->branch?->localized_name)
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('day_of_week')
                    ->label(__('clinic_misc.branch_availability_rule.day'))
                    ->formatStateUsing(fn (mixed $state) => $days[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_open')->label(__('clinic_misc.branch_availability_rule.open'))->boolean()->sortable(),
                Tables\Columns\TextColumn::make('open_at')->label(__('clinic_misc.branch_availability_rule.opens'))->time('H:i')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('close_at')->label(__('clinic_misc.branch_availability_rule.closes'))->time('H:i')->placeholder('-')->sortable(),

                Tables\Columns\TextColumn::make('slot_length_minutes')
                    ->label(__('clinic_misc.branch_availability_rule.slot_length_minutes'))
                    ->formatStateUsing(fn (mixed $state): string => $state !== null ? $state.' min' : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('slot_step_minutes')
                    ->label(__('clinic_misc.branch_availability_rule.slot_interval'))
                    ->formatStateUsing(fn (mixed $state): string => $state !== null ? $state.' min' : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('lead_time_minutes')
                    ->label(__('clinic_misc.branch_availability_rule.min_notice'))
                    ->formatStateUsing(fn (mixed $state): string => $state !== null ? $state.' min' : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('max_party_size')->label(__('clinic_misc.branch_availability_rule.max_patients'))->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->since()->label(__('clinic_misc.branch_availability_rule.updated')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')->label(__('clinic_misc.branch_availability_rule.branch'))
                    ->options(fn () => Branch::query()
                        ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"'))")
                        ->get()->mapWithKeys(fn ($b) => [$b->id => $b->localized_name])->toArray()
                    ),

                Tables\Filters\SelectFilter::make('day_of_week')->label(__('clinic_misc.branch_availability_rule.day'))->options($days),

                Tables\Filters\TernaryFilter::make('is_open')
                    ->label(__('clinic_misc.branch_availability_rule.clinic_open'))
                    ->trueLabel(__('clinic_misc.branch_availability_rule.open_label'))
                    ->falseLabel(__('clinic_misc.branch_availability_rule.closed_label')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(__('common.actions.edit')),

                Tables\Actions\Action::make('copyToDays')
                    ->label(__('clinic_misc.branch_availability_rule.copy_to_days'))
                    ->icon('heroicon-o-clipboard-document')
                    ->modalHeading(__('clinic_misc.branch_availability_rule.copy_to_days_modal'))
                    ->form([
                        Forms\Components\CheckboxList::make('days')
                            ->label(__('clinic_misc.branch_availability_rule.target_days'))
                            ->options($days)
                            ->columns(3)
                            ->required()
                            ->helperText(__('clinic_misc.branch_availability_rule.target_days_help')),

                        Forms\Components\Toggle::make('include_images')
                            ->label(__('clinic_misc.branch_availability_rule.include_images'))
                            ->default(true),
                    ])
                    ->action(function (BranchAvailabilityRule $record, array $data) {
                        $targetDays = collect($data['days'] ?? [])
                            ->map(fn ($d) => (int) $d)
                            ->filter(fn ($d) => $d >= 0 && $d <= 6 && $d !== (int) $record->day_of_week)
                            ->unique();

                        foreach ($targetDays as $dow) {
                            $attrs = [
                                'is_open' => (bool) $record->is_open,
                                'open_at' => $record->open_at,
                                'close_at' => $record->close_at,
                                'slot_length_minutes' => (int) $record->slot_length_minutes,
                                'slot_step_minutes' => (int) $record->slot_step_minutes,
                                'max_party_size' => (int) $record->max_party_size,
                                'lead_time_minutes' => (int) $record->lead_time_minutes,
                                'capacity_map' => $record->capacity_map,
                            ];

                            if (! empty($data['include_images'])) {
                                $attrs['ui_party_images'] = $record->ui_party_images;
                                $attrs['ui_time_image'] = $record->ui_time_image;
                            }

                            BranchAvailabilityRule::updateOrCreate(
                                ['branch_id' => $record->branch_id, 'day_of_week' => $dow],
                                $attrs
                            );
                        }
                    })
                    ->successNotificationTitle(__('clinic_misc.branch_availability_rule.copied_notif')),

                Tables\Actions\Action::make('applyImagesToDays')
                    ->label(__('clinic_misc.branch_availability_rule.apply_images_action'))
                    ->icon('heroicon-o-photo')
                    ->modalHeading(__('clinic_misc.branch_availability_rule.apply_images_modal'))
                    ->form([
                        Forms\Components\Radio::make('scope')
                            ->label(__('clinic_misc.branch_availability_rule.scope_label'))
                            ->options([
                                'all' => __('clinic_misc.branch_availability_rule.scope_all'),
                                'choose' => __('clinic_misc.branch_availability_rule.scope_choose'),
                            ])
                            ->default('all')
                            ->inline(),

                        Forms\Components\CheckboxList::make('days')
                            ->label(__('clinic_misc.branch_availability_rule.target_days'))
                            ->options($days)
                            ->columns(3)
                            ->visible(fn (Get $get) => $get('scope') === 'choose'),

                        Forms\Components\Toggle::make('include_self')
                            ->label(__('clinic_misc.branch_availability_rule.include_self'))
                            ->default(false),
                    ])
                    ->action(function (BranchAvailabilityRule $record, array $data) {
                        $sourceParty = $record->ui_party_images;
                        $sourceTime = $record->ui_time_image;

                        if (empty($sourceParty) && empty($sourceTime)) {
                            return;
                        }

                        $targets = $data['scope'] === 'all'
                            ? collect(range(0, 6))
                            : collect($data['days'] ?? [])->map(fn ($d) => (int) $d);

                        if (empty($data['include_self'])) {
                            $targets = $targets->reject(fn ($d) => $d === (int) $record->day_of_week);
                        }

                        foreach ($targets->unique() as $dow) {
                            BranchAvailabilityRule::updateOrCreate(
                                ['branch_id' => $record->branch_id, 'day_of_week' => $dow],
                                [
                                    'ui_party_images' => $sourceParty,
                                    'ui_time_image' => $sourceTime,
                                ]
                            );
                        }
                    })
                    ->successNotificationTitle(__('clinic_misc.branch_availability_rule.images_applied_notif')),

                Tables\Actions\DeleteAction::make()->label(__('common.actions.delete')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkCopyToDays')
                        ->label(__('clinic_misc.branch_availability_rule.bulk_copy'))
                        ->icon('heroicon-o-clipboard-document-check')
                        ->form([
                            Forms\Components\CheckboxList::make('days')
                                ->label(__('clinic_misc.branch_availability_rule.target_days'))
                                ->options($days)
                                ->columns(3)
                                ->required(),

                            Forms\Components\Toggle::make('include_images')
                                ->label(__('clinic_misc.branch_availability_rule.include_images'))
                                ->default(true),
                        ])
                        ->action(function ($records, array $data) {
                            $record = $records->first();
                            if (! $record) {
                                return;
                            }

                            $targetDays = collect($data['days'] ?? [])
                                ->map(fn ($d) => (int) $d)
                                ->filter(fn ($d) => $d >= 0 && $d <= 6 && $d !== (int) $record->day_of_week)
                                ->unique();

                            foreach ($targetDays as $dow) {
                                $attrs = [
                                    'is_open' => (bool) $record->is_open,
                                    'open_at' => $record->open_at,
                                    'close_at' => $record->close_at,
                                    'slot_length_minutes' => (int) $record->slot_length_minutes,
                                    'slot_step_minutes' => (int) $record->slot_step_minutes,
                                    'max_party_size' => (int) $record->max_party_size,
                                    'lead_time_minutes' => (int) $record->lead_time_minutes,
                                    'capacity_map' => $record->capacity_map,
                                ];

                                if (! empty($data['include_images'])) {
                                    $attrs['ui_party_images'] = $record->ui_party_images;
                                    $attrs['ui_time_image'] = $record->ui_time_image;
                                }

                                BranchAvailabilityRule::updateOrCreate(
                                    ['branch_id' => $record->branch_id, 'day_of_week' => $dow],
                                    $attrs
                                );
                            }
                        })
                        ->successNotificationTitle(__('clinic_misc.branch_availability_rule.copied_notif')),

                    Tables\Actions\DeleteBulkAction::make()->label(__('clinic_misc.branch_availability_rule.delete_selected')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranchAvailabilityRules::route('/'),
            'create' => Pages\CreateBranchAvailabilityRule::route('/create'),
            'edit' => Pages\EditBranchAvailabilityRule::route('/{record}/edit'),
        ];
    }
}
