<?php

namespace App\Console\Commands;

use App\Services\Tournament\TournamentSummaryRebuildService;
use Illuminate\Console\Command;
use Throwable;

class RebuildTournamentSummaries extends Command
{
    protected $signature = 'tournament:rebuild-summaries
                            {year : One tournament year, from 1 to 9999}
                            {--apply : Explicitly rebuild calculated summaries in the configured database}';

    protected $description = 'Rebuild one year of tournament standings/player totals without changing official records';

    public function handle(TournamentSummaryRebuildService $rebuild): int
    {
        if (! app()->runningInConsole()) {
            return self::FAILURE;
        }
        $year = $this->argument('year');
        // Reject malformed years before any database read or write.
        if (! is_string($year) || ! preg_match('/\A[1-9][0-9]{0,3}\z/', $year)) {
            $this->error('Enter one whole tournament year from 1 to 9999.');

            return self::INVALID;
        }
        if (! $this->option('apply')) {
            $this->error('No changes made. Use --apply only after checking the target database and year.');

            return self::INVALID;
        }
        try {
            $result = $rebuild->rebuild((int) $year);
        } catch (Throwable) {
            // Do not print/log source figures, player identities, raw SQL,
            // connection credentials or exception messages in maintenance output.
            $this->error('Rebuild failed. No summary changes were committed. Check source validity or lock contention and retry.');

            return self::FAILURE;
        }
        $this->info('Tournament '.$year.' summaries rebuilt: '.$result['groups'].' groups checked, '
            .$result['standings_updated'].' standings updated, '.$result['players_checked'].' players checked.');

        return self::SUCCESS;
    }
}
