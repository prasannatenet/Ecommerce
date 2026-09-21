@php
    // Brand data for every transactional email. $appSetting is shared with all
    // views by AppServiceProvider, and may be null on a fresh install.
    $mailStoreName = optional($appSetting)->site_name ?: config('app.name', 'Our Store');
    $mailStoreEmail = optional($appSetting)->email ?: config('mail.from.address');
    $mailStorePhone = optional($appSetting)->phone;
    $mailStoreAddress = collect([
        optional($appSetting)->address,
        optional($appSetting)->city,
        optional($appSetting)->state,
        optional($appSetting)->zip,
        optional($appSetting)->country,
    ])->filter()->implode(', ');
    $mailStoreLogo = optional($appSetting)->logo_path
        ? asset('storage/' . $appSetting->logo_path)
        : null;
    $mailSocials = collect([
        'Facebook' => optional($appSetting)->facebook_url,
        'Instagram' => optional($appSetting)->instagram_url,
        'X' => optional($appSetting)->twitter_url,
        'YouTube' => optional($appSetting)->youtube_url,
        'LinkedIn' => optional($appSetting)->linkedin_url,
    ])->filter();
    $mailInitials = collect(explode(' ', trim($mailStoreName)))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title', $mailStoreName)</title>
    <style>
        @media only screen and (max-width: 620px) {
            .email-outer { padding-left: 8px !important; padding-right: 8px !important; }
            .email-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .email-h1 { font-size: 24px !important; line-height: 30px !important; }
            .email-cta-pad { padding-left: 22px !important; padding-right: 22px !important; }
        }
    </style>
</head>

<body
    style="margin:0;padding:0;width:100%;background-color:#EFE9DC;color:#24211D;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">

    {{-- Hidden inbox preview line --}}
    <div
        style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;color:#EFE9DC;">
        @yield('preheader')
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
        style="background-color:#EFE9DC;border-collapse:collapse;">
        <tr>
            <td align="center" class="email-outer" style="padding:36px 12px 44px;">

                {{-- Top micro-bar --}}
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"
                    style="width:100%;max-width:600px;border-collapse:collapse;">
                    <tr>
                        <td align="center"
                            style="padding:0 0 12px;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8A8377;">
                            <span style="color:#B08D3C;">&#10022;</span>&nbsp;&nbsp;Fine jewellery, delivered with
                            care&nbsp;&nbsp;<span style="color:#B08D3C;">&#10022;</span>
                        </td>
                    </tr>
                </table>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"
                    style="width:100%;max-width:600px;background-color:#FFFFFF;border:1px solid #E0D6C0;border-radius:20px;border-collapse:separate;">

                    {{-- ===== Gold hairline ===== --}}
                    <tr>
                        <td style="background-color:#B08D3C;font-size:0;line-height:0;padding:0;height:4px;">
                            &nbsp;</td>
                    </tr>

                    {{-- ===== Brand bar ===== --}}
                    <tr>
                        <td style="background-color:#0F3D32;padding:26px 32px 10px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td align="left" valign="middle" style="vertical-align:middle;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td valign="middle" width="46" style="vertical-align:middle;">
                                                    <div style="width:44px;height:44px;border-radius:50%;background-color:#B08D3C;color:#0F3D32;font-family:Georgia,'Times New Roman',serif;font-size:16px;font-weight:bold;line-height:44px;text-align:center;">{{ $mailInitials ?: 'G' }}</div>
                                                </td>
                                                <td valign="middle" style="padding-left:12px;vertical-align:middle;">
                                                    @if ($mailStoreLogo)
                                                        <img src="{{ $mailStoreLogo }}" alt="{{ $mailStoreName }}" width="150"
                                                            style="display:block;width:150px;max-width:150px;height:auto;border:0;outline:none;text-decoration:none;">
                                                        <div style="margin-top:6px;font-size:10px;letter-spacing:2.4px;text-transform:uppercase;color:#C9B98A;">Fine Jewellery</div>
                                                    @else
                                                        <div style="font-family:Georgia,'Times New Roman',serif;font-size:22px;line-height:24px;letter-spacing:2px;color:#FFFFFF;">{{ mb_strtoupper($mailStoreName) }}<span style="color:#D9BE7A;">.</span></div>
                                                        <div style="margin-top:5px;font-size:10px;letter-spacing:2.4px;text-transform:uppercase;color:#C9B98A;">Fine Jewellery</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td align="right" valign="middle" style="vertical-align:middle;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#CFE0D9;white-space:nowrap;">
                                        <div style="display:inline-block;border:1px solid #B08D3C;color:#E8D9AE;font-size:10px;font-weight:bold;letter-spacing:1.8px;text-transform:uppercase;padding:8px 13px;border-radius:20px;white-space:nowrap;">@yield('header_note')</div>
                                        @if ($mailStorePhone)
                                            <div style="margin-top:9px;font-size:12px;letter-spacing:0;text-transform:none;color:#B9CFC6;">{{ $mailStorePhone }}</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#0F3D32;padding:0 32px 20px;">
                            <div style="border-top:1px solid #2A5A4E;padding-top:12px;font-size:11px;letter-spacing:1.6px;color:#7FA294;text-transform:uppercase;">&#9670;&nbsp;&nbsp;&#9670;&nbsp;&nbsp;&#9670;&nbsp;&nbsp;<span style="color:#D9BE7A;">Handcrafted &bull; Certified &bull; Insured shipping</span></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="height:4px;line-height:4px;font-size:0;background-color:#B08D3C;">&nbsp;</td>
                    </tr>

                    {{-- ===== Headline ===== --}}
                    <tr>
                        <td class="email-pad" style="padding:30px 38px 6px;background-color:#FFFFFF;border-top:4px solid #B08D3C;">
                            @hasSection('eyebrow')
                                <div style="margin:0 0 12px;">
                                    <span style="display:inline-block;background-color:#FBF3E2;border:1px solid #EADFC3;color:#8A6A1F;font-size:11px;font-weight:bold;letter-spacing:1.6px;text-transform:uppercase;padding:7px 14px;border-radius:20px;">@yield('eyebrow')</span>
                                </div>
                            @endif
                            <h1 class="email-h1" style="margin:0;font-family:Georgia,'Times New Roman',serif;font-size:28px;line-height:35px;font-weight:normal;color:#0F3D32;">
                                @yield('heading')
                            </h1>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:16px;">
                                <tr>
                                    <td width="52" style="border-top:2px solid #B08D3C;font-size:0;line-height:0;">&nbsp;</td>
                                    <td width="26" align="center" style="font-size:11px;line-height:11px;color:#B08D3C;padding:0 6px;">&#10022;</td>
                                    <td style="border-top:1px solid #EDE6D8;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ===== Body ===== --}}
                    <tr>
                        <td class="email-pad" style="padding:18px 38px 8px;font-size:15px;line-height:24px;color:#2E2A24;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- ===== Call to action ===== --}}
                    @hasSection('cta')
                        <tr>
                            <td class="email-cta-pad" align="center" style="padding:16px 38px 6px;background-color:#FFFFFF;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#FAF7F0;border:1px dashed #D9CFB8;border-radius:14px;">
                                    <tr>
                                        <td align="center" style="padding:20px 20px 18px;">
                                            <div style="margin:0 0 13px;font-size:11px;letter-spacing:1.8px;text-transform:uppercase;color:#8A8377;font-weight:bold;">Continue in one tap</div>
                                            @yield('cta')
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    {{-- ===== Sign-off ===== --}}
                    <tr>
                        <td class="email-pad" style="padding:14px 38px 10px;background-color:#FFFFFF;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#EFF5F1;border-left:4px solid #0F3D32;border-radius:0 12px 12px 0;">
                                <tr>
                                    <td style="padding:14px 18px;">
                                        <p style="margin:0;font-size:14px;line-height:22px;color:#0F3D32;">@yield('signoff', 'Thank you for shopping with us.')</p>
                                        <p style="margin:6px 0 0;font-size:13px;color:#5F7A70;">&mdash; The {{ $mailStoreName }} team</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ===== Assurance strip ===== --}}
                    <tr>
                        <td class="email-pad" style="padding:14px 38px 26px;background-color:#FFFFFF;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-top:1px solid #F1EADC;">
                                <tr>
                                    <td style="padding-top:16px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td align="center" width="33%" style="font-size:11px;line-height:17px;color:#8A8377;padding:0 6px;">
                                                    <div style="font-size:16px;color:#0F3D32;">&#10003;</div>
                                                    <div style="margin-top:4px;font-weight:bold;color:#4A443C;">Certified</div>Authentic pieces
                                                </td>
                                                <td align="center" width="33%" style="font-size:11px;line-height:17px;color:#8A8377;padding:0 6px;border-left:1px solid #F1EADC;border-right:1px solid #F1EADC;">
                                                    <div style="font-size:16px;color:#0F3D32;">&#9670;</div>
                                                    <div style="margin-top:4px;font-weight:bold;color:#4A443C;">Insured</div>Secure delivery
                                                </td>
                                                <td align="center" width="33%" style="font-size:11px;line-height:17px;color:#8A8377;padding:0 6px;">
                                                    <div style="font-size:16px;color:#0F3D32;">&#9825;</div>
                                                    <div style="margin-top:4px;font-weight:bold;color:#4A443C;">Support</div>Easy returns
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ===== Footer ===== --}}
                    <tr>
                        <td style="background-color:#0C2E26;padding:24px 34px 22px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td align="center" style="font-family:Georgia,'Times New Roman',serif;font-size:15px;letter-spacing:2px;color:#FFFFFF;">{{ mb_strtoupper($mailStoreName) }}<span style="color:#D9BE7A;">.</span></td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top:10px;font-size:12px;line-height:20px;color:#9DBFB3;">
                                        @if ($mailStoreAddress)
                                            <div>{{ $mailStoreAddress }}</div>
                                        @endif
                                        @if ($mailStoreEmail)
                                            <div style="margin-top:3px;">
                                                <a href="mailto:{{ $mailStoreEmail }}" style="color:#E8D9AE;text-decoration:none;font-weight:bold;">{{ $mailStoreEmail }}</a>
                                                @if ($mailStorePhone)
                                                    <span style="color:#3E6B5E;">&nbsp;&middot;&nbsp;</span><span style="color:#C9DCD4;">{{ $mailStorePhone }}</span>
                                                @endif
                                            </div>
                                        @endif
                                        @hasSection('footer_note')
                                            <div style="margin-top:8px;color:#7FA294;">@yield('footer_note')</div>
                                        @endif
                                    </td>
                                </tr>
                                @if ($mailSocials->isNotEmpty())
                                    <tr>
                                        <td align="center" style="padding-top:14px;">
                                            @foreach ($mailSocials as $mailSocialLabel => $mailSocialUrl)
                                                <a href="{{ $mailSocialUrl }}" style="display:inline-block;color:#E8D9AE;font-size:12px;font-weight:bold;text-decoration:none;border:1px solid #2A5A4E;padding:7px 13px;border-radius:20px;margin:0 3px;">{{ $mailSocialLabel }}</a>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td align="center" style="padding-top:16px;border-top:1px solid #1E4A40;font-size:11px;line-height:18px;color:#6E9A8C;">
                                        &copy; {{ date('Y') }} {{ $mailStoreName }}. All rights reserved.
                                        <div style="margin-top:4px;">This is a transactional message about your account or order.</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>

</html>
