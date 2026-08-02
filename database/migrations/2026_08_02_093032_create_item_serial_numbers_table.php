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
        Schema::create('item_serial_numbers', function (Blueprint $table) {

            $table->id();


            $table->foreignId('item_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->string('serial_number')
                ->unique();


            $table->enum('status', [
                'AVAILABLE',
                'SOLD'
            ])
            ->default('AVAILABLE');


            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_serial_numbers');
    }
};
