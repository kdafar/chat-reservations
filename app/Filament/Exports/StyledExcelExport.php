<?php

namespace App\Filament\Exports;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Drop-in replacement for pxlrbt's ExcelExport that produces a genuinely
 * presentable .xlsx instead of a bare CSV dump:
 *
 *   - Branded, bold header row (teal fill, white text) matching the
 *     clinic print theme.
 *   - Frozen header + auto-filter so large sheets stay navigable.
 *   - Auto-sized columns (inherited from ShouldAutoSize) and thin row
 *     borders for readability.
 *   - Right-to-left layout automatically when the panel locale is Arabic.
 *   - A timestamped, human-readable filename.
 *
 * Columns/headings/formatting are pulled straight from the Filament table
 * via fromTable(), so the spreadsheet mirrors exactly what the admin sees
 * on screen — no separate column schema to maintain per resource.
 */
class StyledExcelExport extends ExcelExport
{
    /** Header background — teal-600, same accent used across the print views. */
    protected string $headerColor = '0D9488';

    public function setUp(): void
    {
        parent::setUp();

        // Mirror the on-screen table (labels, formatting, visible columns).
        $this->fromTable();

        // Arabic admins get a proper RTL sheet.
        if (app()->getLocale() === 'ar') {
            $this->rtl();
        }

        // e.g. "patients-2026-05-31-1430"
        $this->withFilename(
            fn ($model) => str(class_basename($model))->plural()->kebab()->value()
                .'-'.now()->format('Y-m-d-Hi')
        );
    }

    public function registerEvents(): array
    {
        return array_merge(parent::registerEvents(), [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();
                $headerRange = "A1:{$highestColumn}1";

                // Branded header row.
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $this->headerColor],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                // Light borders + top-alignment for the data body.
                if ($highestRow > 1) {
                    $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E5E7EB'],
                            ],
                        ],
                    ]);
                    $sheet->getStyle("A2:{$highestColumn}{$highestRow}")
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_TOP)
                        ->setWrapText(true);
                }

                // Keep the header in view + give every column a filter dropdown.
                $sheet->freezePane('A2');
                $sheet->setAutoFilter($headerRange);
            },
        ]);
    }
}
