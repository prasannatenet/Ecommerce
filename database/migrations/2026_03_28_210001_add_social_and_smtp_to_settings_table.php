<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('settings', 'facebook_url')) {
                $table->string('facebook_url')->nullable()->after('zip');
            }
            if (! Schema::hasColumn('settings', 'instagram_url')) {
                $table->string('instagram_url')->nullable()->after('facebook_url');
            }
            if (! Schema::hasColumn('settings', 'twitter_url')) {
                $table->string('twitter_url')->nullable()->after('instagram_url');
            }
            if (! Schema::hasColumn('settings', 'youtube_url')) {
                $table->string('youtube_url')->nullable()->after('twitter_url');
            }
            if (! Schema::hasColumn('settings', 'linkedin_url')) {
                $table->string('linkedin_url')->nullable()->after('youtube_url');
            }

            if (! Schema::hasColumn('settings', 'smtp_host')) {
                $table->string('smtp_host')->nullable()->after('linkedin_url');
            }
            if (! Schema::hasColumn('settings', 'smtp_port')) {
                $table->unsignedSmallInteger('smtp_port')->nullable()->after('smtp_host');
            }
            if (! Schema::hasColumn('settings', 'smtp_username')) {
                $table->string('smtp_username')->nullable()->after('smtp_port');
            }
            if (! Schema::hasColumn('settings', 'smtp_password')) {
                $table->text('smtp_password')->nullable()->after('smtp_username');
            }
            if (! Schema::hasColumn('settings', 'smtp_encryption')) {
                $table->string('smtp_encryption', 10)->nullable()->after('smtp_password');
            }
            if (! Schema::hasColumn('settings', 'smtp_from_email')) {
                $table->string('smtp_from_email')->nullable()->after('smtp_encryption');
            }
            if (! Schema::hasColumn('settings', 'smtp_from_name')) {
                $table->string('smtp_from_name')->nullable()->after('smtp_from_email');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table): void {
            $dropColumns = [
                'facebook_url',
                'instagram_url',
                'twitter_url',
                'youtube_url',
                'linkedin_url',
                'smtp_host',
                'smtp_port',
                'smtp_username',
                'smtp_password',
                'smtp_encryption',
                'smtp_from_email',
                'smtp_from_name',
            ];

            foreach ($dropColumns as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
