<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_sources', function (Blueprint $table) {
            $table->string('http_pagination_mode')->nullable()->after('http_plot_payload_adapter');
            $table->string('http_page_param')->nullable()->after('http_pagination_mode');
            $table->string('http_per_page_param')->nullable()->after('http_page_param');
            $table->unsignedInteger('http_per_page')->nullable()->after('http_per_page_param');
            $table->unsignedInteger('http_max_pages')->nullable()->after('http_per_page');
        });
    }

    public function down(): void
    {
        Schema::table('monitored_sources', function (Blueprint $table) {
            $table->dropColumn([
                'http_pagination_mode',
                'http_page_param',
                'http_per_page_param',
                'http_per_page',
                'http_max_pages',
            ]);
        });
    }
};
