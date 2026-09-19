<?php

use App\Models\User;
use function Pest\Laravel\post;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Doe',
        'email' => 'jane' . uniqid() . '@example.com',
        'phone' => '9876543210',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ], $overrides);
}

it('stores the mobile number when a user signs up', function () {
    $phone = '98' . random_int(10000000, 99999999);

    post(route('register'), registrationPayload(['phone' => $phone]))
        ->assertRedirect();

    $user = User::latest('id')->first();
    expect($user->name)->toBe('Jane Doe');
    expect($user->phone)->toBe($phone);
});

it('rejects signup when the mobile number is missing', function () {
    post(route('register'), registrationPayload(['phone' => null]))
        ->assertSessionHasErrors('phone');

    expect(User::where('name', 'Jane Doe')->exists())->toBeFalse();
});

it('rejects signup with an invalid mobile number format', function () {
    post(route('register'), registrationPayload(['phone' => 'abc-xyz']))
        ->assertSessionHasErrors('phone');

    expect(User::where('name', 'Jane Doe')->exists())->toBeFalse();
});

it('rejects signup when the mobile number is already taken', function () {
    $phone = '98' . random_int(10000000, 99999999);

    User::factory()->create(['phone' => $phone]);

    post(route('register'), registrationPayload(['phone' => $phone]))
        ->assertSessionHasErrors('phone');

    expect(User::where('phone', $phone)->count())->toBe(1);
});
