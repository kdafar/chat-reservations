<?php

namespace App\Imports\V2;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads the first sheet of an .xlsx/.xls/.csv file into an array of assoc rows
 * keyed by the header row. Shared by the import controller (inline) and the
 * queued import job (background).
 */
class SpreadsheetReader
{
    public static function rows(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();

        $matrix = $sheet->toArray(null, true, false, false);
        if (empty($matrix)) {
            return [];
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_shift($matrix));

        $rows = [];
        foreach ($matrix as $line) {
            $row = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = $line[$i] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
