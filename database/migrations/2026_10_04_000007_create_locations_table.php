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
        // Sector whitelist: streets, complexes and districts with their aliases
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            $table->string('name');
            // Russian/Ukrainian, old/new names, abbreviations
            $table->jsonb('aliases')->default('[]');
            $table->timestampsTz();

            $table->unique(['kind', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
