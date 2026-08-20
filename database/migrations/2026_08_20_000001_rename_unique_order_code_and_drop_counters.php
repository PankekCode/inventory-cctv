<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename `unique_order_code` → `order_code` on the orders table.
     * Add a composite index for efficient guest order tracking lookups.
     * Drop the now-obsolete `order_counters` table.
     *
     * Decision 14.5: Order codes are no longer sequential counters.
     * The new format is HBL-YYMMDD-XXXXXX (cryptographically secure random suffix).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('unique_order_code', 'order_code');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Composite index for guest tracking: find order by code and verify
            // the guest_phone_e164 matches — avoids enumeration via code alone.
            $table->index(['order_code', 'guest_phone_e164'], 'idx_orders_guest_tracking');
        });

        Schema::dropIfExists('order_counters');
    }

    public function down(): void
    {
        // Recreate the counters table so the old OrderCodeService can function
        // after a rollback.
        Schema::create('order_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_guest_tracking');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('order_code', 'unique_order_code');
        });
    }
};
