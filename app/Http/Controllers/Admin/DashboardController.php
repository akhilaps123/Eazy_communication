<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Services\CatalogueService;
use App\Support\OrderLifecycle;
use Illuminate\View\View;

/** Admin home: catalogue overview and live-catalogue status. */
class DashboardController extends Controller
{
    public function __invoke(CatalogueService $catalogue): View
    {
        return view('admin.dashboard', [
            'countriesTotal' => Country::count(),
            'countriesActive' => Country::where('is_active', true)->count(),
            'plansTotal' => Plan::count(),
            'plansActive' => Plan::where('is_active', true)->count(),
            'plansSellable' => $catalogue->sellablePlans()->count(),
            'pendingPrices' => PlanPrice::where('approval_status', PlanPrice::APPROVAL_PENDING)->count(),
            'liveCatalogue' => $catalogue->isLive(),
            'currency' => $catalogue->currency(),
            // Phase 9F: order & fulfilment overview (one aggregate query, same rules as the order filters).
            'orders' => OrderLifecycle::overview(),
        ]);
    }
}
