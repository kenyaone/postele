<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * For each unlinked till payment, the group of up to 4 unpaid bills (opened up to 3 hours before it)
 * whose balances add up to exactly the payment. Fewer bills and a shorter wait win. Only groups of 2+
 * are suggested here (single-bill matches are already highlighted on screen). The waiter still confirms with the code.
 */
class ClearSuggest
{
    public function match(Collection $bills, Collection $events): array
    {
        $out = [];
        $used = []; // a bill is suggested for one payment only, the earliest that fits
        foreach ($events->sortBy('received_at') as $ev) {
            $amount = round((float) $ev->amount, 2);
            $at = $ev->received_at ? \Carbon\Carbon::parse($ev->received_at) : null;
            if (! $at || $amount <= 0) { continue; }
            $cands = $bills->filter(fn ($b) => $b->opened_at->lte($at->copy()->addMinutes(2)) && $b->opened_at->gte($at->copy()->subHours(3)) && (float) $b->balance > 0 && ! isset($used[$b->id]))
                ->sortByDesc('opened_at')->take(18)->values()->all();
            $best = null; $ties = 0; $n = count($cands);
            $try = function (array $pick) use (&$best, &$ties, $amount, $at, $cands) {
                $sum = 0.0; $latest = null;
                foreach ($pick as $i) { $sum += (float) $cands[$i]->balance; $latest = max($latest ?? 0, $cands[$i]->opened_at->timestamp); }
                if (abs($sum - $amount) > 0.01) { return; }
                $score = [count($pick), (int) round(($at->timestamp - $latest) / 60)];
                if ($best === null || $score < $best['score']) { $best = ['score' => $score, 'pick' => $pick]; $ties = 0; }
                elseif ($score[0] === $best['score'][0]) { $ties++; }
            };
            for ($a = 0; $a < $n; $a++) { for ($b = $a + 1; $b < $n; $b++) {
                $try([$a, $b]);
                for ($c = $b + 1; $c < $n; $c++) { $try([$a, $b, $c]);
                    for ($d = $c + 1; $d < $n; $d++) { $try([$a, $b, $c, $d]); } }
            } }
            if ($best) {
                $picked = collect($best['pick'])->map(fn ($i) => $cands[$i])->sortBy('opened_at');
                foreach ($picked as $p) { $used[$p->id] = true; }
                $out[$ev->id] = [
                    'ids'   => $picked->pluck('id')->all(),
                    'label' => $picked->map(fn ($t) => '#' . $t->order_no)->implode(' + '),
                    'other' => $ties > 0,
                ];
            }
        }
        return $out;
    }
}
