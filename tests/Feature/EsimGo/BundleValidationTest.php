<?php

namespace Tests\Feature\EsimGo;

use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\ProviderOrder;
use App\Services\EsimGo\EsimGoClient;
use App\Services\EsimGo\EsimGoException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Database\DatabaseTestCase;

class BundleValidationTest extends DatabaseTestCase
{
    use EsimGoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureEsimGo();
    }

    private function validResponse(array $overrides = [])
    {
        return Http::response(array_merge([
            'order' => [['type' => 'bundle', 'item' => 'esim_1GB_7D_GB_V2', 'quantity' => 1, 'subTotal' => 2.28, 'pricePerUnit' => 2.28, 'AllowReassign' => false]],
            'total' => 2.28, 'valid' => true, 'currency' => 'USD', 'createdDate' => '2026-11-18T10:00:00Z', 'assigned' => false,
        ], $overrides));
    }

    private function expectFailure(callable $call): EsimGoException
    {
        try {
            $call();
        } catch (EsimGoException $e) {
            return $e;
        }
        $this->fail('Expected an EsimGoException.');
    }

    public function test_validation_sends_type_validate_and_returns_the_price(): void
    {
        Http::fake([self::BASE.'/orders' => $this->validResponse()]);

        $result = app(EsimGoClient::class)->validateBundle('esim_1GB_7D_GB_V2', 2);

        $this->assertSame(['valid' => true, 'total' => '2.2800', 'currency' => 'USD'], array_intersect_key($result, array_flip(['valid', 'total', 'currency'])));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE.'/orders'
            && $r->header('X-API-Key') === [self::API_KEY]
            && $r->data() === [
                'type' => 'validate',
                'assign' => false,
                'order' => [['type' => 'bundle', 'quantity' => 2, 'item' => 'esim_1GB_7D_GB_V2', 'allowReassign' => false]],
            ]);
        Http::assertNotSent(fn (Request $r) => ($r->data()['type'] ?? null) === 'transaction');

        $log = IntegrationLog::sole();
        $this->assertSame(['validate_order', 'validate'], [$log->operation, $log->request_body['type']]);
        $this->assertKeyNeverLogged();
        $this->assertSame(0, ProviderOrder::count());
        $this->assertSame(0, Order::count());
    }

    public function test_an_invalid_bundle_is_reported_as_not_valid(): void
    {
        Http::fake([self::BASE.'/orders' => $this->validResponse(['valid' => false, 'total' => 0])]);

        $this->assertFalse(app(EsimGoClient::class)->validateBundle('esim_unknown')['valid']);
    }

    public function test_a_transaction_shaped_answer_is_rejected(): void
    {
        Http::fake([self::BASE.'/orders' => $this->validResponse(['orderReference' => 'abc-123', 'status' => 'Completed'])]);

        $e = $this->expectFailure(fn () => app(EsimGoClient::class)->validateBundle('esim_1GB_7D_GB_V2'));

        $this->assertSame(EsimGoException::INVALID_RESPONSE, $e->kind);
        Http::assertSentCount(1);
    }

    public function test_bad_input_makes_no_request(): void
    {
        Http::fake();
        $client = app(EsimGoClient::class);

        $this->assertSame(EsimGoException::REQUEST, $this->expectFailure(fn () => $client->validateBundle('bad name; DROP'))->kind);
        $this->assertSame(EsimGoException::REQUEST, $this->expectFailure(fn () => $client->validateBundle('esim_1', 0))->kind);
        $this->assertSame(EsimGoException::REQUEST, $this->expectFailure(fn () => $client->validateBundle('esim_1', 51))->kind);
        Http::assertNothingSent();
    }

    public function test_the_client_refuses_any_order_request_that_is_not_a_validation(): void
    {
        Http::fake();
        $request = new \ReflectionMethod(EsimGoClient::class, 'request');

        foreach ([['post', ['type' => 'transaction']], ['post', []], ['get', ['type' => 'validate']]] as [$method, $body]) {
            $e = $this->expectFailure(fn () => $request->invoke(app(EsimGoClient::class), $method, '/orders', $body, 'x', fn ($j) => $j));
            $this->assertSame('Only order validation is allowed here; eSIMs are bought only by the fulfilment service.', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    /** Phase 9C: the purchase type exists in exactly one place, used only by EsimGoClient::purchase(). */
    public function test_the_purchase_type_is_defined_once_and_only_used_by_purchase(): void
    {
        $hits = [];
        foreach (File::allFiles(app_path()) as $file) {
            if (preg_match_all('/[\'"]transaction[\'"]/i', $file->getContents()) > 0) {
                $hits[] = $file->getFilename();
            }
        }
        $this->assertSame(['EsimGoClient.php'], $hits);

        $client = file_get_contents(app_path('Services/EsimGo/EsimGoClient.php'));
        $this->assertSame(1, preg_match_all('/[\'"]transaction[\'"]/i', $client));                          // the constant only
        $this->assertSame(2, substr_count($client, 'self::ORDER_TYPE_TRANSACTION'));                        // purchase() + the guard
        $this->assertSame(1, substr_count($client, 'allowPurchase: true'));                                 // only purchase() may buy
    }

    public function test_purchase_is_refused_while_fulfilment_is_switched_off(): void
    {
        Http::fake();

        $e = $this->expectFailure(fn () => app(EsimGoClient::class)->purchase([['item' => 'esim_1GB_7D_GB_V2', 'quantity' => 1]]));

        $this->assertSame(EsimGoException::NOT_CONFIGURED, $e->kind);
        Http::assertNothingSent();
    }

    public function test_validation_is_not_retried_after_a_server_error(): void
    {
        Http::fake([self::BASE.'/orders' => Http::response(['message' => 'oops'], 500)]);

        $this->assertSame(EsimGoException::UNAVAILABLE, $this->expectFailure(fn () => app(EsimGoClient::class)->validateBundle('esim_1'))->kind);
        Http::assertSentCount(1);
    }

    public function test_401_from_orders_is_an_authentication_error(): void
    {
        Http::fake([self::BASE.'/orders' => Http::response('Unauthorized', 401, ['Content-Type' => 'text/plain'])]);

        $this->assertSame(EsimGoException::AUTHENTICATION, $this->expectFailure(fn () => app(EsimGoClient::class)->validateBundle('esim_1'))->kind);
    }

    public function test_validate_and_check_commands(): void
    {
        Http::fake([
            self::BASE.'/orders' => $this->validResponse(),
            self::BASE.'/organisation' => $this->organisationResponse('USD'),
        ]);

        $this->artisan('esimgo:validate-bundle', ['bundle' => 'esim_1GB_7D_GB_V2'])
            ->expectsOutputToContain('Validation only — no order was placed.')
            ->expectsOutputToContain('Valid: yes')
            ->assertSuccessful();

        $this->artisan('esimgo:check')
            ->expectsOutputToContain('the API key was accepted')
            ->expectsOutputToContain('Account currency: USD')
            ->doesntExpectOutputToContain(self::API_KEY)
            ->assertSuccessful();
    }

    public function test_check_command_reports_a_rejected_key(): void
    {
        Http::fake([self::BASE.'/organisation' => Http::response(['message' => 'api token is invalid'], 403)]);

        $this->artisan('esimgo:check')->expectsOutputToContain('rejected the API key (HTTP 403)')->assertFailed();
    }
}
