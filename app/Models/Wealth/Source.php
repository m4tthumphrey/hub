<?php

namespace App\Models\Wealth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $table = 'wealth_sources';

    public function values(): HasMany
    {
        return $this->hasMany(SourceValue::class, 'source_id');
    }
}
