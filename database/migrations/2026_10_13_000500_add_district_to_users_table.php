<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Malawi district slug (App\Platform\Districts); optional, drives district leaderboards.
        Schema::table('users', function (Blueprint $table) {
            $table->string('district', 32)->nullable()->after('avatar')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['district']);
            $table->dropColumn('district');
        });
    }
};
