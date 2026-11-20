<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Esim;
use App\Models\Order;
use App\Services\Esim\EsimQrService;
use App\Services\Esim\EsimReadyNotifier;
use App\Services\Esim\InvalidActivationData;
use App\Support\CustomerEsims;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Phase 9E: "My eSIMs" for the signed-in customer.
 *
 * Every eSIM is loaded through CustomerEsims::findOrFail(): it must belong to
 * the user, its order must belong to the user, be paid with a server-verified
 * payment and be fulfilled — otherwise 404 (IDs cannot be probed). The policy
 * check runs as well (defence in depth). Activation details and the QR code
 * come from the Phase 9D services; nothing here contacts eSIM Go.
 */
class MyEsimsController extends Controller
{
    private const NO_STORE = ['Cache-Control' => 'no-store, private, max-age=0', 'Pragma' => 'no-cache'];

    public function index(Request $request, EsimQrService $qr): View
    {
        $user = $request->user();
        $esims = CustomerEsims::esims($user)->with('orderItem.order')->orderByDesc('esims.id')->get();

        // Orders without a visible eSIM yet: shown as status cards (never as fake eSIMs).
        $pending = CustomerEsims::orders($user)->with('items')
            ->whereNotIn('id', $esims->pluck('orderItem.order_id')->unique())
            ->whereIn('payment_status', [Order::PAYMENT_PENDING, Order::PAYMENT_PAID])
            ->whereNull('cancelled_at')
            ->orderByDesc('created_at')->limit(20)->get();

        return view('pages.my-esims', [
            'esims' => $esims,
            'qrReady' => $esims->mapWithKeys(fn (Esim $e) => [$e->id => $qr->image($e) !== null || $this->payloadValid($qr, $e)]),
            'pending' => $pending,
        ]);
    }

    public function show(Request $request, string $esim, EsimQrService $qr, EsimReadyNotifier $notifier): Response
    {
        $esim = CustomerEsims::findOrFail($request->user(), $esim);
        Gate::authorize('viewActivation', $esim);
        abort_unless($esim->isOwnedBy($request->user()), 404);

        try {
            $payload = $qr->payload($esim);
        } catch (InvalidActivationData) {
            $payload = null;
        }

        // QR image missing but the stored activation details are valid: re-create it
        // from those details (no provider call, nothing bought).
        $qrError = null;
        if ($payload !== null && $qr->image($esim) === null) {
            $result = $qr->generate($esim, 'customer_view');
            if ($result['ok']) {
                try {
                    $notifier->queueIfReady($esim->orderItem->order, 'qr_generated');
                } catch (Throwable) {
                }
            } else {
                $qrError = 'Your QR code could not be prepared right now. Please try again later or contact support.';
            }
        }

        return response()->view('pages.my-esim', [
            'esim' => $esim,
            'order' => $esim->orderItem->order,
            'bundle' => $esim->orderItem->plan_name,
            'payload' => $payload,
            'hasQr' => $payload !== null && $qr->image($esim) !== null,
            'qrError' => $payload === null
                ? 'The activation details for this eSIM are not available yet. Please contact support and quote your order number.'
                : $qrError,
        ])->withHeaders(self::NO_STORE);
    }

    /** The QR PNG — decrypted only after the ownership checks. */
    public function qr(Request $request, string $esim, EsimQrService $qr): Response
    {
        $esim = CustomerEsims::findOrFail($request->user(), $esim);
        Gate::authorize('viewActivation', $esim);
        abort_unless($esim->isOwnedBy($request->user()), 404);

        $png = $qr->image($esim);
        abort_if($png === null, 404);

        return response($png, 200, self::NO_STORE + [
            'Content-Type' => 'image/png',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="eazy-esim-'.substr((string) $esim->iccid, -4).'-qr.png"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function payloadValid(EsimQrService $qr, Esim $esim): bool
    {
        try {
            $qr->payload($esim);

            return true;
        } catch (InvalidActivationData) {
            return false;
        }
    }
}
