<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Co-Mentor Invitation — MNCH Mentorship Platform</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f1f5f9;
                color: #1e293b;
                padding: 32px 16px;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .wrapper { max-width: 560px; width: 100%; margin: 0 auto; }
            .card {
                background: #fff;
                border-radius: 14px;
                box-shadow: 0 4px 24px rgba(0,0,0,0.06);
                overflow: hidden;
            }
            .brand-bar {
                background: linear-gradient(135deg, #1d4ed8 0%, #4f46e5 100%);
                padding: 28px 36px;
                text-align: center;
                color: #fff;
            }
            .brand-bar h1 {
                font-size: 18px;
                font-weight: 700;
                letter-spacing: 0.04em;
            }
            .brand-bar p {
                font-size: 12px;
                opacity: 0.75;
                margin-top: 4px;
            }
            .content { padding: 36px; }
            .badge {
                display: inline-block;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                padding: 4px 12px;
                border-radius: 100px;
                margin-bottom: 16px;
            }
            .badge-success { background: #dbeafe; color: #1e40af; }
            .badge-error { background: #fee2e2; color: #991b1b; }
            h2 {
                font-size: 22px;
                font-weight: 800;
                color: #0f172a;
                margin-bottom: 12px;
                line-height: 1.3;
            }
            .description {
                font-size: 15px;
                color: #475569;
                line-height: 1.7;
                margin-bottom: 24px;
            }
            .info-box {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-left: 4px solid #1d4ed8;
                border-radius: 10px;
                padding: 18px 20px;
                margin-bottom: 24px;
            }
            .info-box-title {
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
            .actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 12px 28px;
                border-radius: 10px;
                font-size: 15px;
                font-weight: 700;
                text-decoration: none;
                border: none;
                cursor: pointer;
                transition: opacity 0.15s;
            }
            .btn:hover { opacity: 0.9; }
            .btn-primary {
                background: linear-gradient(135deg, #1d4ed8, #4f46e5);
                color: #fff;
            }
            .btn-danger {
                background: #fee2e2;
                color: #991b1b;
            }
            .error-box {
                background: #fee2e2;
                border: 1px solid #fecaca;
                border-radius: 10px;
                padding: 18px 20px;
                color: #991b1b;
                font-size: 14px;
                line-height: 1.6;
            }
            .footer {
                text-align: center;
                padding: 20px 36px;
                font-size: 12px;
                color: #94a3b8;
                line-height: 1.6;
            }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <div class="card">
                <div class="brand-bar">
                    <h1>MNCH Mentorship Platform</h1>
                    <p>Ministry of Health · Kenya</p>
                </div>

                <div class="content">
                    @if($success)
                        <div class="badge badge-success">Invitation Accepted</div>
                        <h2>{{ $success }}</h2>
                        <p class="description">
                            You are now a co-mentor for <strong>{{ $training->title ?? 'this mentorship' }}</strong>.
                            You can access it from your mentor dashboard.
                        </p>
                        <div class="actions">
                            <a href="{{ route('filament.admin.home') }}" class="btn btn-primary">Go to Dashboard</a>
                        </div>
                    @elseif($error)
                        <div class="badge badge-error">Invitation Unavailable</div>
                        <h2>This invitation cannot be used</h2>
                        <div class="error-box">
                            {{ $error }}
                        </div>
                    @else
                        <div class="badge badge-success">Co-Mentor Invitation</div>
                        <h2>You've been invited to co-mentor</h2>
                        <p class="description">
                            <strong>{{ $inviter?->full_name ?? 'The lead mentor' }}</strong> invited you to collaborate on the mentorship below. Review the details and accept or decline the invitation.
                        </p>

                        <div class="info-box">
                            <div class="info-box-title">Mentorship Details</div>
                            <div class="info-row">
                                <span class="info-label">Mentorship</span>
                                <span class="info-value">{{ $training->title ?? '—' }}</span>
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
                                <span class="info-label">Invited By</span>
                                <span class="info-value">{{ $inviter?->full_name ?? '—' }}</span>
                            </div>
                        </div>

                        <form method="POST" action="{{ url('/co-mentor/accept/'.$token) }}">
                            @csrf
                            <div class="actions">
                                <button type="submit" name="action" value="accept" class="btn btn-primary">Accept Invitation</button>
                                <button type="submit" name="action" value="decline" class="btn btn-danger">Decline</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            <div class="footer">
                MNCH Mentorship Platform · Ministry of Health, Kenya<br>
                If you believe this invitation was sent in error, please contact the lead mentor.
            </div>
        </div>
    </body>
</html>
