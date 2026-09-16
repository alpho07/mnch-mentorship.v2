<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $isResend ? 'Reminder' : 'Invitation' }} — {{ $class->name }}</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f1f5f9;
                color: #1e293b;
                padding: 32px 16px;
            }
            .wrapper {
                max-width: 580px;
                margin: 0 auto;
            }

            /* ── Top brand bar ── */
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
                margin-bottom: 6px;
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

            /* ── Body card ── */
            .card {
                background: #fff;
                padding: 36px;
                border-left: 1px solid #e2e8f0;
                border-right: 1px solid #e2e8f0;
            }

            .badge-resend {
                display: inline-block;
                background: #fef9c3;
                border: 1px solid #fde047;
                color: #854d0e;
                font-size: 11px;
                font-weight: 600;
                padding: 3px 10px;
                border-radius: 100px;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                margin-bottom: 16px;
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
            .greeting strong {
                color: #0f172a;
            }

            /* ── Class info box ── */
            .class-box {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-left: 4px solid #1d4ed8;
                border-radius: 10px;
                padding: 18px 20px;
                margin-bottom: 24px;
            }
            .class-box-title {
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                color: #94a3b8;
                margin-bottom: 10px;
            }
            .class-row {
                display: flex;
                gap: 8px;
                margin-bottom: 6px;
                font-size: 13.5px;
            }
            .class-label {
                color: #94a3b8;
                font-weight: 500;
                min-width: 90px;
                flex-shrink: 0;
            }
            .class-value {
                color: #1e293b;
                font-weight: 600;
            }

            /* ── Already enrolled notice ── */
            .already-notice {
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
                border-radius: 10px;
                padding: 14px 18px;
                margin-bottom: 24px;
                font-size: 13px;
                color: #15803d;
                line-height: 1.6;
            }
            .already-notice strong {
                color: #14532d;
            }

            /* ── CTA button ── */
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

            /* ── Steps ── */
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

            /* ── Divider ── */
            .divider {
                height: 1px;
                background: #f1f5f9;
                margin: 24px 0;
            }

            /* ── Ignore block ── */
            .ignore-box {
                background: #fff7ed;
                border: 1px solid #fed7aa;
                border-radius: 10px;
                padding: 14px 18px;
                font-size: 12.5px;
                color: #92400e;
                line-height: 1.7;
            }
            .ignore-box strong {
                color: #7c2d12;
            }

            /* ── Footer ── */
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

        {{-- Brand bar --}}
        <div class="brand-bar">
            <div class="brand-logo">
                <div class="brand-icon">🏥</div>
                <div>
                    <div class="brand-name">MNCH Mentorship Platform</div>
                    <div class="brand-sub">Ministry of Health · Kenya</div>
                </div>
            </div>
        </div>

        {{-- Main card --}}
        <div class="card">

            {{-- Badge --}}
        @if($isResend)
                <div class="badge-resend">🔔 Reminder</div>
            @else
                <div class="badge-new">✉️ New Invitation</div>
            @endif

            {{-- Heading --}}
            <h1>
                @if($isResend)
                    A reminder about your class invitation
                @else
                    You've been invited to a mentorship class
            @endif
                </h1>

                <div class="greeting">
                    Hello, <strong>{{ $mentee->full_name }}</strong>!<br>
            @if($isResend)
                    This is a friendly reminder that you have a pending enrollment link for the class below.
                    If you have already joined, you can safely ignore this email.
            @else
                    <strong>{{ $class->training->mentor->name ?? 'Your mentor' }}</strong> has added you
                    to a mentorship class as part of the
                    <strong>{{ $class->training->program->name ?? 'MNCH Mentorship Program' }}</strong>.
                    Please use the link below to confirm your enrollment.
            @endif
                </div>

        {{-- Class info --}}
                <div class="class-box">
                    <div class="class-box-title">Class Details</div>
                    <div class="class-row">
                        <span class="class-label">Class</span>
                        <span class="class-value">{{ $class->name }}</span>
                    </div>
                    <div class="class-row">
                        <span class="class-label">Program</span>
                        <span class="class-value">{{ $class->training->program->name ?? '—' }}</span>
                    </div>
                    <div class="class-row">
                        <span class="class-label">Facility</span>
                        <span class="class-value">{{ $class->training->facility->name ?? '—' }}</span>
                    </div>
                    <div class="class-row">
                        <span class="class-label">Mentor</span>
                        <span class="class-value">{{ $class->training->mentor->name ?? '—' }}</span>
                    </div>
            @if($class->start_date)
                    <div class="class-row">
                        <span class="class-label">Start Date</span>
                        <span class="class-value">{{ \Carbon\Carbon::parse($class->start_date)->format('d M Y') }}</span>
                    </div>
            @endif
                    <div class="class-row">
                        <span class="class-label">Modules</span>
                        <span class="class-value">{{ $class->classModules()->count() }} module(s)</span>
                    </div>
                </div>

        @if($isEmonc && count($moduleRows))
                {{-- EmONC modules & activities --}}
                <div class="class-box" style="border-left-color: #059669;">
                    <div class="class-box-title" style="color: #059669;">Modules & Activities</div>
                    @foreach($moduleRows as $row)
                        <div class="class-row" style="flex-direction: column; align-items: flex-start; margin-bottom: 12px; gap: 2px;">
                            <span class="class-value">{{ $row['module_name'] }}</span>
                            @if($row['track_name'])
                                <span class="class-value" style="font-size: 12px; color: #1d4ed8;">Track: {{ $row['track_name'] }}</span>
                            @endif
                            @if(count($row['activities']))
                                <span style="font-size: 12.5px; color: #475569;">
                                    Activities: {{ implode(', ', $row['activities']) }}
                                </span>
                            @else
                                <span style="font-size: 12.5px; color: #94a3b8;">No activities configured</span>
                            @endif
                        </div>
                    @endforeach
                </div>
        @endif

        {{-- CTA --}}
                <div class="cta-wrap">
                    <a href="{{ $enrollmentLink }}" class="cta-btn">
                {{ $isResend ? 'Open My Enrollment Link' : 'Join This Class →' }}
                    </a>
                </div>

                <div class="link-fallback">
                    If the button doesn't work, copy this link into your browser:<br>
                    <a href="{{ $enrollmentLink }}">{{ $enrollmentLink }}</a>
                </div>

                <div class="divider"></div>

                {{-- Steps --}}
                <div class="steps-title">What happens next</div>
                <div class="step">
                    <div class="step-num">1</div>
                    <div>Click the button above — it will take you to the enrollment page.</div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div>Enter your email address (<strong>{{ $mentee->email }}</strong>) to confirm your identity.</div>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <div>Log in with your existing credentials. Default password is <strong>123456</strong> if you haven't changed it.</div>
                </div>
                <div class="step">
                    <div class="step-num">4</div>
                <div>You'll be enrolled automatically and can start tracking your progress.</div>
            </div>

            <div class="divider"></div>

            {{-- Already enrolled / ignore notice --}}
            <div class="ignore-box">
                <strong>Already enrolled?</strong> If you have already clicked this link and joined the class,
                    you can safely ignore this email — no further action is needed. You will not be enrolled twice.
            </div>

        </div>

        {{-- Footer --}}
        <div class="footer">
            This invitation was sent by <strong>{{ $class->training->mentor->name ?? 'your mentor' }}</strong>
            via the MNCH Mentorship Platform.<br>
                Ministry of Health, Kenya &nbsp;·&nbsp;
            If you believe this was sent in error, contact your facility mentor.<br><br>
            <a href="{{ config('app.url') }}">{{ config('app.url') }}</a>
        </div>

    </div>
    </body>
</html>