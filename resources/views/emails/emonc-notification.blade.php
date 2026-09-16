<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $heading }}</title>
    <style>
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 0; }
        .wrapper { max-width: 560px; margin: 32px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #1d4ed8, #4f46e5); padding: 28px 32px; color: #fff; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .header p { margin: 6px 0 0; opacity: 0.85; font-size: 13px; }
        .body { padding: 28px 32px; font-size: 14px; line-height: 1.7; }
        .body p { margin: 0 0 16px; }
        .action { margin: 24px 0; }
        .action a { display: inline-block; padding: 12px 24px; background: #1d4ed8; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .footer { padding: 20px 32px; background: #f8fafc; font-size: 12px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $heading }}</h1>
            <p>MNCH Mentorship Platform · Ministry of Health, Kenya</p>
        </div>
        <div class="body">
            <p>Hello {{ $user->first_name ?? $user->name }},</p>
            <p>{{ $message }}</p>
            @if($actionUrl && $actionText)
                <div class="action">
                    <a href="{{ $actionUrl }}">{{ $actionText }}</a>
                </div>
            @endif
        </div>
        <div class="footer">
            This is an automated notification from the MNCH Mentorship Platform.<br>
            If you have questions, contact your mentor or system administrator.
        </div>
    </div>
</body>
</html>
