<?php

namespace App\Models\Wealth;

use Illuminate\Database\Eloquent\Model;

class LiabilityRecord extends Model
{
    protected $table = 'wealth_liability_records';

    protected $guarded = ['id'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'effective_date'       => 'date:Y-m-d',
            'fix_end_date'         => 'date:Y-m-d',
            'payoff_date'          => 'date:Y-m-d',
            'contractual_end_date' => 'date:Y-m-d',
            'one_offs'             => 'array',
            'balance'              => 'float',
            'interest_rate'        => 'float',
            'follow_on_rate'       => 'float',
            'regular_overpayment'  => 'float',
            'actual_payment'       => 'float',
            'monthly_payment'      => 'float',
            'calculated_payment'   => 'float',
            'interest_remaining'   => 'float',
            'interest_saved'       => 'float',
            'balance_at_fix_end'   => 'float',
            'projected_balance'    => 'float',
        ];
    }
}
