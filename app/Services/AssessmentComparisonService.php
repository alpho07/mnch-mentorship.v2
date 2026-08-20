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

    /**
     * The rounds a report should actually compare against: everything up
     * to and including the assessment being viewed, in round order — never
     * rounds that come after it. Viewing the baseline report must show
     * just the baseline, not a comparison against a midline/endline that
     * didn't exist yet when it was done (or was done after, workflow-wise,
     * even if it exists in the database by the time someone opens this
     * report). Viewing the midline compares baseline+midline; viewing the
     * endline compares all three; and so on.
     */
    private function comparableAssessmentsUpToCurrent(Assessment $assessment): Collection
    {
        $allSiblings = $this->getComparableAssessments($assessment);

        $currentIndex = $allSiblings->search(fn (Assessment $a) => $a->id === $assessment->id);

        if ($currentIndex === false) {
            return collect([$assessment]);
        }

        return $allSiblings->slice(0, $currentIndex + 1)->values();
    }

    public function prepareComparisonData(Assessment $assessment): ?array
    {
        $siblings = $this->comparableAssessmentsUpToCurrent($assessment);

        if ($siblings->count() < 2) {
            return null;
        }

        $rounds = $siblings->map(fn (Assessment $a) => [
            'id' => $a->id,
            'label' => $a->round_display,
            'date' => $a->assessment_date,
        ])->values()->toArray();

        $perAssessmentData = $siblings->mapWithKeys(
            fn (Assessment $a) => [$a->id => $this->reportService->prepareReportData($a)]
        );

        return [
            'rounds' => $rounds,
            'overallScore' => $this->mergeSimpleValues($perAssessmentData, $rounds, 'overallScore'),
            'sectionScores' => $this->mergeByKey($perAssessmentData, $rounds, 'sectionScores', 'section_name', ['percentage', 'score', 'max_score']),
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
            'indicatorsNewborn' => $this->attachIndicatorDeltas(
                $this->mergeByKey($perAssessmentData, $rounds, 'indicatorsDetails.newborn_array', 'question', ['response']),
                $rounds, false
            ),
            'indicatorsPaediatric' => $this->attachIndicatorDeltas(
                $this->mergeByKey($perAssessmentData, $rounds, 'indicatorsDetails.paediatric_array', 'question', ['response']),
                $rounds, false
            ),
            'indicatorsNewbornProportions' => $this->attachIndicatorDeltas(
                $this->mergeByKey($perAssessmentData, $rounds, 'indicatorsDetails.newborn_proportions_array', 'question', ['response']),
                $rounds, true
            ),
            'indicatorsPaediatricProportions' => $this->attachIndicatorDeltas(
                $this->mergeByKey($perAssessmentData, $rounds, 'indicatorsDetails.paediatric_proportions_array', 'question', ['response']),
                $rounds, true
            ),
        ];
    }

    /**
     * Adds a 'delta' entry to each row, comparing the two most recent
     * rounds shown (the last two entries in $rounds) regardless of how
     * many rounds are displayed in total — "current vs previous" always
     * means the latest pair, not the full span back to baseline.
     *
     * Raw counts (indicatorsNewborn/Paediatric) carry a plain numeric
     * 'response' — delta is (current - previous) plus a percent-change
     * relative to the previous value. Proportions
     * (indicatorsNewborn/PaediatricProportions) instead store 'response'
     * as a pre-formatted "45.0% (12/27)" string — delta there is a
     * percentage-point difference (current% - previous%), not a "percent
     * change of a percentage," which would misleadingly compound two
     * different units. Rows where either side is missing or non-numeric
     * (unanswered, "N/A") get delta => null rather than a fabricated value.
     *
     * @param  array<int, array{id: int}>  $rounds
     */
    private function attachIndicatorDeltas(array $rows, array $rounds, bool $isProportion): array
    {
        if (count($rounds) < 2) {
            return $rows;
        }

        $currentId = $rounds[count($rounds) - 1]['id'];
        $previousId = $rounds[count($rounds) - 2]['id'];

        return array_map(function (array $row) use ($currentId, $previousId, $isProportion) {
            $current = $this->extractNumericIndicatorValue($row['values'][$currentId]['response'] ?? null, $isProportion);
            $previous = $this->extractNumericIndicatorValue($row['values'][$previousId]['response'] ?? null, $isProportion);

            if ($current === null || $previous === null) {
                $row['delta'] = null;

                return $row;
            }

            $diff = round($current - $previous, 1);

            $row['delta'] = [
                'is_proportion' => $isProportion,
                'diff' => $diff,
                'direction' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat'),
                // Proportions: $diff IS the percentage-point change already.
                // Raw counts: a separate percent-change relative to $previous.
                'percent_change' => (! $isProportion && $previous != 0.0)
                    ? round(($diff / $previous) * 100, 1)
                    : null,
            ];

            return $row;
        }, $rows);
    }

    /**
     * Pulls a comparable float out of a row's 'response' value. Proportion
     * rows store a formatted "45.0% (12/27)" (or "N/A") string — only the
     * leading percentage is extracted. Raw-count rows store a plain numeric
     * string already. Returns null for anything non-numeric (unanswered,
     * "N/A", free text) rather than guessing.
     */
    private function extractNumericIndicatorValue(mixed $value, bool $isProportion): ?float
    {
        if ($value === null) {
            return null;
        }

        if ($isProportion) {
            if (! preg_match('/^(-?\d+(?:\.\d+)?)%/', (string) $value, $matches)) {
                return null;
            }

            return (float) $matches[1];
        }

        return is_numeric($value) ? (float) $value : null;
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
                    // group/indent_level are per-question metadata, the
                    // same regardless of which round answered it — taken
                    // from whichever round first has this row, same as
                    // the label itself.
                    $rows[$key] = [
                        'label' => $key,
                        'group' => $item['group'] ?? null,
                        'indent_level' => (int) ($item['indent_level'] ?? 0),
                        'values' => [],
                    ];
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
                            $departments[$departmentName]['categories'][$catName]['items'][$itemName] = [
                                'name' => $itemName,
                                'group' => $item['group'] ?? null,
                                'indent_level' => (int) ($item['indent_level'] ?? 0),
                                'values' => [],
                            ];
                            $departments[$departmentName]['categories'][$catName]['itemOrder'][] = $itemName;
                        }

                        $departments[$departmentName]['categories'][$catName]['items'][$itemName]['values'][$round['id']] = [
                            'available' => $item['available'],
                            'quantity' => $item['quantity'] ?? null,
                        ];
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
