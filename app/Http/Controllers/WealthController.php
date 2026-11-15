<?php

namespace App\Http\Controllers;

use App\Models\Wealth\Liability;
use App\Models\Wealth\Screenshot;
use App\Models\Wealth\Source;
use App\Services\Wealth\LiabilityService;
use App\Services\Wealth\ScreenshotTextParser;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WealthController
{
    public function index(): View
    {
        return view('wealth.index');
    }

    public function data(LiabilityService $liabilityService): array
    {
        $sources = DB::select("
            SELECT s.id, s.who, s.description, s.category_id, c.name as category_name, c.is_liquid,
                   s.regular_amount, s.current_amount
            FROM wealth_sources s
            JOIN wealth_source_categories c ON c.id = s.category_id
            ORDER BY s.category_id, s.id
        ");
        $totals  = DB::select("
            select c.name, sum(s.current_amount) as total
            from wealth_sources s
            join wealth_source_categories c on (c.id = s.category_id)
            where c.is_liquid = 1
            group by c.id, c.name");

        $totals[] = (object) [
            'name'  => 'Total',
            'total' => array_reduce($totals, function ($carry, $item) {
                return $carry + $item->total;
            })
        ];

        // Total stays as liquid wealth; property and liabilities sit alongside it.
        $liquid   = (int) end($totals)->total;
        $property = (int) array_sum(array_map(fn ($s) => $s->is_liquid ? 0 : $s->current_amount, $sources));
        $today    = CarbonImmutable::today();
        $owed     = (int) round(Liability::with('records')->get()->sum(
            fn (Liability $liability) => $liabilityService->balanceOn($liability, $today) ?? 0
        ));

        return [
            'sources' => $sources,
            'totals'  => $totals,
            'summary' => [
                'property'    => $property,
                'liabilities' => $owed,
                'net_worth'   => $liquid + $property - $owed,
            ],
        ];
    }

    public function history(LiabilityService $liabilityService): array
    {
        $liquidSources = "
            SELECT s.id FROM wealth_sources s
            JOIN wealth_source_categories c ON c.id = s.category_id
            WHERE c.is_liquid = 1
        ";

        $totalOverTime = DB::select("
            SELECT d.date, SUM(COALESCE((
                SELECT sv.value
                FROM wealth_source_values sv
                WHERE sv.source_id = s.id AND DATE(sv.created_at) <= d.date
                ORDER BY sv.created_at DESC
                LIMIT 1
            ), 0)) as total
            FROM (
                SELECT DISTINCT DATE(created_at) as date FROM wealth_source_values
                WHERE source_id IN ($liquidSources)
            ) d
            CROSS JOIN ($liquidSources) s
            GROUP BY d.date
            ORDER BY d.date ASC
        ");

        $bySource = DB::select("
            SELECT sv.source_id as id, sv.value, DATE(sv.created_at) as date
            FROM wealth_source_values sv
            INNER JOIN (
                SELECT source_id, DATE(created_at) as date, MAX(id) as max_id
                FROM wealth_source_values
                GROUP BY source_id, DATE(created_at)
            ) latest ON sv.id = latest.max_id
            ORDER BY sv.source_id, DATE(sv.created_at) ASC
        ");

        return [
            'total_over_time' => $totalOverTime,
            'by_source'       => $bySource,
        ] + $this->netWorthHistory($totalOverTime, $bySource, $liabilityService);
    }

    /**
     * Liabilities and net worth by date. Liabilities start at the first saved record;
     * net worth starts on the first day a property value exists.
     */
    private function netWorthHistory(array $liquidOverTime, array $bySource, LiabilityService $liabilityService): array
    {
        $today       = CarbonImmutable::today();
        $liabilities = Liability::with('records')->get();
        $propertyIds = DB::table('wealth_sources as s')
            ->join('wealth_source_categories as c', 'c.id', '=', 's.category_id')
            ->where('c.is_liquid', false)
            ->pluck('s.id')
            ->all();

        $propertyValues = array_values(array_filter($bySource, fn ($row) => in_array($row->id, $propertyIds)));

        $dates = array_merge(
            array_map(fn ($row) => $row->date, $liquidOverTime),
            array_map(fn ($row) => $row->date, $propertyValues),
            $liabilities->flatMap(fn ($l) => $l->records->map(fn ($r) => $r->effective_date->toDateString()))->all(),
            [$today->toDateString()],
        );
        $dates = array_values(array_unique($dates));
        sort($dates);

        // Latest value on or before a date, from rows sorted by date ascending.
        $latest = function (array $rows, string $date, string $field) {
            $value = null;
            foreach ($rows as $row) {
                if ($row->date > $date) {
                    break;
                }
                $value = $row->{$field};
            }

            return $value;
        };

        $liabilitiesOverTime = [];
        $netWorthOverTime    = [];

        foreach ($dates as $date) {
            $balances = $liabilities
                ->map(fn ($liability) => $liabilityService->balanceOn($liability, CarbonImmutable::parse($date)))
                ->filter(fn ($balance) => $balance !== null);

            $owed = (int) round($balances->sum());

            if ($balances->isNotEmpty()) {
                $liabilitiesOverTime[] = ['date' => $date, 'total' => $owed];
            }

            $property = null;
            foreach ($propertyIds as $id) {
                $value = $latest(array_values(array_filter($propertyValues, fn ($row) => $row->id === $id)), $date, 'value');
                if ($value !== null) {
                    $property += $value;
                }
            }

            if ($property !== null) {
                $liquid             = (int) ($latest($liquidOverTime, $date, 'total') ?? 0);
                $netWorthOverTime[] = ['date' => $date, 'total' => $liquid + $property - $owed];
            }
        }

        return [
            'liabilities_over_time' => $liabilitiesOverTime,
            'net_worth_over_time'   => $netWorthOverTime,
        ];
    }

    public function update(Request $request): array
    {
        $id    = $request->get('id');
        $name  = $request->get('name');
        $value = $request->integer('value');

        $source = Source::findOrFail($id);

        if ($name === 'current' && $value !== $source->current_amount) {
            $this->updateAmount($source, $value);
        } elseif ($value !== $source->regular_amount) {
            $source->regular_amount = $value;
            $source->save();
        }

        return [
            'status' => 'ok'
        ];
    }

    public function screenshot(Request $request, ScreenshotTextParser $parser): array
    {
        $text = $request->get('text');
        $app  = $request->get('app', 'Unknown');

        Screenshot::create([
            'text' => $text,
            'app'  => $app,
        ]);

        $updates = $parser->parse($text, $app);

        foreach ($updates as $sourceId => $amount) {
            if ($amount) {
                $this->updateAmount(Source::findOrFail($sourceId), (int) $amount);
            }
        }

        return [
            'status' => 'ok'
        ];
    }

    private function updateAmount(Source $source, int $amount)
    {
        if ($amount > 0 && $amount != $source->current_amount) {
            $source->current_amount = $amount;
            $source->save();

            $source->values()->create([
                'value' => $amount
            ]);
        }
    }
}
