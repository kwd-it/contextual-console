<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_comparison_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('monitored_sources')->cascadeOnDelete();
            $table->foreignId('current_snapshot_id')->constrained('dataset_snapshots')->cascadeOnDelete();
            $table->foreignId('previous_snapshot_id')->nullable()->constrained('dataset_snapshots')->nullOnDelete();

            $table->string('status'); // baseline | completed | failed
            $table->json('summary')->nullable(); // comparison output (added/removed/changed/unchanged + ids)

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'created_at']);
            $table->index(['source_id', 'previous_snapshot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_comparison_runs');
    }
};

