<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_logs', function (Blueprint $table) {
            $table->foreignId('dataset_comparison_run_id')
                ->nullable()
                ->after('entity_id')
                ->constrained('dataset_comparison_runs')
                ->nullOnDelete();

            $table->index('dataset_comparison_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('change_logs', function (Blueprint $table) {
            $table->dropForeign(['dataset_comparison_run_id']);
            $table->dropIndex(['dataset_comparison_run_id']);
            $table->dropColumn('dataset_comparison_run_id');
        });
    }
};
