<?php

namespace App\Models\Wealth;

use Illuminate\Database\Eloquent\Model;

class LiabilityValue extends Model
{
    protected $table = 'wealth_liability_values';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date'              => 'date:Y-m-d',
            'balance'           => 'float',
            'projected_balance' => 'float',
            'is_manual'         => 'boolean',
        ];
    }
}
