<?php

namespace App\Console\Commands;

use App\Services\EsimGo\EsimGoClient;
use App\Services\EsimGo\EsimGoException;
use Illuminate\Console\Command;

class EsimGoCheck extends Command
{
    protected $signature = 'esimgo:check';

    protected $description = 'Check the eSIM Go API key with a read-only request (GET /organisation)';

    public function handle(EsimGoClient $client): int
    {
        try {
            $organisation = $client->organisation();
        } catch (EsimGoException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Connected to eSIM Go: the API key was accepted.');
        $this->line('Account currency: '.($organisation['currency'] ?? 'unknown'));

        return self::SUCCESS;
    }
}
