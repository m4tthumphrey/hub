<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits categories into liquid wealth and everything else, and adds the house
 * as an ordinary source in a non-liquid "Property" category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wealth_source_categories', function (Blueprint $table) {
            $table->boolean('is_liquid')->default(true)->after('name');
        });

        $now = now();

        $categoryId = DB::table('wealth_source_categories')->insertGetId([
            'name'       => 'Property',
            'is_liquid'  => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sourceId = DB::table('wealth_sources')->insertGetId([
            'category_id'    => $categoryId,
            'description'    => 'House',
            'who'            => 'Joint',
            'regular_amount' => 0,
            'current_amount' => 475000,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        DB::table('wealth_source_values')->insert([
            'source_id'  => $sourceId,
            'value'      => 475000,
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        $categoryIds = DB::table('wealth_source_categories')->where('is_liquid', false)->pluck('id');
        $sourceIds   = DB::table('wealth_sources')->whereIn('category_id', $categoryIds)->pluck('id');

        DB::table('wealth_source_values')->whereIn('source_id', $sourceIds)->delete();
        DB::table('wealth_sources')->whereIn('id', $sourceIds)->delete();
        DB::table('wealth_source_categories')->whereIn('id', $categoryIds)->delete();

        Schema::table('wealth_source_categories', function (Blueprint $table) {
            $table->dropColumn('is_liquid');
        });
    }
};
