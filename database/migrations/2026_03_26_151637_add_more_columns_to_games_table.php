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
        Schema::table('games', function (Blueprint $table) {
            $table->string('player_token')->nullable()->after('account');
            $table->unsignedTinyInteger('max_flips')->default(5)->after('segment');
            $table->unsignedTinyInteger('flips_count')->default(0)->after('max_flips');
            $table->unsignedTinyInteger('winning_flip')->default(3)->after('flips_count');
            $table->json('reveal_plan')->nullable()->after('winning_flip');
            $table->foreignId('winning_prize_id')->nullable()->after('prize_id')->constrained('prizes');
            $table->timestamp('won_at')->nullable()->after('finished_at');
            $table->string('result')->nullable()->after('won_at'); // won|lost|blocked

            $table->index(['campaign_id', 'account', 'segment', 'finished_at']);
            $table->index(['player_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['player_token', 'max_flips', 'flips_count', 'winning_flip', 'winning_prize_id', 'won_at', 'result']);
            $table->dropIndex(['campaign_id', 'account', 'segment', 'finished_at']);
            $table->dropColumn('reveal_plan');
            $table->dropIndex(['player_token']);
        });
    }
};
