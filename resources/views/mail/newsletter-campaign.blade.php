<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#09090b;color:#e4e4e7;font-family:Arial,sans-serif">
    <div style="max-width:620px;margin:0 auto;padding:32px 20px">
        <p style="color:#a78bfa;font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase">PlayerSaloons</p>
        <h1 style="color:#fff;font-size:24px">{{ $campaign->subject }}</h1>
        <div style="white-space:pre-line;line-height:1.7;color:#d4d4d8">{{ $campaign->content }}</div>
        <hr style="margin:32px 0;border:0;border-top:1px solid #27272a">
        <p style="font-size:12px;color:#71717a">You received this because you subscribed to PlayerSaloons updates.</p>
        <a href="{{ $unsubscribeUrl }}" style="font-size:12px;color:#a78bfa">Unsubscribe from newsletter emails</a>
    </div>
</body>
</html>
