<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dataset_comparison_runs', function (Blueprint $table) {
            $table->dropForeign(['current_snapshot_id']);
        });

        Schema::table('dataset_comparison_runs', function (Blueprint $table) {
            $table->foreignId('current_snapshot_id')->nullable()->change();
        });

        Schema::table('dataset_comparison_runs', function (Blueprint $table) {
            $table
                ->foreign('current_snapshot_id')
                ->references('id')
                ->on('dataset_snapshots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dataset_comparison_runs', function (Blueprint $table) {
            $table->dropForeign(['current_snapshot_id']);
        });

        Schema::table('dataset_comparison_runs', function (Blueprint $table) {
            $table->foreignId('current_snapshot_id')->nullable(false)->change();
        });

        Schema::table('dataset_comparison_runs', function (Blueprint $table) {
            $table
                ->foreign('current_snapshot_id')
                ->references('id')
                ->on('dataset_snapshots')
                ->cascadeOnDelete();
        });
    }
};
