<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanPriceRequest;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Services\CatalogueService;
use App\Support\PlanAdminInsights;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Plan price management (uses the existing PlanPrice pricing rules, bcmath only).
 *
 * Workflow: new prices start as PENDING and inactive -> an admin (including the
 * creator, decision D4) approves -> APPROVED + active. Approved prices are never
 * edited: a change is a new price row, and the old one is ended or deactivated.
 */
class PlanPriceController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function store(PlanPriceRequest $request, Plan $plan): RedirectResponse
    {
        $price = new PlanPrice;
        $price->plan_id = $plan->id;
        $price->setRelation('plan', $plan->load('providerBundle'));
        $price->currency = $this->catalogue->currency();
        $price->fill($request->priceAttributes());

        $this->calculate($price, $plan);

        if ($request->isPreview()) {
            return back()->withInput()->with('price_preview', $this->preview($price));
        }

        $price->approval_status = PlanPrice::APPROVAL_PENDING;   // server-controlled
        $price->is_active = false;
        $price->created_by = $request->user()->id;
        $price->approved_by = null;
        $this->ensureUniqueStart($price);
        $this->save($price);

        return redirect()->route('admin.plans.show', $plan)
            ->with('status', 'Price saved as pending ('.$this->preview($price)['formatted'].'). Approve it to make it active.');
    }

    public function edit(Plan $plan, PlanPrice $price): View|RedirectResponse
    {
        if ($price->approval_status !== PlanPrice::APPROVAL_PENDING) {
            return $this->lockedResponse($plan);
        }

        return view('admin.prices.edit', [
            'plan' => $plan->load('providerBundle'),
            'price' => $price,
            'currency' => $this->catalogue->currency(),
            'markupAvailable' => strtoupper((string) $plan->providerBundle?->provider_currency) === $this->catalogue->currency(),
        ]);
    }

    public function update(PlanPriceRequest $request, Plan $plan, PlanPrice $price): RedirectResponse
    {
        if ($price->approval_status !== PlanPrice::APPROVAL_PENDING) {
            return $this->lockedResponse($plan);
        }

        $price->setRelation('plan', $plan->load('providerBundle'));
        $price->fill($request->priceAttributes());
        $this->calculate($price, $plan);

        if ($request->isPreview()) {
            return back()->withInput()->with('price_preview', $this->preview($price));
        }

        $this->ensureUniqueStart($price);
        $this->save($price);

        return redirect()->route('admin.plans.show', $plan)->with('status', 'Pending price updated.');
    }

    public function approve(Plan $plan, PlanPrice $price): RedirectResponse
    {
        if ($price->approval_status !== PlanPrice::APPROVAL_PENDING) {
            return back()->withErrors(['price' => 'Only pending prices can be approved.']);
        }
        if ($price->effective_to !== null && $price->effective_to->isPast()) {
            return back()->withErrors(['price' => 'This price has already ended and cannot be approved. Add a new price instead.']);
        }

        // Recalculate with the current provider cost before approving.
        $price->setRelation('plan', $plan->load('providerBundle'));
        $this->calculate($price, $plan, 'price');

        $price->approval_status = PlanPrice::APPROVAL_APPROVED;
        $price->approved_by = auth()->id();   // may be the same admin who created it (decision D4)
        $price->is_active = true;
        $this->save($price, 'price');

        return back()->with('status', 'Price approved ('.$this->preview($price)['formatted'].').');
    }

    /** End an approved price now (keeps it in the history). */
    public function end(Plan $plan, PlanPrice $price): RedirectResponse
    {
        if ($price->approval_status !== PlanPrice::APPROVAL_APPROVED) {
            return back()->withErrors(['price' => 'Only approved prices can be ended.']);
        }
        if ($price->effective_to !== null && $price->effective_to->isPast()) {
            return back()->withErrors(['price' => 'This price has already ended.']);
        }
        if ($price->effective_from->isFuture()) {
            return back()->withErrors(['price' => 'This price has not started yet. Deactivate it instead.']);
        }

        // Direct update of the end date only: an approved row's amounts are never changed.
        PlanPrice::query()->whereKey($price->getKey())->update(['effective_to' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Price ended. It stays in the price history.');
    }

    /**
     * Reject a pending price. The row is kept in the price history (never deleted)
     * with status "rejected"; approved_by records the admin who made the decision
     * (the table has one reviewer column, used for both approve and reject).
     */
    public function reject(Plan $plan, PlanPrice $price): RedirectResponse
    {
        if ($price->approval_status !== PlanPrice::APPROVAL_PENDING) {
            return back()->withErrors(['price' => 'Only pending prices can be rejected.']);
        }

        // Status fields only (amounts untouched). The "pending" condition guards
        // against a second admin approving the same price at the same moment.
        $updated = PlanPrice::query()
            ->whereKey($price->getKey())
            ->where('approval_status', PlanPrice::APPROVAL_PENDING)
            ->update([
                'approval_status' => PlanPrice::APPROVAL_REJECTED,
                'is_active' => false,
                'approved_by' => auth()->id(),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return back()->withErrors(['price' => 'Only pending prices can be rejected.']);
        }

        return back()->with('status', 'Price rejected. It stays in the price history and is never shown to customers.');
    }

    public function deactivate(Plan $plan, PlanPrice $price): RedirectResponse
    {
        if ($price->approval_status !== PlanPrice::APPROVAL_APPROVED || ! $price->is_active) {
            return back()->withErrors(['price' => 'Only active approved prices can be deactivated.']);
        }

        PlanPrice::query()->whereKey($price->getKey())->update(['is_active' => false, 'updated_at' => now()]);

        return back()->with('status', 'Price deactivated. It stays in the price history.');
    }

    /** Runs the existing bcmath pricing rule; currency mismatches block markup (no conversion). */
    private function calculate(PlanPrice $price, Plan $plan, string $errorKey = 'pricing_method'): void
    {
        $bundle = $plan->providerBundle;
        if ($price->pricing_method !== PlanPrice::METHOD_MANUAL
            && strtoupper((string) $bundle?->provider_currency) !== $price->currency) {
            throw ValidationException::withMessages([$errorKey => sprintf(
                'Markup pricing is not available: the provider currency (%s) differs from %s and currency conversion is not set up. Use a manual %s price.',
                $bundle?->provider_currency ?? 'unknown', $price->currency, $price->currency,
            )]);
        }

        try {
            $price->refreshCalculatedPrice();
        } catch (DomainException $e) {
            throw ValidationException::withMessages([$errorKey => $e->getMessage()]);
        }

        if ($plan->isPriceBelowCost($price)) {
            throw ValidationException::withMessages([$errorKey => sprintf(
                'The selling price %s is below the provider cost. Prices below cost are not allowed.',
                CatalogueService::money((string) $price->calculated_price, $price->currency),
            )]);
        }

        if ($price->compare_at_price !== null && bccomp((string) $price->compare_at_price, (string) $price->calculated_price, 2) <= 0) {
            throw ValidationException::withMessages(['compare_at_price' => 'The crossed-out price must be higher than the selling price (or leave it empty).']);
        }
    }

    private function save(PlanPrice $price, string $errorKey = 'pricing_method'): void
    {
        try {
            $price->save();   // the model's own below-cost guard runs here too
        } catch (DomainException $e) {
            throw ValidationException::withMessages([$errorKey => $e->getMessage()]);
        }
    }

    private function ensureUniqueStart(PlanPrice $price): void
    {
        $taken = PlanPrice::query()
            ->where('plan_id', $price->plan_id)
            ->where('currency', $price->currency)
            ->where('effective_from', $price->effective_from)
            ->when($price->exists, fn ($q) => $q->whereKeyNot($price->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['effective_from' => 'Another price for this plan already starts at exactly this time. Choose a different start time.']);
        }
    }

    private function preview(PlanPrice $price): array
    {
        return [
            'amount' => (string) $price->calculated_price,
            'formatted' => CatalogueService::money((string) $price->calculated_price, $price->currency),
            'method' => $price->pricing_method,
            // Admin-only: shown in the preview box, never sent to customers or the API.
            'margin' => PlanAdminInsights::margin(PlanAdminInsights::amount($price), $price->currency, $price->plan?->providerBundle),
        ];
    }

    private function lockedResponse(Plan $plan): RedirectResponse
    {
        return redirect()->route('admin.plans.show', $plan)->withErrors([
            'price' => 'Only pending prices can be edited. To change an approved price, add a new price, then end or deactivate the old one.',
        ]);
    }
}
