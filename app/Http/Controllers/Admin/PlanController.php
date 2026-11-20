<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\Country;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\ProviderBundle;
use App\Services\CatalogueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin plan management. Plans are never deleted (decision D10) — only
 * deactivated — because carts and orders reference them.
 */
class PlanController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q')),
            'country' => $request->integer('country') ?: null,
            'coverage' => in_array($request->query('coverage'), Plan::COVERAGE_TYPES, true) ? $request->query('coverage') : null,
            'data_type' => in_array($request->query('data_type'), Plan::DATA_TYPES, true) ? $request->query('data_type') : null,
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null,
            // Read-only list filter: plans that have at least one price waiting for approval.
            'prices' => $request->query('prices') === PlanPrice::APPROVAL_PENDING ? PlanPrice::APPROVAL_PENDING : null,
        ];
        $currency = $this->catalogue->currency();

        $plans = Plan::query()
            ->with([
                'providerBundle',
                'countries' => fn ($q) => $q->ordered(),
                'prices' => fn ($q) => $q->where('currency', $currency)->applicable()->orderByDesc('effective_from'),
            ])
            ->withCount(['prices as pending_prices_count' => fn ($q) => $q->where('approval_status', PlanPrice::APPROVAL_PENDING)])
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('plans.name', 'like', '%'.$filters['q'].'%')
                ->orWhere('plans.slug', 'like', '%'.$filters['q'].'%')
                ->orWhereHas('providerBundle', fn ($b) => $b->where('bundle_code', 'like', '%'.$filters['q'].'%'))))
            ->when($filters['country'], fn ($q, $id) => $q->whereHas('countries', fn ($c) => $c->whereKey($id)))
            ->when($filters['coverage'], fn ($q, $v) => $q->where('coverage_type', $v))
            ->when($filters['data_type'], fn ($q, $v) => $q->where('data_type', $v))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('plans.is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('plans.is_active', false))
            ->when($filters['prices'], fn ($q, $status) => $q->whereHas('prices', fn ($p) => $p->where('approval_status', $status)))
            ->orderBy('plans.sort_order')->orderBy('plans.name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.plans.index', [
            'plans' => $plans,
            'filters' => $filters,
            'countries' => Country::query()->ordered()->get(['id', 'name']),
            'currency' => $currency,
        ]);
    }

    /**
     * New plan form. "Create plan from this bundle" (Provider Bundles page) links
     * here with ?bundle={id}: that bundle is preselected and, as a starting point
     * only, its data / validity / speed / countries pre-fill the plan fields.
     */
    public function create(Request $request): View
    {
        $plan = new Plan(['data_type' => Plan::DATA_FIXED, 'coverage_type' => Plan::COVERAGE_LOCAL]);
        $countries = Country::query()->active()->ordered()->get();
        $selected = [];

        $bundleId = (int) old('provider_bundle_id', $request->integer('bundle'));
        $bundle = $bundleId > 0 ? ProviderBundle::query()->with('plan:id,provider_bundle_id,name')->find($bundleId) : null;

        if ($bundle !== null && old('provider_bundle_id') === null && $request->integer('bundle') === $bundle->id) {
            $plan->fill([
                'data_type' => $bundle->is_unlimited ? Plan::DATA_UNLIMITED : Plan::DATA_FIXED,
                'data_amount_mb' => $bundle->is_unlimited ? null : $bundle->data_amount_mb,
                'validity_days' => $bundle->duration_days,
                'network_speed' => $bundle->speed,
                'coverage_type' => count($bundle->countryList()) > 1 ? Plan::COVERAGE_REGIONAL : Plan::COVERAGE_LOCAL,
            ]);
            $selected = $countries->whereIn('iso2', $bundle->countryList())->pluck('id')->all();
        }

        return view('admin.plans.create', [
            'plan' => $plan,
            'bundle' => $bundle,
            'countries' => $countries,
            'selected' => $selected,
        ]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        $plan = DB::transaction(function () use ($request) {
            // Reuse the selected existing bundle — no provider bundle is created here.
            // Lock it and re-check, so two admins cannot link the same bundle at once.
            $bundle = ProviderBundle::query()->lockForUpdate()->findOrFail($request->integer('provider_bundle_id'));
            if (! $bundle->is_available || Plan::query()->where('provider_bundle_id', $bundle->id)->exists()) {
                throw ValidationException::withMessages(['provider_bundle_id' => 'This provider bundle can no longer be used. Choose another one.']);
            }

            $plan = new Plan($this->planAttributes($request));
            $plan->provider_bundle_id = $bundle->id;
            $plan->is_active = false;   // activated separately once sellable
            $plan->save();
            $plan->countries()->sync($request->input('countries'));

            return $plan;
        });

        return redirect()->route('admin.plans.show', $plan)
            ->with('status', 'Plan created (inactive). Add and approve a price, then activate it.');
    }

    public function show(Plan $plan): View
    {
        $currency = $this->catalogue->currency();
        $plan->load([
            'providerBundle',
            'countries' => fn ($q) => $q->ordered(),
            'prices' => fn ($q) => $q->with(['creator:id,name', 'approver:id,name'])->orderByDesc('effective_from')->orderByDesc('id'),
        ]);
        $current = $plan->currentPrice($currency);

        return view('admin.plans.show', [
            'plan' => $plan,
            'currency' => $currency,
            'currentPrice' => $current,
            'problems' => $plan->sellabilityProblems($currency),
            'belowCostPriceIds' => $plan->prices->filter(fn ($p) => $plan->isPriceBelowCost($p))->pluck('id')->all(),
            'markupAvailable' => strtoupper((string) $plan->providerBundle?->provider_currency) === $currency,
        ]);
    }

    public function edit(Plan $plan): View
    {
        $plan->load(['providerBundle', 'countries']);
        $selected = $plan->countries->pluck('id')->all();

        return view('admin.plans.edit', [
            'plan' => $plan,
            'bundle' => $plan->providerBundle,
            // Active countries, plus any inactive ones this plan already uses.
            'countries' => Country::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $selected))
                ->ordered()->get(),
            'selected' => $selected,
        ]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        DB::transaction(function () use ($request, $plan) {
            // Synced (eSIM Go) bundles are provider-owned: never changed here.
            if ($request->editsManualBundle()) {
                $plan->providerBundle->update($this->manualBundleAttributes($request));
            }
            $plan->update($this->planAttributes($request));
            $plan->countries()->sync($request->input('countries'));
        });

        $message = 'Plan details were saved.';
        if ($plan->is_active && $problems = $plan->fresh()->sellabilityProblems($this->catalogue->currency())) {
            $message .= ' Warning: this active plan is currently hidden from customers — '.implode(' ', $problems);
        }

        return redirect()->route('admin.plans.show', $plan)->with('status', $message);
    }

    public function activate(Plan $plan): RedirectResponse
    {
        $problems = $plan->sellabilityProblems($this->catalogue->currency());
        if ($problems !== []) {
            return back()->withErrors(['plan' => 'This plan cannot be activated yet: '.implode(' ', $problems)]);
        }

        $plan->update(['is_active' => true]);

        return back()->with('status', 'Plan activated. It is now available in the catalogue.');
    }

    public function deactivate(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => false]);

        return back()->with('status', 'Plan deactivated. Customers can no longer see it.');
    }

    private function planAttributes(PlanRequest $request): array
    {
        return [
            'slug' => $request->input('slug'),
            'name' => $request->input('name'),
            'display_title' => $request->input('display_title') ?: null,
            'description' => $request->input('description') ?: null,
            'coverage_type' => $request->input('coverage_type'),
            'region_name' => $request->input('region_name') ?: null,
            'data_type' => $request->input('data_type'),
            'data_amount_mb' => $request->dataAmountMb(),
            'validity_days' => (int) $request->input('validity_days'),
            'network_speed' => $request->input('network_speed') ?: null,
            'features' => $request->featureList(),
            'badge' => $request->input('badge'),
            'sort_order' => (int) $request->input('sort_order'),
        ];
    }

    /**
     * Editable facts of a MANUALLY entered bundle only (cost, currency,
     * availability). The code is not changed here — use "Change provider
     * bundle". Descriptive columns (data_amount_mb, is_unlimited, duration_days,
     * country_codes, speed) belong to eSIM Go's catalogue data and are never
     * filled with — or overwritten by — the plan's customer-facing values.
     */
    private function manualBundleAttributes(PlanRequest $request): array
    {
        return [
            'provider_cost' => $request->input('provider_cost'),
            'provider_currency' => $request->input('provider_currency'),
            'is_available' => $request->boolean('bundle_available'),
        ];
    }
}
