<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fx_rate_snapshots', function (Blueprint $table) {
            $table->string('source')->nullable();
        });
        Schema::table('market_price_snapshots', function (Blueprint $table) {
            $table->foreignId('fx_inr_snapshot_id')->nullable()->constrained('fx_rate_snapshots');
        });
        DB::table('market_data_providers')->insertOrIgnore([
            'slug' => 'gold_api', 'name' => 'Gold API', 'enabled' => false,
            'monthly_quota' => 100000, 'refresh_minutes' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('market_price_snapshots', fn (Blueprint $table) => $table->dropConstrainedForeignId('fx_inr_snapshot_id'));
        Schema::table('fx_rate_snapshots', fn (Blueprint $table) => $table->dropColumn('source'));
    }
};
