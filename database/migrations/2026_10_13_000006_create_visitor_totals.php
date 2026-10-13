<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_totals', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('total')->default(0);
        });

        // Carry forward genuine recorded visits, never a fabricated opening count.
        DB::table('visitor_totals')->insert([
            'key' => 'platform_visit',
            'total' => DB::table('analytics_events')->where('event', 'platform_visit')->count(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_totals');
    }
};
