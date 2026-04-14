<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeightLog extends Model
{
    protected $table = 'weight_logs';
    protected $guarded = ['id'];
    protected $casts = [
        'weight_kilograms' => 'decimal:2',
        'weight_pounds'    => 'decimal:2',
        'weight_stone'     => 'decimal:2',
    ];
}
