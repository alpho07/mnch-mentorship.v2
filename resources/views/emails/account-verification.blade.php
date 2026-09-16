<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Verify Your MNCH Account</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                background: #f1f5f9;
                color: #1e293b;
                -webkit-font-smoothing: antialiased;
            }
            .wrapper {
                max-width: 600px;
                margin: 40px auto;
                background: #ffffff;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            }
            .header {
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                padding: 36px 40px;
                text-align: center;
            }
            .header h1 {
                color: #ffffff;
                font-size: 22px;
                font-weight: 700;
                letter-spacing: -0.3px;
            }
            .header p {
                color: rgba(255,255,255,0.75);
                font-size: 13px;
                margin-top: 6px;
            }
            .body {
                padding: 40px;
            }
            .greeting {
                font-size: 18px;
                font-weight: 600;
                color: #0f172a;
                margin-bottom: 16px;
            }
            .text {
                font-size: 15px;
                line-height: 1.75;
                color: #475569;
                margin-bottom: 16px;
            }
            .role-badge {
                display: inline-block;
                background: #ede9fe;
                color: #5b21b6;
                font-size: 13px;
                font-weight: 700;
                padding: 5px 14px;
                border-radius: 20px;
                margin: 2px 0 24px;
            }
            .btn-wrap {
                text-align: center;
                margin: 32px 0 24px;
            }
            .btn {
                display: inline-block;
                background: linear-gradient(135deg, #4f46e5, #7c3aed);
                color: #ffffff !important;
                text-decoration: none !important;
                font-size: 16px;
                font-weight: 600;
                padding: 15px 40px;
                border-radius: 8px;
            }
            .expiry {
                text-align: center;
                font-size: 13px;
                color: #94a3b8;
                margin-bottom: 28px;
            }
            .divider {
                border: none;
                border-top: 1px solid #e2e8f0;
                margin: 28px 0;
            }
            .fallback-label {
                font-size: 12px;
                color: #94a3b8;
                margin-bottom: 6px;
            }
            .fallback-url {
                font-size: 12px;
                color: #6366f1;
                word-break: break-all;
                line-height: 1.6;
            }
            .footer {
                background: #f8fafc;
                border-top: 1px solid #e2e8f0;
                padding: 24px 40px;
                text-align: center;
            }
            .footer p {
                font-size: 12px;
                color: #94a3b8;
                line-height: 1.7;
            }
            .footer strong {
                color: #64748b;
            }
        </style>
    </head>
    <body>
    <div class="wrapper">

        <div class="header">
            <h1>MNCH Mentorship Platform</h1>
            <p>Maternal, Newborn and Child Health — Kenya</p>
        </div>

        <div class="body">

            <p class="greeting">Hello {{ $user->full_name }},</p>

            <p class="text">
                    An account has been created for you on the
                <strong>MNCH Kenya Mentorship Platform</strong> as:
            </p>

            <div><span class="role-badge">{{ $roleName }}</span></div>

            <p class="text">
                    Please verify your account and set a secure password of your choice.
                    Once complete you will be logged in automatically and can begin using the platform.
            </p>

            <div class="btn-wrap">
                <a href="{{ $verificationUrl }}" class="btn">Verify &amp; Set My Password</a>
            </div>

            <p class="expiry">⏳ This link expires in <strong>7 days</strong>.</p>

            <hr class="divider">

            <p class="fallback-label">If the button doesn't work, copy and paste this link into your browser:</p>
            <p class="fallback-url">{{ $verificationUrl }}</p>

        </div>

        <div class="footer">
            <p>
                <strong>MNCH Kenya Mentorship Platform</strong><br>
                This email was sent because an administrator created an account on your behalf.<br>
                    If you did not expect this, please contact your programme coordinator.
            </p>
        </div>

    </div>
</body>
</html>