<?php

namespace App\Console\Commands;

use App\Services\EsimGo\EsimGoClient;
use App\Services\EsimGo\EsimGoException;
use Illuminate\Console\Command;

class EsimGoValidateBundle extends Command
{
    protected $signature = 'esimgo:validate-bundle {bundle : eSIM Go bundle name} {--quantity=1}';

    protected $description = 'Validate (not buy) an eSIM Go bundle order: POST /orders with type=validate only';

    public function handle(EsimGoClient $client): int
    {
        try {
            $result = $client->validateBundle((string) $this->argument('bundle'), (int) $this->option('quantity'));
        } catch (EsimGoException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Validation only — no order was placed.');
        $this->line('Valid: '.($result['valid'] ? 'yes' : 'no'));
        $this->line('Total: '.($result['total'] ?? '—').' '.($result['currency'] ?? ''));

        return $result['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
