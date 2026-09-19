<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReturnRequestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ReturnRequest $returnRequest,
        public Order $order,
        public ?User $user = null,
    ) {
    }

    public function build(): self
    {
        return $this->subject('Return Request #' . $this->returnRequest->id . ' submitted for Order #' . $this->order->id)
            ->view('emails.orders.return_request', [
                'returnRequest' => $this->returnRequest,
                'order' => $this->order,
                'user' => $this->user ?? $this->order->user,
            ]);
    }
}
