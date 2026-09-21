@php
    // Renders a label/value summary card with a gold header strip.
    // $rows: array of ['label' => string, 'value' => string, 'strong' => bool]
    // $title: optional small card heading.
    $detailRows = array_values($rows ?? []);
@endphp
@if (!empty($detailRows))
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#FFFDF8;border:1px solid #E7DCC2;border-radius:14px;border-collapse:separate;overflow:hidden;">
        <tr>
            <td colspan="2" style="background-color:#0F3D32;padding:11px 18px;font-size:11px;font-weight:bold;letter-spacing:1.8px;text-transform:uppercase;color:#E8D9AE;">{{ $title ?? 'Summary' }}<span style="color:#B08D3C;">&nbsp;&nbsp;&#10022;</span></td>
        </tr>
        @foreach ($detailRows as $row)
            <tr>
                <td style="padding:12px 18px;font-size:11px;letter-spacing:0.8px;text-transform:uppercase;color:#8A8377;white-space:nowrap;vertical-align:top;{{ $loop->last ? '' : 'border-bottom:1px solid #F1EADC;' }}">{{ $row['label'] ?? '' }}</td>
                <td align="right" style="padding:12px 18px;font-size:14px;line-height:20px;vertical-align:top;{{ $loop->last ? '' : 'border-bottom:1px solid #F1EADC;' }}{{ !empty($row['strong']) ? 'font-weight:bold;color:#0F3D32;' : 'color:#24211D;' }}">{{ $row['value'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>
@endif
