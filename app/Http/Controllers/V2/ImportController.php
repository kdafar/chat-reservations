<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Imports\V2\ImportRegistry;
use App\Imports\V2\SpreadsheetReader;
use App\Jobs\RunSpreadsheetImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generic entry point for all v2 spreadsheet imports. The {type} segment is
 * resolved to a concrete importer via ImportRegistry, so one controller serves
 * every table. Each importer declares the write permission it requires.
 */
class ImportController extends Controller
{
    /** Files with more rows than this are imported in the background. */
    protected const QUEUE_THRESHOLD = 1000;

    /** Download the styled .xlsx template for a table. */
    public function template(Request $request, string $type): StreamedResponse|BinaryFileResponse
    {
        $importer = ImportRegistry::resolve($type);
        abort_unless($importer, 404);
        abort_unless($importer->authorize($request->user()), 403);

        $spreadsheet = $importer->buildTemplate();
        $filename = $type.'-import-template.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new XlsxWriter($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Upload + import (or preview) a filled-in spreadsheet. Returns JSON. */
    public function store(Request $request, string $type): JsonResponse
    {
        $importer = ImportRegistry::resolve($type);
        abort_unless($importer, 404);
        // Imports WRITE data — gate on the table's write permission.
        abort_unless($importer->authorize($request->user()), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'mode' => ['nullable', 'in:upsert,skip'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $upload = $request->file('file');
        $mode = $request->input('mode', 'upsert');
        $dryRun = $request->boolean('dry_run');
        $user = $request->user();

        try {
            $rows = SpreadsheetReader::rows($upload->getRealPath());
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Could not read the file: '.$e->getMessage()], 422);
        }

        if (empty($rows)) {
            return response()->json(['ok' => false, 'message' => 'The file has no data rows.'], 422);
        }

        // Preview (dry-run) reads + classifies each row inline. For very large
        // files that per-row work would be slow, and the commit is queued
        // anyway — so skip the row-by-row preview and let the user confirm
        // straight into the background import.
        if ($dryRun) {
            if (count($rows) > self::QUEUE_THRESHOLD) {
                return response()->json(['ok' => true, 'preview' => true, 'large' => true, 'count' => count($rows)]);
            }

            return response()->json(['ok' => true, 'preview' => true] + $importer->preview($rows, $mode, $user));
        }

        // Large commits run in the background so the request doesn't time out.
        if (count($rows) > self::QUEUE_THRESHOLD) {
            $stored = $upload->store('imports', 'local');
            RunSpreadsheetImport::dispatch($type, $stored, $mode, $user?->id);

            return response()->json(['ok' => true, 'queued' => true, 'count' => count($rows)]);
        }

        return response()->json(['ok' => true, 'preview' => false] + $importer->import($rows, $mode, $user));
    }
}
