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
        // Run state of each listing source; rows appear on first run
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 16)->unique();
            $table->boolean('enabled')->default(true);

            // The next run is picked at random inside this window (jitter)
            $table->unsignedSmallInteger('interval_min_minutes')->default(5);
            $table->unsignedSmallInteger('interval_max_minutes')->default(10);
            $table->timestampTz('next_run_at')->nullable();

            $table->timestampTz('last_run_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->jsonb('last_report')->nullable();

            $table->text('last_error')->nullable();
            $table->timestampTz('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);

            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
