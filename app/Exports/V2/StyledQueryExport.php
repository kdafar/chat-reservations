<?php

namespace App\Exports\V2;

use Closure;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Streaming sibling of StyledArrayExport: takes a query builder + a row mapper
 * and writes the sheet in chunks via FromQuery, so very large/unbounded tables
 * (visits, bookings, logs, …) export without loading every row into memory at
 * once. Same styled header / freeze / autofilter / RTL.
 */
class StyledQueryExport implements FromQuery, ShouldAutoSize, WithCustomChunkSize, WithEvents, WithHeadings, WithMapping, WithTitle
{
    protected string $headerColor = '0D9488';

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $builder
     * @param  array<int, string>  $headings
     * @param  Closure(mixed): array<int, mixed>  $mapper
     */
    public function __construct(
        protected $builder,
        protected array $headings,
        protected Closure $mapper,
        protected string $sheetTitle = 'Export',
        protected bool $rtl = false,
    ) {}

    public function query()
    {
        return $this->builder;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function map($row): array
    {
        return ($this->mapper)($row);
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function chunkSize(): int
    {
        return 500;
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
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->headerColor]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                if ($highestRow > 1) {
                    $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']],
                        ],
                    ]);
                    $sheet->getStyle("A2:{$highestColumn}{$highestRow}")
                        ->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                }

                $sheet->freezePane('A2');
                $sheet->setAutoFilter($headerRange);
            },
        ];
    }
}
