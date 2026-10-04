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
            // Up to RawListing::MAX_PHOTOS photos for the Telegram album, the main one first
            $table->json('photo_urls')->nullable();
            $table->text('title')->nullable();
            $table->text('description')->nullable();
            // The source's own address fields in one line: street and house, complex, area, district
            $table->text('address')->nullable();
            $table->json('raw');
            // What the site's own fields say about power outages, keyed like the
            // enrichment (generator, generator_water…); used where the text is silent
            $table->json('source_hints')->default('{}');

            // Structured fields as the source gives them; null means unknown
            $table->integer('price')->nullable();
            $table->string('currency', 3)->nullable();
            // Price converted at the NBU rate; null when the price or the rate is unknown
            $table->integer('price_uah')->nullable();
            $table->unsignedTinyInteger('rooms')->nullable();
            $table->smallInteger('floor')->nullable();
            $table->unsignedSmallInteger('floors_total')->nullable();
            $table->decimal('area', 7, 2)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->dateTime('published_at')->nullable();

            // Pipeline journal
            $table->string('decision', 16)->index();
            $table->string('rejected_by', 32)->nullable();
            $table->text('reject_reason')->nullable();
            // Marks for the card and the journal, e.g. unknown_rooms (❓)
            $table->json('flags')->default('[]');

            // LLM output (ListingEnricher)
            $table->json('enrichment')->nullable();
            $table->dateTime('enriched_at')->nullable();

            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->dateTime('notified_at')->nullable();

            $table->datetimes();
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
