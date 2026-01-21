<?php

namespace App\Http\Controllers\Admin;

use App\Models\BulkInviteCampaign;
use Illuminate\Routing\Controller;
use Spatie\SimpleExcel\SimpleExcelWriter;

class BulkInviteSamplesController extends Controller
{
    /**
     * CSV prefilled sample from campaign recipients.
     * Headers: msisdn,name,locale (importer also accepts phone/mobile/whatsapp).
     */
    public function csv(BulkInviteCampaign $campaign)
    {
        $filename = 'bulk-invite-'.$campaign->id.'-recipients-sample.csv';
        $bom = "\xEF\xBB\xBF"; // ensure Excel renders Arabic correctly

        return response()->streamDownload(function () use ($campaign, $bom) {
            // open output
            $out = fopen('php://output', 'w');

            // write BOM first
            echo $bom;

            // header row that your importer already supports
            fputcsv($out, ['msisdn', 'name', 'locale']);

            $count = 0;
            foreach (
                $campaign->recipients()
                    ->select('msisdn', 'name', 'locale')
                    ->orderBy('id')
                    ->cursor() as $r
            ) {
                fputcsv($out, [$r->msisdn, $r->name, $r->locale]);
                $count++;
            }

            // fallback (Kuwait E.164 examples) if empty
            if ($count === 0) {
                fputcsv($out, ['+96550000000', 'أحمد', 'ar']);
                fputcsv($out, ['+96551112233', 'Sara',  'en']);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    /**
     * XLSX prefilled sample from campaign recipients (SimpleExcel).
     */
    public function xlsx(BulkInviteCampaign $campaign)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'recipients_').'.xlsx';

        $writer = SimpleExcelWriter::create($tmp)
            ->addHeader(['msisdn', 'name', 'locale']);

        $count = 0;
        foreach (
            $campaign->recipients()
                ->select('msisdn', 'name', 'locale')
                ->orderBy('id')
                ->cursor() as $r
        ) {
            $writer->addRow([
                'msisdn' => $r->msisdn,
                'name' => $r->name,
                'locale' => $r->locale,
            ]);
            $count++;
        }

        if ($count === 0) {
            $writer->addRows([
                ['msisdn' => '+96550000000', 'name' => 'أحمد', 'locale' => 'ar'],
                ['msisdn' => '+96551112233', 'name' => 'Sara', 'locale' => 'en'],
            ]);
        }

        $writer->close();

        return response()->download(
            $tmp,
            'bulk-invite-'.$campaign->id.'-recipients-sample.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }
}
