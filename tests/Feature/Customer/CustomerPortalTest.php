<?php

namespace Tests\Feature\Customer;

use App\Http\Controllers\Api\AuthController;
use App\Models\Esim;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\ProviderOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Database\DatabaseTestCase;

/**
 * Phase 9E: customer portal (My Orders, My eSIMs, eSIM details, QR) and the
 * customer API. Two fake customers; fake activation data; fake storage.
 */
class CustomerPortalTest extends DatabaseTestCase
{
    use CustomerFixtures;

    private const ASHA_ICCID = '8944000000000009101';

    private const ASHA_CODE = 'ASHA-SECRET-MATCH-9E01';

    private const BEN_ICCID = '8944000000000009202';

    private const BEN_CODE = 'BEN-SECRET-MATCH-9E02';

    private const SMDP = 'rsp.fake-9e.example';

    private User $asha;

    private User $ben;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();   // any provider call would be recorded (and asserted against)
        $this->asha = $this->customerNamed('Asha Customer');
        $this->ben = $this->customerNamed('Ben Customer');
    }

    private function ashaEsim(): Esim
    {
        return $this->deliveredEsim($this->asha, self::ASHA_ICCID, self::ASHA_CODE);
    }

    private function benEsim(): Esim
    {
        return $this->deliveredEsim($this->ben, self::BEN_ICCID, self::BEN_CODE);
    }

    // ------------------------------------------------------------------ 1–8 customer pages

    public function test_customer_sees_only_their_own_orders(): void
    {
        $ashaEsim = $this->ashaEsim();
        $ashaPending = $this->pendingOrder($this->asha, '7.50');
        $benEsim = $this->benEsim();

        $this->actingAs($this->asha)->get(route('my-orders'))->assertOk()
            ->assertSee('Order '.$ashaEsim->orderItem->order->order_number)
            ->assertSee('Order '.$ashaPending->order_number)
            ->assertSee('Test eSIM · 5 GB - 7 Days')->assertSee('× 1', false)
            ->assertSee('eSIM ready')->assertSee('Payment pending')->assertSee('Pending payment')
            ->assertSee('USD')
            ->assertSee(route('orders.show', $ashaPending))
            ->assertDontSee($benEsim->orderItem->order->order_number)
            ->assertDontSee('SECRET_BUNDLE')->assertDontSee('FUL-')->assertDontSee('integration');
    }

    public function test_customer_sees_their_own_esim_list_with_masked_iccid(): void
    {
        $esim = $this->ashaEsim();
        $this->benEsim();

        $this->actingAs($this->asha)->get(route('my-esims'))->assertOk()
            ->assertSee('8944 •••• •••• 9101')
            ->assertDontSee(self::ASHA_ICCID)
            ->assertSee($esim->orderItem->order->order_number)
            ->assertSee('Ready to install')->assertSee('Available')
            ->assertSee(route('my-esims.show', $esim))
            ->assertDontSee('9202')->assertDontSee(self::ASHA_CODE)->assertDontSee(self::SMDP);
    }

    public function test_customer_opens_their_esim_details_with_qr_and_manual_installation_details(): void
    {
        $esim = $this->ashaEsim();

        $page = $this->actingAs($this->asha)->get(route('my-esims.show', $esim))->assertOk()
            ->assertSee('Test eSIM · 5 GB - 7 Days')->assertSee($esim->orderItem->order->order_number)->assertSee(self::ASHA_ICCID)
            ->assertSee('Ready to install')
            ->assertSee(route('my-esims.qr', $esim), false)->assertSee('Download QR code (PNG)')
            ->assertSee('Manual installation details')
            ->assertSee(self::SMDP)->assertSee(self::ASHA_CODE)->assertSee('LPA:1$'.self::SMDP.'$'.self::ASHA_CODE)
            ->assertSee('data-copy="manual-smdp"', false)->assertSee('data-copy="manual-code"', false)->assertSee('data-copy="manual-lpa"', false)
            ->assertSeeInOrder(['iPhone', 'Open', 'Settings', 'Mobile Service', 'Add eSIM', 'Use QR Code', 'Android', 'Network &amp; Internet', 'SIMs', 'Add eSIM', 'Scan the QR code'], false)
            ->assertSee('Installing the eSIM does not necessarily activate the plan')
            ->assertDontSee('SECRET_BUNDLE');
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        // Activation data never in a URL on the page.
        $this->assertDoesNotMatchRegularExpression('#href="[^"]*'.preg_quote(self::ASHA_CODE, '#').'#', $page->getContent());
    }

    public function test_customer_views_and_downloads_their_own_qr_code(): void
    {
        $esim = $this->ashaEsim();
        $this->actingAs($this->asha);

        $image = $this->get(route('my-esims.qr', $esim))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('no-store', $image->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $image->headers->get('Cache-Control'));
        $this->assertSame('LPA:1$'.self::SMDP.'$'.self::ASHA_CODE, $this->decodeQr($image->getContent()));

        $this->get(route('my-esims.qr', $esim).'?download=1')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="eazy-esim-9101-qr.png"');
        Http::assertNothingSent();
    }

    public function test_a_missing_qr_image_is_recreated_from_the_stored_details_without_any_provider_call(): void
    {
        $esim = $this->ashaEsim();
        Storage::disk('esim_qr')->delete($esim->qr_code_path);
        $before = Esim::query()->count();

        $this->actingAs($this->asha)->get(route('my-esims.show', $esim))->assertOk()->assertSee(route('my-esims.qr', $esim), false);

        $this->assertSame('LPA:1$'.self::SMDP.'$'.self::ASHA_CODE, $this->decodeQr($this->get(route('my-esims.qr', $esim))->assertOk()->getContent()));
        $this->assertSame($before, Esim::query()->count());
        Http::assertNothingSent();
    }

    public function test_unusable_activation_data_shows_a_clear_message_instead_of_a_qr_code(): void
    {
        $esim = $this->deliveredEsim($this->asha, self::ASHA_ICCID, 'bad code with spaces', qr: false);

        $this->actingAs($this->asha)->get(route('my-esims.show', $esim))->assertOk()
            ->assertSee('QR code not available')->assertSee('activation details for this eSIM are not available yet')
            ->assertDontSee('Manual installation details')->assertDontSee('bad code with spaces');
        $this->get(route('my-esims.qr', $esim))->assertNotFound();
    }

    public function test_customer_without_orders_sees_empty_states(): void
    {
        $this->actingAs($this->asha);

        $this->get(route('my-orders'))->assertOk()->assertSee('No orders yet')->assertSee(route('new-esim'));
        $this->get(route('my-esims'))->assertOk()->assertSee('No eSIM purchased yet')->assertSee('You have not received any eSIM');
    }

    public function test_account_navigation_links_to_orders_and_esims(): void
    {
        $this->actingAs($this->asha)->get(route('my-account'))->assertOk()
            ->assertSee('href="'.route('my-orders').'"', false)->assertSee('href="'.route('my-esims').'"', false)
            ->assertSee('aria-current="page"', false);
        $this->get(route('my-orders'))->assertOk()->assertSeeInOrder(['My Account', 'My Orders', 'My eSIMs']);
    }

    // ------------------------------------------------------------------ 9–14 authorization

    public function test_customer_cannot_access_another_customers_order_or_esim(): void
    {
        $benEsim = $this->benEsim();
        $benOrder = $benEsim->orderItem->order;
        $this->actingAs($this->asha);

        $this->get(route('orders.show', $benOrder))->assertNotFound();
        $this->get(route('my-esims.show', $benEsim))->assertNotFound()->assertDontSee(self::BEN_CODE)->assertDontSee(self::BEN_ICCID);
        $this->get(route('my-esims.qr', $benEsim))->assertNotFound();
        $this->get(route('my-esims.qr', $benEsim).'?download=1')->assertNotFound();
        $this->get(route('my-esims'))->assertOk()->assertDontSee(route('my-esims.show', $benEsim));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $esim = $this->ashaEsim();

        foreach ([route('my-orders'), route('my-esims'), route('my-esims.show', $esim), route('my-esims.qr', $esim), route('orders.show', $esim->orderItem->order)] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->getJson('/api/my-orders')->assertUnauthorized();
        $this->getJson('/api/my-esims/'.$esim->id)->assertUnauthorized();
    }

    public function test_customer_cannot_use_admin_qr_or_email_routes(): void
    {
        $esim = $this->ashaEsim();
        $order = $esim->orderItem->order;
        $this->actingAs($this->asha);

        $this->get(route('admin.orders.esims.qr', [$order, $esim]))->assertForbidden();
        $this->post(route('admin.orders.esims.qr.regenerate', [$order, $esim]))->assertForbidden();
        $this->post(route('admin.orders.notifications.esim-ready', $order))->assertForbidden();
        $this->assertSame(0, IntegrationLog::query()->where('service', 'admin')->count());
    }

    public function test_manipulated_ids_never_expose_private_information(): void
    {
        $ashaEsim = $this->ashaEsim();
        $benEsim = $this->benEsim();
        $this->actingAs($this->asha);

        foreach (['999999', '0', (string) ($benEsim->id + 1000)] as $id) {
            $this->get('/my-esims/'.$id)->assertNotFound();
            $this->get('/my-esims/'.$id.'/qr')->assertNotFound();
        }
        foreach (['abc', $benEsim->id.'abc', '-1', '1.0'] as $id) {
            $this->get('/my-esims/'.$id)->assertNotFound();
        }
        // The other customer's eSIM id gives exactly the same 404 as a non-existent one.
        $other = $this->get('/my-esims/'.$benEsim->id)->assertNotFound()->getContent();
        $missing = $this->get('/my-esims/999999')->assertNotFound()->getContent();
        $this->assertSame(preg_replace('/\s+/', '', strip_tags($missing)), preg_replace('/\s+/', '', strip_tags($other)));
        $this->assertStringNotContainsString(self::BEN_CODE, $other);
        // Own eSIM still fine.
        $this->get('/my-esims/'.$ashaEsim->id)->assertOk();
    }

    // ------------------------------------------------------------------ 15–18 payment & fulfilment protection

    public function test_pending_payment_never_exposes_activation_information(): void
    {
        // Even if an eSIM row existed for an unpaid order (data anomaly), it is never shown.
        $order = $this->pendingOrder($this->asha);
        $po = ProviderOrder::query()->create(['order_id' => $order->id, 'provider' => 'esimgo', 'our_reference' => 'FUL-X', 'status' => 'completed', 'attempts' => 1]);
        $esim = Esim::query()->create(['user_id' => $this->asha->id, 'order_item_id' => $order->items()->first()->id, 'provider_order_id' => $po->id,
            'iccid' => self::ASHA_ICCID, 'smdp_address' => self::SMDP, 'activation_code' => self::ASHA_CODE, 'status' => 'assigned']);
        $order->forceFill(['fulfillment_status' => Order::FULFILLMENT_FULFILLED])->save();
        $this->actingAs($this->asha);

        $this->get(route('my-esims'))->assertOk()->assertSee('Payment pending')->assertSee('Payment has not been verified yet')
            ->assertDontSee(route('my-esims.show', $esim))->assertDontSee('9101');
        $this->get(route('my-esims.show', $esim))->assertNotFound();
        $this->get(route('my-esims.qr', $esim))->assertNotFound();
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Payment pending')->assertDontSee(route('my-esims.show', $esim))->assertDontSee(self::ASHA_CODE);
        Sanctum::actingAs($this->asha, [AuthController::TOKEN_ABILITY]);
        $this->getJson('/api/my-esims')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/my-esims/'.$esim->id)->assertNotFound();
    }

    public function test_an_unverified_payment_never_exposes_activation_information(): void
    {
        $esim = $this->ashaEsim();
        DB::table('payments')->where('order_id', $esim->orderItem->order_id)->update(['verified_at' => null]);
        $this->actingAs($this->asha);

        $this->get(route('my-esims'))->assertOk()->assertDontSee(route('my-esims.show', $esim))->assertSee('Processing');
        $this->get(route('my-esims.show', $esim))->assertNotFound();
        $this->get(route('my-esims.qr', $esim))->assertNotFound();
    }

    public function test_fulfilment_in_progress_shows_processing_and_no_fake_esim(): void
    {
        $order = $this->paidVerified($this->pendingOrder($this->asha));
        $order->forceFill(['fulfillment_status' => Order::FULFILLMENT_PROCESSING])->save();
        $this->actingAs($this->asha);

        $this->get(route('my-esims'))->assertOk()
            ->assertSee('Processing')->assertSee('Payment is complete and your eSIM is being prepared')
            ->assertDontSee('No eSIM purchased yet')->assertDontSee('View details &amp; QR code', false)->assertDontSee('ICCID');
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Processing')->assertDontSee('View eSIM &amp; QR code', false);
    }

    public function test_failed_fulfilment_asks_to_contact_support_without_internal_errors(): void
    {
        $order = $this->paidVerified($this->pendingOrder($this->asha));
        ProviderOrder::query()->create(['order_id' => $order->id, 'provider' => 'esimgo', 'our_reference' => 'FUL-'.$order->order_number,
            'status' => ProviderOrder::STATUS_FAILED, 'attempts' => 1, 'last_error' => 'INTERNAL: eSIM Go balance too low HTTP 402']);
        $order->forceFill(['fulfillment_status' => Order::FULFILLMENT_NEEDS_ATTENTION])->save();
        $this->actingAs($this->asha);

        $this->get(route('my-esims'))->assertOk()
            ->assertSee('Being reviewed')->assertSee('Please contact support')->assertSee(route('esim-support'))
            ->assertDontSee('INTERNAL')->assertDontSee('balance')->assertDontSee('eSIM Go')->assertDontSee('eSIM ready');
        $this->get(route('my-orders'))->assertOk()->assertSee('Being reviewed')->assertDontSee('INTERNAL');
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Being reviewed')->assertDontSee('INTERNAL');
    }

    // ------------------------------------------------------------------ order details page

    public function test_order_page_lists_its_real_esims_only(): void
    {
        $esim = $this->ashaEsim();
        $order = $esim->orderItem->order;

        $this->actingAs($this->asha)->get(route('orders.show', $order))->assertOk()
            ->assertSee('eSIM ready')->assertSee('Your eSIMs')->assertSee('8944 •••• •••• 9101')
            ->assertSee(route('my-esims.show', $esim))
            ->assertDontSee(self::ASHA_CODE)->assertDontSee(self::SMDP)->assertDontSee('fulfilment_')->assertDontSee('SECRET_BUNDLE');
    }

    // ------------------------------------------------------------------ API

    public function test_api_returns_only_the_token_owners_orders_and_esims(): void
    {
        $ashaEsim = $this->ashaEsim();
        $benEsim = $this->benEsim();
        Sanctum::actingAs($this->asha, [AuthController::TOKEN_ABILITY]);

        $orders = $this->getJson('/api/my-orders')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $ashaEsim->orderItem->order->order_number)
            ->assertJsonPath('data.0.fulfilment_stage', 'ready')
            ->assertJsonPath('data.0.items.0.plan', 'Test eSIM · 5 GB - 7 Days');
        $this->assertStringContainsString('no-store', $orders->headers->get('Cache-Control'));

        $list = $this->getJson('/api/my-esims')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ashaEsim->id)->assertJsonPath('data.0.iccid_masked', '8944 •••• •••• 9101')
            ->assertJsonPath('data.0.qr_available', true)->assertJsonMissingPath('data.0.activation');

        $one = $this->getJson('/api/my-esims/'.$ashaEsim->id)->assertOk()
            ->assertJsonPath('data.iccid', self::ASHA_ICCID)
            ->assertJsonPath('data.activation.smdp_address', self::SMDP)
            ->assertJsonPath('data.activation.activation_code', self::ASHA_CODE)
            ->assertJsonPath('data.activation.lpa', 'LPA:1$'.self::SMDP.'$'.self::ASHA_CODE);

        $this->getJson('/api/my-esims/'.$benEsim->id)->assertNotFound()->assertJsonMissingPath('data');
        $this->getJson('/api/my-esims/999999')->assertNotFound();

        foreach ([$orders, $list, $one] as $response) {
            $json = $response->getContent();
            foreach (['qr_code_path', 'eyJpdiI6', 'provider', 'SECRET_BUNDLE', self::BEN_CODE, self::BEN_ICCID, 'esim-qr', '.png.enc', 'user_id'] as $never) {
                $this->assertStringNotContainsString($never, $json, $never);
            }
        }
        $this->assertStringNotContainsString(self::ASHA_CODE, $list->getContent());
        Http::assertNothingSent();
    }

    public function test_api_needs_the_customer_token_ability(): void
    {
        $this->ashaEsim();
        Sanctum::actingAs($this->asha, ['something-else']);

        $this->getJson('/api/my-esims')->assertForbidden();
    }
}
