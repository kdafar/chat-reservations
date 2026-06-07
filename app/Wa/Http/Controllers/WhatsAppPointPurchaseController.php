<?php

namespace App\Wa\Http\Controllers;

use App\Wa\Hub\Models\PointPurchase;
use App\Wa\Jobs\SendPointPurchaseInvoiceJob;
use App\Wa\Services\MyFatoorahPointPurchaseService;
use Illuminate\Http\Request;

class WhatsAppPointPurchaseController extends Controller
{
    private function fleetResultUrl(PointPurchase $purchase): string
    {
        // Filament Fleet panel path is: /fleet/portal
        return "/fleet/portal/points/purchase-result/{$purchase->id}";
    }

    private function fleetLoginRedirectUrl(PointPurchase $purchase): string
    {
        $target = $this->fleetResultUrl($purchase);

        // If user already logged in, Filament will redirect straight to $target.
        // If not logged in, Filament login will redirect after success.
        return '/fleet/portal/login?redirect='.urlencode($target);
    }

    public function callback(Request $request, MyFatoorahPointPurchaseService $mf)
    {
        $purchaseId = (int) $request->get('UserDefinedField', 0);

        if (! $purchaseId) {
            $purchaseId = (int) $request->get('purchase_id', 0);
        }

        $purchase = PointPurchase::find($purchaseId);
        if (! $purchase) {
            abort(404, 'Purchase not found');
        }

        // Save payment id if present
        if ($pid = $request->get('paymentId') ?: $request->get('PaymentId')) {
            $purchase->transaction_id = (string) $pid;
            $purchase->payment_gateway = 'myfatoorah';
            $purchase->save();
        }

        // Enquiry for full details (best source of truth)
        if ($purchase->transaction_id) {
            if ($res = $mf->enquiry($purchase)) {
                $mf->syncFromEnquiry($purchase, $res);
            }
        }

        // If paid/completed → create invoice + send whatsapp
        if (in_array((string) $purchase->status, ['paid', 'completed'], true)) {
            SendPointPurchaseInvoiceJob::dispatch($purchase->id);
        }

        //  Redirect user to Fleet portal result page (works even if logged out)
        return redirect()->to($this->fleetLoginRedirectUrl($purchase));
    }

    public function error(Request $request)
    {
        $purchaseId = (int) $request->get('UserDefinedField', 0);

        if (! $purchaseId) {
            $purchaseId = (int) $request->get('purchase_id', 0);
        }

        if ($purchaseId) {
            if ($purchase = PointPurchase::find($purchaseId)) {
                $purchase->status = 'failed';
                $purchase->save();

                //  Redirect user to Fleet portal result page (works even if logged out)
                return redirect()->to($this->fleetLoginRedirectUrl($purchase));
            }
        }

        return redirect('/'); // fallback
    }

    //  Optional: You can remove this now because we use the Filament page result.
    // If you keep it, it still works, but callback/error should not go here anymore.
    public function result(Request $request, PointPurchase $purchase)
    {
        abort_unless((int) $request->user()->id === (int) $purchase->user_id, 403);

        return view('points.result', compact('purchase'));
    }
}
