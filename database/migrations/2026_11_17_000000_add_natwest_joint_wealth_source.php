<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the NatWest joint current account as a savings source, alongside the
 * existing NatWest regular saver. Both are read from the same NatWest screenshot.
 */
return new class extends Migration
{
    private const DESCRIPTION = 'NatWest joint';

    public function up(): void
    {
        $now = now();

        $categoryId = DB::table('wealth_source_categories')->where('name', 'Savings')->value('id')
            ?? DB::table('wealth_source_categories')->insertGetId([
                'name'       => 'Savings',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $sourceId = DB::table('wealth_sources')->insertGetId([
            'category_id'    => $categoryId,
            'description'    => self::DESCRIPTION,
            'who'            => 'Joint',
            'regular_amount' => 0,
            'current_amount' => 61757,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        DB::table('wealth_source_values')->insert([
            'source_id'  => $sourceId,
            'value'      => 61757,
            'origin'     => 'screenshot',
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        $sourceIds = DB::table('wealth_sources')->where('description', self::DESCRIPTION)->pluck('id');

        DB::table('wealth_source_values')->whereIn('source_id', $sourceIds)->delete();
        DB::table('wealth_sources')->whereIn('id', $sourceIds)->delete();
    }
};
