<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One Try Once challenge per game per calendar day.
        Schema::create('daily_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->date('challenge_date');
            // Secret, server-only seed (encrypted). Identical for all players that day.
            $table->text('seed');
            $table->unsignedSmallInteger('starting_level');
            $table->unsignedSmallInteger('max_levels');
            $table->string('title')->nullable();
            $table->timestamp('rewards_granted_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'challenge_date']);
        });

        /*
         * A session is the live state of a run and, once finalized, the immutable
         * result record used by statistics and leaderboards.
         */
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('game_id')->constrained();
            $table->foreignId('game_mode_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_token', 64)->nullable()->index();
            $table->foreignId('daily_challenge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_season_id')->nullable()->constrained()->nullOnDelete();
            $table->text('seed');
            $table->json('rules');
            // Timed Rush only: 30, 45 or 60.
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->boolean('is_ranked')->default(false);

            // active | completed | failed | abandoned | expired | voided
            $table->string('status', 16)->default('active');
            $table->string('end_reason', 32)->nullable();

            // Progress pointer: next expected round is level/attempt (level modes) or rounds_played + 1 (timed).
            $table->unsignedSmallInteger('level');
            $table->unsignedSmallInteger('attempt')->default(0);
            $table->unsignedTinyInteger('lives')->nullable();
            // Highest level (level modes) or round number (timed) whose specs were sent to the client.
            $table->unsignedSmallInteger('revealed')->default(0);

            $table->unsignedSmallInteger('highest_level')->default(0);
            $table->unsignedSmallInteger('rounds_played')->default(0);
            $table->unsignedSmallInteger('rounds_cleared')->default(0);
            $table->unsignedInteger('correct_inputs')->default(0);
            $table->unsignedInteger('incorrect_inputs')->default(0);
            $table->unsignedInteger('expected_inputs')->default(0);
            $table->decimal('accuracy', 5, 2)->default(0);
            $table->unsignedSmallInteger('success_streak')->default(0);
            $table->unsignedSmallInteger('best_success_streak')->default(0);
            $table->unsignedBigInteger('total_response_ms')->default(0);
            $table->unsignedInteger('cleared_units')->default(0);
            $table->unsignedInteger('avg_unit_ms')->nullable();
            // Sum of play time the client reports (preview + answering), checked against the server clock.
            $table->unsignedBigInteger('claimed_ms')->default(0);
            $table->unsignedInteger('base_score')->default(0);
            $table->unsignedInteger('bonus_score')->default(0);
            $table->unsignedInteger('penalty_score')->default(0);
            $table->unsignedInteger('final_score')->default(0);
            $table->json('outcome')->nullable();

            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'last_activity_at']);
            $table->index(['game_id', 'is_ranked', 'status', 'ended_at'], 'game_sessions_ranked_lookup');
            $table->index(['is_flagged', 'reviewed_at']);
        });

        Schema::create('game_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number');
            // "L{level}A{attempt}" in level modes, "N{n}" in Timed Rush.
            $table->string('key', 16);
            $table->unsignedSmallInteger('level');
            $table->unsignedSmallInteger('units');
            // The round as regenerated by the server from the session seed.
            $table->json('spec');
            // cleared | failed | expired | invalid
            $table->string('status', 16);
            $table->string('fail_reason', 32)->nullable();
            $table->json('inputs');
            $table->json('input_timings');
            $table->unsignedSmallInteger('correct_inputs')->default(0);
            $table->unsignedSmallInteger('mistakes')->default(0);
            $table->unsignedInteger('preview_ms');
            $table->unsignedInteger('response_ms');
            $table->unsignedInteger('base_points')->default(0);
            $table->unsignedInteger('difficulty_bonus')->default(0);
            $table->unsignedInteger('speed_bonus')->default(0);
            $table->unsignedInteger('penalty')->default(0);
            $table->integer('points')->default(0); // Negative for a Timed Rush penalty.
            $table->timestamps();

            $table->unique(['game_session_id', 'round_number']);
            $table->unique(['game_session_id', 'key']);
        });

        // Exactly one ranked Try Once attempt per player per game per day.
        Schema::create('daily_challenge_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['daily_challenge_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_challenge_attempts');
        Schema::dropIfExists('game_rounds');
        Schema::dropIfExists('game_sessions');
        Schema::dropIfExists('daily_challenges');
    }
};
