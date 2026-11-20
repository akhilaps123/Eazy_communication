<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Factories\PlanPriceFactory;
use Tests\Feature\Catalogue\CatalogueFixtures;
use Tests\Feature\Database\DatabaseTestCase;

class DashboardTest extends DatabaseTestCase
{
    use CatalogueFixtures;

    private function kpi(string $html, string $name): string
    {
        preg_match('/data-kpi="'.$name.'">(.*?)<\/a>/s', $html, $m);

        return preg_replace('/\s+/', ' ', strip_tags($m[1] ?? ''));
    }

    public function test_dashboard_figures_come_from_the_database(): void
    {
        $this->actingAs($this->admin());
        $active = $this->country();
        $this->country(['is_active' => false]);
        $this->sellablePlan(country: $active);                    // active + sellable
        $this->draftPlan(country: $active);                        // inactive, no price
        $draft = $this->draftPlan(country: $active);
        PlanPriceFactory::new()->pending()->create(['plan_id' => $draft->id]);
        PlanPriceFactory::new()->pending()->create(['plan_id' => $draft->id, 'effective_from' => now()->subHours(3)]);

        $html = $this->get('/admin')->assertOk()->getContent();

        // 3 countries in total: 2 created here + 1 inside draftPlan(..) is not created because a country was passed.
        $this->assertStringContainsString('1 / 2', $this->kpi($html, 'countries'));
        $this->assertStringContainsString('1 / 3', $this->kpi($html, 'plans'));
        $this->assertMatchesRegularExpression('/Plans customers can buy\s*1\b/', $this->kpi($html, 'sellable'));
        $this->assertMatchesRegularExpression('/Prices waiting for approval\s*2\b/', $this->kpi($html, 'pending'));
    }

    public function test_empty_catalogue_shows_zeros_not_invented_numbers(): void
    {
        $this->actingAs($this->admin());

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('0 / 0', $this->kpi($html, 'countries'));
        $this->assertStringContainsString('0 / 0', $this->kpi($html, 'plans'));
        $this->assertStringContainsString('Nothing to review right now', $this->kpi($html, 'pending'));
        // No invented sales figures in the dashboard content. (Phase 9F: the sidebar
        // now has an "Orders & Fulfillment" link, so only <main> is checked, and the
        // database-backed order overview must show zeros on an empty database.)
        $this->assertSame(1, preg_match('#<main\b.*</main>#s', $html, $main));
        foreach (['total', 'pending_payment', 'awaiting', 'fulfilled', 'failed', 'attention'] as $card) {
            $this->assertMatchesRegularExpression('/^\s*[^0-9]+ 0 /', $this->kpi($html, 'orders-'.$card), $card);
        }
        $catalogue = preg_replace('#<section[^>]*data-section="orders-overview".*?</section>#s', '', $main[0]);
        foreach (['revenue', 'sales', 'orders', 'customers:'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, strip_tags($catalogue));
        }
    }

    public function test_live_catalogue_status_is_shown_read_only(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin')->assertSee('OFF')->assertSee('original static design')->assertSee('CATALOGUE_LIVE=true');

        $this->enableLiveCatalogue();
        $this->get('/admin')->assertSee('ON')->assertSee('no plan is sellable yet');

        // No form or route on the dashboard can change the switch.
        $this->assertStringNotContainsString('<form', preg_replace('/<form method="POST" action="'.preg_quote(route('logout'), '/').'">.*?<\/form>/s', '', $this->get('/admin')->getContent()));
        $this->post('/admin', ['live' => '1'])->assertStatus(405);
        $this->assertTrue(config('catalogue.live'));
    }

    public function test_quick_actions_and_steps_link_to_existing_routes(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin')
            ->assertSee('href="'.route('admin.countries.create').'"', false)
            ->assertSee('href="'.route('admin.plans.create').'"', false)
            ->assertSee('href="'.route('admin.plans.index').'"', false)
            ->assertSee('href="'.route('admin.plans.index', ['prices' => 'pending']).'"', false)
            ->assertSee('How to publish a plan')
            ->assertSeeInOrder(['Add countries', 'Create the plan', 'Add a USD price', 'Review &amp; approve', 'Activate the plan'], false);
    }

    public function test_sidebar_header_breadcrumb_and_active_link(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $country = $this->country(['name' => 'Japan & Co']);

        $dashboard = $this->get('/admin');
        $this->assertMatchesRegularExpression(
            '#href="'.preg_quote(route('admin.dashboard'), '#').'"\s+class="adm-sidenav-link is-active"\s+aria-current="page"#',
            $dashboard->getContent(),
        );
        $this->assertSame(1, substr_count($dashboard->getContent(), 'adm-sidenav-link is-active'));   // only one highlighted link
        $dashboard
            ->assertSee($admin->name)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('View website');

        $this->get("/admin/countries/{$country->id}/edit")
            ->assertSeeInOrder(['Dashboard', 'Countries', 'Edit Japan &amp; Co'], false)
            ->assertDontSee('&amp;amp;', false);
    }

    public function test_pending_prices_filter_on_the_plans_list(): void
    {
        $this->actingAs($this->admin());
        $waiting = $this->draftPlan(['name' => 'Plan Waiting']);
        PlanPriceFactory::new()->pending()->create(['plan_id' => $waiting->id]);
        $this->sellablePlan(['name' => 'Plan Approved']);

        $this->get('/admin/plans?prices=pending')->assertOk()->assertSee('Plan Waiting')->assertDontSee('Plan Approved');
        $this->get('/admin/plans?prices=anything')->assertOk()->assertSee('Plan Waiting')->assertSee('Plan Approved');
    }

    public function test_dashboard_permissions_are_unchanged(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }
}
