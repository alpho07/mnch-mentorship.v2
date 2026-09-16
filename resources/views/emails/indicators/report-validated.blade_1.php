<x-mail::message>
    # Report Returned for Corrections

    Your indicator report has been reviewed and returned for corrections before it can be validated.

    <x-mail::panel>
        **Facility:** {{ $period->facility->name }}
        **Report Type:** {{ $period->reportType->name }}
        **Period:** {{ $period->period_label }}
        **Reviewed By:** {{ $period->validatedByUser?->name ?? 'System' }}
        **Reviewed At:** {{ $period->validated_at?->format('d M Y, H:i') }}
    </x-mail::panel>

    <x-mail::panel>
        **Reason for Return:**

        {{ $period->rejection_reason }}
    </x-mail::panel>

    Please log in, review the feedback above, make the necessary corrections, and resubmit.

    <x-mail::button :url="route('filament.admin.pages.indicators.fill-report', ['period' => $period->id])" color="error">
        Open Report & Correct
    </x-mail::button>

    If you have questions about what corrections are needed, please contact your county mentor.

    Thanks,
    {{ config('app.name') }} — MNCH Mentorship Platform
</x-mail::message>