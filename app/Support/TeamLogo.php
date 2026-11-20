<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * The one place that decides which logo a tournament team shows, on every
 * public tab (Matches, Table, Bracket, Stats) and in the scorer portal.
 *
 * Order:
 *   1. the hardcoded map in config/tournament-teams.php (team name => badge)
 *   2. the logo the team's company uploaded (companies.logo, public disk)
 *   3. the team's initials
 */
class TeamLogo
{
    /**
     * @return array{type: 'logo', url: string}|array{type: 'cse', batch: string}|array{type: 'initials', initials: string}
     */
    public static function resolve(?string $teamName, ?string $uploadedPath = null): array
    {
        $badge = self::configBadge((string) $teamName);

        if (isset($badge['logo'])) {
            return ['type' => 'logo', 'url' => asset('images/teams/'.$badge['logo'])];
        }
        if (isset($badge['cse'])) {
            return ['type' => 'cse', 'batch' => (string) $badge['cse']];
        }
        if (filled($uploadedPath)) {
            return ['type' => 'logo', 'url' => Storage::disk('public')->url($uploadedPath)];
        }

        return ['type' => 'initials', 'initials' => self::initials((string) $teamName)];
    }

    private static function configBadge(string $teamName): ?array
    {
        $config = config('tournament-teams');
        $normalise = fn (string $value) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
        $key = collect($config['teams'] ?? [])
            ->mapWithKeys(fn ($badgeKey, $team) => [$normalise((string) $team) => $badgeKey])
            ->get($normalise($teamName));

        // Local development only: demo seeder teams ("DEMO 2026 A1") get library
        // badges in turn so cards can be reviewed with logos.
        if ($key === null && ($config['demo_badges'] ?? false) && app()->environment('local')
            && preg_match('/^DEMO \d{4} ([A-Z])(\d+)$/', $teamName, $demo)) {
            $keys = array_keys($config['badges'] ?? []);
            $index = (ord($demo[1]) - 65) * 4 + ((int) $demo[2] - 1);
            $key = $keys ? $keys[$index % count($keys)] : null;
        }

        return $key !== null ? ($config['badges'][$key] ?? null) : null;
    }

    private static function initials(string $teamName): string
    {
        $initials = collect(preg_split('/\s+/u', trim($teamName)))
            ->filter(fn ($word) => preg_match('/[\p{L}\p{N}]/u', $word))->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');

        return $initials !== '' ? $initials : '?';
    }
}
