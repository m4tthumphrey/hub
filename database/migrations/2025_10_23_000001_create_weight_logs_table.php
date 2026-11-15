<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline for weight_logs, which was originally created by hand.
 * Skipped where the table already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('weight_logs')) {
            return;
        }

        Schema::create('weight_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('weight_grams');
            $table->decimal('weight_kilograms', 10, 2);
            $table->decimal('weight_pounds', 10, 2);
            $table->decimal('weight_stone', 10, 2);
            $table->dateTime('date')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_logs');
    }
};
