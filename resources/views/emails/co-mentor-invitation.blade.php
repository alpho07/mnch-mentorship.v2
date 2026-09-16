<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Co-Mentor Invitation — {{ $training->title }}</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f1f5f9;
                color: #1e293b;
                padding: 32px 16px;
            }
            .wrapper { max-width: 580px; margin: 0 auto; }
            .brand-bar {
                background: linear-gradient(135deg, #1d4ed8 0%, #4f46e5 100%);
                border-radius: 14px 14px 0 0;
                padding: 28px 36px;
                text-align: center;
            }
            .brand-logo {
                display: inline-flex;
                align-items: center;
                gap: 10px;
            }
            .brand-icon {
                width: 40px;
                height: 40px;
                background: rgba(255,255,255,0.2);
                border-radius: 10px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
            }
            .brand-name {
                color: #fff;
                font-size: 16px;
                font-weight: 700;
                letter-spacing: 0.04em;
            }
            .brand-sub {
                color: rgba(255,255,255,0.75);
                font-size: 11px;
                margin-top: 2px;
                letter-spacing: 0.04em;
            }
            .card {
                background: #fff;
                padding: 36px;
                border-left: 1px solid #e2e8f0;
                border-right: 1px solid #e2e8f0;
            }
            .badge-new {
                display: inline-block;
                background: #dbeafe;
                border: 1px solid #93c5fd;
                color: #1e40af;
                font-size: 11px;
                font-weight: 600;
                padding: 3px 10px;
                border-radius: 100px;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                margin-bottom: 16px;
            }
            h1 {
                font-size: 22px;
                font-weight: 800;
                color: #0f172a;
                line-height: 1.3;
                margin-bottom: 10px;
            }
            .greeting {
                font-size: 15px;
                color: #475569;
                line-height: 1.7;
                margin-bottom: 24px;
            }
            .greeting strong { color: #0f172a; }
            .mentorship-box {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-left: 4px solid #1d4ed8;
                border-radius: 10px;
                padding: 18px 20px;
                margin-bottom: 24px;
            }
            .mentorship-box-title {
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                color: #94a3b8;
                margin-bottom: 10px;
            }
            .info-row {
                display: flex;
                gap: 8px;
                margin-bottom: 6px;
                font-size: 13.5px;
            }
            .info-label {
                color: #94a3b8;
                font-weight: 500;
                min-width: 90px;
                flex-shrink: 0;
            }
            .info-value {
                color: #1e293b;
                font-weight: 600;
            }
            .personal-message {
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
                border-radius: 10px;
                padding: 18px 20px;
                margin-bottom: 24px;
                font-size: 14px;
                color: #14532d;
                line-height: 1.6;
            }
            .personal-message strong {
                display: block;
                margin-bottom: 6px;
                color: #166534;
            }
            .cta-wrap {
                text-align: center;
                margin: 28px 0 20px;
            }
            .cta-btn {
                display: inline-block;
                background: linear-gradient(135deg, #1d4ed8, #4f46e5);
                color: #fff !important;
                text-decoration: none;
                font-size: 15px;
                font-weight: 700;
                padding: 14px 36px;
                border-radius: 10px;
                letter-spacing: 0.02em;
                box-shadow: 0 4px 14px rgba(29,78,216,0.35);
            }
            .link-fallback {
                font-size: 12px;
                color: #94a3b8;
                text-align: center;
                line-height: 1.6;
                margin-bottom: 24px;
            }
            .link-fallback a {
                color: #1d4ed8;
                word-break: break-all;
            }
            .steps-title {
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #64748b;
                margin-bottom: 12px;
            }
            .step {
                display: flex;
                gap: 12px;
                margin-bottom: 10px;
                align-items: flex-start;
                font-size: 13px;
                color: #475569;
                line-height: 1.5;
            }
            .step-num {
                width: 22px;
                height: 22px;
                min-width: 22px;
                background: #1d4ed8;
                color: #fff;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 11px;
                font-weight: 700;
                margin-top: 1px;
            }
            .divider {
                height: 1px;
                background: #f1f5f9;
                margin: 24px 0;
            }
            .footer {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-top: none;
                border-radius: 0 0 14px 14px;
                padding: 20px 36px;
                text-align: center;
                font-size: 11px;
                color: #94a3b8;
                line-height: 1.7;
            }
            .footer a {
                color: #1d4ed8;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
    <div class="wrapper">
        <div class="brand-bar">
            <div class="brand-logo">
                <div class="brand-icon">🏥</div>
                <div>
                    <div class="brand-name">MNCH Mentorship Platform</div>
                    <div class="brand-sub">Ministry of Health · Kenya</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="badge-new">✉️ Co-Mentor Invitation</div>

            <h1>You've been invited to co-mentor a facility mentorship</h1>

            <div class="greeting">
                Hello, <strong>{{ $coMentor->user->full_name ?? 'Mentor' }}</strong>!<br>
                <strong>{{ $training->mentor->name ?? 'The lead mentor' }}</strong> has invited you to collaborate as a co-mentor
                for the <strong>{{ $training->program->name ?? 'MNCH Mentorship Program' }}</strong> at
                <strong>{{ $training->facility->name ?? 'your facility' }}</strong>.
            </div>

            <div class="mentorship-box">
                <div class="mentorship-box-title">Mentorship Details</div>
                <div class="info-row">
                    <span class="info-label">Mentorship</span>
                    <span class="info-value">{{ $training->title }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Program</span>
                    <span class="info-value">{{ $training->program->name ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Facility</span>
                    <span class="info-value">{{ $training->facility->name ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Lead Mentor</span>
                    <span class="info-value">{{ $training->mentor->name ?? '—' }}</span>
                </div>
                @if($training->start_date)
                    <div class="info-row">
                        <span class="info-label">Start Date</span>
                        <span class="info-value">{{ $training->start_date->format('d M Y') }}</span>
                    </div>
                @endif
                @if($training->end_date)
                    <div class="info-row">
                        <span class="info-label">End Date</span>
                        <span class="info-value">{{ $training->end_date->format('d M Y') }}</span>
                    </div>
                @endif
            </div>

            @if($invitationMessage)
                <div class="personal-message">
                    <strong>Personal message from {{ $training->mentor->name ?? 'the lead mentor' }}:</strong>
                    {{ $invitationMessage }}
                </div>
            @endif

            <div class="cta-wrap">
                <a href="{{ $invitationLink }}" class="cta-btn">Accept Invitation →</a>
            </div>

            <div class="link-fallback">
                If the button doesn't work, copy this link into your browser:<br>
                <a href="{{ $invitationLink }}">{{ $invitationLink }}</a>
            </div>

            <div class="divider"></div>

            <div class="steps-title">What happens next</div>
            <div class="step">
                <div class="step-num">1</div>
                <div>Click the button above to review the invitation.</div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div>Log in with your existing MNCH credentials.</div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div>Accept the invitation to gain access to the mentorship classes and mentees.</div>
            </div>
        </div>

        <div class="footer">
            This invitation was sent by <strong>{{ $training->mentor->name ?? 'the lead mentor' }}</strong>
            via the MNCH Mentorship Platform.<br>
            Ministry of Health, Kenya &nbsp;·&nbsp;
            If you believe this was sent in error, contact the lead mentor.<br><br>
            <a href="{{ config('app.url') }}">{{ config('app.url') }}</a>
        </div>
    </div>
    </body>
</html>
