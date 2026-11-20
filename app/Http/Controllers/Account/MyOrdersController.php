<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\CatalogueService;
use App\Support\CustomerEsims;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 9E: "My Orders" — the signed-in customer's own orders only.
 * Read-only: no status can be changed here. Customer-safe fields only
 * (no provider data, logs, audit entries or gateway details).
 */
class MyOrdersController extends Controller
{
    public function index(Request $request): View
    {
        $orders = CustomerEsims::orders($request->user())
            ->with(['items' => fn ($q) => $q->orderBy('id')])
            ->withCount('esims')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(10);

        return view('pages.my-orders', [
            'orders' => $orders,
            'money' => fn (string $amount, string $currency) => CatalogueService::money($amount, $currency),
        ]);
    }
}
