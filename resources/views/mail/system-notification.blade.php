<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#09090b;color:#e4e4e7;font-family:Arial,sans-serif">
    <div style="max-width:600px;margin:0 auto;padding:32px 20px">
        <div style="border:1px solid #27272a;border-radius:16px;background:#18181b;padding:28px">
            <p style="margin:0 0 8px;color:#22d3ee;font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase">PlayerSaloons</p>
            <h1 style="margin:0 0 16px;color:#fff;font-size:24px">{{ $notificationTitle }}</h1>
            <p style="margin:0;color:#a1a1aa;font-size:15px;line-height:1.65">{{ $notificationMessage }}</p>
            <a href="{{ $actionUrl ? url($actionUrl) : config('app.url') }}" style="display:inline-block;margin-top:24px;border-radius:10px;background:#4f46e5;padding:12px 18px;color:#fff;text-decoration:none;font-size:13px;font-weight:700">Open PlayerSaloons</a>
        </div>
    </div>
</body>
</html>
