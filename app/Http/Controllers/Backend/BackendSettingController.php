<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Throwable;

class BackendSettingController extends Controller
{
    public function edit()
    {
        $setting = Setting::first();
        return view('backend.settings.edit', compact('setting'));
    }

    public function update(Request $request)
    {
        $data = $this->validateData($request);

        $setting = Setting::first() ?? new Setting();

        // Preserve existing encrypted credentials when the user leaves them blank.
        if (($data['smtp_password'] ?? '') === '') {
            unset($data['smtp_password']);
        }
        if (($data['resend_api_key'] ?? '') === '') {
            unset($data['resend_api_key']);
        }

        if ($request->hasFile('logo')) {
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('settings', 'public');
        }

        if ($request->hasFile('favicon')) {
            if ($setting->favicon_path) {
                Storage::disk('public')->delete($setting->favicon_path);
            }
            $data['favicon_path'] = $request->file('favicon')->store('settings', 'public');
        }

        $setting->fill($data);
        $setting->save();

        if ($request->input('action') === 'test_mail') {
            $testEmail = (string) $request->input('test_email', '');

            if ($testEmail === '') {
                return redirect()->route('admin.settings.edit')
                    ->withErrors(['test_email' => 'Please enter a test recipient email address.'])
                    ->withInput();
            }

            $this->applyMailConfig($setting);

            try {
                Mail::raw('This is a test email from your store mail configuration.', function ($message) use ($testEmail): void {
                    $message->to($testEmail)
                        ->subject('Mail Test Email');
                });

                return redirect()->route('admin.settings.edit')
                    ->with('success', 'Settings updated and test email sent successfully to ' . $testEmail . '.');
            } catch (Throwable $e) {
                return redirect()->route('admin.settings.edit')
                    ->withErrors(['smtp_test' => 'Settings saved, but test email failed: ' . $e->getMessage()])
                    ->withInput();
            }
        }

        return redirect()->route('admin.settings.edit')->with('success', 'Settings updated successfully.');
    }

        /**
     * Apply the admin-configured mail driver to the runtime config.
     *
     * The driver is selected via the Setting.mail_driver column so switching
     * providers (Resend, SMTP, Log, ...) is a matter of choosing a different
     * value in the admin UI — no code changes needed. Adding a new provider
     * later is just one new match-arm + one dedicated credential column.
     */
    private function applyMailConfig(Setting $setting): void
    {
        $driver = $setting->mail_driver ?: 'smtp';

        // The "from" address / name are shared by every transport.
        Config::set('mail.from.address', $setting->smtp_from_email ?: config('mail.from.address'));
        Config::set('mail.from.name', $setting->smtp_from_name ?: config('mail.from.name'));

        match ($driver) {
            'smtp'   => $this->applySmtp($setting),
            'resend' => $this->applyResend($setting),
            default  => null,
        };

        Config::set('mail.default', $driver);
    }

    /**
     * Apply SMTP transport credentials pulled from the settings table.
     */
    private function applySmtp(Setting $setting): void
    {
        Config::set('mail.mailers.smtp.host', $setting->smtp_host);
        Config::set('mail.mailers.smtp.port', (int) ($setting->smtp_port ?: 587));
        Config::set('mail.mailers.smtp.username', $setting->smtp_username);
        Config::set('mail.mailers.smtp.password', $setting->smtp_password);
        Config::set('mail.mailers.smtp.scheme', $setting->smtp_encryption ?: null);
    }

    /**
     * Apply the Resend transport. Laravel reads the API key from
     * config('services.resend.key') at send-time — the 'resend' mailer
     * block in config/mail.php is already sufficient.
     */
    private function applyResend(Setting $setting): void
    {
        Config::set('services.resend.key', $setting->resend_api_key);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'site_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'zip' => 'nullable|string|max:20',
            'facebook_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'twitter_url' => 'nullable|url|max:255',
            'youtube_url' => 'nullable|url|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'mail_driver' => 'required|in:smtp,resend,sendmail,log',
            'resend_api_key' => 'nullable|string|max:255',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|in:tls,ssl',
            'smtp_from_email' => 'nullable|email|max:255',
            'smtp_from_name' => 'nullable|string|max:255',
            'test_email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,svg|max:2048',
            'favicon' => 'nullable|image|mimes:jpg,jpeg,ico,svg|max:1024',
        ]);
    }
}