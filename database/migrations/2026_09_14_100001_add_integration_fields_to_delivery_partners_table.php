<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_partners', 'driver')) {
                $table->string('driver')->default('manual')->after('code');
            }
            if (! Schema::hasColumn('delivery_partners', 'api_key')) {
                $table->text('api_key')->nullable()->after('contact_phone');
            }
            if (! Schema::hasColumn('delivery_partners', 'client_id')) {
                $table->string('client_id')->nullable()->after('api_key');
            }
            if (! Schema::hasColumn('delivery_partners', 'webhook_secret')) {
                $table->text('webhook_secret')->nullable()->after('client_id');
            }
            if (! Schema::hasColumn('delivery_partners', 'base_url')) {
                $table->string('base_url', 500)->nullable()->after('webhook_secret');
            }
            if (! Schema::hasColumn('delivery_partners', 'is_sandbox')) {
                $table->boolean('is_sandbox')->default(true)->after('base_url');
            }
            if (! Schema::hasColumn('delivery_partners', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_sandbox');
            }
            if (! Schema::hasColumn('delivery_partners', 'auto_book_on')) {
                $table->string('auto_book_on')->default('manual')->after('is_default');
            }
            if (! Schema::hasColumn('delivery_partners', 'auto_update_order_status')) {
                $table->boolean('auto_update_order_status')->default(true)->after('auto_book_on');
            }
            if (! Schema::hasColumn('delivery_partners', 'tracking_url_template')) {
                $table->string('tracking_url_template', 500)->nullable()->after('auto_update_order_status');
            }
            if (! Schema::hasColumn('delivery_partners', 'status_map')) {
                $table->json('status_map')->nullable()->after('tracking_url_template');
            }
            if (! Schema::hasColumn('delivery_partners', 'config')) {
                $table->json('config')->nullable()->after('status_map');
            }
            if (! Schema::hasColumn('delivery_partners', 'last_connection_test_at')) {
                $table->timestamp('last_connection_test_at')->nullable()->after('config');
            }
            if (! Schema::hasColumn('delivery_partners', 'last_connection_test_ok')) {
                $table->boolean('last_connection_test_ok')->nullable()->after('last_connection_test_at');
            }
            if (! Schema::hasColumn('delivery_partners', 'last_error')) {
                $table->text('last_error')->nullable()->after('last_connection_test_ok');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->dropColumn([
                'driver', 'api_key', 'client_id', 'webhook_secret', 'base_url',
                'is_sandbox', 'is_default', 'auto_book_on', 'auto_update_order_status',
                'tracking_url_template', 'status_map', 'config',
                'last_connection_test_at', 'last_connection_test_ok', 'last_error',
            ]);
        });
    }
};
