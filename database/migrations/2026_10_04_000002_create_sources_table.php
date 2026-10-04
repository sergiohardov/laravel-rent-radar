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
            // LUN and OLX block the hosting IP; in production they go through SOURCES_PROXY_URL
            $table->boolean('use_proxy')->default(false);

            // The next run is picked at random inside this window (jitter)
            $table->unsignedSmallInteger('interval_min_minutes')->default(5);
            $table->unsignedSmallInteger('interval_max_minutes')->default(10);
            $table->dateTime('next_run_at')->nullable();

            $table->dateTime('last_run_at')->nullable();
            $table->dateTime('last_success_at')->nullable();
            $table->json('last_report')->nullable();

            $table->text('last_error')->nullable();
            $table->dateTime('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            // The group heard about this source bringing nothing new; one alert per silence
            $table->dateTime('silence_alerted_at')->nullable();

            $table->datetimes();
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
