<?php

namespace Tests\Feature\EsimGo;

use App\Models\IntegrationLog;
use App\Services\EsimGo\EsimGoClient;
use App\Services\EsimGo\EsimGoException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Database\DatabaseTestCase;

class EsimGoClientTest extends DatabaseTestCase
{
    use EsimGoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureEsimGo();
    }

    private function client(): EsimGoClient
    {
        return app(EsimGoClient::class);
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

    public function test_authentication_uses_the_x_api_key_header_over_https_and_never_logs_the_key(): void
    {
        Http::fake([self::BASE.'/organisation' => $this->organisationResponse('USD')]);

        $this->assertSame(['name' => 'Eazy Test Org', 'currency' => 'USD'], $this->client()->organisation());

        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && $r->url() === self::BASE.'/organisation'
            && $r->header('X-API-Key') === [self::API_KEY]
            && ! str_contains($r->url(), self::API_KEY));

        $log = IntegrationLog::sole();
        $this->assertSame(['esimgo', 'organisation', 'GET', 200, true], [$log->service, $log->operation, $log->http_method, $log->status_code, $log->success]);
        $this->assertSame(['name' => 'Eazy Test Org', 'currency' => 'USD'], $log->response_body);   // no apiKey, users or balance
        $this->assertKeyNeverLogged();
        $this->assertStringNotContainsString('private@example.test', json_encode($log->toArray()));
    }

    /** The shape the live API returns: the account inside "organisations". */
    private function nestedOrganisation(array $first, array ...$more): array
    {
        $entry = fn (array $o) => array_merge([
            'id' => 'org-1', 'name' => 'Eazy Test Org', 'apiKey' => self::API_KEY, 'balance' => 100,
            'billingEmail' => 'billing@example.test', 'billingPhone' => '+10000000000', 'addr1' => '1 Private Road',
            'accountManager' => ['firstName' => 'Private', 'emailAddress' => 'manager@example.test'],
            'users' => [['firstName' => 'Private', 'emailAddress' => 'private@example.test']],
        ], $o);

        return ['organisations' => array_map($entry, [$first, ...$more])];
    }

    #[DataProvider('organisationResponses')]
    public function test_organisation_currency_is_read_from_either_response_shape(array $body, array $expected): void
    {
        Http::fake([self::BASE.'/organisation' => Http::response($body)]);

        $this->assertSame($expected, $this->client()->organisation());
        $this->assertSame($expected, IntegrationLog::sole()->response_body);   // logged summary = safe output only
        $this->assertKeyNeverLogged();
    }

    public static function organisationResponses(): array
    {
        return [
            'top level USD' => [['name' => 'Top Org', 'currency' => 'USD', 'apiKey' => 'x'], ['name' => 'Top Org', 'currency' => 'USD']],
            'empty organisations list' => [['organisations' => []], ['name' => null, 'currency' => null]],
            'organisations not a list' => [['organisations' => 'oops'], ['name' => null, 'currency' => null]],
            'entries that are not objects' => [['organisations' => ['oops', 7, null]], ['name' => null, 'currency' => null]],
            'missing currency' => [['organisations' => [['name' => 'No Currency Org']]], ['name' => 'No Currency Org', 'currency' => null]],
            'invalid currency' => [['organisations' => [['name' => 'Bad', 'currency' => 'US Dollar']]], ['name' => 'Bad', 'currency' => null]],
            'unexpected format' => [['something' => ['else' => true]], ['name' => null, 'currency' => null]],
            'top-level list' => [[['currency' => 'USD']], ['name' => null, 'currency' => null]],
        ];
    }

    public function test_nested_organisations_response_gives_usd_and_drops_sensitive_fields(): void
    {
        Http::fake([self::BASE.'/organisation' => Http::response($this->nestedOrganisation(['currency' => 'usd']))]);

        $result = $this->client()->organisation();

        $this->assertSame(['name' => 'Eazy Test Org', 'currency' => 'USD'], $result);
        $stored = json_encode(IntegrationLog::sole()->toArray());
        foreach ([self::API_KEY, 'billing@example.test', 'manager@example.test', 'private@example.test', '+10000000000', '1 Private Road', 'balance'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($result));
            $this->assertStringNotContainsString($secret, $stored);
        }
    }

    public function test_the_first_organisation_with_a_valid_currency_is_used(): void
    {
        Http::fake([self::BASE.'/organisation' => Http::response($this->nestedOrganisation(
            ['name' => 'No Currency', 'currency' => ''],
            ['name' => 'Second', 'currency' => 'USD'],
            ['name' => 'Third', 'currency' => 'EUR'],
        ))]);

        $this->assertSame(['name' => 'Second', 'currency' => 'USD'], $this->client()->organisation());
    }

    public function test_check_command_shows_usd_for_the_nested_response(): void
    {
        Http::fake([self::BASE.'/organisation' => Http::response($this->nestedOrganisation(['currency' => 'USD']))]);

        $this->artisan('esimgo:check')
            ->expectsOutputToContain('Connected to eSIM Go: the API key was accepted.')
            ->expectsOutputToContain('Account currency: USD')
            ->doesntExpectOutputToContain(self::API_KEY)
            ->assertSuccessful();
    }

    public function test_sync_reads_the_nested_currency_when_no_currency_is_configured(): void
    {
        Http::fake([
            self::BASE.'/organisation' => Http::response($this->nestedOrganisation(['currency' => 'USD'])),
            self::BASE.'/catalogue*' => Http::sequence()->push([$this->bundle('esim_1')])->push([]),
        ]);

        $stats = app(\App\Services\EsimGo\CatalogueSynchroniser::class)->sync(fresh: true, dryRun: true);

        $this->assertSame(['USD', true], [$stats['currency'], $stats['dry_run']]);
        $this->assertSame(0, \App\Models\ProviderBundle::count());
    }

    public function test_nothing_is_sent_without_a_key_or_over_plain_http(): void
    {
        Http::fake();

        config(['esimgo.api_key' => '']);
        $this->assertFalse($this->client()->isConfigured());
        $this->assertSame(EsimGoException::NOT_CONFIGURED, $this->expectFailure(fn () => $this->client()->organisation())->kind);

        config(['esimgo.api_key' => self::API_KEY, 'esimgo.api_url' => 'http://api.esim-go.com/v2.5']);
        $this->assertFalse($this->client()->isConfigured());
        $this->assertSame(EsimGoException::NOT_CONFIGURED, $this->expectFailure(fn () => $this->client()->organisation())->kind);

        Http::assertNothingSent();
    }

    #[DataProvider('authStatuses')]
    public function test_401_and_403_are_authentication_errors_and_are_not_retried(int $status): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'api token is invalid '.self::API_KEY], $status)]);

        $e = $this->expectFailure(fn () => $this->client()->organisation());

        $this->assertSame([EsimGoException::AUTHENTICATION, $status], [$e->kind, $e->status]);
        $this->assertStringNotContainsString(self::API_KEY, $e->getMessage());   // even if echoed back
        Http::assertSentCount(1);
        $this->assertKeyNeverLogged();
        $this->assertFalse(IntegrationLog::sole()->success);
    }

    public static function authStatuses(): array
    {
        return ['401' => [401], '403' => [403]];
    }

    public function test_429_is_retried_honouring_retry_after(): void
    {
        Http::fake([self::BASE.'/organisation' => Http::sequence()
            ->push(['message' => 'Rate limit exceeded'], 429, ['Retry-After' => '2'])
            ->pushResponse($this->organisationResponse())]);

        $this->assertSame('USD', $this->client()->organisation()['currency']);

        Http::assertSentCount(2);
        Sleep::assertSequence([Sleep::for(2000)->milliseconds()]);
        $this->assertSame([429, 200], IntegrationLog::orderBy('id')->pluck('status_code')->all());
    }

    public function test_429_gives_up_after_the_configured_retries(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'Rate limit exceeded'], 429)]);

        $this->assertSame(EsimGoException::RATE_LIMITED, $this->expectFailure(fn () => $this->client()->organisation())->kind);
        Http::assertSentCount(3);   // 1 + 2 retries
    }

    public function test_5xx_on_a_read_is_retried_then_reported_as_unavailable(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'Service Unavailable'], 503)]);

        $e = $this->expectFailure(fn () => $this->client()->cataloguePage(1, 2));

        $this->assertSame([EsimGoException::UNAVAILABLE, 503], [$e->kind, $e->status]);
        Http::assertSentCount(3);
    }

    public function test_connection_failures_are_reported_with_the_real_cause(): void
    {
        Http::fake([self::BASE.'/*' => Http::failedConnection('cURL error 60: SSL certificate problem: self-signed certificate in certificate chain')]);

        $e = $this->expectFailure(fn () => $this->client()->organisation());

        $this->assertSame(EsimGoException::CONNECTION, $e->kind);
        $this->assertStringContainsString('cURL error 60', $e->getMessage());
        $this->assertStringContainsString('cURL error 60', (string) IntegrationLog::query()->latest('id')->value('error_message'));
    }

    public function test_400_and_404_are_request_errors_and_are_not_retried(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'Bad page'], 400)]);

        $e = $this->expectFailure(fn () => $this->client()->cataloguePage(1, 2));

        $this->assertSame(EsimGoException::REQUEST, $e->kind);
        $this->assertStringContainsString('Bad page', $e->getMessage());
        Http::assertSentCount(1);
    }

    public function test_catalogue_page_accepts_both_documented_and_enveloped_responses(): void
    {
        Http::fake([self::BASE.'/catalogue*' => Http::sequence()
            ->push([$this->bundle('esim_A')])
            ->push(['bundles' => [$this->bundle('esim_B')], 'pageCount' => 4, 'rows' => 7, 'pageSize' => 2])
            ->push(['unexpected' => true])]);

        $bare = $this->client()->cataloguePage(1, 2);
        $this->assertSame([null, 'esim_A'], [$bare['pageCount'], $bare['bundles'][0]['name']]);

        $enveloped = $this->client()->cataloguePage(2, 2);
        $this->assertSame([4, 'esim_B'], [$enveloped['pageCount'], $enveloped['bundles'][0]['name']]);

        $this->assertSame(EsimGoException::INVALID_RESPONSE, $this->expectFailure(fn () => $this->client()->cataloguePage(3, 2))->kind);

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/catalogue?page=2&perPage=2');
        $this->assertSame(['bundles' => 1, 'pageCount' => 4], IntegrationLog::query()->orderBy('id')->skip(1)->value('response_body'));
    }
}
