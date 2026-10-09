<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $key = '0123456789abcdef0123456789abcdef';
    Config::set('app.key', $key);
    Config::set('app.cipher', 'AES-256-CBC');
    app()->forgetInstance('encrypter');
    $encrypter = new Encrypter($key, 'AES-256-CBC');
    app()->instance('encrypter', $encrypter);
    app()->instance(EncrypterContract::class, $encrypter);
});

function settingsApiAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

it('protects settings management from unauthenticated users and customers', function () {
    $this->getJson('/api/v1/admin/settings')
        ->assertUnauthorized()
        ->assertJsonPath('success', false);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/admin/settings')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('returns editable settings and only exposes whether the smtp password is configured', function () {
    Sanctum::actingAs(settingsApiAdmin());
    Setting::create([
        'site_name' => 'Gehna',
        'smtp_host' => 'smtp.example.com',
        'smtp_username' => 'mailer',
        'smtp_password' => 'secret-password',
        'facebook_url' => 'https://facebook.com/store',
    ]);

    $response = $this->getJson('/api/v1/admin/settings')
        ->assertOk()
        ->assertJsonPath('data.site_name', 'Gehna')
        ->assertJsonPath('data.smtp_host', 'smtp.example.com')
        ->assertJsonPath('data.smtp_username', 'mailer')
        ->assertJsonPath('data.facebook_url', 'https://facebook.com/store')
        ->assertJsonPath('data.smtp_password_configured', true);

    expect($response->getContent())
        ->not->toContain('secret-password')
        ->not->toContain('"smtp_password":');
});

it('updates settings, replaces uploaded images, and preserves a blank smtp password', function () {
    Sanctum::actingAs(settingsApiAdmin());
    Storage::fake('public');
    Storage::disk('public')->put('settings/current.png', 'old-logo');
    Setting::create([
        'site_name' => 'Old Store',
        'logo_path' => 'settings/current.png',
        'smtp_password' => 'keep-this-password',
    ]);

    $response = $this->put('/api/v1/admin/settings', [
        'site_name' => 'New Store',
        'email' => 'hello@example.com',
        'instagram_url' => 'https://instagram.com/new-store',
        'smtp_password' => '',
        'logo' => UploadedFile::fake()->createWithContent(
            'new-logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l28AAAAASUVORK5CYII='),
        ),
    ])->assertOk()
        ->assertJsonPath('data.site_name', 'New Store')
        ->assertJsonPath('data.smtp_password_configured', true);

    $setting = Setting::first()->refresh();

    expect($setting->email)->toBe('hello@example.com')
        ->and($setting->instagram_url)->toBe('https://instagram.com/new-store')
        ->and($setting->smtp_password)->toBe('keep-this-password')
        ->and($setting->logo_path)->not->toBe('settings/current.png')
        ->and($response->json('data.logo'))->toContain('/storage/'.$setting->logo_path);

    Storage::disk('public')->assertMissing('settings/current.png');
    Storage::disk('public')->assertExists($setting->logo_path);
});

it('serves the latest public settings after an admin saves changes without exposing mail configuration', function () {
    Sanctum::actingAs(settingsApiAdmin());
    Setting::create(['site_name' => 'Before Update']);

    $this->putJson('/api/v1/admin/settings', [
        'site_name' => 'After Update',
        'email' => 'contact@example.com',
        'facebook_url' => 'https://facebook.com/new-store',
        'instagram_url' => 'https://instagram.com/new-store',
    ])->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.site_name', 'After Update')
        ->assertJsonPath('data.email', 'contact@example.com')
        ->assertJsonPath('data.social.facebook', 'https://facebook.com/new-store')
        ->assertJsonPath('data.social.instagram', 'https://instagram.com/new-store')
        ->assertJsonMissingPath('data.smtp_host')
        ->assertJsonMissingPath('data.smtp_password');
});

it('validates settings fields and can remove an existing logo', function () {
    Sanctum::actingAs(settingsApiAdmin());
    Storage::fake('public');
    Storage::disk('public')->put('settings/current.png', 'old-logo');
    Setting::create(['logo_path' => 'settings/current.png']);

    $this->patchJson('/api/v1/admin/settings', [
        'email' => 'not-an-email',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->patchJson('/api/v1/admin/settings', [
        'remove_logo' => true,
    ])->assertOk()
        ->assertJsonPath('data.logo', null);

    expect(Setting::first()->refresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing('settings/current.png');
});

it('sends a test email using saved mail configuration for administrators', function () {
    Sanctum::actingAs(settingsApiAdmin());
    Setting::create([
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_password' => 'secret-password',
    ]);
    Mail::shouldReceive('raw')->once();

    $this->postJson('/api/v1/admin/settings/test-email', ['email' => 'test@example.com'])
        ->assertOk()
        ->assertJsonPath('data.sent', true);
});
