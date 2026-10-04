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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();

            // Identity: duplicates are cut only by source + external id
            $table->string('source', 16);
            $table->string('external_id', 64);
            $table->unique(['source', 'external_id']);

            $table->text('url');
            $table->text('title')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('raw');

            // Structured fields as the source gives them; null means unknown
            $table->integer('price')->nullable();
            $table->string('currency', 3)->nullable();
            $table->unsignedTinyInteger('rooms')->nullable();
            $table->smallInteger('floor')->nullable();
            $table->unsignedSmallInteger('floors_total')->nullable();
            $table->decimal('area', 7, 2)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->timestampTz('published_at')->nullable();

            // Pipeline journal
            $table->string('decision', 16)->index();
            $table->string('rejected_by', 32)->nullable();
            $table->text('reject_reason')->nullable();

            // LLM output (ListingEnricher)
            $table->jsonb('enrichment')->nullable();
            $table->timestampTz('enriched_at')->nullable();

            $table->timestampsTz();
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
