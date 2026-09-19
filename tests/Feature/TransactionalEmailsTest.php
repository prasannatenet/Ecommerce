<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sends a welcome email to a new user on registration', function () {
    Mail::fake();

    // Breeze's register route lives under the `web` middleware group, which
    // enforces a CSRF token. Use the withoutMiddleware helper so the test
    // POST only needs the form payload.
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->post(route('register'), [
            'name' => 'Riya Sharma',
            'email' => 'riya.sharma@example.com',
            'phone' => '9812345678',
            'password' => 'Password#123',
            'password_confirmation' => 'Password#123',
        ]);

    Mail::assertSent(\App\Mail\WelcomeUserMail::class, function ($mail) {
        return $mail->hasTo('riya.sharma@example.com')
            && $mail->user->name === 'Riya Sharma';
    });
});

it('renders the welcome email view with the user name and store name', function () {
    $user = User::factory()->create(['name' => 'Priya Patel']);

    $mail = new \App\Mail\WelcomeUserMail($user);

    // Render the mailable without actually dispatching mail.
    $rendered = $mail->render();

    expect($rendered)->toContain('Priya Patel')
        ->and($mail->subject)->toContain(config('app.name'));
});

it('sends a return request email to the order owner', function () {
    Mail::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    $order = Order::create([
        'user_id' => $user->id,
        'status' => 'delivered',
        'total' => 1000.00,
    ]);

    $item = new OrderItem([
        'order_id' => $order->id,
        'product_name' => 'Cotton Saree',
        'sku' => 'CS-001',
        'unit_price' => 500.00,
        'quantity' => 2,
        'line_total' => 1000.00,
    ]);
    $item->order_id = $order->id;
    $item->save();

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->post(route('orders.return-request.store', $order), [
            'reason' => 'Wrong size',
            'payout_method' => 'upi',
            'upi_id' => 'riya@upi',
            'items' => [$item->id],
            'quantities' => [$item->id => 1],
        ]);

    $response->assertRedirect(route('orders.show', $order));

    Mail::assertSent(\App\Mail\ReturnRequestMail::class, function ($mail) use ($order) {
        return $mail->hasTo($order->user->email)
            && $mail->order->is($order)
            && $mail->returnRequest->reason === 'Wrong size';
    });
});

it('does not send a return request email when the order belongs to another user', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $this->actingAs($intruder);

    $order = Order::create([
        'user_id' => $owner->id,
        'status' => 'delivered',
        'total' => 500.00,
    ]);

    $item = new OrderItem([
        'order_id' => $order->id,
        'product_name' => 'Silk Dupatta',
        'sku' => 'SD-002',
        'unit_price' => 500.00,
        'quantity' => 1,
        'line_total' => 500.00,
    ]);
    $item->save();

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->post(route('orders.return-request.store', $order), [
            'reason' => 'Damaged',
            'payout_method' => 'bank',
            'account_holder' => 'R Sharma',
            'account_number' => '1234567890',
            'ifsc' => 'SBIN0123456',
            'items' => [$item->id],
            'quantities' => [$item->id => 1],
        ]);

    $response->assertStatus(403);
    Mail::assertNotSent(\App\Mail\ReturnRequestMail::class);
});
