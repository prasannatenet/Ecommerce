@extends('emails.layout')

@section('title', 'Your password change code')
@section('header_note', 'Security')
@section('preheader', 'Your 6-digit verification code is ' . $code . '. It expires in ' . $expiresInMinutes . ' minutes.')
@section('eyebrow', 'Password change')
@section('heading')
    One more step to secure your account, {{ $user->name }}
@endsection

@section('content')
    <p style="margin:0 0 14px;">
        Someone (hopefully you) asked to change the admin panel password for
        <strong>{{ $user->email }}</strong>. Enter the 6-digit code below on the
        Profile page to confirm the change:
    </p>

    {{-- Big, copy-friendly code --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
           style="width:100%;background-color:#0F3D32;border-radius:14px;border-collapse:separate;margin:4px 0 16px;">
        <tr>
            <td align="center" style="padding:24px 12px 20px;">
                <div style="font-family:Consolas,Menlo,monospace;font-size:36px;line-height:42px;letter-spacing:12px;color:#E8D9AE;font-weight:bold;padding-left:12px;">{{ $code }}</div>
                <div style="margin-top:10px;font-size:11px;letter-spacing:1.6px;text-transform:uppercase;color:#7FA294;">
                    Valid for {{ $expiresInMinutes }} minutes &middot; can be used only once
                </div>
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
           style="width:100%;background-color:#FBF3E2;border:1px dashed #E7DCC2;border-radius:12px;margin:0 0 6px;">
        <tr>
            <td style="padding:14px 18px;font-size:14px;line-height:21px;color:#4A443C;">
                &#9888;&nbsp; Did not request this? Ignore this email &mdash; your password
                stays exactly as it is until the code is entered. If you are unsure,
                change your password from a device you trust.
            </td>
        </tr>
    </table>
@endsection

@section('signoff', 'Thank you for keeping your account secure.')
