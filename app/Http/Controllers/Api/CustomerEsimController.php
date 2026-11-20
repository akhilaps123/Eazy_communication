<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Esim;
use App\Models\Order;
use App\Services\Esim\EsimQrService;
use App\Services\Esim\InvalidActivationData;
use App\Support\CustomerEsims;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Phase 9E: customer orders and eSIMs over the API (Sanctum Bearer token only).
 *
 * Same rules as the website (App\Support\CustomerEsims): only the token owner's
 * records; eSIMs only for paid, server-verified, fulfilled orders; anything
 * else 404. Never returned: encrypted columns, storage paths, provider data,
 * logs or another customer's data. Activation details only on the single-eSIM
 * endpoint, decrypted for the owner, with Cache-Control: no-store.
 */
class CustomerEsimController extends Controller
{
    private const NO_STORE = ['Cache-Control' => 'no-store, private, max-age=0'];

    public function orders(Request $request): JsonResponse
    {
        $page = CustomerEsims::orders($request->user())->with(['items' => fn ($q) => $q->orderBy('id')])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Order $o) => $this->orderData($o))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ], 200, self::NO_STORE);
    }

    public function esims(Request $request, EsimQrService $qr): JsonResponse
    {
        $esims = CustomerEsims::esims($request->user())->with('orderItem.order')->orderByDesc('esims.id')->get();

        return response()->json(['data' => $esims->map(fn (Esim $e) => $this->esimData($e, $qr))->values()], 200, self::NO_STORE);
    }

    public function esim(Request $request, string $esim, EsimQrService $qr): JsonResponse
    {
        $esim = CustomerEsims::findOrFail($request->user(), $esim);
        Gate::forUser($request->user())->authorize('viewActivation', $esim);
        abort_unless($esim->isOwnedBy($request->user()), 404);

        try {
            $p = $qr->payload($esim);
            $activation = ['smdp_address' => $p->smdpHost, 'activation_code' => $p->matchingId, 'lpa' => $p->toString()];
        } catch (InvalidActivationData) {
            $activation = null;
        }

        return response()->json(['data' => $this->esimData($esim, $qr) + ['iccid' => $esim->iccid, 'activation' => $activation]], 200, self::NO_STORE);
    }

    private function orderData(Order $o): array
    {
        $stage = CustomerEsims::stage($o);

        return [
            'order_number' => $o->order_number,
            'created_at' => $o->created_at?->toIso8601String(),
            'currency' => $o->currency,
            'total' => (string) $o->total,
            'payment_status' => $o->payment_status,
            'payment_label' => CustomerEsims::paymentLabel($o),
            'fulfilment_stage' => $stage,
            'fulfilment_label' => CustomerEsims::stageLabel($stage),
            'items' => $o->items->map(fn ($i) => ['plan' => $i->plan_name, 'quantity' => $i->quantity, 'line_total' => (string) $i->line_total])->values(),
        ];
    }

    private function esimData(Esim $e, EsimQrService $qr): array
    {
        $order = $e->orderItem->order;

        return [
            'id' => $e->id,
            'plan' => $e->orderItem->plan_name,
            'iccid_masked' => CustomerEsims::maskIccid($e->iccid),
            'order_number' => $order->order_number,
            'purchased_at' => ($order->paid_at ?? $e->created_at)?->toIso8601String(),
            'status' => $e->status,
            'status_label' => CustomerEsims::activationLabel($e->status),
            'qr_available' => $qr->image($e) !== null,
        ];
    }
}
