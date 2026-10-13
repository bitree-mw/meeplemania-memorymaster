<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform-wide aggregates across every game, plus the daily activity streak.
        Schema::create('player_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('games_played')->default(0);
            $table->unsignedBigInteger('total_points')->default(0);
            $table->unsignedInteger('rounds_cleared')->default(0);
            $table->unsignedSmallInteger('current_daily_streak')->default(0);
            $table->unsignedSmallInteger('longest_daily_streak')->default(0);
            $table->date('last_active_on')->nullable();
            $table->timestamps();

            $table->index(['total_points']);
        });

        /*
         * Per-player, per-game aggregates and personal bests, derived from
         * finalized ranked sessions and rebuildable (StatisticsService::rebuild).
         */
        Schema::create('player_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('games_played')->default(0);
            $table->unsignedBigInteger('total_points')->default(0);
            $table->unsignedInteger('rounds_cleared')->default(0);
            $table->unsignedBigInteger('correct_inputs')->default(0);
            $table->unsignedBigInteger('expected_inputs')->default(0);
            $table->unsignedSmallInteger('best_session_streak')->default(0);

            $table->unsignedSmallInteger('best_level')->default(0);
            $table->timestamp('best_level_at')->nullable();
            $table->unsignedInteger('best_score')->default(0);
            $table->timestamp('best_score_at')->nullable();
            $table->unsignedInteger('best_avg_unit_ms')->nullable();
            $table->timestamp('best_avg_unit_at')->nullable();
            $table->unsignedInteger('best_timed_30')->default(0);
            $table->timestamp('best_timed_30_at')->nullable();
            $table->unsignedInteger('best_timed_45')->default(0);
            $table->timestamp('best_timed_45_at')->nullable();
            $table->unsignedInteger('best_timed_60')->default(0);
            $table->timestamp('best_timed_60_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'game_id']);
            $table->index(['game_id', 'best_level', 'best_level_at'], 'ps_level_idx');
            $table->index(['game_id', 'best_avg_unit_ms', 'best_avg_unit_at'], 'ps_fastest_idx');
            $table->index(['game_id', 'best_timed_30'], 'ps_timed30_idx');
            $table->index(['game_id', 'best_timed_45'], 'ps_timed45_idx');
            $table->index(['game_id', 'best_timed_60'], 'ps_timed60_idx');
            $table->index(['game_id', 'total_points'], 'ps_points_idx');
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('description');
            $table->string('icon', 32);
            $table->string('criteria', 48);
            $table->unsignedInteger('threshold')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('player_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_session_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('unlocked_at');
            $table->timestamps();

            $table->unique(['user_id', 'achievement_id']);
        });

        // Daily rank snapshots power movement arrows. board = "<game|all>:<category>[:<duration>]".
        Schema::create('leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('board', 64);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rank');
            $table->date('snapshot_on');
            $table->timestamps();

            $table->unique(['board', 'user_id', 'snapshot_on']);
            $table->index(['board', 'snapshot_on']);
        });

        // One row per visitor key ("u:{id}" or "g:{hash}") so duplicate tabs count once.
        Schema::create('online_presence', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_key', 80)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_playing')->default(false);
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_presence');
        Schema::dropIfExists('leaderboard_snapshots');
        Schema::dropIfExists('player_achievements');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('player_statistics');
        Schema::dropIfExists('player_profiles');
    }
};
