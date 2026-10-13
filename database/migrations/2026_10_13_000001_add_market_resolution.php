<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_price_snapshots', fn (Blueprint $t) => $t->string('observation_resolution')->default('timestamp')->index());
    }

    public function down(): void
    {
        Schema::table('market_price_snapshots', fn (Blueprint $t) => $t->dropColumn('observation_resolution'));
    }
};
