<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Esim;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Esim\EsimQrService;
use App\Services\Esim\EsimReadyNotifier;
use App\Services\Esim\InvalidActivationData;
use App\Services\EsimGo\FulfilmentService;
use App\Support\AdminAudit;
use App\Support\OrderLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Phase 9F: Admin "Orders & Fulfillment" — monitoring of the order lifecycle.
 * Viewing never changes data or contacts eSIM Go. The only two actions
 * (POST, audited) delegate to FulfilmentService (Phase 9C), which re-checks
 * every rule: a read-only reconciliation and a deliberate retry of a
 * confirmed failed purchase. No cancel / mark-paid / mark-fulfilled / delete.
 *
 * Never shown: passwords, tokens, API keys or gateway payloads. Phase 9D: the
 * eSIM section shows the QR code and manual installation details (SM-DP+
 * address, activation code) to administrators only — collapsed, on a page
 * sent with Cache-Control: no-store; the QR image comes from an admin-only
 * route scoped to the order. Logs and audit entries never contain them.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = self::filters($request);

        $query = Order::query()
            ->select('orders.*')
            ->addSelect(['latest_payment_status' => Payment::query()->select('status')
                ->whereColumn('payments.order_id', 'orders.id')->orderByDesc('id')->limit(1)])
            ->with(['user:id,name,email', 'providerOrder:id,order_id,status']);

        OrderLifecycle::search($query, $filters['q']);
        OrderLifecycle::wherePayment($query, $filters['payment']);
        OrderLifecycle::whereStage($query, $filters['fulfilment']);
        $query->when($filters['status'], fn ($q, $status) => $q->where('payment_status', $status));

        return view('admin.orders.index', [
            'orders' => $query->orderByDesc('orders.created_at')->orderByDesc('orders.id')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'total' => Order::query()->count(),
            'attention' => Order::query()->where('fulfillment_status', Order::FULFILLMENT_NEEDS_ATTENTION)->count(),
        ]);
    }

    public function show(Order $order, FulfilmentService $fulfilment, EsimQrService $qr, EsimReadyNotifier $notifier): Response
    {
        if (EsimReadyNotifier::trackingAvailable()) {
            $order->load('esimReadyNotification');
        }

        $order->load([
            'user:id,name,email',
            'items.plan:id,name',
            'payments' => fn ($q) => $q->orderBy('id'),
            'providerOrder',
            'esims' => fn ($q) => $q->orderBy('esims.id'),
            'integrationLogs' => fn ($q) => $q->where('service', '!=', IntegrationLog::SERVICE_ADMIN)->orderByDesc('id')->limit(50),
        ]);

        // Phase 9D: QR state per eSIM (database + private storage only; no provider call, no writes).
        $qrLogs = IntegrationLog::query()->where('order_id', $order->id)->where('service', IntegrationLog::SERVICE_ESIM_QR)->orderByDesc('id')->get();
        $esimQr = $order->esims->mapWithKeys(function (Esim $esim) use ($qr, $qrLogs) {
            Gate::authorize('viewActivation', $esim);
            try {
                $payload = $qr->payload($esim);
            } catch (InvalidActivationData) {
                $payload = null;
            }

            return [$esim->id => ['status' => $qr->status($esim, $qrLogs), 'payload' => $payload]];
        });

        $actions = IntegrationLog::query()->where('order_id', $order->id)->where('service', IntegrationLog::SERVICE_ADMIN)
            ->orderByDesc('id')->limit(50)->get();
        $admins = User::query()->whereIn('id', $actions->pluck('request_body.admin_user_id')->filter()->unique())->pluck('name', 'id');

        // Eligibility is worked out on the server from the database only (no provider call).
        // The page can contain eSIM activation details: never cache it.
        return response()->view('admin.orders.show', [
            'order' => $order,
            'stage' => OrderLifecycle::stage($order),
            'timeline' => OrderLifecycle::timeline($order),
            'reconcileBlocked' => $fulfilment->reconcileBlockedReason($order),
            'retryBlocked' => $fulfilment->retryBlockedReason($order),
            'actions' => $actions,
            'admins' => $admins,
            'esimQr' => $esimQr,
            'emailTracking' => EsimReadyNotifier::trackingAvailable(),
            'email' => $order->relationLoaded('esimReadyNotification') ? $order->esimReadyNotification : null,
            'emailBlocked' => $notifier->blockingReason($order),
        ])->withHeaders(self::NO_STORE);
    }

    /**
     * Phase 9E: queue the "eSIM ready" email again after a failure (or a first time
     * when none was queued). Only the email: no provider call, no status change.
     */
    public function retryEsimReadyEmail(Request $request, Order $order, EsimReadyNotifier $notifier): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $state = AdminAudit::state($order);

        $result = $notifier->retry($order);
        $ok = $result['result'] === EsimReadyNotifier::RESULT_QUEUED;
        AdminAudit::record($request->user(), $order, $ok ? 'email_retry_queued' : 'email_retry_rejected', $ok,
            $state, AdminAudit::state($order->refresh()), $data['reason'] ?? null, $result['message']);

        return $this->back($order, $ok, ($ok ? '' : 'Email not queued: ').$result['message']);
    }

    /**
     * Phase 9D: the eSIM's QR code PNG (decrypted from private storage).
     * Admin only, and only for an eSIM that belongs to the order in the URL.
     */
    public function qrImage(Request $request, Order $order, Esim $esim, EsimQrService $qr): Response
    {
        $this->esimOfOrder($order, $esim);
        Gate::authorize('viewActivation', $esim);

        $png = $qr->image($esim);
        abort_if($png === null, 404, 'No QR code has been generated for this eSIM.');

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($png, 200, self::NO_STORE + [
            'Content-Type' => 'image/png',
            'Content-Disposition' => $disposition.'; filename="esim-'.$esim->iccid.'-qr.png"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Phase 9D: re-create the QR code from the activation details already stored
     * for this eSIM. No eSIM Go call, no purchase, no status change. Audited.
     */
    public function regenerateQr(Request $request, Order $order, Esim $esim, EsimQrService $qr): RedirectResponse
    {
        $this->esimOfOrder($order, $esim);
        Gate::authorize('regenerateQr', $esim);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $state = AdminAudit::state($order);
        $result = $qr->generate($esim, 'admin_regenerate');
        AdminAudit::record($request->user(), $order, $result['ok'] ? 'qr_regenerated' : 'qr_regeneration_failed', $result['ok'],
            $state, AdminAudit::state($order->refresh()), $data['reason'] ?? null, 'eSIM '.$esim->iccid.': '.$result['message']);

        // Phase 9E: a QR code that was missing may have been the only thing holding back the email.
        if ($result['ok']) {
            try {
                app(EsimReadyNotifier::class)->queueIfReady($order, 'qr_regenerated');
            } catch (\Throwable) {
            }
        }

        return $this->back($order, $result['ok'], $result['message']);
    }

    private const NO_STORE = ['Cache-Control' => 'no-store, private, max-age=0', 'Pragma' => 'no-cache'];

    /** 404 unless the eSIM really belongs to this order (no access through an unrelated order). */
    private function esimOfOrder(Order $order, Esim $esim): void
    {
        abort_unless((int) $esim->orderItem()->value('order_id') === (int) $order->id, 404);
    }

    /**
     * Read-only reconciliation (Phase 9F): fetch the results of the EXISTING
     * eSIM Go order by its stored reference. Never sends a purchase.
     */
    public function reconcile(Request $request, Order $order, FulfilmentService $fulfilment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $admin = $request->user();
        $before = AdminAudit::state($order);
        AdminAudit::record($admin, $order, 'reconciliation_requested', true, $before, null, $data['reason'] ?? null);

        $result = $fulfilment->reconcile($order);
        $after = AdminAudit::state($order->refresh());
        $action = match ($result['result']) {
            FulfilmentService::RESULT_FULFILLED => 'reconciliation_completed',
            FulfilmentService::RESULT_SKIPPED, FulfilmentService::RESULT_BUSY => 'action_blocked',
            default => 'reconciliation_incomplete',
        };
        AdminAudit::record($admin, $order, $action, $action === 'reconciliation_completed', $before, $after, $data['reason'] ?? null, $result['message']);

        return $this->back($order, $action === 'reconciliation_completed', 'Reconciliation: '.$result['message']);
    }

    /**
     * Deliberate retry of a CONFIRMED failed purchase (Phase 9F). All checks and
     * the purchase itself stay in FulfilmentService; this only records the request.
     */
    public function retry(Request $request, Order $order, FulfilmentService $fulfilment): RedirectResponse
    {
        $data = $request->validate([
            'confirm' => ['accepted'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], ['confirm.accepted' => 'Tick the box to confirm the retry.']);
        $admin = $request->user();
        $before = AdminAudit::state($order);
        AdminAudit::record($admin, $order, 'retry_requested', true, $before, null, $data['reason'] ?? null);

        $result = $fulfilment->queueRetry($order);
        $after = AdminAudit::state($order->refresh());
        $action = match ($result['result']) {
            FulfilmentService::RESULT_QUEUED => 'retry_queued',
            FulfilmentService::RESULT_BUSY => 'action_blocked',
            default => 'retry_rejected',
        };
        AdminAudit::record($admin, $order, $action, $action === 'retry_queued', $before, $after, $data['reason'] ?? null, $result['message']);

        return $this->back($order, $action === 'retry_queued', ($action === 'retry_queued' ? '' : 'Retry not queued: ').$result['message']);
    }

    private function back(Order $order, bool $ok, string $message): RedirectResponse
    {
        $redirect = redirect()->route('admin.orders.show', $order);

        return $ok ? $redirect->with('status', $message) : $redirect->withErrors(['action' => $message]);
    }

    /** @return array{q: string, payment: ?string, status: ?string, fulfilment: ?string} */
    public static function filters(Request $request): array
    {
        $pick = fn (string $key, array $allowed) => in_array($request->query($key), $allowed, true) ? $request->query($key) : null;

        return [
            'q' => mb_substr(trim((string) $request->query('q')), 0, 100),
            'payment' => $pick('payment', array_keys(OrderLifecycle::PAYMENT_FILTERS)),
            'status' => $pick('status', array_keys(OrderLifecycle::ORDER_STATUSES)),
            'fulfilment' => $pick('fulfilment', array_keys(OrderLifecycle::FULFILMENT_FILTERS)),
        ];
    }
}
