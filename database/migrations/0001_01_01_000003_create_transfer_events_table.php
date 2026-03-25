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
        Schema::create('transfer_events', function (Blueprint $table) {
            $table->string('event_id', 64)->primary();
            $table->unsignedBigInteger('station_id')->index();
            $table->decimal('amount', 15, 2);
            $table->string('status', 100);
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_events');
    }
};

