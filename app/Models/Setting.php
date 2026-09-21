<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Setting extends Model
{
    protected $fillable = [
        'site_name',
        'email',
        'phone',
        'logo_path',
        'favicon_path',
        'description',
        'address',
        'city',
        'state',
        'country',
        'zip',
        'facebook_url',
        'instagram_url',
        'twitter_url',
        'youtube_url',
        'linkedin_url',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_email',
        'smtp_from_name',
        'mail_driver',
        'resend_api_key',
    ];

    protected $casts = [
        'smtp_password' => 'encrypted',
        'resend_api_key' => 'encrypted',
    ];

    /**
     * Push this row's SMTP credentials into the runtime mail configuration.
     *
     * This is the single code path for outgoing mail: AppServiceProvider calls
     * it once per request so every message (orders, returns, shipment updates,
     * password resets) uses the admin "SMTP Configuration" values, and the
     * "Save & Send Test Email" action re-uses it so the test can never drift
     * from what real mail does.
     */
    public function applyMailConfig(): void
    {
        Config::set('mail.default', 'smtp');

        // Symfony only understands the "smtp" and "smtps" DSN schemes; a blank
        // scheme lets Laravel decide on its own (port 465 => smtps). Legacy
        // 'tls'/'ssl' values keep working.
        Config::set('mail.mailers.smtp.scheme', match ($this->smtp_encryption) {
            'tls', 'smtp' => 'smtp',
            'ssl', 'smtps' => 'smtps',
            default => null,
        });

        Config::set('mail.mailers.smtp.host', $this->smtp_host);
        Config::set('mail.mailers.smtp.port', (int) ($this->smtp_port ?: 587));
        Config::set('mail.mailers.smtp.username', $this->smtp_username);
        Config::set('mail.mailers.smtp.password', $this->smtp_password);

        // The sender identity is shared by every message.
        Config::set('mail.from.address', $this->smtp_from_email ?: config('mail.from.address'));
        Config::set('mail.from.name', $this->smtp_from_name ?: config('mail.from.name'));
    }
}