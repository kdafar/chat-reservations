<?php

namespace App\Filament\Exports;

use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

/**
 * Factory for the styled "Export to Excel" table actions, so the same
 * configuration is reused everywhere instead of copy-pasted.
 *
 * The header action (export the whole filtered table) is attached to every
 * admin table globally in AppServiceProvider. It is also added explicitly to
 * the handful of resources that define their own ->headerActions() (which
 * would otherwise replace the global one), plus the bulk "Export selected"
 * action on the core clinical tables.
 */
class ExcelExportActions
{
    /** Export the full, currently-filtered table. */
    public static function header(): ExportAction
    {
        return ExportAction::make('excelExport')
            ->label(__('Export to Excel'))
            ->icon('heroicon-o-table-cells')
            ->color('gray')
            ->exports([StyledExcelExport::make()]);
    }

    /** Export only the rows the user ticked. */
    public static function bulk(): ExportBulkAction
    {
        return ExportBulkAction::make('excelExport')
            ->label(__('Export selected'))
            ->icon('heroicon-o-table-cells')
            ->color('gray')
            ->exports([StyledExcelExport::make()]);
    }
}
