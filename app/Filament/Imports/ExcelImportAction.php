<?php

namespace App\Filament\Imports;

use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use Filament\Tables\Actions\ImportAction;

/**
 * Drop-in replacement for Filament's ImportAction that also accepts real
 * Excel workbooks (.xlsx / .xls / .ods), not just CSV.
 *
 * Filament's importer is hard-wired to League\Csv. Rather than fork it, we
 * tap the single chokepoint every read goes through — getUploadedFileStream()
 * — and transparently convert a spreadsheet upload into a CSV stream on the
 * fly. From there everything downstream (header detection, the smart
 * column-mapping modal, validation, the queued import job) is unchanged, so
 * staff can upload the same workbook they keep their roster in and Filament's
 * existing header auto-mapping does the matching. No rigid template required.
 */
class ExcelImportAction extends ImportAction
{
    /** Spreadsheet formats we convert to CSV before Filament reads them. */
    protected const SPREADSHEET_EXTENSIONS = ['xlsx', 'xls', 'ods'];

    /** Stashed copy of the parent's form closure so we can wrap it. */
    protected Closure | array | null $baseImportForm = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Wrap Filament's import form so the file picker also accepts Excel.
        $this->baseImportForm = $this->form;

        $this->form(function (Form $form): array {
            $components = $this->evaluate($this->baseImportForm, ['form' => $form]) ?? [];

            foreach ($components as $component) {
                if ($component instanceof FileUpload && $component->getName() === 'file') {
                    $component->acceptedFileTypes(array_values(array_unique(array_merge(
                        $component->getAcceptedFileTypes() ?? [],
                        [
                            // .xlsx
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            // .xls
                            'application/vnd.ms-excel',
                            'application/octet-stream',
                            // .ods
                            'application/vnd.oasis.opendocument.spreadsheet',
                        ],
                    ))));

                    $component->helperText(__('Upload a CSV or Excel file (.xlsx, .xls). Column headers are matched automatically — no template needed.'));
                }
            }

            return $components;
        });
    }

    /**
     * Transparently convert spreadsheet uploads to a CSV stream; CSV uploads
     * pass straight through to Filament's native handling.
     */
    public function getUploadedFileStream(TemporaryUploadedFile $file)
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::SPREADSHEET_EXTENSIONS, true)) {
            return parent::getUploadedFileStream($file);
        }

        return $this->convertSpreadsheetToCsvStream($file);
    }

    /**
     * Read the workbook (first sheet) with PhpSpreadsheet and re-emit it as a
     * UTF-8 CSV stream that League\Csv — and therefore Filament — understands.
     */
    protected function convertSpreadsheetToCsvStream(TemporaryUploadedFile $file)
    {
        // Materialise the upload to a local temp file so PhpSpreadsheet can read
        // it regardless of whether livewire-tmp is on local disk or S3.
        $sourcePath = tempnam(sys_get_temp_dir(), 'import_src_');
        file_put_contents($sourcePath, $file->get());

        try {
            $reader = IOFactory::createReaderForFile($sourcePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($sourcePath);

            $csvPath = tempnam(sys_get_temp_dir(), 'import_csv_');

            $writer = new CsvWriter($spreadsheet);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->setUseBOM(false);
            $writer->setSheetIndex(0);
            $writer->save($csvPath);

            return fopen($csvPath, 'r');
        } finally {
            @unlink($sourcePath);
        }
    }
}
