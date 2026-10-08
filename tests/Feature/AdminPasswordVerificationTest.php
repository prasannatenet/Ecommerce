<?php

use App\Mail\PasswordChangeCodeMail;
use App\Models\PasswordVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Admin panel password change (POST /profile/password/code -> verify)
|--------------------------------------------------------------------------
|
| The admin password may never move directly: step 1 validates and emails a
| 6-digit code, step 2 applies the staged password only when that code is
| confirmed. These tests pin the whole path, including the bypass attempts.
|
*/

/** An authenticated admin, the way the panel creates one. */
function adminForPasswordChange(): User
{
    Role::firstOrCreate(['name' => 'admin']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

/** Every code Mail has captured so far, in send order. */
function sentPasswordCodes(): array
{
    $codes = [];

    Mail::assertSent(PasswordChangeCodeMail::class, function ($mail) use (&$codes) {
        $codes[] = $mail->code;

        return true;
    });

    return $codes;
}

/** A code guaranteed to differ from the given one (no 1-in-a-million flake). */
function differentCodeThan(string $code): string
{
    return str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
}

it('emails a 6-digit code instead of changing the password on step one', function () {
    Mail::fake();

    $admin = adminForPasswordChange();

    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.code'), [
            'current_password' => 'password',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    // The email carries a 6-digit code addressed to the admin.
    $codes = sentPasswordCodes();
    expect($codes)->toHaveCount(1)
        ->and($codes[0])->toMatch('/^\d{6}$/');

    // The password must NOT have moved yet.
    expect(Hash::check('password', $admin->refresh()->password))->toBeTrue()
        ->and(Hash::check('NewSecret123!', $admin->password))->toBeFalse();

    // The challenge is staged, unexpired and unused.
    $record = PasswordVerificationCode::where('user_id', $admin->id)->sole();
    expect($record->used)->toBeFalse()
        ->and($record->expires_at->isFuture())->toBeTrue()
        ->and($record->pending_password_hash)->not->toBeNull()
        // The row never stores the plaintext code.
        ->and($record->code_hash)->not->toBe($codes[0]);
});

it('updates the password only after the emailed code is verified', function () {
    Mail::fake();

    $admin = adminForPasswordChange();

    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.code'), [
            'current_password' => 'password',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

    $code = sentPasswordCodes()[0];

    // A near-miss code must not slip through.
    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.verify'), ['code' => differentCodeThan($code)])
        ->assertSessionHasErrorsIn('passwordVerify', 'code');

    expect(Hash::check('password', $admin->refresh()->password))->toBeTrue();

    // The real code completes the change.
    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.verify'), ['code' => $code])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    expect(Hash::check('NewSecret123!', $admin->refresh()->password))->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeFalse();

    // The challenge is burned: nothing staged remains for a replay.
    $record = PasswordVerificationCode::where('user_id', $admin->id)->sole();
    expect($record->used)->toBeTrue()
        ->and($record->used_at)->not->toBeNull();
});

it('rejects a correct code once it has expired', function () {
    $admin = adminForPasswordChange();

    PasswordVerificationCode::create([
        'code_hash' => PasswordVerificationCode::hash('123456'),
        'user_id' => $admin->id,
        'pending_password_hash' => Hash::make('NewSecret123!'),
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.verify'), ['code' => '123456'])
        ->assertSessionHasErrorsIn('passwordVerify', 'code');

    expect(Hash::check('password', $admin->refresh()->password))->toBeTrue()
        ->and(Hash::check('NewSecret123!', $admin->password))->toBeFalse();
});

it('refuses to replay a code that was already used', function () {
    $admin = adminForPasswordChange();

    PasswordVerificationCode::create([
        'code_hash' => PasswordVerificationCode::hash('123456'),
        'user_id' => $admin->id,
        'pending_password_hash' => Hash::make('NewSecret123!'),
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.verify'), ['code' => '123456'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('NewSecret123!', $admin->refresh()->password))->toBeTrue();

    // Second attempt with the same code: the row is burned, so it fails.
    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.verify'), ['code' => '123456'])
        ->assertSessionHasErrorsIn('passwordVerify', 'code');
});

it('requires the current password before any code is emailed', function () {
    Mail::fake();

    $admin = adminForPasswordChange();

    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.code'), [
            'current_password' => 'wrong-password',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ])
        ->assertSessionHasErrorsIn('passwordChange', 'current_password');

    Mail::assertNothingSent();
    expect(PasswordVerificationCode::where('user_id', $admin->id)->count())->toBe(0);
});

it('blocks an admin from changing the password through the old direct route', function () {
    $admin = adminForPasswordChange();

    $this->actingAs($admin)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect('/profile')
        ->assertSessionHas('error');

    // Nothing moved: only the emailed-code flow may change it.
    expect(Hash::check('password', $admin->refresh()->password))->toBeTrue()
        ->and(Hash::check('new-password', $admin->password))->toBeFalse();
});

it('still lets a regular storefront user update the password directly', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

it('burns the old code when a resend issues a fresh one', function () {
    Mail::fake();

    $admin = adminForPasswordChange();

    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.code'), [
            'current_password' => 'password',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.resend'))
        ->assertRedirect('/profile');

    [$oldCode, $newCode] = sentPasswordCodes();
    expect($newCode)->not->toBe($oldCode);

    // The superseded code is dead; the fresh one completes the change.
    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.verify'), ['code' => $oldCode])
        ->assertSessionHasErrorsIn('passwordVerify', 'code');

    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.verify'), ['code' => $newCode])
        ->assertSessionHasNoErrors();

    expect(Hash::check('NewSecret123!', $admin->refresh()->password))->toBeTrue();
});

it('lets the admin cancel a pending change and start over', function () {
    Mail::fake();

    $admin = adminForPasswordChange();

    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.code'), [
            'current_password' => 'password',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.cancel'))
        ->assertRedirect('/profile');

    expect(PasswordVerificationCode::where('user_id', $admin->id)->count())->toBe(0)
        ->and(Hash::check('password', $admin->refresh()->password))->toBeTrue();

    // Step 2 must be gone: the profile page is back to the password form.
    $this->actingAs($admin)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Send verification code')
        ->assertDontSee('Verification Code');
});

it('shows the code entry step on the profile page while a code is pending', function () {
    Mail::fake();

    $admin = adminForPasswordChange();

    // Step 1 state: password form, no code field.
    $this->actingAs($admin)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Send verification code')
        ->assertDontSee('Verification Code');

    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.code'), [
            'current_password' => 'password',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

    // Step 2 state survives a refresh because it is driven by the row, not
    // just the one-shot flash.
    $this->actingAs($admin)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Verification Code')
        ->assertSee('Verify & Update Password')
        ->assertDontSee('Send verification code');
});

it('renders the code email with the code and a subject naming the store', function () {
    $admin = User::factory()->create(['name' => 'Kavita Rao']);

    $mail = new PasswordChangeCodeMail($admin, '482913', 10);

    $rendered = $mail->render();

    expect($rendered)->toContain('482913')
        ->and($rendered)->toContain('Kavita Rao')
        ->and($mail->subject)->toContain(config('app.name'));
});

it('never lists a pending challenge for a different account', function () {
    $admin = adminForPasswordChange();
    $other = adminForPasswordChange();

    PasswordVerificationCode::create([
        'code_hash' => PasswordVerificationCode::hash('111222'),
        'user_id' => $other->id,
        'pending_password_hash' => Hash::make('OtherSecret123!'),
        'expires_at' => now()->addMinutes(10),
    ]);

    // The signed-in admin sees step 1: the other account's row is invisible.
    $this->actingAs($admin)
        ->get('/profile')
        ->assertOk()
        ->assertDontSee('Verification Code');

    // And verifying against it fails: the lookup is scoped to the session user.
    $this->actingAs($admin)
        ->from('/profile')
        ->post(route('profile.password.verify'), ['code' => '111222'])
        ->assertSessionHasErrorsIn('passwordVerify', 'code');

    // The other account's challenge is untouched and still works for them.
    $this->actingAs($other)
        ->from('/profile')
        ->post(route('profile.password.verify'), ['code' => '111222'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('OtherSecret123!', $other->refresh()->password))->toBeTrue()
        ->and(Hash::check('password', $admin->refresh()->password))->toBeTrue();
});
