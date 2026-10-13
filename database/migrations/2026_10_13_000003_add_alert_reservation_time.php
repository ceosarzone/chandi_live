<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_alerts', fn (Blueprint $t) => $t->dateTime('last_reserved_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('price_alerts', fn (Blueprint $t) => $t->dropColumn('last_reserved_at'));
    }
};
