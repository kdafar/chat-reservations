<?php

namespace App\Filament\Resources\BranchResource\RelationManagers;

use App\Models\Block;
use App\Models\State;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\Concerns\Translatable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CoverageRelationManager extends RelationManager
{
    use Translatable;

    protected static string $relationship = 'coverageBlocks';

    protected static ?string $title = 'Coverage';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('delivery_fee')->numeric()->step('0.001')->label(__('Delivery Fee'))->required(),
            TextInput::make('min_order_amount')->numeric()->step('0.001')->label(__('Min Order'))->required()->default(0),
            Tables\Columns\IconColumn::make('is_active')->label(__('Active')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label(__('Block'))
                    // 👇 FIX: Use the $this->activeLocale property
                    ->searchable(query: fn (Builder $q, $s) => $q->where('name->'.$this->activeLocale, 'like', "%{$s}%"))
                    ->sortable(),
                TextColumn::make('city.name')->label(__('City'))->sortable(),
                TextColumn::make('city.state.name')->label(__('State'))->sortable(),
                TextColumn::make('pivot.delivery_fee')->label(__('Delivery Fee'))->money('kwd', true),
                TextColumn::make('pivot.min_order_amount')->label(__('Min Order'))->money('kwd', true),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('Filter by State')
                    // 👇 FIX: Use the $this->activeLocale property
                    ->relationship('city.state', 'name->'.$this->activeLocale)
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                Action::make('bulk_assign_by_state')
                    ->label(__('Bulk Assign by State'))
                    ->icon('heroicon-o-plus-circle')
                    ->modalWidth('5xl')
                    ->form(function () {
                        // 👇 FIX: Use the $this->activeLocale property
                        $locale = $this->activeLocale;
                        $states = State::with('cities')->get();
                        $tabs = [];

                        foreach ($states as $state) {
                            $tabs[] = Tabs\Tab::make($state->getTranslation('name', $locale))
                                ->schema([
                                    TextInput::make($state->id.'_delivery_fee')->label('Delivery Fee for selected cities in '.$state->getTranslation('name', $locale))->numeric()->step('0.001')->prefix('KWD')->requiredWith($state->id.'_cities'),
                                    TextInput::make($state->id.'_min_order_amount')->label('Minimum Order for selected cities in '.$state->getTranslation('name', $locale))->numeric()->step('0.001')->prefix('KWD')->default(0)->requiredWith($state->id.'_cities'),
                                    CheckboxList::make($state->id.'_cities')
                                        ->label('Select Cities in '.$state->getTranslation('name', $locale))
                                        ->options(
                                            $state->cities->mapWithKeys(fn ($city) => [
                                                $city->id => $city->getTranslation('name', $locale),
                                            ])
                                        )
                                        ->bulkToggleable()
                                        ->columns(3),
                                ]);
                        }

                        return [Tabs::make('States')->tabs($tabs)];
                    })
                    ->action(function (array $data) {
                        $branch = $this->getOwnerRecord();
                        $states = State::all();
                        $dataToAttach = [];

                        foreach ($states as $state) {
                            $feeKey = $state->id.'_delivery_fee';
                            $minOrderKey = $state->id.'_min_order_amount';
                            $citiesKey = $state->id.'_cities';

                            if (! empty($data[$citiesKey]) && isset($data[$feeKey])) {
                                $blockIds = Block::whereIn('city_id', $data[$citiesKey])->pluck('id');
                                foreach ($blockIds as $blockId) {
                                    $dataToAttach[$blockId] = [
                                        'delivery_fee' => $data[$feeKey],
                                        'min_order_amount' => $data[$minOrderKey] ?? 0,
                                        'is_active' => true,
                                    ];
                                }
                            }
                        }

                        if (! empty($dataToAttach)) {
                            $branch->coverageBlocks()->syncWithoutDetaching($dataToAttach);
                            Notification::make()->title(__('Coverage areas updated successfully'))->success()->send();
                        }
                    }),
                Tables\Actions\AttachAction::make()->preloadRecordSelect(),
                Tables\Actions\LocaleSwitcher::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
            ]);
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }
}
