<?php

namespace App\Console\Commands;

use App\Services\EsimGo\CatalogueSynchroniser;
use App\Services\EsimGo\EsimGoException;
use Illuminate\Console\Command;

class EsimGoSyncCatalogue extends Command
{
    protected $signature = 'esimgo:sync-catalogue
                            {--fresh : Ignore the cached catalogue and fetch it from eSIM Go again}
                            {--dry-run : Fetch and compare only; write nothing to provider_bundles}';

    protected $description = 'Copy the eSIM Go bundle catalogue into provider_bundles (never changes plans or retail prices)';

    public function handle(CatalogueSynchroniser $synchroniser): int
    {
        try {
            $stats = $synchroniser->sync((bool) $this->option('fresh'), (bool) $this->option('dry-run'));
        } catch (EsimGoException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($stats['dry_run'] ? 'Dry run — nothing was written.' : 'Catalogue synchronised.');
        $this->table(['Item', 'Value'], collect($stats)->reject(fn ($v) => is_array($v))
            ->map(fn ($v, $k) => [$k, is_bool($v) ? ($v ? 'yes' : 'no') : $v])->values()->all());

        // Country mapping details: provider locations that are not ISO country codes.
        $report = $stats['country_report'] ?? [];
        $list = fn (array $items) => collect($items)->map(fn ($n, $id) => "{$id} ({$n})")->join(', ');
        if (! empty($report['resolved'])) {
            $this->line('Locations mapped through the provider\'s roamingEnabled countries (bundles): '.$list($report['resolved']));
        }
        if (! empty($report['unmapped'])) {
            $this->warn('Identifiers NOT mapped to a country — review (bundles): '.$list($report['unmapped']));
        }

        return self::SUCCESS;
    }
}
