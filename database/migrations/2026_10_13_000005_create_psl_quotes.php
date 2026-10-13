<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psl_quotes', function (Blueprint $t) {
            $t->id();
            $t->string('source')->default('GoldToday');
            $t->decimal('price', 24, 12);
            $t->char('currency', 3)->default('PKR');
            $t->string('unit_status')->default('unconfirmed');
            $t->dateTime('observed_at');
            $t->dateTime('fetched_at');
            $t->timestamps();
            $t->unique(['source', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psl_quotes');
    }
};
