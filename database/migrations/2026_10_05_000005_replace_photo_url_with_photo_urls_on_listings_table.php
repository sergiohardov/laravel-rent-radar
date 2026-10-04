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
        Schema::table('listings', function (Blueprint $table) {
            // Up to 10 photos, the first is the main one: a Telegram album holds 10
            $table->jsonb('photo_urls')->nullable()->after('url');
        });

        DB::statement('UPDATE listings SET photo_urls = jsonb_build_array(photo_url) WHERE photo_url IS NOT NULL');

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('photo_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->text('photo_url')->nullable()->after('url');
        });

        DB::statement('UPDATE listings SET photo_url = photo_urls->>0 WHERE photo_urls IS NOT NULL');

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('photo_urls');
        });
    }
};
