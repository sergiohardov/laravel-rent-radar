<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Price converted at the NBU rate; null when the price or the rate is unknown
            $table->integer('price_uah')->nullable()->after('currency');

            // Marks for the card and the journal, e.g. unknown_rooms (❓)
            $table->jsonb('flags')->default('[]')->after('reject_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['price_uah', 'flags']);
        });
    }
};
