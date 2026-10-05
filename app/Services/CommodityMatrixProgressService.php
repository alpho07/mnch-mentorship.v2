<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentCommodityResponse;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use Illuminate\Support\Collection;

/**
 * Derives commodity-matrix ("Health Products") completion from the answers
 * actually on record, rather than from a flag flipped the first time any one
 * department was saved.
 *
 * Two things made that flag unreliable:
 *  - it was set to true after saving a *single* department, so a matrix with
 *    Skills Lab answered and NBU untouched read as complete; and
 *  - a template may carry more than one active commodity_matrix section
 *    (template 2 has both `department_health_products` and `health_products`),
 *    while EditHealthProducts only ever flags the one it happened to resolve —
 *    leaving the other permanently "Pending" on the dashboard even once every
 *    department had been answered.
 *
 * Completion here means: for every visible department, every visible applicable
 * commodity has a response row. All commodity_matrix sections on a template
 * share that answer, because they all render the same department × commodity
 * matrix (departments and categories are scoped by assessment_type, not by
 * section).
 */
class CommodityMatrixProgressService
{
    /** @var array<int, array<int, array<int, int>>> assessmentId => [departmentId => commodityIds] */
    private array $expectedCache = [];

    /** @var array<int, Collection<int, AssessmentDepartment>> */
    private array $departmentCache = [];

    /** @var array<int, array<string, mixed>> */
    private array $questionResponseCache = [];

    /**
     * Departments the assessor can actually see for this assessment, in tab
     * order — the same visibility rules EditHealthProducts renders with.
     *
     * @return Collection<int, AssessmentDepartment>
     */
    public function visibleDepartments(Assessment $assessment): Collection
    {
        return $this->departmentCache[$assessment->id] ??= (function () use ($assessment) {
            $responsesByCode = $this->responsesByQuestionCode($assessment);

            return AssessmentDepartment::where('is_active', true)
                ->where('assessment_type_id', $assessment->assessment_type_id)
                ->orderBy('order')
                ->get()
                ->filter(fn (AssessmentDepartment $d) => $this->isBlockVisible($d->display_conditions, $responsesByCode))
                ->values();
        })();
    }

    /**
     * Commodity ids expected per department: active commodities in a visible
     * category, applicable to that department, and themselves visible under
     * the current conditional logic.
     *
     * @return array<int, array<int, int>> departmentId => commodityIds
     */
    public function expectedCommodityIdsByDepartment(Assessment $assessment): array
    {
        if (isset($this->expectedCache[$assessment->id])) {
            return $this->expectedCache[$assessment->id];
        }

        $responsesByCode = $this->responsesByQuestionCode($assessment);

        $categoryIds = CommodityCategory::where('assessment_type_id', $assessment->assessment_type_id)
            ->get()
            ->filter(fn (CommodityCategory $c) => $this->isBlockVisible($c->display_conditions, $responsesByCode))
            ->pluck('id');

        $departmentIds = $this->visibleDepartments($assessment)->pluck('id');

        $expected = array_fill_keys($departmentIds->all(), []);

        if ($categoryIds->isEmpty() || $departmentIds->isEmpty()) {
            return $this->expectedCache[$assessment->id] = $expected;
        }

        // One pass over the applicability pivot instead of a query per
        // department — the matrix runs to hundreds of commodities and this is
        // read on every dashboard and section-chrome render.
        $rows = Commodity::query()
            ->where('commodities.is_active', true)
            ->whereIn('commodities.commodity_category_id', $categoryIds)
            ->join('commodity_applicability', 'commodity_applicability.commodity_id', '=', 'commodities.id')
            ->whereIn('commodity_applicability.assessment_department_id', $departmentIds)
            ->get([
                'commodities.id',
                'commodities.display_conditions',
                'commodity_applicability.assessment_department_id as department_id',
            ]);

        foreach ($rows as $row) {
            if (! $this->isBlockVisible($row->display_conditions, $responsesByCode)) {
                continue;
            }

            $expected[$row->department_id][] = (int) $row->id;
        }

        return $this->expectedCache[$assessment->id] = $expected;
    }

    /**
     * Departments that still have at least one unanswered commodity, in tab
     * order.
     *
     * @return Collection<int, AssessmentDepartment>
     */
    public function incompleteDepartments(Assessment $assessment): Collection
    {
        $expected = $this->expectedCommodityIdsByDepartment($assessment);

        $answered = AssessmentCommodityResponse::where('assessment_id', $assessment->id)
            ->get(['assessment_department_id', 'commodity_id'])
            ->groupBy('assessment_department_id')
            ->map(fn ($rows) => $rows->pluck('commodity_id')->map(fn ($id) => (int) $id)->flip());

        return $this->visibleDepartments($assessment)
            ->filter(function (AssessmentDepartment $dept) use ($expected, $answered) {
                $expectedIds = $expected[$dept->id] ?? [];

                // Nothing to answer here — an empty department can't hold the
                // section back.
                if (empty($expectedIds)) {
                    return false;
                }

                $answeredIds = $answered->get($dept->id);

                if ($answeredIds === null) {
                    return true;
                }

                foreach ($expectedIds as $commodityId) {
                    if (! $answeredIds->has($commodityId)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * True once every visible department has every visible applicable
     * commodity answered.
     */
    public function isComplete(Assessment $assessment): bool
    {
        if ($this->visibleDepartments($assessment)->isEmpty()) {
            return false;
        }

        $expected = $this->expectedCommodityIdsByDepartment($assessment);

        // A template with no commodities at all isn't "complete" — it's
        // unconfigured, and saying otherwise would let an assessment be
        // submitted with an empty matrix.
        if (collect($expected)->flatten()->isEmpty()) {
            return false;
        }

        return $this->incompleteDepartments($assessment)->isEmpty();
    }

    /**
     * Codes of every active commodity_matrix section on the template. More
     * than one may exist, and they all describe the same matrix.
     *
     * @return array<int, string>
     */
    public function sectionCodes(Assessment $assessment): array
    {
        return $assessment->assessmentType
            ?->sections()
            ->where('is_active', true)
            ->get()
            ->filter(fn (AssessmentSection $s) => $s->resolvedKind() === 'commodity_matrix')
            ->pluck('code')
            ->all() ?? [];
    }

    /**
     * Writes the derived state onto every commodity_matrix section code in
     * section_progress, so allSectionsComplete() and the submit gate agree
     * with what the dashboard shows. Saves only when something changed — this
     * runs on ordinary page renders.
     */
    public function sync(Assessment $assessment): bool
    {
        $codes = $this->sectionCodes($assessment);

        if (empty($codes)) {
            return false;
        }

        $complete = $this->isComplete($assessment);
        $progress = $assessment->section_progress ?? [];
        $changed = false;

        foreach ($codes as $code) {
            if (($progress[$code] ?? null) !== $complete) {
                $progress[$code] = $complete;
                $changed = true;
            }
        }

        if ($changed) {
            $assessment->section_progress = $progress;
            $assessment->save();
        }

        return $complete;
    }

    /**
     * Clears memoised state — call after writing responses so a later read in
     * the same request sees them.
     */
    public function forget(Assessment $assessment): void
    {
        unset(
            $this->expectedCache[$assessment->id],
            $this->departmentCache[$assessment->id],
            $this->questionResponseCache[$assessment->id],
        );
    }

    private function responsesByQuestionCode(Assessment $assessment): array
    {
        return $this->questionResponseCache[$assessment->id] ??= AssessmentQuestionResponse::query()
            ->where('assessment_id', $assessment->id)
            ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_question_responses.assessment_question_id')
            ->pluck('assessment_question_responses.response_value', 'assessment_questions.question_code')
            ->all();
    }

    private function isBlockVisible(?array $conditions, array $responsesByCode): bool
    {
        if (empty($conditions)) {
            return true;
        }

        return ConditionalLogicEvaluator::isVisible(
            $conditions,
            fn (string $code) => $responsesByCode[$code] ?? null
        );
    }
}
