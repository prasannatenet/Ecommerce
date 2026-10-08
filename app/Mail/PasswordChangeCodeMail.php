<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Carries the 6-digit code an admin must enter before the admin panel
 * password is allowed to change.
 */
class PasswordChangeCodeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
        public int $expiresInMinutes = 10,
    ) {}

    public function build(): self
    {
        return $this->subject('Your '.config('app.name', 'Our Store').' password change code')
            ->view('emails.users.password-change-code', [
                'user' => $this->user,
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }
}
