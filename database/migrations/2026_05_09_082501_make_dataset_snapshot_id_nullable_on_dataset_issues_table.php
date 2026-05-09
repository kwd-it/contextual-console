<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dataset_issues', function (Blueprint $table) {
            $table->dropForeign(['dataset_snapshot_id']);
        });

        Schema::table('dataset_issues', function (Blueprint $table) {
            $table->foreignId('dataset_snapshot_id')->nullable()->change();
        });

        Schema::table('dataset_issues', function (Blueprint $table) {
            $table
                ->foreign('dataset_snapshot_id')
                ->references('id')
                ->on('dataset_snapshots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dataset_issues', function (Blueprint $table) {
            $table->dropForeign(['dataset_snapshot_id']);
        });

        Schema::table('dataset_issues', function (Blueprint $table) {
            $table->foreignId('dataset_snapshot_id')->nullable(false)->change();
        });

        Schema::table('dataset_issues', function (Blueprint $table) {
            $table
                ->foreign('dataset_snapshot_id')
                ->references('id')
                ->on('dataset_snapshots')
                ->cascadeOnDelete();
        });
    }
};
