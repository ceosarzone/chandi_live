<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->index(['dealer_profile_id', 'status', 'deleted_at', 'id'], 'public_entitlement_index'));
        Schema::table('subscriptions', fn (Blueprint $t) => $t->index(['dealer_profile_id', 'status', 'expires_at'], 'current_entitlement_index'));
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropIndex('public_entitlement_index'));
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropIndex('current_entitlement_index'));
    }
};
