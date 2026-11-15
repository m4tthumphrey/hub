<?php

namespace App\Models\Wealth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Liability extends Model
{
    protected $table = 'wealth_liabilities';

    protected $guarded = ['id'];

    /** Newest first: the first record is the one currently in force. */
    public function records(): HasMany
    {
        return $this->hasMany(LiabilityRecord::class, 'liability_id')
            ->orderByDesc('effective_date')
            ->orderByDesc('id');
    }

    /** The balance log, newest first. */
    public function values(): HasMany
    {
        return $this->hasMany(LiabilityValue::class, 'liability_id')->orderByDesc('date');
    }

    public function overpayments(): HasMany
    {
        return $this->hasMany(LiabilityOverpayment::class, 'liability_id')->orderByDesc('paid_on');
    }
}
