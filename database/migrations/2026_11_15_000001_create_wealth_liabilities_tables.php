<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wealth_liabilities', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type', 20); // mortgage | loan
            $table->string('description', 100);
            $table->unsignedInteger('property_source_id')->nullable();
            $table->timestamps();
        });

        // One row per save. Rows are never updated: each holds everything that was
        // entered and everything that was calculated from it at that moment.
        Schema::create('wealth_liability_records', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('liability_id')->index();

            // Entered
            $table->date('effective_date');
            $table->decimal('balance', 12, 2);
            $table->decimal('interest_rate', 6, 3);
            $table->unsignedSmallInteger('term_months');
            $table->date('fix_end_date')->nullable();
            $table->decimal('follow_on_rate', 6, 3)->nullable();
            $table->decimal('regular_overpayment', 10, 2)->default(0);
            $table->decimal('actual_payment', 10, 2)->nullable();
            $table->json('one_offs'); // the one-off overpayments as they stood at save time

            // Calculated at save time
            $table->decimal('monthly_payment', 10, 2);
            $table->decimal('calculated_payment', 10, 2);
            $table->date('payoff_date')->nullable();
            $table->date('contractual_end_date')->nullable();
            $table->decimal('interest_remaining', 12, 2);
            $table->decimal('interest_saved', 12, 2)->nullable();
            $table->decimal('balance_at_fix_end', 12, 2)->nullable();
            $table->decimal('projected_balance', 12, 2)->nullable(); // what the previous record projected for this date

            $table->text('change_note')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('wealth_liability_overpayments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('liability_id')->index();
            $table->date('paid_on');
            $table->decimal('amount', 10, 2);
            $table->string('note', 100)->nullable();
            $table->timestamps();
        });

        $house = DB::table('wealth_sources')
            ->join('wealth_source_categories', 'wealth_source_categories.id', '=', 'wealth_sources.category_id')
            ->where('wealth_source_categories.is_liquid', false)
            ->orderBy('wealth_sources.id')
            ->value('wealth_sources.id');

        DB::table('wealth_liabilities')->insert([
            'type'               => 'mortgage',
            'description'        => 'Mortgage',
            'property_source_id' => $house,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('wealth_liability_overpayments');
        Schema::dropIfExists('wealth_liability_records');
        Schema::dropIfExists('wealth_liabilities');
    }
};
