<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A single row of search settings; defaults follow docs/REQUIREMENTS.md
        Schema::create('settings', function (Blueprint $table) {
            // Not auto-increment: MariaDB allows no CHECK on such a column, and there is one row
            $table->unsignedTinyInteger('id')->primary();

            // Sector point and radius, only for the OpenStreetMap import
            $table->decimal('center_lat', 9, 6)->nullable();
            $table->decimal('center_lng', 9, 6)->nullable();
            $table->unsignedInteger('radius_m')->nullable();

            // Hard filters
            $table->unsignedInteger('max_price_uah')->default(15000);
            $table->unsignedTinyInteger('rooms_min')->default(2);
            $table->unsignedTinyInteger('rooms_max')->default(2);
            $table->smallInteger('floor_min')->default(1);
            $table->smallInteger('floor_max')->default(6);

            // "Pass when unknown (❓)" switches
            $table->boolean('allow_unknown_price')->default(true);
            $table->boolean('allow_unknown_rooms')->default(true);
            $table->boolean('allow_unknown_floor')->default(true);
            $table->boolean('allow_unknown_generator')->default(true);

            // A euro two-room flat listed as one room counts as two rooms
            $table->boolean('allow_euro_two_room')->default(true);

            $table->datetimes();
        });

        DB::statement('ALTER TABLE settings ADD CONSTRAINT settings_single_row CHECK (id = 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
