<?php

namespace App\Jobs;

use App\Mail\OrderInvoiceMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOrderInvoiceMail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 180];

    public function __construct(public int $orderId) {}

    public function uniqueId(): string
    {
        return 'order-invoice-'.$this->orderId;
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            return;
        }

        $meta = $order->payment_meta ?? [];
        if (! empty($meta['invoice_emailed_at'])) {
            return;
        }

        $billingEmail = $order->billing_address['email'] ?? null;
        if (! $billingEmail) {
            $billingEmail = $order->user?->email;
        }

        if (! $billingEmail) {
            return;
        }

        Mail::to($billingEmail)->send(new OrderInvoiceMail($order->fresh()));

        $order->refresh();
        $latestMeta = $order->payment_meta ?? [];
        $order->update([
            'payment_meta' => array_merge($latestMeta, [
                'invoice_queued_at' => $meta['invoice_queued_at'] ?? now()->toDateTimeString(),
                'invoice_emailed_at' => now()->toDateTimeString(),
            ]),
        ]);
    }
}
