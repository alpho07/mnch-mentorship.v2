<x-mail::message>
    # New Report Submitted for Validation

    A facility has submitted an indicator report and it is awaiting your review.

    <x-mail::panel>
        **Facility:** {{ $period->facility->name }}
@if($period->facility->mfl_code)
            **MFL Code:** {{ $period->facility->mfl_code }}
        @endif
        **Report Type:** {{ $period->reportType->name }}
        **Period:** {{ $period->period_label }}
        **Submitted By:** {{ $submittedBy->name }} ({{ $submittedBy->email }})
        **Submitted At:** {{ $period->submitted_at?->format('d M Y, H:i') }}
@if($period->notes)
            **Notes from Facility:** {{ $period->notes }}
        @endif
    </x-mail::panel>

    Please log in to review the submission, check the indicator values, and either validate or return it for corrections.

    <x-mail::button :url="route('filament.admin.pages.indicators.view-submission', ['period' => $period->id])">
        Review Submission
    </x-mail::button>

    If you believe this report was submitted to you in error, please contact the system administrator.

    Thanks,
    {{ config('app.name') }} — MNCH Mentorship Platform
</x-mail::message>