<?php

namespace Tests\Feature\Admin;

use App\Models\Country;
use Tests\Feature\Catalogue\CatalogueFixtures;
use Tests\Feature\Database\DatabaseTestCase;

class CountryManagementTest extends DatabaseTestCase
{
    use CatalogueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
    }

    /** What the simplified form submits (hidden 0 + checked 1 for the checkboxes). */
    private function form(array $overrides = []): array
    {
        return $overrides + ['name' => 'Japan', 'iso2' => 'jp', 'slug' => '', 'sort_order' => '', 'is_active' => '1', 'is_popular' => '0'];
    }

    // ------------------------------------------------------------------ form

    public function test_form_shows_only_the_simplified_fields(): void
    {
        $this->get('/admin/countries/create')->assertOk()
            ->assertSee('name="name"', false)->assertSee('name="iso2"', false)->assertSee('name="slug"', false)
            ->assertSee('name="sort_order"', false)->assertSee('name="is_active"', false)->assertSee('name="is_popular"', false)
            ->assertDontSee('name="iso3"', false)->assertDontSee('name="region"', false)->assertDontSee('name="flag_image"', false);

        $country = $this->country();
        $this->get("/admin/countries/{$country->id}/edit")->assertOk()
            ->assertDontSee('name="iso3"', false)->assertDontSee('name="region"', false)->assertDontSee('name="flag_image"', false);
    }

    public function test_new_country_form_defaults_to_active_not_popular_sort_order_zero(): void
    {
        $this->get('/admin/countries/create')
            ->assertSee('name="is_active" value="1" checked', false)
            ->assertDontSee('name="is_popular" value="1" checked', false)
            ->assertSee('name="sort_order" value="0"', false);
    }

    // -------------------------------------------------------------- create

    public function test_country_can_be_created_with_only_the_required_fields(): void
    {
        $this->post('/admin/countries', ['name' => 'Japan', 'iso2' => 'jp'])->assertSessionHasNoErrors();

        $country = Country::firstOrFail();
        $this->assertSame(['Japan', 'JP', 'japan', 0, true, false, null, null, null], [
            $country->name, $country->iso2, $country->slug, $country->sort_order, $country->is_active, $country->is_popular,
            $country->iso3, $country->region, $country->flag_image,
        ]);
    }

    public function test_slug_is_generated_from_the_name_when_empty_and_kept_when_given(): void
    {
        $this->post('/admin/countries', $this->form(['name' => 'United States', 'iso2' => 'us']))->assertSessionHasNoErrors();
        $this->assertSame('united-states', Country::where('iso2', 'US')->value('slug'));

        $this->post('/admin/countries', $this->form(['name' => 'United Kingdom', 'iso2' => 'gb', 'slug' => 'UK']))->assertSessionHasNoErrors();
        $this->assertSame('uk', Country::where('iso2', 'GB')->value('slug'));
    }

    public function test_iso2_is_stored_uppercase_and_must_be_exactly_two_letters(): void
    {
        $this->post('/admin/countries', $this->form(['iso2' => ' jp ']))->assertSessionHasNoErrors();
        $this->assertSame('JP', Country::firstOrFail()->iso2);

        foreach (['', 'J', 'JPN', 'J1', '12', 'J-', 'ÉÉ'] as $bad) {
            $this->post('/admin/countries', $this->form(['name' => 'Bad '.$bad, 'iso2' => $bad]))->assertSessionHasErrors('iso2');
        }
        $this->assertDatabaseCount('countries', 1);
    }

    public function test_required_name_and_sort_order_rules(): void
    {
        $this->post('/admin/countries', $this->form(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/admin/countries', $this->form(['sort_order' => '-1']))->assertSessionHasErrors('sort_order');
        $this->post('/admin/countries', $this->form(['sort_order' => '1.5']))->assertSessionHasErrors('sort_order');
        $this->post('/admin/countries', $this->form(['sort_order' => 'abc']))->assertSessionHasErrors('sort_order');
        $this->post('/admin/countries', $this->form(['slug' => 'Not a slug!']))->assertSessionHasErrors('slug');
        $this->assertDatabaseCount('countries', 0);

        $this->post('/admin/countries', $this->form(['sort_order' => '7']))->assertSessionHasNoErrors();
        $this->assertSame(7, Country::firstOrFail()->sort_order);
    }

    public function test_duplicate_countries_are_rejected(): void
    {
        $this->post('/admin/countries', $this->form())->assertSessionHasNoErrors();

        $this->post('/admin/countries', $this->form(['name' => 'Other', 'iso2' => 'JP']))
            ->assertSessionHasErrors(['iso2' => 'A country with this ISO2 code already exists.']);
        $this->post('/admin/countries', $this->form(['name' => 'Other', 'iso2' => 'OT', 'slug' => 'japan']))
            ->assertSessionHasErrors(['slug' => 'A country with this URL slug already exists.']);
        $this->post('/admin/countries', $this->form(['name' => 'JAPAN', 'iso2' => 'OT', 'slug' => 'other']))
            ->assertSessionHasErrors(['name' => 'A country with this name already exists.']);
        $this->post('/admin/countries', $this->form(['name' => 'japan', 'iso2' => 'OT']))   // auto slug "japan" also taken
            ->assertSessionHasErrors(['name', 'slug']);

        $this->assertDatabaseCount('countries', 1);
    }

    public function test_removed_fields_cannot_be_set_through_the_form(): void
    {
        $this->post('/admin/countries', $this->form([
            'iso3' => 'JPN', 'region' => 'Asia', 'flag_image' => '../../.env',
        ]))->assertSessionHasNoErrors();

        $country = Country::firstOrFail();
        $this->assertNull($country->iso3);
        $this->assertNull($country->region);
        $this->assertNull($country->flag_image);
    }

    // ---------------------------------------------------------------- edit

    public function test_admin_can_edit_a_country(): void
    {
        $country = $this->country(['name' => 'Japan', 'iso2' => 'JP', 'slug' => 'japan', 'sort_order' => 5]);

        $this->put("/admin/countries/{$country->id}", $this->form(['name' => 'Japan', 'iso2' => 'jp', 'slug' => 'japan', 'sort_order' => '2', 'is_popular' => '1']))
            ->assertRedirect("/admin/countries/{$country->id}")
            ->assertSessionHasNoErrors();   // its own ISO2, name and slug are not "duplicates"

        $country->refresh();
        $this->assertSame([2, true], [$country->sort_order, $country->is_popular]);
    }

    public function test_editing_keeps_existing_iso3_region_and_flag_data(): void
    {
        // Like the Australia record entered before the form was simplified.
        $country = $this->country([
            'name' => 'Australia', 'iso2' => 'AU', 'iso3' => 'AUS', 'slug' => 'australia',
            'region' => 'Oceania', 'flag_image' => 'assets/images/flag-round-australia.png',
        ]);

        $this->put("/admin/countries/{$country->id}", $this->form(['name' => 'Australia', 'iso2' => 'AU', 'slug' => 'australia', 'sort_order' => '3']))
            ->assertSessionHasNoErrors();

        $country->refresh();
        $this->assertSame(['AUS', 'Oceania', 'assets/images/flag-round-australia.png', 3], [
            $country->iso3, $country->region, $country->flag_image, $country->sort_order,
        ]);
        $this->get("/admin/countries/{$country->id}")->assertSee('AUS')->assertSee('Oceania');
    }

    public function test_editing_keeps_plan_links(): void
    {
        $country = $this->country(['name' => 'Japan', 'iso2' => 'JP', 'slug' => 'japan']);
        $plan = $this->sellablePlan(country: $country);

        $this->put("/admin/countries/{$country->id}", $this->form(['name' => 'Japan', 'iso2' => 'JP', 'slug' => 'japan']))->assertSessionHasNoErrors();

        $this->assertSame([$country->id], $plan->countries()->pluck('countries.id')->all());
    }

    // ------------------------------------------------- active / popular / order

    public function test_active_and_inactive_behaviour(): void
    {
        $this->post('/admin/countries', $this->form(['is_active' => '0']))->assertSessionHasNoErrors();
        $country = Country::firstOrFail();
        $this->assertFalse($country->is_active);

        $this->post("/admin/countries/{$country->id}/toggle");
        $this->assertTrue($country->fresh()->is_active);

        // An inactive country disappears from the public catalogue.
        $this->sellablePlan(country: $country);
        $this->assertCount(1, $this->getJson('/api/countries')->json('data'));
        $this->post("/admin/countries/{$country->id}/toggle");
        $this->assertCount(0, $this->getJson('/api/countries')->json('data'));
    }

    public function test_popular_destinations_are_shown_first_and_sort_order_applies(): void
    {
        $this->enableLiveCatalogue();
        $plain = $this->country(['name' => 'Aland', 'iso2' => 'AX', 'slug' => 'aland', 'sort_order' => 0]);
        $popularLate = $this->country(['name' => 'Brazil', 'iso2' => 'BR', 'slug' => 'brazil', 'is_popular' => true, 'sort_order' => 9]);
        $popularEarly = $this->country(['name' => 'Chile', 'iso2' => 'CL', 'slug' => 'chile', 'is_popular' => true, 'sort_order' => 1]);
        foreach ([$plain, $popularLate, $popularEarly] as $country) {
            $this->sellablePlan(country: $country);
        }

        $order = array_column($this->getJson('/api/countries')->json('data'), 'name');
        $this->assertSame(['Chile', 'Brazil', 'Aland'], $order);

        $html = $this->get('/')->getContent();
        $this->assertTrue(strpos($html, 'data-country="Chile"') < strpos($html, 'data-country="Brazil"'));
        $this->assertTrue(strpos($html, 'data-country="Brazil"') < strpos($html, 'data-country="Aland"'));

        $this->get('/admin/countries')->assertSeeInOrder(['Aland', 'Chile', 'Brazil']);   // admin list: sort order (0, 1, 9), then name
    }

    // ---------------------------------------------------------------- flags

    public function test_flag_is_chosen_automatically_from_iso2_with_fallback(): void
    {
        $japan = $this->country(['name' => 'Japan', 'iso2' => 'JP', 'slug' => 'japan']);
        $unknown = $this->country(['name' => 'Testland', 'iso2' => 'ZZ', 'slug' => 'testland']);

        $this->assertSame('assets/images/flag-round-japan.png', $japan->imagePath('flag'));
        $this->assertNull($unknown->imagePath('flag'));                                       // no flag image exists
        $this->assertSame(config('catalogue.fallback_images.card'), $unknown->imagePath('card'));   // existing fallback

        $this->sellablePlan(country: $japan);
        $this->sellablePlan(country: $unknown);
        $flags = collect($this->getJson('/api/countries')->json('data'))->pluck('flag_image_url', 'iso2');
        $this->assertSame(asset('assets/images/flag-round-japan.png'), $flags['JP']);
        $this->assertNull($flags['ZZ']);

        $this->get("/admin/countries/{$unknown->id}")->assertSee('No website flag image exists for ZZ');
    }

    // ---------------------------------------------------------- list & delete

    public function test_list_search_filter_and_pagination(): void
    {
        $this->country(['name' => 'Japan', 'iso2' => 'JP', 'slug' => 'japan']);
        $this->country(['name' => 'France', 'iso2' => 'FR', 'slug' => 'france', 'is_active' => false]);
        for ($i = 0; $i < 22; $i++) {
            $this->country();
        }

        $this->get('/admin/countries?q=jap')->assertOk()->assertSee('Japan')->assertDontSee('France');
        $this->get('/admin/countries?q=FR')->assertOk()->assertSee('France')->assertDontSee('Japan');
        $this->get('/admin/countries?status=inactive')->assertOk()->assertSee('France')->assertDontSee('Japan');
        $this->get('/admin/countries')->assertOk()->assertSee('page=2', false);
        $this->get('/admin/countries?page=2')->assertOk();
    }

    public function test_country_without_plans_can_be_deleted(): void
    {
        $country = $this->country();

        $this->delete("/admin/countries/{$country->id}")->assertRedirect('/admin/countries');
        $this->assertModelMissing($country);
    }

    public function test_country_used_by_a_plan_cannot_be_deleted_only_deactivated(): void
    {
        $country = $this->country();
        $plan = $this->draftPlan(country: $country);

        $this->from("/admin/countries/{$country->id}")->delete("/admin/countries/{$country->id}")
            ->assertRedirect("/admin/countries/{$country->id}")
            ->assertSessionHasErrors('country');

        $this->assertModelExists($country);
        $this->assertTrue($plan->countries()->whereKey($country->id)->exists());   // link not removed
        $this->get("/admin/countries/{$country->id}")->assertOk()->assertDontSee('>Delete</button>', false)->assertSee('cannot be deleted');

        $this->post("/admin/countries/{$country->id}/toggle");
        $this->assertFalse($country->fresh()->is_active);
    }
}
