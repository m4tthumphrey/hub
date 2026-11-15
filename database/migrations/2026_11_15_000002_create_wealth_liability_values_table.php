<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The balance log: one row per liability per date. Rows are written automatically
 * from the projection on each payment date, and can be corrected by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wealth_liability_values', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('liability_id');
            $table->date('date');
            $table->decimal('balance', 12, 2);
            $table->decimal('projected_balance', 12, 2)->nullable(); // what the projection said for this date
            $table->boolean('is_manual')->default(false);            // entered or corrected by hand
            $table->timestamps();

            $table->unique(['liability_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wealth_liability_values');
    }
};
