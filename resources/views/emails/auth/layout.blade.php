<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $appName }}</title>
</head>
<body style="margin:0;padding:0;background:#09090b;color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#09090b;margin:0;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#18181b;border:1px solid #27272a;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td style="padding:26px 26px 18px;text-align:center;background:#0f0f12;border-bottom:1px solid #27272a;">
                            <img src="{{ $logoUrl }}" width="54" height="54" alt="{{ $appName }}" style="display:block;margin:0 auto 12px;border-radius:12px;">
                            <div style="font-size:18px;line-height:24px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#ffffff;">
                                {{ $appName }}
                            </div>
                            <div style="margin-top:6px;font-size:12px;line-height:18px;color:#a1a1aa;">
                                Competitive tournaments, wallet rewards, and head-to-head duels.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 26px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 26px 24px;border-top:1px solid #27272a;background:#111113;text-align:center;">
                            <div style="font-size:12px;line-height:18px;color:#71717a;">
                                This automated account email was sent by {{ $appName }}.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
