<?php

namespace App\Services;

use App\Models\Assessment;
use Illuminate\Support\Collection;

class AssessmentComparisonService
{
    public function __construct(private AssessmentPdfReportService $reportService)
    {
    }

    public function getComparableAssessments(Assessment $assessment): Collection
    {
        return Assessment::where('facility_id', $assessment->facility_id)
            ->where('assessment_type_id', $assessment->assessment_type_id)
            ->whereNull('deleted_at')
            ->get()
            ->sortBy(fn (Assessment $a) => [$a->roundSortWeight(), $a->assessment_date?->timestamp ?? 0])
            ->values();
    }

    public function prepareComparisonData(Assessment $assessment): ?array
    {
        $siblings = $this->getComparableAssessments($assessment);

        if ($siblings->count() < 2) {
            return null;
        }

        $rounds = $siblings->map(fn (Assessment $a) => [
            'id' => $a->id,
            'label' => $a->round_display,
        ])->values()->toArray();

        $perAssessmentData = $siblings->mapWithKeys(
            fn (Assessment $a) => [$a->id => $this->reportService->prepareReportData($a)]
        );

        return [
            'rounds' => $rounds,
            'overallScore' => $this->mergeSimpleValues($perAssessmentData, $rounds, 'overallScore'),
            'humanResources' => $this->mergeByKey($perAssessmentData, $rounds, 'humanResourcesDetails.responses', 'cadre', [
                'total_in_facility', 'etat_plus', 'comprehensive_newborn_care', 'imnci', 'type_1_diabetes', 'essential_newborn_care',
            ]),
            'infrastructure' => $this->mergeByKey($perAssessmentData, $rounds, 'infrastructureDetails.responses', 'question', ['response']),
            'infrastructureBeds' => $this->mergeByKey($perAssessmentData, $rounds, 'infrastructureDetails.beds_table', 'unit', ['functional', 'non_functional', 'total']),
            'skillsLab' => $this->mergeByKey($perAssessmentData, $rounds, 'skillsLabDetails.responses', 'question', ['response']),
            'informationSystems' => $this->mergeByKey($perAssessmentData, $rounds, 'informationSystemsDetails.responses', 'question', ['response']),
            'informationSystemsDataTools' => $this->mergeByKey($perAssessmentData, $rounds, 'informationSystemsDetails.data_tools_table', 'form', ['available', 'completeness']),
            'qualityYesNo' => $this->mergeByKey($perAssessmentData, $rounds, 'qualityOfCareDetails.yes_no_array', 'question', ['response']),
            'qualitySelect' => $this->mergeByKey($perAssessmentData, $rounds, 'qualityOfCareDetails.select_array', 'question', ['response']),
            'qualityNewbornStats' => $this->mergeByKey($perAssessmentData, $rounds, 'qualityOfCareDetails.newborn_stats_array', 'question', ['response']),
            'qualityPaedStats' => $this->mergeByKey($perAssessmentData, $rounds, 'qualityOfCareDetails.paed_stats_array', 'question', ['response']),
            'healthProducts' => $this->mergeHealthProducts($perAssessmentData, $rounds),
        ];
    }

    /**
     * @param  array<int, array{id: int, label: string}>  $rounds
     */
    private function mergeByKey(Collection $perAssessmentData, array $rounds, string $dataPath, string $keyField, array $valueFields): array
    {
        $rows = [];
        $order = [];

        foreach ($rounds as $round) {
            $list = data_get($perAssessmentData[$round['id']], $dataPath, []);

            foreach ($list as $item) {
                $key = $item[$keyField] ?? '-';

                if (! isset($rows[$key])) {
                    $rows[$key] = ['label' => $key, 'values' => []];
                    $order[] = $key;
                }

                $rows[$key]['values'][$round['id']] = array_intersect_key($item, array_flip($valueFields));
            }
        }

        return array_values(array_map(fn ($key) => $rows[$key], $order));
    }

    private function mergeSimpleValues(Collection $perAssessmentData, array $rounds, string $dataPath): array
    {
        $values = [];

        foreach ($rounds as $round) {
            $values[$round['id']] = data_get($perAssessmentData[$round['id']], $dataPath, []);
        }

        return $values;
    }

    private function mergeHealthProducts(Collection $perAssessmentData, array $rounds): array
    {
        $departments = [];
        $deptOrder = [];

        foreach ($rounds as $round) {
            $data = data_get($perAssessmentData[$round['id']], 'healthProductsDetails', []);

            foreach ($data as $departmentName => $dept) {
                if (! isset($departments[$departmentName])) {
                    $departments[$departmentName] = ['categories' => [], 'categoryOrder' => []];
                    $deptOrder[] = $departmentName;
                }

                foreach ($dept['categories'] as $category) {
                    $catName = $category['name'];

                    if (! isset($departments[$departmentName]['categories'][$catName])) {
                        $departments[$departmentName]['categories'][$catName] = ['name' => $catName, 'items' => [], 'itemOrder' => []];
                        $departments[$departmentName]['categoryOrder'][] = $catName;
                    }

                    foreach ($category['items'] as $item) {
                        $itemName = $item['name'];

                        if (! isset($departments[$departmentName]['categories'][$catName]['items'][$itemName])) {
                            $departments[$departmentName]['categories'][$catName]['items'][$itemName] = ['name' => $itemName, 'values' => []];
                            $departments[$departmentName]['categories'][$catName]['itemOrder'][] = $itemName;
                        }

                        $departments[$departmentName]['categories'][$catName]['items'][$itemName]['values'][$round['id']] = $item['available'];
                    }
                }
            }
        }

        $result = [];

        foreach ($deptOrder as $departmentName) {
            $dept = $departments[$departmentName];
            $categories = [];

            foreach ($dept['categoryOrder'] as $catName) {
                $cat = $dept['categories'][$catName];
                $items = [];

                foreach ($cat['itemOrder'] as $itemName) {
                    $items[] = $cat['items'][$itemName];
                }

                $categories[] = ['name' => $catName, 'items' => $items];
            }

            $result[$departmentName] = ['categories' => $categories];
        }

        return $result;
    }
}
