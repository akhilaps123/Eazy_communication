<?php

namespace Tests\Feature\EsimGo;

use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/** Test-only helpers. All eSIM Go traffic is faked; stray requests fail the test. */
trait EsimGoFixtures
{
    protected const API_KEY = 'test-esimgo-key-DO-NOT-LOG-7f3a9c';

    protected const BASE = 'https://api.esim-go.com/v2.5';

    protected function configureEsimGo(array $overrides = []): void
    {
        config(array_merge([
            'esimgo.api_url' => self::BASE,
            'esimgo.api_key' => self::API_KEY,
            'esimgo.currency' => null,
            'esimgo.catalogue.per_page' => 2,
            'esimgo.catalogue.page_delay_ms' => 0,
        ], $overrides));
        Http::preventStrayRequests();
        Sleep::fake();
    }

    /** A bundle as returned by GET /catalogue. */
    protected function bundle(string $name, array $overrides = []): array
    {
        return array_merge([
            'name' => $name,
            'description' => "eSIM, {$name}",
            'groups' => ['Standard Fixed'],
            'countries' => [['name' => 'United Kingdom', 'region' => 'Europe', 'iso' => 'GB']],
            'countryNetworks' => [['country' => ['iso' => 'GB'], 'networks' => [['name' => 'EE', 'speeds' => ['4G', '5G']]]]],
            'dataAmount' => 1000,
            'duration' => 7,
            'speed' => ['4G', '5G'],
            'autostart' => true,
            'unlimited' => false,
            'roamingEnabled' => [],
            'price' => 1.5,
            'billingType' => 'FixedCost',
        ], $overrides);
    }

    protected function organisationResponse(string $currency = 'USD')
    {
        // The real endpoint also returns the API key and user details.
        return Http::response([
            'name' => 'Eazy Test Org', 'apiKey' => self::API_KEY, 'currency' => $currency, 'balance' => 10000,
            'users' => [['firstName' => 'Private', 'emailAddress' => 'private@example.test']],
        ]);
    }

    /** Assert nothing stored in integration_logs contains the API key. */
    protected function assertKeyNeverLogged(): void
    {
        $this->assertStringNotContainsString(self::API_KEY, json_encode(IntegrationLog::query()->get()->toArray()));
    }
}
