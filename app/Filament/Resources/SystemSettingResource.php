<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemSettingResource\Pages;
use App\Models\SystemSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SystemSettingResource extends Resource
{
    protected static ?string $model = SystemSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'System Settings';

    protected static ?int $navigationSort = 12;

    // Allowed keys we surface in admin
    public static function allowedKeys(): array
    {
        return [
            'use_flows' => ['type' => 'bool', 'label' => 'Use WhatsApp Flows 7.2'],
            'fallback_when_template_pending' => ['type' => 'bool', 'label' => 'Fallback to plain text if template pending'],
            'session_expiry_minutes' => ['type' => 'int', 'label' => 'Session expiry (minutes)'],

            'whatsapp.access_token' => ['type' => 'secret', 'label' => 'WABA Access Token'],
            'whatsapp.verify_token' => ['type' => 'text', 'label' => 'Verify Token'],
            'whatsapp.app_secret' => ['type' => 'secret', 'label' => 'App Secret'],
            'whatsapp.phone_number_id' => ['type' => 'text', 'label' => 'Phone Number ID'],
            'whatsapp.graph_version' => ['type' => 'text', 'label' => 'Graph Version', 'placeholder' => 'v21.0'],

            'whatsapp.template.confirmed' => ['type' => 'text', 'label' => 'Template: Confirmed (name)'],
            'whatsapp.template.invite' => ['type' => 'text', 'label' => 'Template: Invite (name)'],
            'whatsapp.template.lang_en' => ['type' => 'text', 'label' => 'Template Language (EN)', 'placeholder' => 'en'],
            'whatsapp.template.lang_ar' => ['type' => 'text', 'label' => 'Template Language (AR)', 'placeholder' => 'ar'],

            // Public booking website (EVA) — brand, contact + socials shown to visitors.
            // Leave the phone blank to show a "Book Now" button instead of a call button.
            'clinic.public.name_en' => ['type' => 'text', 'label' => 'Brand name (English)', 'placeholder' => 'EVA Medical'],
            'clinic.public.name_ar' => ['type' => 'text', 'label' => 'Brand name (Arabic)', 'placeholder' => 'إيفا الطبية'],
            'clinic.public.tagline_en' => ['type' => 'text', 'label' => 'Tagline (English)', 'placeholder' => 'Aesthetic & Dermatology'],
            'clinic.public.tagline_ar' => ['type' => 'text', 'label' => 'Tagline (Arabic)', 'placeholder' => 'التجميل والجلدية'],
            'clinic.public.logo_url' => ['type' => 'image', 'label' => 'Logo (square image, PNG/JPG/WebP)'],
            'clinic.public.phone' => ['type' => 'text', 'label' => 'Public phone number', 'placeholder' => '+965 5000 0000'],
            'clinic.public.whatsapp' => ['type' => 'text', 'label' => 'WhatsApp number', 'placeholder' => '+965 5000 0000'],
            'clinic.public.email' => ['type' => 'text', 'label' => 'Public email', 'placeholder' => 'hello@evamedical.kw'],
            'clinic.public.website' => ['type' => 'text', 'label' => 'Website (shown)', 'placeholder' => 'evamedical.kw'],
            'clinic.public.address_en' => ['type' => 'text', 'label' => 'Address (English)'],
            'clinic.public.address_ar' => ['type' => 'text', 'label' => 'Address (Arabic)'],
            'clinic.public.instagram' => ['type' => 'text', 'label' => 'Instagram URL'],
            'clinic.public.tiktok' => ['type' => 'text', 'label' => 'TikTok URL'],
            'clinic.public.snapchat' => ['type' => 'text', 'label' => 'Snapchat URL'],
        ];
    }

    public static function form(Form $form): Form
    {
        $keys = collect(self::allowedKeys())->mapWithKeys(fn ($cfg, $k) => [$k => $cfg['label']])->all();

        return $form->schema([
            Forms\Components\Select::make('key')
                ->options($keys)
                ->searchable()
                ->required()
                ->helperText('Choose a setting key to edit')
                ->reactive(),

            Forms\Components\Group::make([
                Forms\Components\Toggle::make('value')->label('Value')
                    ->visible(fn ($get) => self::allowedKeys()[$get('key')]['type'] ?? null === 'bool')
                    ->default(true),

                Forms\Components\TextInput::make('value')
                    ->label('Value')
                    ->visible(fn ($get) => in_array(self::allowedKeys()[$get('key')]['type'] ?? null, ['text', 'int', 'secret']))
                    ->password(fn ($get) => self::allowedKeys()[$get('key')]['type'] === 'secret')
                    ->numeric(fn ($get) => self::allowedKeys()[$get('key')]['type'] === 'int')
                    ->placeholder(fn ($get) => self::allowedKeys()[$get('key')]['placeholder'] ?? null),
            ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('value')->formatStateUsing(function ($state) {
                    if (is_array($state)) {
                        return json_encode($state);
                    }
                    $isSecret = str_contains((string) $state, 'EAAG'); // naive; hide long tokens

                    return $isSecret ? '••••••' : (string) $state;
                })->wrap(),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemSettings::route('/'),
            'create' => Pages\CreateSystemSetting::route('/create'),
            'edit' => Pages\EditSystemSetting::route('/{record}/edit'),
        ];
    }
}
