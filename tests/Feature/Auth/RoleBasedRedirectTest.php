<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('admin is redirected to admin dashboard after login', function () {
    Role::firstOrCreate(['name' => 'admin']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('admin.dashboard', absolute: false));
});

test('normal user is redirected to account page after login', function () {
    Role::firstOrCreate(['name' => 'user']);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('account.index', absolute: false));
});

test('normal user cannot access admin area', function () {
    Role::firstOrCreate(['name' => 'user']);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->get('/admin');

    $response->assertRedirect(route('account.index', absolute: false));
});

test('normal user can access frontend account pages', function () {
    Role::firstOrCreate(['name' => 'user']);

    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->get(route('account.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('account.profile'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('account.orders'))
        ->assertOk();
});
