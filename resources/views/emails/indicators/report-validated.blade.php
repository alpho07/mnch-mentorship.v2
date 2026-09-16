<x-mail::message>
    # Your Report Has Been Validated ✓

    Your indicator report has been reviewed and successfully validated.

    <x-mail::panel>
        **Facility:** {{ $period->facility->name }}
        **Report Type:** {{ $period->reportType->name }}
        **Period:** {{ $period->period_label }}
        **Validated By:** {{ $period->validatedByUser?->name ?? 'System' }}
        **Validated At:** {{ $period->validated_at?->format('d M Y, H:i') }}
    </x-mail::panel>

    Your data has been accepted and is now eligible for DHIS2 synchronisation. No further action is required from you for this period.

    <x-mail::button :url="route('filament.admin.pages.indicators.view-submission', ['period' => $period->id])">
        View Validated Report
    </x-mail::button>

    **What happens next?**
    A system administrator will push the validated data to DHIS2. You will be notified if any issues arise.

    Thanks,
    {{ config('app.name') }} — MNCH Mentorship Platform
</x-mail::message>