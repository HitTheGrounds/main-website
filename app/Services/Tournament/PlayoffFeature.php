<?php

namespace App\Services\Tournament;

use App\Helpers\CompanyAuth;
use App\Models\PlayerMatchStat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlayoffFeature
{
    public function enabled(int $year): bool
    {
        return config('tournament.enabled') === true
            && Schema::hasTable('tournament_settings')
            && (bool) DB::table('tournament_settings')->where('tournament_year', $year)->value('playoffs_enabled');
    }

    public function stages(int $year): array
    {
        return array_keys($this->labels($year));
    }

    public function labels(int $year): array
    {
        return ['G' => 'Group', ...($this->enabled($year) ? ['PO' => 'Playoffs'] : []),
            'QF' => 'Quarter-final', 'SF' => 'Semi-final', 'F' => 'Final'];
    }

    public function settings(int $year): array
    {
        $this->authorize();
        $this->validateYear($year);
        $row = DB::table('tournament_settings')->where('tournament_year', $year)->first();

        return ['enabled' => (bool) ($row->playoffs_enabled ?? false), 'version' => (int) ($row->version ?? 0)];
    }

    public function save(int $year, bool $enabled, int $version): array
    {
        $this->authorize();
        $this->validateYear($year);

        return app(TournamentWriteService::class)->run($year, function () use ($year, $enabled, $version) {
            $this->authorize(true);
            $current = $this->settings($year);
            if ($current['version'] !== $version) {
                throw ValidationException::withMessages(['playoffs' => 'Tournament settings changed. Reload before saving.']);
            }
            $values = ['playoffs_enabled' => $enabled, 'version' => $version + 1, 'updated_at' => now()];
            if ($version === 0) {
                DB::table('tournament_settings')->insert(['tournament_year' => $year, 'created_at' => now(), ...$values]);
            } else {
                DB::table('tournament_settings')->where('tournament_year', $year)->update($values);
            }
            // Keep derived public totals consistent with the switch in the same transaction.
            $members = PlayerMatchStat::where('record_state', 'published')
                ->whereHas('tournamentMatch', fn ($query) => $query->where('tournament_year', $year)->where('stage', 'PO'))
                ->distinct()->pluck('team_member_id')->map(fn ($id) => (int) $id)->all();
            app(PlayerTotalsService::class)->recalculate($year, $members);

            return $this->settings($year);
        });
    }

    private function validateYear(int $year): void
    {
        Validator::make(compact('year'), ['year' => ['integer', 'between:1,9999']])->validate();
    }

    private function authorize(bool $lock = false): void
    {
        abort_unless(config('tournament.enabled') === true, 404);
        $query = User::whereKey(CompanyAuth::user()?->id);
        if ($lock) {
            $query->sharedLock();
        }
        abort_unless($query->first()?->is_admin, 403);
    }
}
