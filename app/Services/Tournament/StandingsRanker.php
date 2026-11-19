<?php

namespace App\Services\Tournament;

use Brick\Math\BigDecimal;

class StandingsRanker
{
    public function rank(array $entries): array
    {
        $compare = function (array $a, array $b): int {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            if ($a['nrr'] === null || $b['nrr'] === null) {
                return ($a['nrr'] === null ? 1 : 0) <=> ($b['nrr'] === null ? 1 : 0);
            }

            return BigDecimal::of($b['nrr'])->compareTo(BigDecimal::of($a['nrr']));
        };
        usort($entries, fn ($a, $b) => $compare($a, $b) ?: $a['id'] <=> $b['id']);
        foreach ($entries as $index => &$entry) {
            $entry['rank'] = $index > 0 && $compare($entries[$index - 1], $entry) === 0 ? $entries[$index - 1]['rank'] : $index + 1;
        }
        unset($entry);

        return $entries;
    }
}
