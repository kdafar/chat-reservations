<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WhatsappTriggerResource\Pages;
use App\Models\WhatsappTrigger;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource; // Added missing import
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
// Removed unused Cache import
use Illuminate\Support\Str;

class WhatsappTriggerResource extends Resource
{
    protected static ?string $model = WhatsappTrigger::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'type';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Trigger')
                ->collapsible()
                ->schema([
                    Select::make('type')
                        ->options([
                            'keyword' => 'Keyword Trigger',
                            'welcome' => 'Welcome (New Conversation)',
                            'finale' => 'Finale (After Booking)',
                            'fallback' => 'Fallback (Not Understood)',
                        ])
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn (Set $set) => $set('keyword', null)),

                    // REPLACED TextInput with TagsInput for better UX
                    TagsInput::make('keyword')
                        ->label('Keywords')
                        ->helperText('Add keywords one by one (EN + AR). Press comma or Enter to add.')
                        ->visible(fn (Get $get) => $get('type') === 'keyword')
                        ->required(fn (Get $get) => $get('type') === 'keyword')
    // Only save when this trigger type is actually "keyword"
                        ->dehydrated(fn (Get $get) => $get('type') === 'keyword')
                        ->separator(',') // keep if supported in your Filament version
                        ->placeholder('Type a keyword and press Enter')

    // DB -> component (string CSV -> array of unique trimmed tags)
                        ->afterStateHydrated(function (TagsInput $component, $state) {
                            if (is_string($state)) {
                                $parts = array_filter(array_map('trim', explode(',', $state)));
                                $component->state(array_values(array_unique($parts)));
                            } elseif ($state === null) {
                                $component->state([]);
                            }
                        })

    // component -> DB (array -> comma+space separated string, or null)
                        ->dehydrateStateUsing(function ($state) {
                            if (empty($state)) {
                                return null;
                            }
                            $parts = array_filter(array_map(function ($s) {
                                $s = trim((string) $s);

                                return preg_replace('/\s+/', ' ', $s); // collapse multiple spaces
                            }, (array) $state));
                            $parts = array_values(array_unique($parts));

                            return implode(', ', $parts);
                        }),

                    Select::make('response_type')
                        ->label('Response Type')
                        ->options([
                            'text' => 'Text',
                            'link' => 'Link (e.g., Google Maps)',
                            'image_upload' => 'Image (Upload)',
                            // 'image_url'       => 'Image (from URL)', // Removed
                            'document_upload' => 'Document (Upload)',
                            // 'document_url'    => 'Document (from URL)', // Removed
                            'buttons' => 'Buttons (<=3 or auto-list if >3)',
                            'list' => 'List (sections & rows)',
                            'template' => 'WhatsApp Template',
                            'flow' => 'Open Flow',
                        ])
                        ->required()
                        ->default('text')
                        ->reactive(),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])->columns(3),

            Section::make('Content')
                ->collapsible()
                ->schema([
                    // Base text (used by text/buttons/list/link as body)
                    Textarea::make('response_message_en')
                        ->label('Response (English)')
                        ->rows(3)
                        ->visible(fn (Get $get) => in_array($get('response_type'), ['text', 'buttons', 'list', 'link'])),
                    Textarea::make('response_message_ar')
                        ->label('Response (Arabic)')
                        ->rows(3)
                        ->visible(fn (Get $get) => in_array($get('response_type'), ['text', 'buttons', 'list', 'link'])),

                    // LINK
                    Fieldset::make('Link URL')
                        ->visible(fn (Get $get) => $get('response_type') === 'link')
                        ->schema([
                            TextInput::make('response_meta.link_url')
                                ->label('URL')
                                ->helperText('e.g., https://maps.google.com/...')
                                ->url()
                                ->required(),
                        ]),

                    // IMAGE (Upload)
                    Fieldset::make('Image (Upload)')
                        ->visible(fn (Get $get) => $get('response_type') === 'image_upload')
                        ->schema([
                            FileUpload::make('response_meta.image_upload_path')
                                ->label('Image File')
                                ->disk('public') // Make sure 'public' disk is configured
                                ->directory('whatsapp-media')
                                ->image()
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('response_meta.caption_en')->label('Caption (EN)'),
                            TextInput::make('response_meta.caption_ar')->label('Caption (AR)'),
                        ])->columns(2),

                    // IMAGE (from URL) - REMOVED
                    /*
                    Fieldset::make('Image (from URL)')
                        ->visible(fn (Get $get) => $get('response_type') === 'image_url')
                        ->schema([
                            TextInput::make('response_meta.image_url')
                                ->label('Image URL')
                                ->url()
                                ->required(),
                            TextInput::make('response_meta.caption_en')->label('Caption (EN)'),
                            TextInput::make('response_meta.caption_ar')->label('Caption (AR)'),
                        ])->columns(3),
                    */

                    // DOCUMENT (Upload)
                    Fieldset::make('Document (Upload)')
                        ->visible(fn (Get $get) => $get('response_type') === 'document_upload')
                        ->schema([
                            FileUpload::make('response_meta.document_upload_path')
                                ->label('Document File')
                                ->disk('public') // Make sure 'public' disk is configured
                                ->directory('whatsapp-media')
                                ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'])
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('response_meta.filename')->label('Filename (optional)')
                                ->helperText('If blank, original filename will be used.'),
                            TextInput::make('response_meta.caption_en')->label('Caption (EN)'),
                            TextInput::make('response_meta.caption_ar')->label('Caption (AR)'),
                        ])->columns(3),

                    // DOCUMENT (from URL) - REMOVED
                    /*
                    Fieldset::make('Document (from URL)')
                        ->visible(fn (Get $get) => $get('response_type') === 'document_url')
                        ->schema([
                            TextInput::make('response_meta.document_url')
                                ->label('Document URL')
                                ->url()
                                ->required(),
                            TextInput::make('response_meta.filename')->label('Filename (optional)'),
                            TextInput::make('response_meta.caption_en')->label('Caption (EN)'),
                            TextInput::make('response_meta.caption_ar')->label('Caption (AR)'),
                        ])->columns(4),
                    */

                    // BUTTONS
                    Fieldset::make('Buttons')
                        ->visible(fn (Get $get) => $get('response_type') === 'buttons')
                        ->schema([
                            TextInput::make('response_meta.header_en')->label('Header (EN)'),
                            TextInput::make('response_meta.header_ar')->label('Header (AR)'),
                            TextInput::make('response_meta.body_en')->label('Body (EN)'),
                            TextInput::make('response_meta.body_ar')->label('Body (AR)'),
                            Repeater::make('response_meta.buttons')
                                ->label('Buttons')
                                ->minItems(1)
                                ->maxItems(10) // your sender auto-falls back to list if >3
                                ->schema([
                                    TextInput::make('id')
                                        ->label('ID')
                                        ->default(fn () => (string) Str::uuid())
                                        ->required(),
                                    TextInput::make('title_en')
                                        ->label('Title (EN)')
                                        ->required(),
                                    TextInput::make('title_ar')
                                        ->label('Title (AR)'),
                                    TextInput::make('desc_en')
                                        ->label('Desc (EN)'),
                                    TextInput::make('desc_ar')
                                        ->label('Desc (AR)'),
                                ])->columns(2),
                        ])->columns(2),

                    // LIST
                    Fieldset::make('List')
                        ->visible(fn (Get $get) => $get('response_type') === 'list')
                        ->schema([
                            TextInput::make('response_meta.header_en')->label('Header (EN)'),
                            TextInput::make('response_meta.header_ar')->label('Header (AR)'),
                            TextInput::make('response_meta.body_en')->label('Body (EN)'),
                            TextInput::make('response_meta.body_ar')->label('Body (AR)'),
                            TextInput::make('response_meta.button_label_en')
                                ->label('Button Label (EN)')
                                ->default('Open')
                                ->helperText('<= 20 characters'),
                            TextInput::make('response_meta.button_label_ar')
                                ->label('Button Label (AR)')
                                ->default('افتح')
                                ->helperText('<= 20 characters'),
                            TextInput::make('response_meta.footer_en')->label('Footer (EN)'),
                            TextInput::make('response_meta.footer_ar')->label('Footer (AR)'),

                            Repeater::make('response_meta.sections')
                                ->label('Sections')
                                ->minItems(1)
                                ->schema([
                                    TextInput::make('title_en')->label('Section Title (EN)'),
                                    TextInput::make('title_ar')->label('Section Title (AR)'),
                                    Repeater::make('rows')
                                        ->label('Rows')
                                        ->minItems(1)
                                        ->schema([
                                            TextInput::make('id')
                                                ->label('Row ID')
                                                ->default(fn () => (string) Str::uuid())
                                                ->required(),
                                            TextInput::make('title_en')->label('Title (EN)')->required(),
                                            TextInput::make('title_ar')->label('Title (AR)'),
                                            TextInput::make('desc_en')->label('Desc (EN)'),
                                            TextInput::make('desc_ar')->label('Desc (AR)'),
                                        ])->columns(2),
                                ])->columns(2),
                        ])->columns(2),

                    // TEMPLATE
                    Fieldset::make('WhatsApp Template')
                        ->visible(fn (Get $get) => $get('response_type') === 'template')
                        ->schema([
                            TextInput::make('response_meta.template_name')
                                ->label('Template Name')
                                ->placeholder('barfres_invite')
                                ->required(),
                            TextInput::make('response_meta.lang_override')
                                ->label('Lang Override (optional, e.g., ar or en_US)'),
                            TextInput::make('response_meta.header_image_url')
                                ->label('Header Image URL (optional)')
                                ->url(),
                            // Body params (EN)
                            Repeater::make('response_meta.body_params_en')
                                ->label('Body Params (EN)')
                                ->schema([
                                    TextInput::make('value')
                                        ->label('Value')
                                        ->required(),
                                ])
                                ->columns(1)
                                // Flatten to simple array on save: ['A','B','C']
                                ->dehydrateStateUsing(function ($state) {
                                    return array_values(array_filter(array_map(
                                        fn ($row) => Arr::get($row, 'value', ''),
                                        is_array($state) ? $state : []
                                    ), fn ($v) => $v !== ''));
                                })
                                // If already flat array, inflate for UI
                                ->afterStateHydrated(function ($component, $state) {
                                    if (is_array($state) && isset($state[0]) && is_string($state[0])) {
                                        $component->state(array_map(fn ($v) => ['value' => $v], $state));
                                    }
                                }),
                            // Body params (AR)
                            Repeater::make('response_meta.body_params_ar')
                                ->label('Body Params (AR)')
                                ->schema([
                                    TextInput::make('value')
                                        ->label('Value')
                                        ->required(),
                                ])
                                ->columns(1)
                                ->dehydrateStateUsing(function ($state) {
                                    return array_values(array_filter(array_map(
                                        fn ($row) => Arr::get($row, 'value', ''),
                                        is_array($state) ? $state : []
                                    ), fn ($v) => $v !== ''));
                                })
                                ->afterStateHydrated(function ($component, $state) {
                                    if (is_array($state) && isset($state[0]) && is_string($state[0])) {
                                        $component->state(array_map(fn ($v) => ['value' => $v], $state));
                                    }
                                }),
                        ])->columns(3),

                    // FLOW
                    Fieldset::make('Flow')
                        ->visible(fn (Get $get) => $get('response_type') === 'flow')
                        ->schema([
                            TextInput::make('response_meta.flow_id')
                                ->label('Flow ID')
                                ->required(),
                            TextInput::make('response_meta.cta_en')
                                ->label('CTA (EN)')
                                ->default('Book now'),
                            TextInput::make('response_meta.cta_ar')
                                ->label('CTA (AR)')
                                ->default('احجز الآن'),
                            Select::make('response_meta.mode')
                                ->label('Mode')
                                ->options([
                                    'published' => 'published',
                                    'draft' => 'draft',
                                ])
                                ->default('published'),
                        ])->columns(4),
                ]),
        ]);
        // ->afterSave(...) hook was removed from here as it's invalid on the Form object.
        // It has been moved to the Page classes (CreateWhatsappTrigger and EditWhatsappTrigger).
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ToggleColumn::make('is_active')->label('Active')->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'keyword' => 'info',
                        'welcome', 'finale' => 'success',
                        'fallback' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('response_type')
                    ->label('Reply Type')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'text' => 'gray',
                        'link' => 'warning',
                        'image_upload' => 'info',
                        // 'image_url' => 'info', // Removed
                        'document_upload' => 'info',
                        // 'document_url' => 'info', // Removed
                        'buttons' => 'primary',
                        'list' => 'primary',
                        'template' => 'success',
                        'flow' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('keyword')->searchable()->limit(40)->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('response_message_en')->label('EN')->wrap()->limit(60)->toggleable(),
                TextColumn::make('response_message_ar')->label('AR')->wrap()->limit(60)->toggleable(),
                TextColumn::make('updated_at')->dateTime()->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'keyword' => 'Keyword',
                        'welcome' => 'Welcome',
                        'finale' => 'Finale',
                        'fallback' => 'Fallback',
                    ]),
                Tables\Filters\SelectFilter::make('response_type')
                    ->label('Reply Type')
                    ->options([
                        'text' => 'Text',
                        'link' => 'Link',
                        'image_upload' => 'Image (Upload)',
                        // 'image_url' => 'Image (URL)', // Removed
                        'document_upload' => 'Document (Upload)',
                        // 'document_url' => 'Document (URL)', // Removed
                        'buttons' => 'Buttons',
                        'list' => 'List',
                        'template' => 'Template',
                        'flow' => 'Flow',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsappTriggers::route('/'),
            'create' => Pages\CreateWhatsappTrigger::route('/create'),
            'edit' => Pages\EditWhatsappTrigger::route('/{record}/edit'),
        ];
    }
}
