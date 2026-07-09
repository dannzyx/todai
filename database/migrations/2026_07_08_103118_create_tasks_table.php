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
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('source')->default('manual');
            $table->foreignUlid('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();

            // AI project suggestion, shown in UI until accepted or dismissed.
            $table->foreignUlid('suggested_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('suggestion_confidence')->nullable();
            $table->text('suggestion_reasoning')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'project_id']);
            $table->index(['user_id', 'due_date']);
            $table->index(['user_id', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
