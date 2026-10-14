<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A paused Timed Rush run: its deadline is pushed back by the paused time on resume. */
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn('paused_at');
        });
    }
};
