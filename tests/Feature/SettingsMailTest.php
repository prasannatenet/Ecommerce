<?php

use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

/*
|--------------------------------------------------------------------------
| Settings mail configuration
|--------------------------------------------------------------------------
|
| The admin "SMTP Configuration" card is the single source of truth for
| outgoing mail; the duplicate "Mail Driver / Provider" + Resend API key
| block was removed. So the settings form has to save without any
| mail_driver field, and Setting::applyMailConfig() has to be the only
| place that maps the settings row onto Laravel's mail config.
|
*/

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
    app()->instance(EncrypterContract::class, $encrypter);
});

function settingsMailAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function resendSmtpSetting(array $attributes = []): Setting
{
    return Setting::create(array_merge([
        'site_name' => 'Store',
        'smtp_host' => 'smtp.resend.com',
        'smtp_port' => 465,
        'smtp_username' => 'resend',
        'smtp_password' => 're_secret_key',
        'smtp_encryption' => 'smtps',
        'smtp_from_email' => 'noreply@astroemerging.com',
        'smtp_from_name' => 'Astro Emerging',
    ], $attributes));
}

it('renders the SMTP configuration section without the removed driver block', function () {
    actingAs(settingsMailAdmin());
    resendSmtpSetting();

    get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('SMTP Configuration')
        ->assertSee('smtp.resend.com')
        ->assertSee('noreply@astroemerging.com')
        ->assertDontSee('Mail Service Provider')
        ->assertDontSee('Mail Driver / Provider')
        ->assertDontSee('name="mail_driver"', false)
        ->assertDontSee('resend_api_key')
        ->assertDontSee('Resend API Key');
});

it('shows the stored smtps encryption as the SSL option instead of None', function () {
    actingAs(settingsMailAdmin());
    resendSmtpSetting();

    $content = get(route('admin.settings.edit'))->assertOk()->getContent();

    expect($content)->toContain('<option value="smtps" selected>')
        ->and($content)->not->toContain('<option value="" selected>');
});

it('renders a legacy tls encryption value as the STARTTLS option', function () {
    actingAs(settingsMailAdmin());
    resendSmtpSetting(['smtp_encryption' => 'tls']);

    $content = get(route('admin.settings.edit'))->assertOk()->getContent();

    expect($content)->toContain('<option value="smtp" selected>')
        ->and($content)->not->toContain('<option value="tls"');
});

it('saves smtp settings without a mail_driver field', function () {
    actingAs(settingsMailAdmin());
    Setting::create(['site_name' => 'Store']);

    put(route('admin.settings.update'), [
        'site_name' => 'My Store',
        'smtp_host' => 'smtp.mailtrap.io',
        'smtp_port' => 587,
        'smtp_username' => 'user',
        'smtp_password' => 'pass',
        'smtp_encryption' => 'smtp',
        'smtp_from_email' => 'hello@store.com',
        'smtp_from_name' => 'Store',
    ])->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHasNoErrors();

    $setting = Setting::first()->refresh();

    expect($setting->site_name)->toBe('My Store');
    expect($setting->smtp_host)->toBe('smtp.mailtrap.io');
    expect($setting->smtp_encryption)->toBe('smtp');
    expect($setting->smtp_from_email)->toBe('hello@store.com');
    expect($setting->smtp_from_name)->toBe('Store');
    expect($setting->smtp_password)->toBe('pass');
});

it('rejects an unknown encryption value', function () {
    actingAs(settingsMailAdmin());
    Setting::create(['site_name' => 'Store']);

    put(route('admin.settings.update'), [
        'smtp_encryption' => 'bogus_encryption',
    ])->assertSessionHasErrors(['smtp_encryption']);
});

it('stores the smtp password encrypted', function () {
    resendSmtpSetting(['smtp_password' => 're_plaintext_key']);
    $setting = Setting::first();

    expect($setting->smtp_password)->toBe('re_plaintext_key');
    expect($setting->getRawOriginal('smtp_password'))->not->toBe('re_plaintext_key');
    expect(DB::table('settings')->where('smtp_password', 're_plaintext_key')->exists())->toBeFalse();
});

it('keeps the stored password when the password field is left blank', function () {
    actingAs(settingsMailAdmin());
    resendSmtpSetting();

    put(route('admin.settings.update'), [
        'site_name' => 'Store',
        'smtp_host' => 'smtp.resend.com',
        'smtp_port' => 465,
        'smtp_username' => 'resend',
        'smtp_encryption' => 'smtps',
        'smtp_from_email' => 'noreply@astroemerging.com',
    ])->assertRedirect(route('admin.settings.edit'));

    expect(Setting::first()->refresh()->smtp_password)->toBe('re_secret_key');
});

it('maps the settings row onto the mail config', function () {
    resendSmtpSetting()->applyMailConfig();

    expect(Config::get('mail.default'))->toBe('smtp');
    expect(Config::get('mail.mailers.smtp.host'))->toBe('smtp.resend.com');
    expect(Config::get('mail.mailers.smtp.port'))->toBe(465);
    expect(Config::get('mail.mailers.smtp.username'))->toBe('resend');
    expect(Config::get('mail.mailers.smtp.password'))->toBe('re_secret_key');
    expect(Config::get('mail.mailers.smtp.scheme'))->toBe('smtps');
    expect(Config::get('mail.from.address'))->toBe('noreply@astroemerging.com');
    expect(Config::get('mail.from.name'))->toBe('Astro Emerging');
});

it('always builds a usable smtp transport', function ($stored, $expectedScheme) {
    resendSmtpSetting(['smtp_encryption' => $stored])->applyMailConfig();

    expect(Config::get('mail.mailers.smtp.scheme'))->toBe($expectedScheme);

    // Symfony only accepts the "smtp"/"smtps" DSN schemes, so the old
    // 'tls'/'ssl' values used to blow up right here.
    app('mail.manager')->forgetMailers();

    expect(fn () => app('mail.manager')->mailer('smtp'))->not->toThrow(Throwable::class);
})->with([
    'new ssl value' => ['smtps', 'smtps'],
    'legacy ssl value' => ['ssl', 'smtps'],
    'new starttls value' => ['smtp', 'smtp'],
    'legacy tls value' => ['tls', 'smtp'],
    'empty string' => ['', null],
    'null value' => [null, null],
]);

it('applies the stored settings when the provider boots', function () {
    resendSmtpSetting();

    expect(Config::get('mail.mailers.smtp.host'))->not->toBe('smtp.resend.com');

    (new AppServiceProvider(app()))->boot();

    expect(Config::get('mail.default'))->toBe('smtp');
    expect(Config::get('mail.mailers.smtp.host'))->toBe('smtp.resend.com');
    expect(Config::get('mail.mailers.smtp.scheme'))->toBe('smtps');
    expect(Config::get('mail.from.address'))->toBe('noreply@astroemerging.com');
});

it('leaves the mail config alone until an smtp host is configured', function () {
    Setting::create(['site_name' => 'Store']);

    (new AppServiceProvider(app()))->boot();

    expect(Config::get('mail.default'))->not->toBe('smtp');
    expect(Config::get('mail.mailers.smtp.host'))->not->toBe('smtp.resend.com');
});

it('sends the test email through the same config path', function () {
    actingAs(settingsMailAdmin());
    resendSmtpSetting();
    Mail::fake();

    put(route('admin.settings.update'), [
        'site_name' => 'Store',
        'action' => 'test_mail',
        'test_email' => 'inbox@example.com',
    ])->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();
});

it('asks for a recipient before sending a test email', function () {
    actingAs(settingsMailAdmin());
    resendSmtpSetting();
    Mail::fake();

    put(route('admin.settings.update'), [
        'site_name' => 'Store',
        'action' => 'test_mail',
        'test_email' => '',
    ])->assertSessionHasErrors(['test_email']);
});
