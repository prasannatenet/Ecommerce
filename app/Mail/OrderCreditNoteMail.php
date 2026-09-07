<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderRefund;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderCreditNoteMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order, public OrderRefund $refund)
    {
    }

    public function build(): self
    {
        $pdf = Pdf::loadView('backend.orders.pdf.credit_note', [
            'order' => $this->order,
            'refund' => $this->refund,
        ]);

        return $this->subject('Credit Note for Order #' . $this->order->id)
            ->view('emails.orders.credit_note')
            ->attachData(
                $pdf->output(),
                'credit-note-order-' . $this->order->id . '-refund-' . $this->refund->id . '.pdf',
                ['mime' => 'application/pdf']
            );
    }
}
