<?php

namespace App\Services;

use App\Mail\Indicators\ReportRejectedMail;
use App\Mail\Indicators\ReportSubmittedMail;
use App\Mail\Indicators\ReportValidatedMail;
use App\Models\Indicators\IndicatorReportPeriod;
use App\Models\User;
use App\Support\NotificationEvents;
use App\Support\SafeMailer;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class IndicatorNotificationService
{
    // ──────────────────────────────────────────────────────────────────────────
    // Submitted — notify validators
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Send "new report submitted" emails to all users who can validate reports.
     * Recipients: super_admin, admin, county_mentor_lead, national_mentor_lead.
     * Scoped by county where possible — falls back to all validators.
     */
    public function notifySubmitted(IndicatorReportPeriod $period, User $submittedBy): void
    {
        $validators = $this->resolveValidators($period);

        if ($validators->isEmpty()) {
            Log::warning('IndicatorNotification: No validators found for submitted report', [
                'period_id' => $period->id,
            ]);

            return;
        }

        foreach ($validators as $validator) {
            if (! $validator->email || ! $validator->wantsNotification(NotificationEvents::INDICATOR_REPORT_SUBMITTED, NotificationEvents::CHANNEL_MAIL)) {
                continue;
            }

            SafeMailer::send(
                $validator,
                new ReportSubmittedMail($period->load(['reportType', 'facility', 'frequency']), $submittedBy),
                NotificationEvents::INDICATOR_REPORT_SUBMITTED,
                ['period_id' => $period->id, 'user_id' => $validator->id]
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Validated — notify facility user(s)
    // ──────────────────────────────────────────────────────────────────────────

    public function notifyValidated(IndicatorReportPeriod $period): void
    {
        $recipients = $this->resolveFacilityUsers($period);

        foreach ($recipients as $user) {
            if (! $user->email || ! $user->wantsNotification(NotificationEvents::INDICATOR_REPORT_VALIDATED, NotificationEvents::CHANNEL_MAIL)) {
                continue;
            }

            SafeMailer::send(
                $user,
                new ReportValidatedMail(
                    $period->load(['reportType', 'facility', 'frequency', 'validatedByUser'])
                ),
                NotificationEvents::INDICATOR_REPORT_VALIDATED,
                ['period_id' => $period->id, 'user_id' => $user->id]
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Rejected — notify facility user(s)
    // ──────────────────────────────────────────────────────────────────────────

    public function notifyRejected(IndicatorReportPeriod $period): void
    {
        $recipients = $this->resolveFacilityUsers($period);

        foreach ($recipients as $user) {
            if (! $user->email || ! $user->wantsNotification(NotificationEvents::INDICATOR_REPORT_REJECTED, NotificationEvents::CHANNEL_MAIL)) {
                continue;
            }

            SafeMailer::send(
                $user,
                new ReportRejectedMail(
                    $period->load(['reportType', 'facility', 'frequency', 'validatedByUser'])
                ),
                NotificationEvents::INDICATOR_REPORT_REJECTED,
                ['period_id' => $period->id, 'user_id' => $user->id]
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Recipient resolution helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Resolve validator recipients for a submitted report.
     *
     * Priority:
     * 1. Users with county_mentor_lead role whose geographic scope includes the facility's county.
     * 2. Users with national_mentor_lead role (always included).
     * 3. super_admin + admin users (always included).
     */
    private function resolveValidators(IndicatorReportPeriod $period): \Illuminate\Support\Collection
    {
        $countyId = $period->facility?->subcounty?->county_id;

        $validatorRoles = ['super_admin', 'admin', 'national_mentor_lead'];

        // Base: super_admin, admin, national_mentor_lead
        $validators = User::role($validatorRoles)
            ->whereNotNull('email')
            ->get();

        // County mentor leads scoped to this facility's county
        if ($countyId) {
            $countyMentors = User::role('county_mentor_lead')
                ->whereNotNull('email')
                ->whereHas('counties', fn ($q) => $q->where('counties.id', $countyId))
                ->get();

            $validators = $validators->merge($countyMentors);
        } else {
            // No county info — notify all county mentor leads as fallback
            $validators = $validators->merge(
                User::role('county_mentor_lead')->whereNotNull('email')->get()
            );
        }

        return $validators->unique('id');
    }

    /**
     * Resolve facility-side recipients (users associated with the facility).
     *
     * Includes:
     * - The user who submitted the report (if still resolvable).
     * - All other active users linked to the same facility.
     */
    private function resolveFacilityUsers(IndicatorReportPeriod $period): \Illuminate\Support\Collection
    {
        $users = User::where('facility_id', $period->facility_id)
            ->whereNotNull('email')
            ->whereNotNull('email_verified_at')
            ->where('status', '!=', 'trainee')
            ->get();

        // Always include the submitter even if their facility_id has changed
        if ($period->submitted_by && ! $users->contains('id', $period->submitted_by)) {
            $submitter = User::find($period->submitted_by);
            if ($submitter?->email) {
                $users = $users->push($submitter);
            }
        }

        return $users->unique('id');
    }
}
