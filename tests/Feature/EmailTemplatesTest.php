<?php

use App\Mail\OrderCreditNoteMail;
use App\Mail\OrderInvoiceMail;
use App\Mail\ReturnRequestMail;
use App\Mail\ShipmentStatusMail;
use App\Mail\WelcomeUserMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Models\User;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;

/*
|--------------------------------------------------------------------------
| Transactional email templates
|--------------------------------------------------------------------------
|
| Every mail: view extends emails/layout, which supplies the branded header,
| the label/value summary card, the CTA button and the footer. These tests
| render each mailable so a broken template or a missing field fails loudly
| instead of silently sending a plain-text-looking email.
|
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // ReturnRequest::payout_details is an "encrypted:array" cast, so the test
    // process needs a usable encrypter even without an APP_KEY in phpunit.xml.
    $key = '0123456789abcdef0123456789abcdef';
    Config::set('app.key', $key);
    Config::set('app.cipher', 'AES-256-CBC');
    app()->forgetInstance('encrypter');
    $encrypter = new Encrypter($key, 'AES-256-CBC');
    app()->instance('encrypter', $encrypter);
    app()->instance(EncrypterContract::class, $encrypter);
});

function emailTemplateStore(): void
{
    $setting = Setting::create([
        'site_name' => 'Gehna Jewels',
        'email' => 'care@gehna.test',
        'phone' => '+91 90000 00000',
        'address' => '12 MG Road',
        'city' => 'Jaipur',
        'state' => 'Rajasthan',
        'zip' => '302001',
        'country' => 'India',
    ]);

    // AppServiceProvider shares appSetting at boot, before the row existed.
    view()->share('appSetting', $setting);
}

function emailTemplateOrder(): Order
{
    $user = User::factory()->create(['name' => 'Riya Sharma']);

    $order = Order::create([
        'user_id' => $user->id,
        'status' => 'processing',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'total' => 1189.00,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_name' => 'Cotton Saree',
        'sku' => 'CS-001',
        'unit_price' => 594.50,
        'quantity' => 2,
        'line_total' => 1189.00,
    ]);

    return $order;
}

it('renders the branded invoice email with the order summary', function () {
    emailTemplateStore();
    $order = emailTemplateOrder();

    $html = (new OrderInvoiceMail($order->fresh()))->render();

    expect($html)->toContain('Gehna Jewels')                       // brand header
        ->toContain('INV-'.$order->id)
        ->toContain('Riya Sharma')
        ->toContain('Cotton Saree')                                // item table
        ->toContain('SKU: CS-001')
        ->toContain('Rs 1,189.00')                                 // totals
        ->toContain('care@gehna.test')                             // footer contact
        ->toContain('12 MG Road, Jaipur, Rajasthan, 302001, India') // footer address
        ->toContain('background-color:#0F3D32')                    // brand bar
        ->toContain('role="presentation"')                         // table-based layout
        ->toContain('Invoice summary')                             // summary card title
        ->toContain('Continue in one tap')                         // CTA panel
        ->toContain('background-color:#0C2E26')                    // footer band
        ->toContain('View your order');                            // CTA button
});

it('renders the credit note email with the refund summary', function () {
    emailTemplateStore();
    $order = emailTemplateOrder();

    $refund = OrderRefund::create([
        'order_id' => $order->id,
        'amount' => 500.00,
        'status' => 'processed',
        'reason' => 'Item returned in original condition',
        'refund_method' => 'bank_transfer',
    ]);

    $html = (new OrderCreditNoteMail($order->fresh(), $refund))->render();

    expect($html)->toContain('Gehna Jewels')
        ->toContain('Your credit note is attached')
        ->toContain('Rs 500.00')
        ->toContain('PROCESSED')
        ->toContain('BANK TRANSFER')
        ->toContain('Item returned in original condition')
        ->toContain('Refund summary')
        ->toContain('Thank you for shopping with us.')
        ->toContain('View your order');
});

it('renders the return request email with the payout details', function () {
    emailTemplateStore();
    $order = emailTemplateOrder();

    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id,
        'user_id' => $order->user_id,
        'type' => 'return',
        'status' => 'requested',
        'reason' => 'Wrong size',
        'amount' => 1189.00,
        'payout_method' => 'bank',
        'payout_details' => ['bank_name' => 'State Bank', 'account_number' => '1234567890'],
    ]);

    $html = (new ReturnRequestMail($returnRequest, $order->fresh(), $order->user))->render();

    expect($html)->toContain('Gehna Jewels')
        ->toContain('We have received your return request')
        ->toContain('Wrong size')
        ->toContain('State Bank')
        ->toContain('REQUESTED')
        ->toContain('Rs 1,189.00')
        ->toContain('Return summary')
        ->toContain('Thank you for shopping with us.')
        ->toContain('View order &amp; return status');
});

it('renders the shipment email with a status pill and tracking timeline', function () {
    emailTemplateStore();
    $order = emailTemplateOrder();

    $shipment = Shipment::create([
        'order_id' => $order->id,
        'tracking_number' => 'AWB123456',
        'tracking_url' => 'https://track.example.com/AWB123456',
        'status' => 'out_for_delivery',
    ]);

    ShipmentTrackingEvent::create([
        'shipment_id' => $shipment->id,
        'status_code' => 'OUT FOR DELIVERY',
        'status_label' => 'Out for delivery',
        'location' => 'Jaipur',
        'scanned_at' => now(),
    ]);

    $html = (new ShipmentStatusMail($shipment->fresh(), 'out_for_delivery'))->render();

    expect($html)->toContain('Gehna Jewels')
        ->toContain('Out For Delivery')
        ->toContain('AWB123456')
        ->toContain('https://track.example.com/AWB123456')
        ->toContain('Track your shipment')
        ->toContain('Jaipur')
        ->toContain('Delivery summary')
        ->toContain('Tracking timeline')
        ->toContain('background-color:#FBF3E2'); // amber status banner for in-transit states
});

it('renders the welcome email with the member benefits', function () {
    emailTemplateStore();
    $user = User::factory()->create(['name' => 'Priya Patel']);

    $html = (new WelcomeUserMail($user))->render();

    expect($html)->toContain('Priya Patel')
        ->toContain('Gehna Jewels')
        ->toContain('Your account lets you')
        ->toContain('Member perks')
        ->toContain('Start shopping')
        ->toContain('Thank you for joining us');
});
