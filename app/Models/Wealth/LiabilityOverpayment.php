<?php

namespace App\Models\Wealth;

use Illuminate\Database\Eloquent\Model;

class LiabilityOverpayment extends Model
{
    protected $table = 'wealth_liability_overpayments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date:Y-m-d',
            'amount'  => 'float',
        ];
    }
}
