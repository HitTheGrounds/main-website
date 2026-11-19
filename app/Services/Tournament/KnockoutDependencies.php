<?php

namespace App\Services\Tournament;

use App\Models\TournamentMatch;
use Illuminate\Validation\ValidationException;

/** Read-only actual-fixture checks; caller authorizes and locks the year snapshot. */
class KnockoutDependencies
{
    public function assertChange(TournamentMatch $before, TournamentMatch $proposed, iterable $matches): void
    {
        if ($before->id !== $proposed->id || $before->tournament_year !== $proposed->tournament_year || $before->stage !== $proposed->stage) {
            throw ValidationException::withMessages(['tournament' => 'A result correction must retain its match identity, year and stage.']);
        }
        $next = ['QF' => 'SF', 'SF' => 'F'][$before->stage] ?? null;
        if ($next === null || ! $this->officialWin($before)
            || ($this->officialWin($proposed) && $before->winner_id === $proposed->winner_id)) {
            return;
        }
        foreach ($matches as $match) {
            if ($match->tournament_year === $before->tournament_year && $match->stage === $next
                && in_array($before->winner_id, [$match->team1_id, $match->team2_id], true)) {
                $published = $match->result_published_at !== null || $match->player_stats_published_at !== null;
                throw ValidationException::withMessages(['tournament' => 'This change would invalidate '.$next.' match #'.$match->id.'. '
                    .($published ? 'Its history is already published; resolve it through the organizers.' : 'Edit or delete that unused fixture from Matches before retrying. Saved drafts must be discarded first.')
                    .' No fixtures are removed automatically.']);
            }
        }
    }

    private function officialWin(TournamentMatch $match): bool
    {
        return $match->status === 'finished' && $match->result_published_at !== null && $match->result_type === 'win'
            && $match->winner_id !== null && in_array($match->winner_id, [$match->team1_id, $match->team2_id], true);
    }
}
