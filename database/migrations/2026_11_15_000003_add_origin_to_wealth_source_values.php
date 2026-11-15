<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records where each logged value came from: a parsed screenshot or typed in by hand.
 * Values logged before this stay null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wealth_source_values', function (Blueprint $table) {
            $table->string('origin', 20)->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('wealth_source_values', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
