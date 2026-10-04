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
        Schema::table('settings', function (Blueprint $table) {
            // Cards and alerts come without sound in this window, Kyiv time; it may cross midnight
            $table->boolean('quiet_hours_enabled')->default(false)->after('allow_euro_two_room');
            $table->time('quiet_from')->default('23:00:00')->after('quiet_hours_enabled');
            $table->time('quiet_to')->default('08:00:00')->after('quiet_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['quiet_hours_enabled', 'quiet_from', 'quiet_to']);
        });
    }
};
