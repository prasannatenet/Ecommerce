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

        if (($data['smtp_password'] ?? '') === '') {
            unset($data['smtp_password']);
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

        if ($request->input('action') === 'test_smtp') {
            $testEmail = (string) $request->input('test_email', '');

            if ($testEmail === '') {
                return redirect()->route('admin.settings.edit')
                    ->withErrors(['test_email' => 'Please enter a test recipient email address.'])
                    ->withInput();
            }

            if (! $setting->smtp_host) {
                return redirect()->route('admin.settings.edit')
                    ->withErrors(['smtp_host' => 'SMTP Host is required to send a test email.'])
                    ->withInput();
            }

            $this->applyMailConfig($setting);

            try {
                Mail::raw('This is a test email from your store SMTP configuration.', function ($message) use ($testEmail): void {
                    $message->to($testEmail)
                        ->subject('SMTP Test Email');
                });

                return redirect()->route('admin.settings.edit')
                    ->with('success', 'Settings updated and test email sent successfully to '.$testEmail.'.');
            } catch (Throwable $e) {
                return redirect()->route('admin.settings.edit')
                    ->withErrors(['smtp_test' => 'Settings saved, but test email failed: '.$e->getMessage()])
                    ->withInput();
            }
        }

        return redirect()->route('admin.settings.edit')->with('success', 'Settings updated successfully.');
    }

    private function applyMailConfig(Setting $setting): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $setting->smtp_host);
        Config::set('mail.mailers.smtp.port', (int) ($setting->smtp_port ?: 587));
        Config::set('mail.mailers.smtp.username', $setting->smtp_username);
        Config::set('mail.mailers.smtp.password', $setting->smtp_password);
        Config::set('mail.mailers.smtp.scheme', $setting->smtp_encryption ?: null);
        Config::set('mail.from.address', $setting->smtp_from_email ?: config('mail.from.address'));
        Config::set('mail.from.name', $setting->smtp_from_name ?: config('mail.from.name'));
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
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|in:tls,ssl',
            'smtp_from_email' => 'nullable|email|max:255',
            'smtp_from_name' => 'nullable|string|max:255',
            'test_email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,svg|max:2048',
            'favicon' => 'nullable|image|mimes:jpg,jpeg,png,ico,svg|max:1024',
        ]);
    }
}
