<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AdminSettingController extends ApiController
{
    public function show(): JsonResponse
    {
        $setting = Setting::query()->first() ?? new Setting();

        return $this->ok($this->settingData($setting))
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string'],
            'address' => ['sometimes', 'nullable', 'string'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'zip' => ['sometimes', 'nullable', 'string', 'max:20'],
            'facebook_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'instagram_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'twitter_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'youtube_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'smtp_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_encryption' => ['sometimes', 'nullable', 'in:tls,ssl,smtp,smtps'],
            'smtp_from_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'smtp_from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,svg', 'max:2048'],
            'favicon' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,ico,svg', 'max:1024'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
        ]);

        $setting = Setting::query()->first() ?? new Setting();
        $oldFiles = [];
        $newFiles = [];

        if (blank($data['smtp_password'] ?? null)) {
            unset($data['smtp_password']);
        }

        unset($data['remove_logo'], $data['remove_favicon']);

        try {
            foreach (['logo', 'favicon'] as $field) {
                $pathField = $field.'_path';
                $removeField = 'remove_'.$field;

                if ($request->hasFile($field)) {
                    $path = $request->file($field)->store('settings', 'public');

                    if (! $path) {
                        throw new RuntimeException("Unable to store the {$field} image.");
                    }

                    $newFiles[] = $path;
                    $data[$pathField] = $path;
                } elseif ($request->boolean($removeField)) {
                    if ($setting->{$pathField}) {
                        $oldFiles[] = $setting->{$pathField};
                    }

                    $data[$pathField] = null;
                }

                if (isset($data[$pathField]) && $setting->{$pathField} && $data[$pathField] !== $setting->{$pathField}) {
                    $oldFiles[] = $setting->{$pathField};
                }
            }

            $setting->fill($data);
            $setting->save();
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newFiles);
            throw $exception;
        }

        if ($oldFiles !== []) {
            Storage::disk('public')->delete(array_unique($oldFiles));
        }

        return $this->ok($this->settingData($setting->refresh()), message: 'Settings updated successfully.')
            ->header('Cache-Control', 'no-store, private');
    }

    public function testEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $setting = Setting::query()->first();

        if (! $setting) {
            return $this->fail(
                'Save mail settings before sending a test email.',
                errors: ['settings' => ['No store settings have been saved.']],
            );
        }

        $setting->applyMailConfig();

        try {
            Mail::raw('This is a test email from your store mail configuration.', function ($message) use ($data): void {
                $message->to($data['email'])->subject('Mail Test Email');
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->fail('Test email could not be sent. Check the saved mail configuration.', 502);
        }

        return $this->ok(['sent' => true], message: 'Test email sent successfully.');
    }

    private function settingData(Setting $setting): array
    {
        $base = rtrim((string) (config('storefront.url') ?: url('/')), '/');

        return [
            'site_name' => $setting->site_name,
            'email' => $setting->email,
            'phone' => $setting->phone,
            'description' => $setting->description,
            'address' => $setting->address,
            'city' => $setting->city,
            'state' => $setting->state,
            'country' => $setting->country,
            'zip' => $setting->zip,
            'logo' => $setting->logo_path ? $base.'/storage/'.ltrim($setting->logo_path, '/') : null,
            'favicon' => $setting->favicon_path ? $base.'/storage/'.ltrim($setting->favicon_path, '/') : null,
            'facebook_url' => $setting->facebook_url,
            'instagram_url' => $setting->instagram_url,
            'twitter_url' => $setting->twitter_url,
            'youtube_url' => $setting->youtube_url,
            'linkedin_url' => $setting->linkedin_url,
            'smtp_host' => $setting->smtp_host,
            'smtp_port' => $setting->smtp_port,
            'smtp_username' => $setting->smtp_username,
            'smtp_encryption' => $setting->smtp_encryption,
            'smtp_from_email' => $setting->smtp_from_email,
            'smtp_from_name' => $setting->smtp_from_name,
            'smtp_password_configured' => filled($setting->smtp_password),
        ];
    }
}
