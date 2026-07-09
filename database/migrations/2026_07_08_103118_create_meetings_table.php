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
        Schema::create('meetings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('fireflies');

            // Fireflies meetings carry an external id; manual meetings do not.
            $table->string('fireflies_meeting_id')->nullable()->unique();

            $table->string('title')->nullable();
            $table->timestamp('meeting_date')->nullable();

            // Content: manual notes plus everything Fireflies returns.
            $table->text('notes')->nullable();
            $table->text('summary')->nullable();
            $table->text('action_items')->nullable();
            $table->longText('transcript')->nullable();

            // The resolved project, set once the project suggestion is accepted.
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();

            // AI project suggestion: an existing project or a proposed new name.
            $table->foreignUlid('suggested_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('suggested_project_name')->nullable();
            $table->string('suggestion_confidence')->nullable();
            $table->text('suggestion_reasoning')->nullable();

            $table->string('status')->default('draft');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
