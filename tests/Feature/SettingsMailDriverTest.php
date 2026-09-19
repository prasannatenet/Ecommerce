<?php

use App\Http\Controllers\Backend\BackendSettingController;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\put;

beforeEach(function () {
    // The Setting model casts credentials as "encrypted"; make sure a valid
    // app key + encrypter exist for the test process even though the base
    // phpunit.xml does not declare APP_KEY.
    $key = '0123456789abcdef0123456789abcdef';
    Config::set('app.key', $key);
    Config::set('app.cipher', 'AES-256-CBC');
    app()->forgetInstance('encrypter');
    $encrypter = new Encrypter($key, 'AES-256-CBC');
    app()->instance('encrypter', $encrypter);
    app()->instance(\Illuminate\Contracts\Encryption\Encrypter::class, $encrypter);
});

function makeSettingsAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

it('switches the admin-configured mail provider to resend and stores the api key encrypted', function () {
    $admin = makeSettingsAdmin();
    Setting::create(['site_name' => 'Store']);

    actingAs($admin);

    put(route('admin.settings.update'), [
        'mail_driver' => 'resend',
        'resend_api_key' => 're_test_resend_api_key',
        'smtp_from_email' => 'hello@example.com',
        'smtp_from_name' => 'My Store',
    ])->assertRedirect(route('admin.settings.edit'));

    $setting = Setting::first();

    expect($setting->mail_driver)->toBe('resend');
    expect($setting->resend_api_key)->toBe('re_test_resend_api_key');
    expect($setting->getRawOriginal('resend_api_key'))
        ->not->toBe('re_test_resend_api_key');
    expect(DB::table('settings')->where('resend_api_key', 're_test_resend_api_key')->exists())
        ->toBeFalse();
});

it('preserves the stored resend api key when the input is left blank', function () {
    $admin = makeSettingsAdmin();

    Setting::create([
        'site_name' => 'Store',
        'mail_driver' => 'resend',
        'resend_api_key' => 're_secret_value',
    ]);

    actingAs($admin);

    // Resend key intentionally omitted -> should keep the existing encrypted value.
    put(route('admin.settings.update'), [
        'mail_driver' => 'resend',
        'smtp_from_email' => 'hello@example.com',
    ])->assertRedirect(route('admin.settings.edit'));

    $setting = Setting::first()->refresh();

    expect($setting->mail_driver)->toBe('resend');
    expect($setting->resend_api_key)->toBe('re_secret_value');
});

it('applies the resend transport to the runtime mail config', function () {
    $admin = makeSettingsAdmin();

    Setting::create([
        'mail_driver' => 'resend',
        'resend_api_key' => 're_runtime_key',
        'smtp_from_email' => 'hello@store.com',
        'smtp_from_name' => 'Store',
    ]);

    $controller = new BackendSettingController();
    $reflection = new ReflectionMethod($controller, 'applyMailConfig');
    $reflection->setAccessible(true);
    $reflection->invoke($controller, Setting::first());

    expect(Config::get('mail.default'))->toBe('resend');
    expect(Config::get('services.resend.key'))->toBe('re_runtime_key');
    expect(Config::get('mail.from.address'))->toBe('hello@store.com');
    expect(Config::get('mail.from.name'))->toBe('Store');
});

it('falls back to the smtp transport when mail_driver is empty', function () {
    $admin = makeSettingsAdmin();

    // A legacy/blank driver value (falsy) should resolve to smtp at runtime.
    $setting = Setting::create(['site_name' => 'Store']);
    $setting->mail_driver = '';
    $setting->save();

    $controller = new BackendSettingController();
    $reflection = new ReflectionMethod($controller, 'applyMailConfig');
    $reflection->setAccessible(true);
    $reflection->invoke($controller, Setting::first());

    expect(Config::get('mail.default'))->toBe('smtp');
});

it('reverts to smtp transport when the admin switches the provider back to smtp', function () {
    $admin = makeSettingsAdmin();

    Setting::create([
        'mail_driver' => 'resend',
        'resend_api_key' => 're_stored',
    ]);

    actingAs($admin);

    put(route('admin.settings.update'), [
        'mail_driver' => 'smtp',
        'smtp_host' => 'smtp.mailtrap.io',
        'smtp_port' => 587,
        'smtp_username' => 'user',
        'smtp_password' => 'pass',
        'smtp_encryption' => 'tls',
        'smtp_from_email' => 'hello@store.com',
        'smtp_from_name' => 'Store',
    ])->assertRedirect(route('admin.settings.edit'));

    $setting = Setting::first()->refresh();

    expect($setting->mail_driver)->toBe('smtp');
    expect($setting->smtp_host)->toBe('smtp.mailtrap.io');
    expect($setting->smtp_password)->toBe('pass');
    expect($setting->resend_api_key)->toBe('re_stored'); // untouched by the smtp payload
});

it('rejects an unknown mail driver', function () {
    $admin = makeSettingsAdmin();
    Setting::create(['site_name' => 'Store']);

    actingAs($admin);

    put(route('admin.settings.update'), [
        'mail_driver' => 'bogus_driver',
    ])->assertSessionHasErrors(['mail_driver']);
});