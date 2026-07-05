@extends('emails.auth.layout', [
    'title' => 'Reset your PlayerSaloons password',
    'appName' => $appName,
    'logoUrl' => $logoUrl,
])

@section('content')
    <h1 style="margin:0 0 12px;font-size:24px;line-height:32px;color:#ffffff;">Reset your password</h1>

    <p style="margin:0 0 18px;font-size:15px;line-height:24px;color:#d4d4d8;">
        We received a request to reset your PlayerSaloons password. Use the secure link below to choose a new password.
    </p>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
        <tr>
            <td style="border-radius:10px;background:#7c3aed;">
                <a href="{{ $url }}" style="display:inline-block;padding:13px 22px;font-size:14px;line-height:18px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">
                    Reset Password
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 10px;font-size:13px;line-height:21px;color:#a1a1aa;">
        This link expires in {{ $expiresIn }} minutes. If the button does not work, copy and paste this link into your browser:
    </p>
    <p style="margin:0;word-break:break-all;font-size:12px;line-height:19px;color:#8b5cf6;">
        {{ $url }}
    </p>

    <p style="margin:22px 0 0;font-size:13px;line-height:21px;color:#71717a;">
        If you did not request a password reset, no further action is required.
    </p>
@endsection
