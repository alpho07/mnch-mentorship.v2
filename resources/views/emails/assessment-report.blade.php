<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MNCH Assessment Report</title>
    <style>
        body { margin: 0; padding: 0; background: #F3F4F6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #374151; }
        .wrapper { max-width: 580px; margin: 32px auto; }
        .card { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #065F46 0%, #059669 100%); padding: 32px 32px 28px; }
        .header-badge { display: inline-block; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); border-radius: 20px; padding: 4px 14px; font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.85); letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 12px; }
        .header h1 { margin: 0 0 6px; color: #fff; font-size: 22px; font-weight: 800; line-height: 1.2; }
        .header p { margin: 0; color: rgba(255,255,255,0.65); font-size: 14px; }
        .body { padding: 28px 32px; }
        .intro { font-size: 14px; line-height: 1.7; color: #4B5563; margin: 0 0 24px; }
        .stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
        .stat { background: #F0FDF4; border: 1px solid #A7F3D0; border-radius: 10px; padding: 12px 18px; flex: 1; min-width: 110px; }
        .stat-label { font-size: 11px; font-weight: 700; color: #065F46; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .stat-value { font-size: 20px; font-weight: 800; color: #064E3B; line-height: 1; }
        .grade-green  { background: #D1FAE5; border-color: #6EE7B7; }
        .grade-yellow { background: #FEF3C7; border-color: #FDE68A; }
        .grade-yellow .stat-label, .grade-yellow .stat-value { color: #92400E; }
        .grade-red    { background: #FEE2E2; border-color: #FCA5A5; }
        .grade-red .stat-label, .grade-red .stat-value { color: #991B1B; }
        .note { background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 10px; padding: 14px 18px; font-size: 13px; color: #6B7280; line-height: 1.6; margin-bottom: 24px; }
        .note strong { color: #374151; }
        .footer { padding: 18px 32px; background: #F9FAFB; border-top: 1px solid #E5E7EB; font-size: 12px; color: #9CA3AF; text-align: center; line-height: 1.6; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="header">
            <div class="header-badge">MNCH Mentorship Platform</div>
            <h1>Assessment Report</h1>
            <p>
                {{ $assessment->facility->name ?? 'Facility' }}
                &middot;
                {{ $assessment->assessment_date instanceof \Carbon\Carbon
                    ? $assessment->assessment_date->format('d M Y')
                    : $assessment->assessment_date }}
            </p>
        </div>

        <div class="body">
            <p class="intro">
                Please find the attached MNCH Assessment Report for
                <strong>{{ $assessment->facility->name ?? 'the assessed facility' }}</strong>.
                The full scored report is included as a PDF attachment.
            </p>

            <div class="stats">
                @if($assessment->overall_percentage !== null)
                <div class="stat {{ $assessment->overall_grade ? 'grade-' . $assessment->overall_grade : '' }}">
                    <div class="stat-label">Overall Score</div>
                    <div class="stat-value">{{ number_format($assessment->overall_percentage, 1) }}%</div>
                </div>
                @endif

                @if($assessment->overall_grade)
                <div class="stat {{ 'grade-' . $assessment->overall_grade }}">
                    <div class="stat-label">Grade</div>
                    <div class="stat-value">{{ ucfirst($assessment->overall_grade) }}</div>
                </div>
                @endif

                <div class="stat">
                    <div class="stat-label">Type</div>
                    <div class="stat-value" style="font-size:15px;">{{ ucfirst(str_replace('_', ' ', $assessment->assessment_type ?? '')) }}</div>
                </div>
            </div>

            <div class="note">
                <strong>Facility details:</strong>
                {{ $assessment->facility->name ?? '—' }}
                @if($assessment->facility->mfl_code)
                    &middot; MFL {{ $assessment->facility->mfl_code }}
                @endif
                @if($assessment->facility->subcounty)
                    &middot; {{ $assessment->facility->subcounty->name ?? '' }}
                @endif
                @if($assessment->facility->subcounty?->county)
                    &middot; {{ $assessment->facility->subcounty->county->name ?? '' }} County
                @endif
            </div>
        </div>

        <div class="footer">
            This report was shared via the MNCH Mentorship Platform mobile assessment app.<br>
            Please do not reply to this email.
        </div>
    </div>
</div>
</body>
</html>
