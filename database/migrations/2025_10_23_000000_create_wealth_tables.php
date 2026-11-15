<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline for the wealth tables, which were originally created by hand.
 * Each table is skipped where it already exists, so this is safe to run
 * against databases that predate the migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wealth_source_categories')) {
            Schema::create('wealth_source_categories', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 100);
                $table->timestamp('created_at');
                $table->timestamp('updated_at');
            });
        }

        if (! Schema::hasTable('wealth_sources')) {
            Schema::create('wealth_sources', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('category_id');
                $table->string('description', 100);
                $table->string('who', 15);
                $table->integer('regular_amount')->default(0);
                $table->integer('current_amount');
                $table->timestamp('created_at');
                $table->timestamp('updated_at');
            });
        }

        if (! Schema::hasTable('wealth_source_values')) {
            Schema::create('wealth_source_values', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('source_id');
                $table->integer('value');
                $table->timestamp('created_at');
            });
        }

        if (! Schema::hasTable('wealth_screenshots')) {
            Schema::create('wealth_screenshots', function (Blueprint $table) {
                $table->increments('id');
                $table->longText('text')->nullable();
                $table->string('app', 100)->nullable();
                $table->timestamp('created_at');
                $table->timestamp('updated_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wealth_screenshots');
        Schema::dropIfExists('wealth_source_values');
        Schema::dropIfExists('wealth_sources');
        Schema::dropIfExists('wealth_source_categories');
    }
};
