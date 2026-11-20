<?php

namespace Tests\Feature\Esim;

use App\Models\Esim;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProviderOrder;
use App\Models\User;
use App\Services\Esim\ActivationPayload;
use App\Services\Esim\EsimQrService;
use App\Services\Esim\InvalidActivationData;
use App\Services\EsimGo\FulfilmentService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Database\DatabaseTestCase;
use Tests\Feature\EsimGo\EsimGoFixtures;
use Tests\Feature\Payments\PaymentFixtures;

/**
 * Phase 9D: QR codes and activation details for purchased eSIMs.
 * eSIM Go is always faked (unexpected requests fail the test); storage is fake.
 */
class EsimQrTest extends DatabaseTestCase
{
    use EsimGoFixtures;
    use PaymentFixtures;

    private const ICCID = '8944000000000000901';

    private const MATCHING_ID = 'QR9D-MATCH-SECRET-0001';

    private const SMDP = 'rsp.qr9d-secret.example';

    private const PAYLOAD = 'LPA:1$'.self::SMDP.'$'.self::MATCHING_ID;

    private const REF = '9d0c8a52-1f3b-4e7a-9c55-0a1b2c3d4e5f';

    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        $this->configureEsimGo(['esimgo.currency' => 'USD']);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $url = $request->url();
            if ($url === self::BASE.'/orders') {
                $type = $request->data()['type'] ?? null;

                return $this->answers[$type] ?? ($type === 'validate'
                    ? Http::response(['order' => [], 'total' => 2.0, 'valid' => true, 'currency' => 'USD', 'assigned' => false])
                    : Http::response($this->purchaseBody()));
            }
            if (str_starts_with($url, self::BASE.'/esims/assignments')) {
                return $this->answers['assignments'] ?? Http::response([]);
            }

            return null;
        });
    }

    // ------------------------------------------------------------------ fixtures

    private function purchaseBody(?array $esim = null): array
    {
        $esim ??= ['iccid' => self::ICCID, 'matchingId' => self::MATCHING_ID, 'smdpAddress' => self::SMDP];

        return ['order' => [['type' => 'bundle', 'item' => 'SECRET_BUNDLE', 'quantity' => 1, 'subTotal' => 2.0, 'pricePerUnit' => 2.0,
            'iccids' => [self::ICCID], 'esims' => [$esim]]],
            'total' => 2.0, 'currency' => 'USD', 'status' => 'completed', 'statusMessage' => 'Order completed', 'orderReference' => self::REF, 'assigned' => true];
    }

    private function paidOrder(?User $customer = null): Order
    {
        $order = $this->pendingOrder($customer ?? User::factory()->create(['name' => 'Priya QR Customer']));
        Payment::query()->create([
            'order_id' => $order->id, 'user_id' => $order->user_id, 'gateway' => 'razorpay', 'gateway_order_id' => 'order_9D'.$order->id,
            'gateway_payment_id' => 'pay_9D'.$order->id, 'amount' => '19.99', 'currency' => 'USD', 'status' => Payment::STATUS_SUCCEEDED,
            'paid_at' => now(), 'verified_at' => now(),
        ]);
        $order->forceFill(['payment_status' => Order::PAYMENT_PAID, 'paid_at' => now()])->save();

        return $order->fresh();
    }

    /** A fulfilled order with one stored eSIM (no QR yet). */
    private function fulfilledEsim(array $attributes = [], ?Order $order = null): Esim
    {
        $order ??= $this->paidOrder();
        $po = ProviderOrder::query()->create([
            'order_id' => $order->id, 'provider' => 'esimgo', 'our_reference' => 'FUL-'.$order->order_number, 'status' => ProviderOrder::STATUS_COMPLETED,
            'provider_reference' => self::REF.$order->id, 'attempts' => 1, 'submitted_at' => now(), 'completed_at' => now(),
        ]);
        $order->forceFill(['fulfillment_status' => Order::FULFILLMENT_FULFILLED, 'fulfilled_at' => now()])->save();

        return Esim::query()->create($attributes + [
            'user_id' => $order->user_id, 'order_item_id' => $order->items()->first()->id, 'provider_order_id' => $po->id,
            'iccid' => self::ICCID, 'smdp_address' => self::SMDP, 'activation_code' => self::MATCHING_ID, 'bundle_code' => 'SECRET_BUNDLE', 'status' => 'assigned',
        ]);
    }

    private function decode(string $png): string
    {
        return (string) (new QRCode(new QROptions))->readFromBlob($png);
    }

    private function qr(): EsimQrService
    {
        return app(EsimQrService::class);
    }

    private function order(Esim $esim): Order
    {
        return $esim->orderItem->order;
    }

    private function snapshot(): array
    {
        return [
            'orders' => DB::table('orders')->orderBy('id')->get(['id', 'payment_status', 'fulfillment_status', 'fulfilled_at'])->toArray(),
            'provider_orders' => DB::table('provider_orders')->orderBy('id')->get(['id', 'status', 'attempts', 'provider_reference'])->toArray(),
            'esims' => DB::table('esims')->orderBy('id')->get(['id', 'iccid', 'status', 'smdp_address', 'activation_code'])->toArray(),
            'payments' => DB::table('payments')->orderBy('id')->get()->toArray(),
        ];
    }

    private function purchaseRequests(): int
    {
        return Http::recorded(fn (Request $r) => $r->url() === self::BASE.'/orders' && ($r->data()['type'] ?? null) === 'transaction')->count();
    }

    // ------------------------------------------------------------------ 1–2: generation and payload

    public function test_valid_activation_information_generates_an_encrypted_private_qr_code(): void
    {
        $esim = $this->fulfilledEsim();

        $result = $this->qr()->generate($esim, 'test');

        $this->assertTrue($result['ok'], $result['message']);
        $esim->refresh();
        $this->assertSame($esim->id.'.png.enc', $esim->qr_code_path);
        Storage::disk('esim_qr')->assertExists($esim->qr_code_path);

        // Stored encrypted: the file is not a PNG and does not contain the payload.
        $raw = Storage::disk('esim_qr')->get($esim->qr_code_path);
        $this->assertStringStartsNotWith("\x89PNG", $raw);
        $this->assertStringNotContainsString(self::MATCHING_ID, $raw);

        $png = $this->qr()->image($esim);
        $this->assertNotNull($png);
        [$width, $height] = getimagesizefromstring($png);
        $this->assertSame($width, $height);
        $this->assertGreaterThanOrEqual(300, $width);
        $this->assertSame(EsimQrService::STATE_GENERATED, $this->qr()->status($esim)['state']);

        // Nothing else changed: the eSIM is still just "assigned".
        $this->assertSame('assigned', $esim->status);
        $this->assertSame(1, IntegrationLog::query()->where('service', 'esim_qr')->where('operation', 'qr_generated')->count());
    }

    public function test_the_qr_code_contains_exactly_the_provider_activation_payload(): void
    {
        $esim = $this->fulfilledEsim();
        $this->qr()->generate($esim, 'test');

        $decoded = $this->decode($this->qr()->image($esim->refresh()));

        $this->assertSame(self::PAYLOAD, $decoded);
        foreach ([$esim->user->name, $esim->user->email, $this->order($esim)->order_number, self::ICCID, 'SECRET_BUNDLE'] as $unrelated) {
            $this->assertStringNotContainsString($unrelated, $decoded);
        }
    }

    public function test_an_smdp_address_written_with_a_scheme_uses_the_bare_host_like_the_provider_install_links(): void
    {
        // eSIM Go returns e.g. "http://rsp.example.com" but its install links carry "LPA:1$rsp.example.com$…".
        $this->assertSame(self::PAYLOAD, ActivationPayload::from(self::ICCID, 'https://'.self::SMDP.'/', self::MATCHING_ID)->toString());
        $this->assertSame(self::PAYLOAD, ActivationPayload::from(self::ICCID, 'http://'.self::SMDP, self::MATCHING_ID)->toString());
    }

    // ------------------------------------------------------------------ 3–5: missing / invalid data

    public function test_a_missing_activation_code_prevents_qr_generation(): void
    {
        $esim = $this->fulfilledEsim(['activation_code' => null]);

        $result = $this->qr()->generate($esim, 'test');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('activation code (matching ID) is missing', $result['message']);
        $this->assertNull($esim->refresh()->qr_code_path);
        $this->assertSame([], Storage::disk('esim_qr')->allFiles());
        $this->assertSame(EsimQrService::STATE_UNAVAILABLE, $this->qr()->status($esim)['state']);
    }

    public function test_a_missing_smdp_address_prevents_qr_generation(): void
    {
        $esim = $this->fulfilledEsim(['smdp_address' => null]);

        $result = $this->qr()->generate($esim, 'test');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('SM-DP+ address is missing', $result['message']);
        $this->assertNull($esim->refresh()->qr_code_path);
        $this->assertSame([], Storage::disk('esim_qr')->allFiles());
    }

    public static function invalidActivationData(): array
    {
        return [
            'missing ICCID' => ['', self::SMDP, self::MATCHING_ID, 'ICCID is missing'],
            'ICCID not digits' => ['89440000ABC0000901', self::SMDP, self::MATCHING_ID, 'ICCID is not a valid'],
            'SM-DP+ with a path' => [self::ICCID, 'https://'.self::SMDP.'/download', self::MATCHING_ID, 'not a valid host name'],
            'SM-DP+ with a port' => [self::ICCID, self::SMDP.':8443', self::MATCHING_ID, 'not a valid host name'],
            'SM-DP+ with a $' => [self::ICCID, 'rsp$evil.example', self::MATCHING_ID, 'not a valid host name'],
            'SM-DP+ not a host' => [self::ICCID, 'localhost', self::MATCHING_ID, 'not a valid host name'],
            'matching ID with a $' => [self::ICCID, self::SMDP, 'ABC$1$extra', 'characters that are not allowed'],
            'matching ID with spaces' => [self::ICCID, self::SMDP, 'ABC DEF', 'characters that are not allowed'],
            'matching ID already an LPA string' => [self::ICCID, self::SMDP, 'LPA:1$'.self::SMDP.'$X', 'characters that are not allowed'],
        ];
    }

    #[DataProvider('invalidActivationData')]
    public function test_invalid_activation_information_is_rejected(string $iccid, string $smdp, string $matching, string $expected): void
    {
        try {
            ActivationPayload::from($iccid, $smdp, $matching);
            $this->fail('Invalid activation data was accepted.');
        } catch (InvalidActivationData $e) {
            $this->assertStringContainsString($expected, $e->getMessage());
            // The error names the field, never the value.
            $this->assertStringNotContainsString($matching, $e->getMessage());
            $this->assertStringNotContainsString('rsp', $e->getMessage());
        }

        $esim = $this->fulfilledEsim(['iccid' => $iccid === '' ? '8944000000000000999' : $iccid, 'smdp_address' => $smdp, 'activation_code' => $matching]);
        if ($iccid === '') {
            $esim->forceFill(['iccid' => ''])->save();
        }
        $this->assertFalse($this->qr()->generate($esim, 'test')['ok']);
        $this->assertNull($esim->refresh()->qr_code_path);
        $this->assertSame([], Storage::disk('esim_qr')->allFiles());
        $this->assertSame(1, IntegrationLog::query()->where('operation', 'qr_generation_failed')->count());
    }

    // ------------------------------------------------------------------ 6: failures never duplicate or change state

    public function test_qr_failure_during_fulfilment_keeps_the_esim_and_the_true_fulfilment_state(): void
    {
        config(['esimgo.fulfilment.enabled' => true, 'esimgo.fulfilment.allow_test_payments' => true]);
        // eSIM Go returns an SM-DP+ address that cannot be put in a QR code.
        $this->answers['transaction'] = Http::response($this->purchaseBody(['iccid' => self::ICCID, 'matchingId' => self::MATCHING_ID, 'smdpAddress' => 'not a host']));
        $order = $this->paidOrder();
        $service = app(FulfilmentService::class);

        $first = $service->fulfil($order);
        $second = $service->fulfil($order);   // repeated run (e.g. job retry)

        $this->assertSame(FulfilmentService::RESULT_FULFILLED, $first['result']);
        $this->assertSame(FulfilmentService::RESULT_FULFILLED, $second['result']);
        $this->assertSame(1, $this->purchaseRequests(), 'A QR failure must never lead to another purchase.');
        $this->assertSame(1, Esim::query()->count());
        $esim = Esim::query()->sole();
        $this->assertNull($esim->qr_code_path);
        $this->assertSame('assigned', $esim->status);
        $this->assertSame(Order::FULFILLMENT_FULFILLED, $order->refresh()->fulfillment_status);
        $this->assertSame(EsimQrService::STATE_UNAVAILABLE, $this->qr()->status($esim)['state']);
        $this->assertSame(1, IntegrationLog::query()->where('operation', 'qr_generation_failed')->count());
    }

    public function test_a_qr_library_or_private_storage_failure_keeps_the_esim_and_is_reported_safely(): void
    {
        $esim = $this->fulfilledEsim();
        $before = $this->snapshot();

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->andThrow(new RuntimeException('disk full at /secret/path '.self::MATCHING_ID));
        Storage::set('esim_qr', $disk);

        $result = $this->qr()->generate($esim, 'test');

        $this->assertFalse($result['ok']);
        $this->assertSame('QR code not generated: the image could not be saved to private storage.', $result['message']);
        $this->assertNull($esim->refresh()->qr_code_path);
        $this->assertEquals($before, $this->snapshot());
        $log = IntegrationLog::query()->where('operation', 'qr_generation_failed')->sole();
        $this->assertStringNotContainsString(self::MATCHING_ID, json_encode($log->getAttributes()));
        $this->assertSame(EsimQrService::STATE_FAILED, $this->qr()->status($esim)['state']);
    }

    public function test_missing_assignment_responses_and_timeouts_store_nothing_and_never_buy_again(): void
    {
        config(['esimgo.fulfilment.enabled' => true, 'esimgo.fulfilment.allow_test_payments' => true]);
        // The purchase answer has no activation details yet; the lookup then times out, then answers empty.
        $this->answers['transaction'] = Http::response($this->purchaseBody(['iccid' => self::ICCID, 'matchingId' => null, 'smdpAddress' => null]));
        $this->answers['assignments'] = Http::failedConnection('cURL error 28: Operation timed out');
        $order = $this->paidOrder();
        $service = app(FulfilmentService::class);

        $this->assertSame(FulfilmentService::RESULT_AWAITING_ESIMS, $service->fulfil($order)['result']);
        $this->answers['assignments'] = Http::response([]);
        $this->assertSame(FulfilmentService::RESULT_AWAITING_ESIMS, $service->fulfil($order)['result']);

        $this->assertSame(1, $this->purchaseRequests());
        $this->assertSame(0, Esim::query()->count());
        $this->assertSame([], Storage::disk('esim_qr')->allFiles());
        $this->assertSame(Order::FULFILLMENT_PROCESSING, $order->refresh()->fulfillment_status);
        $this->assertSame(0, IntegrationLog::query()->where('service', 'esim_qr')->count());

        // When eSIM Go finally returns the details, the eSIM and its QR code are stored once.
        $this->answers['assignments'] = Http::response(['iccid' => self::ICCID, 'matchingId' => self::MATCHING_ID, 'smdpAddress' => self::SMDP]);
        $this->assertSame(FulfilmentService::RESULT_FULFILLED, $service->fulfil($order)['result']);
        $this->assertSame(1, $this->purchaseRequests());
        $this->assertSame(self::PAYLOAD, $this->decode($this->qr()->image(Esim::query()->sole())));
    }

    public function test_fulfilment_generates_the_qr_code_without_changing_any_status(): void
    {
        config(['esimgo.fulfilment.enabled' => true, 'esimgo.fulfilment.allow_test_payments' => true]);
        $order = $this->paidOrder();

        $result = app(FulfilmentService::class)->fulfil($order);

        $this->assertSame(FulfilmentService::RESULT_FULFILLED, $result['result']);
        $esim = Esim::query()->sole();
        $this->assertSame(self::PAYLOAD, $this->decode($this->qr()->image($esim)));
        $this->assertSame('assigned', $esim->status, 'A QR code does not mean the eSIM is installed.');
        $this->assertNull($esim->activated_at);
    }

    // ------------------------------------------------------------------ 7–8: regeneration

    public function test_admin_regeneration_never_contacts_the_provider_and_is_audited(): void
    {
        $esim = $this->fulfilledEsim();
        $order = $this->order($esim);
        $before = $this->snapshot();
        $this->actingAs($admin = $this->admin());

        $this->post(route('admin.orders.esims.qr.regenerate', [$order, $esim]), ['reason' => 'Customer lost the email'])
            ->assertRedirect(route('admin.orders.show', $order))->assertSessionHas('status', 'QR code generated for eSIM '.self::ICCID.'.');

        Http::assertNothingSent();
        $this->assertEquals($before, $this->snapshot(), 'Regeneration changes no order, payment, provider order or eSIM status.');
        $this->assertSame(1, Esim::query()->count());
        $audit = IntegrationLog::query()->where('service', 'admin')->sole();
        $this->assertSame('qr_regenerated', $audit->operation);
        $this->assertSame($admin->id, $audit->request_body['admin_user_id']);
        $this->assertSame('Customer lost the email', $audit->request_body['reason']);
        $this->assertStringContainsString(self::ICCID, $audit->response_body['note']);
    }

    public function test_regeneration_rebuilds_the_qr_code_from_the_stored_activation_information(): void
    {
        $esim = $this->fulfilledEsim();
        $order = $this->order($esim);
        $this->qr()->generate($esim, 'test');
        Storage::disk('esim_qr')->delete($esim->refresh()->qr_code_path);   // image lost
        $this->assertSame(EsimQrService::STATE_FAILED, $this->qr()->status($esim)['state']);
        $this->actingAs($this->admin());

        $this->post(route('admin.orders.esims.qr.regenerate', [$order, $esim]))->assertSessionHas('status');

        Http::assertNothingSent();
        $this->assertSame(self::PAYLOAD, $this->decode($this->qr()->image($esim->refresh())));
        $this->assertSame(EsimQrService::STATE_GENERATED, $this->qr()->status($esim)['state']);
    }

    public function test_regeneration_with_incomplete_data_fails_clearly_and_is_audited(): void
    {
        $esim = $this->fulfilledEsim(['activation_code' => null]);
        $order = $this->order($esim);
        $this->actingAs($this->admin());

        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Activation information unavailable')->assertSee('activation code (matching ID) is missing')
            ->assertDontSee(route('admin.orders.esims.qr.regenerate', [$order, $esim]));

        $response = $this->post(route('admin.orders.esims.qr.regenerate', [$order, $esim]));
        $response->assertSessionHasErrors('action');
        $this->assertStringContainsString('activation code (matching ID) is missing', session('errors')->first('action'));
        Http::assertNothingSent();
        $this->assertSame('qr_regeneration_failed', IntegrationLog::query()->where('service', 'admin')->sole()->operation);
        $this->assertSame(1, Esim::query()->count());
    }

    // ------------------------------------------------------------------ 9–10: access

    public function test_admin_sees_the_qr_code_and_manual_installation_details_for_the_order(): void
    {
        $esim = $this->fulfilledEsim();
        $order = $this->order($esim);
        $this->qr()->generate($esim, 'test');
        $this->actingAs($this->admin());
        $logsBefore = IntegrationLog::query()->count();

        $page = $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('eSIM activation &amp; QR codes', false)
            ->assertSee(route('admin.orders.esims.qr', [$order, $esim]), false)
            ->assertSee('QR code generated')->assertSee('Test eSIM · 5 GB - 7 Days')
            ->assertSee('Installation is not confirmed')
            ->assertSeeInOrder(['Open the phone’s', 'Mobile Network', 'Add eSIM', 'Scan the displayed QR code', 'Follow the phone’s instructions'], false)
            ->assertSee(self::SMDP)->assertSee(self::MATCHING_ID)->assertSee(self::PAYLOAD)
            ->assertSee('data-copy="esim-'.$esim->id.'-code"', false)
            ->assertSee('Regenerate QR code');
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));

        $image = $this->get(route('admin.orders.esims.qr', [$order, $esim]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('no-store', (string) $image->headers->get('Cache-Control'));
        $this->assertSame(self::PAYLOAD, $this->decode($image->getContent()));
        $this->get(route('admin.orders.esims.qr', [$order, $esim]).'?download=1')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="esim-'.self::ICCID.'-qr.png"');

        Http::assertNothingSent();
        $this->assertSame($logsBefore, IntegrationLog::query()->count(), 'Viewing writes nothing.');
    }

    public function test_unauthorised_users_cannot_access_qr_information(): void
    {
        $esim = $this->fulfilledEsim();
        $order = $this->order($esim);
        $this->qr()->generate($esim, 'test');
        $imageUrl = route('admin.orders.esims.qr', [$order, $esim]);
        $regenerateUrl = route('admin.orders.esims.qr.regenerate', [$order, $esim]);

        // Guests.
        $this->get($imageUrl)->assertRedirect(route('login'));
        $this->post($regenerateUrl)->assertRedirect(route('login'));

        // Customers — even the eSIM's owner — cannot use the admin routes.
        foreach ([$esim->user, User::factory()->create()] as $customer) {
            $this->actingAs($customer);
            $this->get($imageUrl)->assertForbidden();
            $this->post($regenerateUrl)->assertForbidden();
            $this->get(route('admin.orders.show', $order))->assertForbidden();
        }

        // An admin cannot reach an eSIM through an unrelated order.
        $otherOrder = $this->paidOrder();
        $this->actingAs($this->admin());
        $this->get(route('admin.orders.esims.qr', [$otherOrder, $esim]))->assertNotFound();
        $this->post(route('admin.orders.esims.qr.regenerate', [$otherOrder, $esim]))->assertNotFound();
        $this->get(route('admin.orders.esims.qr', [$order, 999999]))->assertNotFound();

        $this->assertSame(0, IntegrationLog::query()->where('service', 'admin')->count());
        Http::assertNothingSent();
    }

    public function test_the_esim_policy_limits_activation_details_to_the_owner_and_admins(): void
    {
        $esim = $this->fulfilledEsim();

        $this->assertTrue(Gate::forUser($esim->user)->allows('viewActivation', $esim));
        $this->assertTrue(Gate::forUser($this->admin())->allows('viewActivation', $esim));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('viewActivation', $esim));
        $this->assertFalse(Gate::forUser($esim->user)->allows('regenerateQr', $esim));
        $this->assertTrue(Gate::forUser($this->admin())->allows('regenerateQr', $esim));
    }

    public function test_csrf_protects_regeneration(): void
    {
        $esim = $this->fulfilledEsim();
        $this->actingAs($this->admin());

        // The CSRF middleware skips itself while running unit tests; switch the real check back on.
        $this->app->instance('env', 'production');
        $status = $this->call('POST', route('admin.orders.esims.qr.regenerate', [$this->order($esim), $esim]))->status();
        $this->app->instance('env', 'testing');

        $this->assertSame(419, $status);
        $this->assertNull($esim->refresh()->qr_code_path);
        $this->assertSame(0, IntegrationLog::query()->count());
    }

    // ------------------------------------------------------------------ 11: never logged

    public function test_the_qr_payload_and_activation_code_never_appear_in_logs(): void
    {
        $messages = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$messages) {
            $messages[] = $e->message.' '.json_encode($e->context);
        });
        config(['esimgo.fulfilment.enabled' => true, 'esimgo.fulfilment.allow_test_payments' => true]);

        // Fulfilment (QR generated), admin regeneration, a failed generation and page/image views.
        $order = $this->paidOrder();
        app(FulfilmentService::class)->fulfil($order);
        $esim = Esim::query()->sole();
        $this->actingAs($this->admin());
        $this->post(route('admin.orders.esims.qr.regenerate', [$order, $esim]), ['reason' => 'Code '.self::PAYLOAD])->assertSessionHas('status');
        $this->get(route('admin.orders.show', $order))->assertOk();
        $this->get(route('admin.orders.esims.qr', [$order, $esim]))->assertOk();
        $broken = $this->fulfilledEsim(['iccid' => '8944000000000000777', 'smdp_address' => 'bad host', 'activation_code' => self::MATCHING_ID]);
        $this->post(route('admin.orders.esims.qr.regenerate', [$this->order($broken), $broken]));

        $logs = json_encode(IntegrationLog::query()->get()->map->getAttributes());
        $qrFiles = implode('', array_map(fn ($f) => Storage::disk('esim_qr')->get($f), Storage::disk('esim_qr')->allFiles()));
        foreach ([self::MATCHING_ID, self::SMDP, 'LPA:1', 'bad host'] as $secret) {
            $this->assertStringNotContainsString($secret, $logs, "integration_logs contain {$secret}");
            $this->assertStringNotContainsString($secret, implode("\n", $messages), "application log contains {$secret}");
            $this->assertStringNotContainsString($secret, $qrFiles, "QR files are not encrypted ({$secret})");
        }
        $this->assertSame(2, IntegrationLog::query()->where('service', 'esim_qr')->where('operation', 'qr_generated')->count());
        $this->assertSame(1, IntegrationLog::query()->where('service', 'esim_qr')->where('operation', 'qr_generation_failed')->count());
        // Activation details remain encrypted at rest.
        $raw = (array) DB::table('esims')->where('id', $esim->id)->first();
        $this->assertStringNotContainsString(self::MATCHING_ID, json_encode($raw));
    }
}
