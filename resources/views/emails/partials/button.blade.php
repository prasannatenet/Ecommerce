@php
    // Solid brand button with a gold edge. $url + $label are required.
@endphp
@if (!empty($url) && !empty($label))
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="border-collapse:separate;margin:0 auto;">
        <tr>
            <td align="center" bgcolor="#0F3D32" style="border-radius:10px;border:1px solid #0F3D32;border-bottom:3px solid #B08D3C;">
                <a href="{{ $url }}" style="display:inline-block;padding:14px 34px;font-size:15px;font-weight:bold;line-height:20px;color:#FFFFFF;text-decoration:none;letter-spacing:0.6px;border-radius:10px;">{{ $label }}&nbsp;&nbsp;&#8594;</a>
            </td>
        </tr>
    </table>
@endif
