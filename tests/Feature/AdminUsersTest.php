<?php

use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;

function makePanelAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function makePanelCustomer(array $attributes = []): User
{
    Role::firstOrCreate(['name' => 'user']);

    $user = User::factory()->create($attributes);
    $user->assignRole('user');

    return $user;
}

it('lets admins view all registered users on the admin users page', function () {
    $admin = makePanelAdmin();

    $customer = makePanelCustomer([
        'name' => 'Registered Customer',
        'phone' => '9812345678',
    ]);

    actingAs($admin);

    get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Registered Customer')
        ->assertSee('9812345678')
        ->assertSee($customer->email)
        ->assertSee('Registered Users');
});

it('lets admins search users by name, email or mobile', function () {
    $admin = makePanelAdmin();

    makePanelCustomer(['name' => 'Alice Walker', 'phone' => '9800000001']);
    makePanelCustomer(['name' => 'Bob Stone', 'phone' => '9800000002']);

    actingAs($admin);

    get(route('admin.users.index', ['q' => 'Alice']))
        ->assertOk()
        ->assertSee('Alice Walker')
        ->assertDontSee('Bob Stone');

    get(route('admin.users.index', ['q' => '9800000002']))
        ->assertOk()
        ->assertSee('Bob Stone')
        ->assertDontSee('Alice Walker');
});

it('lets admins filter users by role', function () {
    $admin = makePanelAdmin();

    $customer = makePanelCustomer(['name' => 'Only Customer']);

    actingAs($admin);

    get(route('admin.users.index', ['role' => 'user']))
        ->assertOk()
        ->assertSee('Only Customer')
        ->assertDontSee($admin->email);

    get(route('admin.users.index', ['role' => 'admin']))
        ->assertOk()
        ->assertSee($admin->email)
        ->assertDontSee('Only Customer');
});

it('blocks non-admin users from the admin users page', function () {
    $customer = makePanelCustomer();

    actingAs($customer);

    get(route('admin.users.index'))
        ->assertRedirect(route('account.index'));
});

it('lets admins remove a customer but protects admin accounts', function () {
    $admin = makePanelAdmin();

    $customer = makePanelCustomer();

    actingAs($admin);

    delete(route('admin.users.destroy', $customer))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(User::find($customer->id))->toBeNull();

    delete(route('admin.users.destroy', $admin))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(User::find($admin->id))->not->toBeNull();
});

it('shows the users page with order counts and coin balances', function () {
    $admin = makePanelAdmin();

    $customer = makePanelCustomer(['gehna_coins' => 250, 'name' => 'Coiny Customer']);

    Order::create([
        'user_id' => $customer->id,
        'status' => 'pending',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'refund_status' => 'none',
        'total' => 500,
        'refunded_total' => 0,
        'stock_deducted' => false,
    ]);

    actingAs($admin);

    get(route('admin.users.index', ['q' => 'Coiny']))
        ->assertOk()
        ->assertSee('250')
        ->assertSee('Coiny Customer');
});

