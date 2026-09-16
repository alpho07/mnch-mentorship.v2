<?php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\MonthlyReport;

class UniqueMonthlyReport implements Rule
{
    private $facilityId;
    private $templateId;
    private $period;
    private $ignoreId;

    public function __construct($facilityId, $templateId, $period, $ignoreId = null)
    {
        $this->facilityId = $facilityId;
        $this->templateId = $templateId;
        $this->period = $period;
        $this->ignoreId = $ignoreId;
    }

    public function passes($attribute, $value)
    {
        $query = MonthlyReport::where([
            'facility_id' => $this->facilityId,
            'report_template_id' => $this->templateId,
            'reporting_period' => $this->period,
        ]);

        if ($this->ignoreId) {
            $query->where('id', '!=', $this->ignoreId);
        }

        return !$query->exists();
    }

    public function message()
    {
        return 'A monthly report for this facility, template, and period already exists.';
    }
}
