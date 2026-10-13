<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform catalogue: Meeple Mania games, their modes, seasons and settings.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('game_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            // classic | timed | once
            $table->string('slug', 32);
            $table->boolean('is_ranked')->default(true);
            $table->boolean('requires_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['game_id', 'slug']);
        });

        // Seasons are platform-wide: every game's ranked play counts toward them.
        Schema::create('game_seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });

        // scope = "platform" or a game slug.
        Schema::create('game_settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 64);
            $table->string('key', 64);
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scope', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_settings');
        Schema::dropIfExists('game_seasons');
        Schema::dropIfExists('game_modes');
        Schema::dropIfExists('games');
    }
};
