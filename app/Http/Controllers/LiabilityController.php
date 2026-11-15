<?php

namespace App\Http\Controllers;

use App\Models\Wealth\Liability;
use App\Models\Wealth\LiabilityOverpayment;
use App\Models\Wealth\LiabilityValue;
use App\Services\Wealth\LiabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiabilityController
{
    public function __construct(private LiabilityService $liabilities) {}

    public function index(): array
    {
        // Log any payment dates that have passed since the dashboard was last opened.
        Liability::all()->each(fn (Liability $liability) => $this->liabilities->syncLog($liability));

        return [
            'liabilities' => Liability::with(['records', 'overpayments', 'values'])
                ->orderBy('id')
                ->get()
                ->map(fn (Liability $liability) => $this->liabilities->state($liability)),
        ];
    }

    public function store(Request $request): array
    {
        $data = $request->validate(['description' => ['required', 'string', 'max:100']] + LiabilityService::rules());

        DB::transaction(function () use ($data) {
            $liability = Liability::create(['type' => 'loan', 'description' => $data['description']]);
            $this->liabilities->saveRecord($liability, $data);
        });

        return ['status' => 'ok'];
    }

    public function preview(Request $request): array
    {
        $data      = $request->validate(LiabilityService::rules());
        $liability = $request->filled('liability_id') ? Liability::findOrFail($request->integer('liability_id')) : null;

        return $this->liabilities->preview($liability, $data);
    }

    /** A hypothetical forecast for the dashboard's what-if panel. Never saved. */
    public function whatIf(Request $request, Liability $liability): array
    {
        $data = $request->validate([
            'regular_overpayment' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'lump_sum'            => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $number = fn (string $key) => isset($data[$key]) ? (float) $data[$key] : null;

        return [
            'what_if' => $this->liabilities->whatIf($liability->load('records', 'overpayments', 'values'), $number('regular_overpayment'), $number('lump_sum')),
        ];
    }

    public function storeRecord(Request $request, Liability $liability): array
    {
        $this->liabilities->saveRecord($liability, $request->validate(LiabilityService::rules()));

        return ['status' => 'ok'];
    }

    public function updateValue(Request $request, Liability $liability, LiabilityValue $value): array
    {
        abort_unless($value->liability_id === $liability->id, 404);

        $data    = $request->validate(['balance' => ['required', 'numeric', 'min:0', 'max:99999999']]);
        $current = $liability->records()->first();

        abort_if(! $current || $value->date->lt($current->effective_date), 422, 'Only entries since the last saved details can be corrected.');

        DB::transaction(fn () => $this->liabilities->correctValue($liability, $value, (float) $data['balance']));

        return ['status' => 'ok'];
    }

    public function storeOverpayment(Request $request, Liability $liability): array
    {
        $data = $request->validate([
            'paid_on' => ['required', 'date'],
            'amount'  => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'note'    => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($liability, $data) {
            $liability->overpayments()->create($data);

            $this->recordOverpaymentChange($liability, sprintf(
                'One-off overpayment of £%s on %s added.',
                number_format($data['amount'], fmod((float) $data['amount'], 1) == 0.0 ? 0 : 2),
                CarbonImmutable::parse($data['paid_on'])->format('j M Y'),
            ));
        });

        return ['status' => 'ok'];
    }

    public function destroyOverpayment(Liability $liability, LiabilityOverpayment $overpayment): array
    {
        abort_unless($overpayment->liability_id === $liability->id, 404);

        DB::transaction(function () use ($liability, $overpayment) {
            $overpayment->delete();

            $this->recordOverpaymentChange($liability, sprintf(
                'One-off overpayment of £%s on %s removed.',
                number_format($overpayment->amount, fmod($overpayment->amount, 1) == 0.0 ? 0 : 2),
                $overpayment->paid_on->format('j M Y'),
            ));
        });

        return ['status' => 'ok'];
    }

    /** Re-save the current values so the history shows what the one-off did to the forecast. */
    private function recordOverpaymentChange(Liability $liability, string $note): void
    {
        if ($current = $liability->records()->first()) {
            $this->liabilities->saveRecord($liability, $this->liabilities->inputs($current), $note);
        }
    }
}
