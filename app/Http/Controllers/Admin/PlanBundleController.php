<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\ProviderBundle;
use App\Services\CatalogueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin: change which provider bundle a plan uses ("re-link").
 *
 * Only plans.provider_bundle_id changes. The plan keeps its id, its
 * customer-facing details, its active/inactive status and all its prices;
 * the previous bundle is kept; past orders keep their own snapshots
 * (order_items.bundle_code, unit_provider_cost) and are never touched.
 */
class PlanBundleController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function edit(Request $request, Plan $plan): View
    {
        $plan->load('providerBundle');
        $filters = [
            'q' => trim((string) $request->query('q')),
            'country' => strtoupper(trim((string) $request->query('country'))) ?: null,
        ];

        $candidates = ProviderBundle::query()
            ->selectableForPlan($plan)
            ->whereKeyNot($plan->provider_bundle_id)
            ->search($filters['q'])
            ->forCountry($filters['country'])
            ->orderBy('bundle_code')
            ->paginate(15)
            ->withQueryString();

        return view('admin.plans.provider-bundle', [
            'plan' => $plan,
            'current' => $plan->providerBundle,
            'candidates' => $candidates,
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $request->validate(
            ['provider_bundle_id' => ['required', 'integer']],
            ['provider_bundle_id.required' => 'Select a provider bundle.', 'provider_bundle_id.integer' => 'Select a provider bundle.'],
        );
        $newId = $request->integer('provider_bundle_id');

        [$old, $new] = DB::transaction(function () use ($plan, $newId) {
            // Lock both rows so two admins cannot link the same bundle at once.
            $plan = Plan::query()->lockForUpdate()->findOrFail($plan->id);
            $new = ProviderBundle::query()->lockForUpdate()->find($newId);
            $old = ProviderBundle::query()->find($plan->provider_bundle_id);

            $error = match (true) {
                $new === null || $new->provider !== ProviderBundle::PROVIDER_ESIMGO => 'The selected provider bundle does not exist.',
                $new->id === $plan->provider_bundle_id => 'This plan already uses that provider bundle.',
                ! $new->is_available => 'That provider bundle is not available from eSIM Go and cannot be linked.',
                Plan::query()->where('provider_bundle_id', $new->id)->exists() => 'That provider bundle is already linked to another plan.',
                default => null,
            };
            if ($error !== null) {
                throw ValidationException::withMessages(['provider_bundle_id' => $error]);
            }

            // Only the link changes — nothing else on the plan (is_active included).
            $plan->forceFill(['provider_bundle_id' => $new->id])->save();

            return [$old, $new];
        });

        $plan->refresh()->load('providerBundle');
        $message = 'Provider bundle changed from '.($old?->bundle_code ?? '—').' to '.$new->bundle_code.'. The previous bundle, the plan\'s prices and past orders were not changed.';

        $currency = $this->catalogue->currency();
        $warnings = [];
        if ($current = $plan->currentPrice($currency)) {
            if ($plan->isPriceBelowCost($current)) {
                $warnings[] = 'The current price is below the new provider cost — add a new price.';
            }
        }
        if (strtoupper((string) $new->provider_currency) !== $currency) {
            $warnings[] = "The new bundle's cost is in {$new->provider_currency}, so markup prices cannot be calculated.";
        }
        if ($plan->is_active && $problems = $plan->sellabilityProblems($currency)) {
            $warnings[] = 'This active plan is currently hidden from customers — '.implode(' ', $problems);
        }
        if ($warnings !== []) {
            $message .= ' Warning: '.implode(' ', $warnings);
        }

        return redirect()->route('admin.plans.show', $plan)->with('status', $message);
    }
}
