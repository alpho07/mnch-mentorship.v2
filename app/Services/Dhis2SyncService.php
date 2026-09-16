<?php

namespace App\Services;

use App\Models\Indicators\IndicatorReportPeriod;
use App\Models\Indicators\IndicatorValue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DHIS2 Sync Service
 *
 * Builds DHIS2 dataValueSet payloads from indicator_values and pushes to DHIS2.
 * UIDs on indicators and facilities must be configured before sync is available.
 *
 * DHIS2 API reference: POST /api/dataValueSets
 */
class Dhis2SyncService {

    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct() {
        $this->baseUrl = rtrim(config('services.dhis2.base_url', ''), '/');
        $this->username = config('services.dhis2.username', '');
        $this->password = config('services.dhis2.password', '');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Pre-flight checks
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Check whether a period is ready to push to DHIS2.
     * Returns array of blocking issues; empty = ready.
     */
    public function getBlockers(IndicatorReportPeriod $period): array {
        $blockers = [];

        if ($period->status !== IndicatorReportPeriod::STATUS_VALIDATED) {
            $blockers[] = 'Report must be validated before pushing to DHIS2.';
        }

        if (empty($this->baseUrl)) {
            $blockers[] = 'DHIS2 base URL is not configured (services.dhis2.base_url).';
        }

        if (empty($period->reportType->dhis2_dataset_id)) {
            $blockers[] = "Report type [{$period->reportType->name}] does not have a DHIS2 dataset ID configured.";
        }

        $orgUnitUid = $period->facility->dhis2_org_unit_uid ?? $period->facility->uid ?? null;
        if (empty($orgUnitUid)) {
            $blockers[] = "Facility [{$period->facility->name}] does not have a DHIS2 org unit UID configured.";
        }

        if (empty($period->dhis2_period)) {
            $blockers[] = 'DHIS2 period string is not computed for this report period.';
        }

        // Check at least one indicator has DHIS2 UIDs
        $hasAnyMappedIndicator = $period->values()
                ->with('indicator')
                ->get()
                ->filter(fn(IndicatorValue $v) => $v->indicator->isDhis2Ready())
                ->isNotEmpty();

        if (!$hasAnyMappedIndicator) {
            $blockers[] = 'No indicators in this report have DHIS2 UIDs configured. Set dhis2_numerator_uid / dhis2_data_element_uid on indicators first.';
        }

        return $blockers;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Payload building
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Build the DHIS2 dataValueSet payload for a period.
     * Returns the array ready to JSON-encode and POST.
     */
    public function buildPayload(IndicatorReportPeriod $period): array {
        $orgUnitUid = $period->facility->dhis2_org_unit_uid ?? $period->facility->uid;

        $dataValues = $period->values()
                ->with('indicator')
                ->get()
                ->flatMap(fn(IndicatorValue $v) => $v->toDhis2DataValues())
                ->filter()
                ->values()
                ->toArray();

        return [
            'dataSet' => $period->reportType->dhis2_dataset_id,
            'orgUnit' => $orgUnitUid,
            'period' => $period->dhis2_period,
            'dataValues' => $dataValues,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Push
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Push a validated period to DHIS2.
     * Updates the period's dhis2_push_status and dhis2_import_summary.
     *
     * @throws \RuntimeException if there are blockers
     */
    public function push(IndicatorReportPeriod $period): array {
        $blockers = $this->getBlockers($period);
        if (!empty($blockers)) {
            throw new \RuntimeException(
                            'Cannot push to DHIS2: ' . implode(' | ', $blockers)
                    );
        }

        $payload = $this->buildPayload($period);

        // Mark as pending
        $period->update([
            'dhis2_push_status' => 'pending',
            'dhis2_push_at' => now(),
        ]);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                    ->timeout(30)
                    ->post("{$this->baseUrl}/api/dataValueSets", $payload);

            $importSummary = $response->json();
            $success = $response->successful() && ($importSummary['status'] ?? '') !== 'ERROR';

            $period->update([
                'dhis2_push_status' => $success ? 'success' : 'failed',
                'dhis2_push_at' => now(),
                'dhis2_import_summary' => $importSummary,
                'status' => $success ? IndicatorReportPeriod::STATUS_PUSHED_DHIS2 : $period->status,
            ]);

            if (!$success) {
                Log::error('DHIS2 push failed', [
                    'period_id' => $period->id,
                    'http_status' => $response->status(),
                    'import_summary' => $importSummary,
                ]);
            }

            return [
                'success' => $success,
                'import_summary' => $importSummary,
                'payload' => $payload,
            ];
        } catch (\Exception $e) {
            $period->update([
                'dhis2_push_status' => 'failed',
                'dhis2_import_summary' => ['error' => $e->getMessage()],
            ]);

            Log::error('DHIS2 push exception', [
                'period_id' => $period->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Dry run
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Perform a dry-run push (importStrategy=VALIDATE_ONLY).
     * Does not persist data to DHIS2, but validates the payload.
     */
    public function dryRun(IndicatorReportPeriod $period): array {
        $payload = $this->buildPayload($period);

        $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(30)
                ->post("{$this->baseUrl}/api/dataValueSets?dryRun=true", $payload);

        return [
            'success' => $response->successful(),
            'import_summary' => $response->json(),
            'payload' => $payload,
        ];
    }
}
