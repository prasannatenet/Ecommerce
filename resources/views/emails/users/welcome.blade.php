@extends('emails.layout')

@section('title', 'Welcome to ' . (optional($appSetting)->site_name ?: config('app.name', 'Our Store')))
@section('header_note', 'Welcome')
@section('preheader', 'Your account is ready — here is everything you can do next.')
@section('eyebrow', 'Welcome aboard')
@section('heading')
    Welcome to {{ optional($appSetting)->site_name ?: config('app.name', 'Our Store') }}, {{ $user->name }}!
@endsection

@section('content')
    @php
        $welcomeSupportEmail = optional($appSetting)->email ?: (config('mail.from.address') ?: 'support@example.com');
    @endphp

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#EFF5F1;border:1px solid #DCE7E1;border-radius:14px;margin:0 0 20px;">
        <tr>
            <td style="padding:16px 20px;font-size:15px;line-height:23px;color:#0F3D32;">
                <div style="font-size:15px;">Hello <strong>{{ $user->name }}</strong>, &#127881;</div>
                <div style="margin-top:6px;font-size:14px;line-height:22px;color:#3E5A51;">Your account has been created successfully. We are delighted to have you with us &mdash; here is everything you can do next.</div>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 10px;font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:#B08D3C;font-weight:bold;">&#10022;&nbsp;&nbsp;Your account lets you</p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#FFFDF8;border:1px solid #E7DCC2;border-radius:14px;border-collapse:separate;overflow:hidden;">
        <tr>
            <td colspan="2" style="background-color:#0F3D32;padding:11px 18px;font-size:11px;font-weight:bold;letter-spacing:1.8px;text-transform:uppercase;color:#E8D9AE;">Member perks<span style="color:#B08D3C;">&nbsp;&nbsp;&#10022;</span></td>
        </tr>
        @foreach ([
            ['Browse and order the full product catalogue.', '&#9670;'],
            ['Track every order in real time from your account.', '&#9679;'],
            ['Save favourites to your wishlist and collect Gehna Coins.', '&#10022;'],
        ] as $welcomeBenefit)
            <tr>
                <td width="42" align="center" style="padding:12px 0 12px 18px;vertical-align:top;{{ $loop->last ? '' : 'border-bottom:1px solid #F4EFE4;' }}">
                    <span style="display:inline-block;background-color:#FBF3E2;border:1px solid #E7DCC2;color:#B08D3C;font-size:12px;width:28px;height:28px;line-height:28px;text-align:center;border-radius:50%;">{!! $welcomeBenefit[1] !!}</span>
                </td>
                <td style="padding:14px 18px 14px 10px;font-size:14px;line-height:21px;color:#24211D;vertical-align:middle;{{ $loop->last ? '' : 'border-bottom:1px solid #F4EFE4;' }}">{{ $welcomeBenefit[0] }}</td>
            </tr>
        @endforeach
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#FAF7F0;border:1px dashed #D9CFB8;border-radius:12px;margin:18px 0 0;">
        <tr>
            <td style="padding:13px 18px;font-size:14px;line-height:22px;color:#4A443C;">
                &#9993;&nbsp; Questions? Simply reply to this email or write to
                <a href="mailto:{{ $welcomeSupportEmail }}" style="color:#0F3D32;text-decoration:none;font-weight:bold;">{{ $welcomeSupportEmail }}</a>.
            </td>
        </tr>
    </table>
@endsection

@section('cta')
    @include('emails.partials.button', ['url' => route('products.index'), 'label' => 'Start shopping'])
@endsection

@section('signoff', 'Thank you for joining us — we look forward to serving you!')

