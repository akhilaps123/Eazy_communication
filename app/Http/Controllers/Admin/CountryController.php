<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CountryRequest;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin country management (decision D10: delete only when no plan uses the
 * country; otherwise deactivate).
 */
class CountryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = $request->query('status');

        $countries = Country::query()
            ->withCount('plans')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('iso2', strtoupper($search))
                ->orWhere('iso3', strtoupper($search))))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('admin.countries.index', compact('countries', 'search', 'status'));
    }

    public function create(): View
    {
        return view('admin.countries.create', [
            'country' => new Country(['is_active' => true, 'is_popular' => false, 'sort_order' => 0]),
        ]);
    }

    public function store(CountryRequest $request): RedirectResponse
    {
        $country = Country::create($request->validated());

        return redirect()->route('admin.countries.show', $country)->with('status', "Country \"{$country->name}\" was created.");
    }

    public function show(Country $country): View
    {
        $country->load(['plans' => fn ($q) => $q->orderBy('name')]);

        return view('admin.countries.show', compact('country'));
    }

    public function edit(Country $country): View
    {
        return view('admin.countries.edit', ['country' => $country]);
    }

    public function update(CountryRequest $request, Country $country): RedirectResponse
    {
        $country->update($request->validated());

        return redirect()->route('admin.countries.show', $country)->with('status', 'Country details were saved.');
    }

    public function toggle(Country $country): RedirectResponse
    {
        $country->update(['is_active' => ! $country->is_active]);

        return back()->with('status', "Country \"{$country->name}\" is now ".($country->is_active ? 'active' : 'inactive').'.');
    }

    public function destroy(Country $country): RedirectResponse
    {
        // Checked inside a transaction with a row lock, so a plan cannot be
        // linked between the check and the delete.
        $deleted = DB::transaction(function () use ($country) {
            $locked = Country::query()->whereKey($country->getKey())->lockForUpdate()->first();
            if ($locked === null || $locked->plans()->exists()) {
                return false;
            }

            return (bool) $locked->delete();
        });

        if (! $deleted) {
            return back()->withErrors(['country' => "\"{$country->name}\" is used by at least one plan, so it cannot be deleted. Deactivate it instead."]);
        }

        return redirect()->route('admin.countries.index')->with('status', "Country \"{$country->name}\" was deleted.");
    }
}
