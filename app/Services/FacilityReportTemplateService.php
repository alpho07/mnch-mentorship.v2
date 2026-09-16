<?php
// FacilityReportTemplateService.php
namespace App\Services;

use App\Models\Facility;
use App\Models\ReportTemplate;
use App\Models\FacilityReportTemplate;
use Carbon\Carbon;

class FacilityReportTemplateService
{
    public function assignTemplateToFacility(
        Facility $facility,
        ReportTemplate $template,
        Carbon $startDate,
        ?Carbon $endDate = null
    ): FacilityReportTemplate {
        return FacilityReportTemplate::updateOrCreate(
            [
                'facility_id' => $facility->id,
                'report_template_id' => $template->id,
            ],
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );
    }

    public function assignTemplateToFacilities(
        array $facilityIds,
        ReportTemplate $template,
        Carbon $startDate,
        ?Carbon $endDate = null
    ): void {
        foreach ($facilityIds as $facilityId) {
            FacilityReportTemplate::updateOrCreate(
                [
                    'facility_id' => $facilityId,
                    'report_template_id' => $template->id,
                ],
                [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            );
        }
    }

    public function getActiveFacilityTemplates(Facility $facility): \Illuminate\Support\Collection
    {
        return $facility->reportTemplates()
            ->wherePivot('start_date', '<=', now())
            ->where(function ($query) {
                $query->wherePivot('end_date', '>=', now())
                      ->orWherePivot('end_date', null);
            })
            ->where('is_active', true)
            ->get();
    }
}
