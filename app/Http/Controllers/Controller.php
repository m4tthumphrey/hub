<?php

namespace App\Http\Controllers;

use App\Models\Screenshot;
use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class Controller
{
    public function index(): View
    {
        $sources = Source::all();
        $totals  = DB::select("
            select c.name, sum(s.current_amount) as total
            from sources s
            join source_categories c on (c.id = s.category_id)
            group by c.id");

        $totals[] = (object) [
            'name'  => 'Total',
            'total' => array_reduce($totals, function ($carry, $item) {
                return $carry + $item->total;
            })
        ];

        return view('index', [
            'sources' => $sources,
            'totals'  => $totals
        ]);
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

    public function screenshot(Request $request): array
    {
        $text = $request->get('text');
        $app  = $request->get('app', 'Unknown');

        $screenshot = Screenshot::create([
            'text' => $text,
            'app'  => $app
        ]);

        $lines  = explode("\n", $text);
        $updates = [];

        switch ($app) {
            case 'Trading 212':
                if (str_contains($lines[1], '212 Invest')) {
                    $updates[3] = $lines[3];
                } elseif (str_contains($lines[1], 'CashISA')) {
                    $updates[4] = $lines[3];
                }
                break;
            case 'Chip':
                $updates[10] = $lines[7];
                break;
            case 'MyAviva':
                $updates[9] = $lines[8];
                break;
            case 'NatWest':
                $updates[1] = $lines[17];
                break;
            case 'Chrome':
                if (str_contains($lines[2], 'retiready.co.uk')) {
                    $updates[5] = $lines[10];
                    $updates[6] = $lines[12];
                }
                break;
            case 'HL':
                $updates[7] = $lines[6];
                break;
            default:
                if (str_contains($lines[2], 'COURTIERS')) {
                    $updates[8] = str_replace(['£', 'k'], '', $lines[8]) * 100;
                }
                break;
        }

        if (count($updates)) {
            foreach ($updates as $sourceId => $amount) {
                $source = Source::findOrFail($sourceId);
                $amount = str_replace(['£', ','], '', $amount);

                $this->updateAmount($source, $amount);
            }
        }

        return [
            'status' => 'ok'
        ];
    }

    private function updateAmount(Source $source, int $amount)
    {
        if ($amount != $source->current_amount) {
            $source->current_amount = $amount;
            $source->save();

            $source->values()->create([
                'value' => $amount
            ]);
        }
    }
}
