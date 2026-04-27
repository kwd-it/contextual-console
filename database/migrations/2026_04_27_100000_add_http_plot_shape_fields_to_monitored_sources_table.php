<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_sources', function (Blueprint $table) {
            $table->string('http_json_items_key')->nullable()->after('auth_token_env_key');
            $table->string('http_plot_payload_adapter')->nullable()->after('http_json_items_key');
        });
    }

    public function down(): void
    {
        Schema::table('monitored_sources', function (Blueprint $table) {
            $table->dropColumn([
                'http_json_items_key',
                'http_plot_payload_adapter',
            ]);
        });
    }
};
