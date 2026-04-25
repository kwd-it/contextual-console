<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_sources', function (Blueprint $table) {
            $table->string('endpoint_url')->nullable()->after('name');
            $table->string('auth_header_name')->nullable()->after('endpoint_url');
            $table->string('auth_token_env_key')->nullable()->after('auth_header_name');
        });
    }

    public function down(): void
    {
        Schema::table('monitored_sources', function (Blueprint $table) {
            $table->dropColumn([
                'endpoint_url',
                'auth_header_name',
                'auth_token_env_key',
            ]);
        });
    }
};
