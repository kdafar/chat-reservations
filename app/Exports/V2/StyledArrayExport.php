<?php

namespace App\Exports\V2;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Reusable, presentable .xlsx export for the v2 admin's "Export to Excel"
 * buttons. Replaces the old hand-rolled `fputcsv` streams: instead of a bare
 * CSV, callers pass headings + rows and get a styled workbook — branded bold
 * header (teal, matching the print theme), frozen header row, per-column
 * auto-filter, auto-sized columns, and RTL when the locale is Arabic.
 */
class StyledArrayExport implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    /** Header background — teal-600, consistent with print + Filament exports. */
    protected string $headerColor = '0D9488';

    /**
     * @param  array<int, string>        $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function __construct(
        protected array $headings,
        protected array $rows,
        protected string $sheetTitle = 'Export',
        protected bool $rtl = false,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                if ($this->rtl) {
                    $sheet->setRightToLeft(true);
                }

                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();
                $headerRange = "A1:{$highestColumn}1";

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
                        ->setVertical(Alignment::VERTICAL_TOP);
                }

                $sheet->freezePane('A2');
                $sheet->setAutoFilter($headerRange);
            },
        ];
    }
}
