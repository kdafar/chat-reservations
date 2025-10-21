<?php

namespace App\Filament\Partner\Pages;

use Filament\Actions;
use Filament\Pages\Page;

class ImportCenter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-on-square-stack';

    protected static ?string $navigationLabel = 'Import Center';

    protected static ?string $title = 'Import Center';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.partner.pages.import-center';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadTemplate')
                ->label(__('Download Template'))
                ->icon('heroicon-o-document-arrow-down')
                ->url(route('partner.import.template')), // define a simple route to a stored file

            Actions\Action::make('uploadExcel')
                ->label(__('Upload Excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('file')
                        ->label(__('Excel file (.xlsx)'))
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->required()
                        ->directory('partner-imports'),
                ])
                ->action(function (array $data) {
                    \App\Jobs\ImportBranchMenus::dispatch((int) session('active_partner_id'), $data['file']);
                    \Filament\Notifications\Notification::make()->title(__('Import started'))->success()->send();
                })
                ->modalWidth('lg')
                ->slideOver(),
        ];
    }
}
