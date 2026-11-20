<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Plan;
use App\Services\CatalogueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Customer catalogue pages: Home, New eSIM and country pages.
 *
 * Live catalogue OFF (default, decision D5): exactly the existing static pages.
 * Live catalogue ON: the same pages and markup, filled from the database.
 * Only customer-safe data is passed to the views (no provider cost or markup).
 */
class CatalogueController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function home(): View
    {
        if (! $this->catalogue->isLive()) {
            return view('pages.home');
        }

        $destinations = $this->catalogue->destinations();

        return view('pages.home', [
            'catalogueLive' => true,
            'homeDestinations' => $destinations->take((int) config('catalogue.home_destinations', 8)),
            'homeOffers' => $this->offerCards($destinations->take((int) config('catalogue.home_offers', 12))),
        ]);
    }

    public function newEsim(): View
    {
        if (! $this->catalogue->isLive()) {
            return view('pages.new-esim');
        }

        $plans = $this->catalogue->sellablePlans();
        $destinations = $this->catalogue->destinations($plans);

        return view('pages.new-esim', [
            'catalogueLive' => true,
            'catalogueCards' => $this->offerCards($destinations),
            'catalogueCounter' => sprintf('Showing %d %s in %d %s',
                $plans->count(), $plans->count() === 1 ? 'plan' : 'plans',
                $destinations->count(), $destinations->count() === 1 ? 'destination' : 'destinations'),
        ]);
    }

    /** Old URL: static page while the live catalogue is off; redirect when it is on (decision D6). */
    public function japanEsim(): View|RedirectResponse
    {
        if (! $this->catalogue->isLive()) {
            return view('pages.japan-esim');
        }

        $japan = Country::query()->active()->where('iso2', 'JP')->first();
        $hasPlans = $japan && $this->catalogue->sellablePlans($japan)->isNotEmpty();

        return redirect()->route($hasPlans ? 'esim.country' : 'new-esim', $hasPlans ? $japan : []);
    }

    /** /esim/{country} — reuses the Japan eSIM page design (decision D6). */
    public function country(Country $country): View
    {
        abort_unless($this->catalogue->isLive() && $country->is_active, 404);

        $plans = $this->catalogue->sellablePlans($country);
        abort_if($plans->isEmpty(), 404);

        $byType = fn (string $type) => $plans->where('data_type', $type)->values()
            ->map(fn (Plan $plan, int $i) => $this->planCard($plan, $i === 0))->all();
        $planData = ['unlimited' => $byType(Plan::DATA_UNLIMITED), 'fixed' => $byType(Plan::DATA_FIXED)];

        return view('pages.japan-esim', [
            'catalogueLive' => true,
            'country' => $country,
            'bannerImage' => $country->imagePath('banner'),
            'planData' => $planData,
            'initialType' => $planData['unlimited'] !== [] ? 'unlimited' : 'fixed',
            'specData' => $this->specData($country, $plans),
        ]);
    }

    /** View data for offer cards (one card per destination, "From" = cheapest plan). */
    private function offerCards(Collection $destinations): Collection
    {
        return $destinations->values()->map(function (array $d) {
            /** @var Country $country */
            $country = $d['country'];
            $category = $d['has_best_seller'] ? 'best-seller' : ($country->is_popular ? 'popular' : 'latest');

            return [
                'country' => $country,
                'category' => $category,
                'badge' => ['best-seller' => 'Best Seller', 'popular' => 'Popular'][$category] ?? null,
                'plans_count' => $d['plans_count'],
                'has_5g' => $d['has_5g'],
                'price' => $this->catalogue->priceData($d['cheapest']->cataloguePrice),
                'url' => route('esim.country', $country),
                'card_image' => $country->imagePath('card'),
                'flag_image' => $country->imagePath('flag'),
            ];
        });
    }

    /** Plan card for the existing japan-esim.js configurator (values HTML-escaped: the script uses innerHTML). */
    private function planCard(Plan $plan, bool $selected): array
    {
        $price = $this->catalogue->priceData($plan->cataloguePrice);

        return [
            'id' => 'p'.$plan->id,
            'slug' => $plan->slug,
            'title' => e($plan->title()),
            'price' => e($price['formatted']),
            'crossed' => e($price['compare_at_formatted'] ?? ''),
            'selected' => $selected,
        ];
    }

    /**
     * Rows for the "What does ... eSIM include?" tabs. Plan-specific rows come
     * from the database; general placeholder text stays as in the design (D7).
     * All values are HTML-escaped (the script uses innerHTML).
     */
    private function specData(Country $country, Collection $plans): array
    {
        $dataOptions = $plans->sortBy(fn (Plan $p) => $p->data_type === Plan::DATA_UNLIMITED ? PHP_INT_MAX : (int) $p->data_amount_mb)
            ->map(fn (Plan $p) => $p->dataLabel())->unique()->join(', ');
        $validity = $plans->pluck('validity_days')->unique()->sort()->map(fn ($d) => $d.' '.($d == 1 ? 'day' : 'days'))->join(', ');
        $countries = $plans->flatMap(fn (Plan $p) => $p->countries->pluck('name'))->unique()->sort()->values();
        $speeds = $plans->pluck('network_speed')->filter()->unique()->join(', ') ?: 'Not specified';
        $row = fn (string $label, string $value) => ['label' => e($label), 'value' => e($value)];

        return [
            'overview' => [
                $row('Data Allowance', $dataOptions),
                $row('Validity Period', $validity),
                $row('Calling / SMS', 'Data only (VoIP apps like WhatsApp & FaceTime supported)'),
                $row('Activation Type', 'Instant QR code delivery via email'),
            ],
            'network' => [
                $row('Data Allowance', $dataOptions),
                $row('Validity Period', $validity),
                $row('Countries Covered', $countries->join(', ')),
                $row('Network Speed', $speeds),
            ],
            'activation' => [
                $row('Delivery Method', 'Instant email with setup QR code'),
                $row('Activation Steps', 'Scan QR code in phone cellular settings'),
                $row('Timing', 'Validity starts upon connection to local network in '.$country->name),
                $row('Expiration', 'Must be activated within 90 days of purchase'),
            ],
        ];
    }
}
