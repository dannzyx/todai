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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('source')->default('fireflies');

            // The owning user, when the delivery could be attributed to one.
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('outcome');
            $table->string('event_type')->nullable();
            $table->string('fireflies_meeting_id')->nullable();
            $table->boolean('signed')->default(false);
            $table->string('ip')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['source', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
