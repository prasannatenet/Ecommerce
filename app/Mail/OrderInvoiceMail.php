<?php

namespace App\Mail;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderInvoiceMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function build(): self
    {
        $pdf = Pdf::loadView('backend.orders.pdf.invoice', [
            'order' => $this->order,
        ]);

        return $this->subject('Invoice for Order #' . $this->order->id)
            ->view('emails.orders.invoice')
            ->attachData(
                $pdf->output(),
                'invoice-order-' . $this->order->id . '.pdf',
                ['mime' => 'application/pdf']
            );
    }
}
