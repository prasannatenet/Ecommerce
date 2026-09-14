<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipment_tracking_events')) {
            return;
        }

        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('provider_event_id')->nullable();
            $table->string('status_code');
            $table->string('status_label')->nullable();
            $table->string('location')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'scanned_at']);
            $table->unique(['shipment_id', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');
    }
};
