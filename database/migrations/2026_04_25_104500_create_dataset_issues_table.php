<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_issues', function (Blueprint $table) {
            $table->id();

            $table->foreignId('monitored_source_id')->constrained('monitored_sources')->cascadeOnDelete();
            $table->foreignId('dataset_snapshot_id')->constrained('dataset_snapshots')->cascadeOnDelete();
            $table->foreignId('dataset_comparison_run_id')->constrained('dataset_comparison_runs')->cascadeOnDelete();

            $table->string('entity_type')->nullable(); // e.g. plot
            $table->string('entity_id')->nullable(); // canonical id if present
            $table->string('field')->nullable(); // e.g. price, status

            $table->string('issue_type'); // e.g. missing_field, invalid_value, duplicate_id
            $table->string('severity'); // error | warning | info
            $table->text('message');
            $table->json('context')->nullable();

            $table->timestamps();

            $table->index(['monitored_source_id', 'created_at']);
            $table->index(['dataset_snapshot_id']);
            $table->index(['dataset_comparison_run_id']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['severity', 'issue_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_issues');
    }
};
