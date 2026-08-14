<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentCommodityResponse;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HealthProductsSeeder extends Seeder
{
    /** @var array<int, int> ids touched this run — see pruneStaleCommodities() */
    private array $seededCommodityIds = [];

    // The 6 department columns of the Health Products matrix (source
    // spreadsheet "Assessments. v2.xlsx", row 105). Laboratory is a
    // separate, self-contained department — ported from the 2025
    // "Standard Facility Assessment" template's LABORATORY category, which
    // has no equivalent column in this matrix at all — so it's created
    // separately below and never included in these 6.
    private const DEPARTMENTS = ['Skills lab', 'NBU', 'Maternity', 'Theatre', 'Paediatric ward', 'Paediatric outpatient'];

    private const LABORATORY_DEPARTMENT = 'Laboratory';

    private const NICU_GATE = ['question_code' => 'INFRA_HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'];

    private const PICU_GATE = ['question_code' => 'INFRA_HAS_PICU', 'operator' => 'equals', 'value' => 'Yes'];

    private const NICU_OR_PICU_GATE = ['operator' => 'or', 'conditions' => [self::NICU_GATE, self::PICU_GATE]];

    private const SKILLS_LAB_GATE = ['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'Yes'];

    /**
     * Category => ordered list of rows, transcribed row-by-row from
     * "Assessments. v2.xlsx" rows 107-227 (Health Products and
     * Technologies). Every N/A marker in that range — whole-item,
     * whole-group, or single-sub-item — is represented explicitly below;
     * a row with no modifier had no N/A marker in the sheet at all.
     *
     * A row is one of:
     *   'Name'                          — single item, every department, no gate
     *   ['Name', modifier]              — single item with a modifier
     *   [groupLabel, [items]]           — split group, every department, no gate
     *   [groupLabel, [items], modifier] — split group with a group-wide modifier
     *
     * An item inside a group's item list is 'Name' or ['Name', modifier].
     * An item-level modifier's department restriction narrows (intersects
     * with) the group-level one rather than replacing it — e.g. ETT's
     * group is excluded from Theatre entirely, and sizes 4.5-6.0 are
     * additionally excluded from NBU on top of that.
     *
     * A modifier is one of:
     *   'nicu' | 'picu' | 'nicu_or_picu'            — gate only, no department restriction
     *   ['exclude' => [dept names]]                 — every department except these
     *   ['only' => [dept names]]                    — only these departments
     *   ['only'|'exclude' => [...], 'gate' => '...'] — both combined
     */
    private const CATEGORIES = [
        'AIRWAY' => [
            'Functional suction machine (including ability for pressure adjustment)',
            ['Suction catheters size', ['Fr-6', 'Fr-8', 'Fr-10', 'Fr-12']],
            'Penguin Sucker',
            ['Oropharyngeal Airway of appropriate sizes', ['00', '0', '1', '2', '3', '4'], ['exclude' => ['NBU', 'Maternity', 'Theatre']]],
            // Row 112: whole group excluded from Theatre; sizes 4.5-6.0
            // additionally excluded from NBU ("N/A in newborn 4.5 to 6" —
            // 4.0 is not listed, so it stays applicable there).
            ['Endotracheal Tubes (size 2 –size 4)', [
                '2.5', '3.0', '3.5', '4.0',
                ['4.5', ['exclude' => ['NBU']]],
                ['5.0', ['exclude' => ['NBU']]],
                ['5.5', ['exclude' => ['NBU']]],
                ['6.0', ['exclude' => ['NBU']]],
            ], ['exclude' => ['Theatre']]],
            'Magill forceps',
            'Umbilical vein catheters',
            'Umbilical artery catheters',
            ['Oxygen source', ['piped', 'Cylinder', 'Concentrator']],
            ['Can each child receive oxygen individually. If no, are there Oxygen splitters', ['exclude' => ['Theatre']]],
            'BVM device 200-300mls',
            ['BVM device 500ml and 750ml', ['exclude' => ['NBU', 'Maternity', 'Theatre']]],
            ["BVM masks' sizes", [
                ['00', ['exclude' => ['Paediatric ward']]],
                '0', '1',
                ['2', ['exclude' => ['NBU', 'Maternity', 'Theatre']]],
                ['3', ['exclude' => ['NBU', 'Maternity', 'Theatre']]],
            ]],
            'neonatal non rebreather mask',
            ['Infant non-rebreather mask', ['exclude' => ['NBU']]],
            'Pulse oximeter with neonatal probes and paediatric probes',
            'Neonatal nasal prongs',
            ['Paediatric non rebreather masks', ['exclude' => ['NBU', 'Maternity', 'Theatre']]],
            ['Do you have CPAP. If yes: Number of complete functional CPAP units, Are accessories available', ['exclude' => ['NBU', 'Maternity', 'Theatre']]],
            'Metered Dose Inhaler',
            ['Spacer and mask', ['exclude' => ['NBU', 'Theatre']]],
            ['Paediatric Nebulising Kit', ['exclude' => ['NBU', 'Theatre']]],
        ],
        'CIRCULATION' => [
            'Stethoscope',
            ['Patient monitor with neonatal cuffs', ['exclude' => ['Paediatric ward']]],
            ['Cardiac monitor with paediatric BP cuffs', ['exclude' => ['NBU', 'Maternity', 'Theatre']]],
            ['IV cannulas-Gauge', ['26', '24', '22']],
            ['Syringes', ['2cc', '5cc', '10cc', '20cc']],
            ['Needles', ['G21', 'G22', 'G23', 'G24', 'G25']],
            'Intraosseuos needle or bone marrow needle 15-18G',
            '3-way stop cock',
            'Solusets',
            'giving sets',
            'blood transfusion set',
            'Functional Infusion Pumps. If yes indicate number',
            ['Functional Syringe pumps. If yes indicate number', ['exclude' => ['Theatre']]],
            ['Perfuser lines', ['exclude' => ['Theatre']]],
            ['Sample bottles', ['EDTA', 'Biochemistry', 'Blood culture bottle', 'urine', 'stool', 'CSF bottles']],
            'IV line dressing (transparent)',
            'Medical adhesive',
            // Row 146: sizes 8 and 10 excluded from NBU/Maternity/Theatre;
            // sizes 4 and 6 apply everywhere.
            ['Urinary catheters', [
                '4', '6',
                ['8', ['exclude' => ['NBU', 'Maternity', 'Theatre']]],
                ['10', ['exclude' => ['NBU', 'Maternity', 'Theatre']]],
            ]],
            'Urine bag',
            'Stethoscope',
        ],
        'DISABILITY' => [
            'Functional glucometer with strips',
            'Lancets',
            // Row 152: excluded from NBU/Maternity/Theatre in the source
            // sheet exactly as written, despite the "newborn sizes" label —
            // transcribed literally rather than corrected.
            ['NG tube (newborn sizes)', ['4', '5', '6'], ['exclude' => ['NBU', 'Maternity', 'Theatre']]],
            ['NG tube', ['8', '10', '12']],
        ],
        'EXPOSURE' => [
            'Standard digital Thermometer',
            'Radiant warmer/rescuscitaire with a temperature probe',
            ['2 dry baby wraps/towel', ['exclude' => ['NBU', 'Paediatric ward']]],
            ['Plastic wrap', ['exclude' => ['Paediatric ward']]],
        ],
        'INFECTION PREVENTION AND CONTROL (IPC)' => [
            'Hand washing station with clean running water and liquid soap',
            ['Gloves', ['Clean', 'Sterile']],
            'Alcohol Hand Rub (at least 70% alcohol)',
            'Surgical spirit',
            'Alcohol Swabs',
            'Sharps box',
            ['Colour-coded waste disposal bins with appropriate liners. At least', ['Yellow', 'Black', 'Red']],
            'Are handwashing audits done (verify using report)',
            'Are decontamination buckets well labelled with date and time indicated(observe)',
        ],
        'NUTRITION ASSESSMENT' => [
            ['MUAC Tape', ['exclude' => ['NBU', 'Maternity', 'Theatre', 'Paediatric ward']]],
            'Weighing Scale',
            'Infantometer and Stadiometer',
            'Tape measure',
        ],
        'MEDICINE/DRUGS' => [
            'Adrenaline',
            'Vitamin K 2mg',
            'TEO',
            'Chlorhexidine digluconate 7.1%',
            ['Caffeine citrate', ['exclude' => ['Theatre', 'Paediatric ward', 'Paediatric outpatient']]],
            // Row 180: blank in NBU/Maternity (apply normally there), N/A
            // in Theatre/Paed ward/Paed outpatient, and gated to "For NICU"
            // specifically within Skills lab — a single shared gate can't
            // express "gated in one department, unconditional in others",
            // so this is two commodity rows sharing the same name.
            ['surfactant', ['exclude' => ['Skills lab', 'Theatre', 'Paediatric ward', 'Paediatric outpatient']]],
            ['surfactant', ['only' => ['Skills lab'], 'gate' => 'nicu']],
            ['Preterm supplements', ['Multivitamins', 'Vitamin D 400 IU', 'Folate tabs', 'Iron', 'Calcium'], ['exclude' => ['Skills lab', 'Theatre']]],
            'Phenobarbital',
            // Row 183: same "For NICU"-in-one-department pattern as surfactant.
            ['Midazolam', ['exclude' => ['Skills lab', 'Theatre']]],
            ['Midazolam', ['only' => ['Skills lab'], 'gate' => 'nicu']],
            ['Diazepam', ['exclude' => ['NBU']]],
            'Leviteracetam',
            'Phenytoin',
            ['Artesunate', ['exclude' => ['Theatre']]],
            'Crystalline penicilln',
            'Gentamycin',
            'Ceftriaxone',
            'Ceftazidime/cefepime/cefotaxime',
            ['Amoxicillin DT', ['exclude' => ['Theatre']]],
            ['Metronidazole', ['exclude' => ['Skills lab']]],
            ['Amikacin', ['exclude' => ['Skills lab']]],
            ['AL tablets', ['exclude' => ['NBU', 'Theatre']]],
            'Paracetamol',
            ['Lasix', ['exclude' => ['Skills lab']]],
            '10%Dextrose',
            'Ringer lactate',
            '50% Dextrose',
            'Normal saline',
            'Water for injection',
            'KCl',
            ['Resomal', ['exclude' => ['NBU', 'Theatre']]],
            ['Zinc sulphate/ORS-copack', ['exclude' => ['NBU', 'Theatre']]],
            ['Therapeutic feeds', ['F75', 'F100', 'RUTF'], ['exclude' => ['NBU', 'Theatre']]],
            // Row 207: whole group excluded from Theatre; "long acting
            // insulin" additionally excluded from NBU.
            ['Insulin', ['Soluble insulin', ['long acting insulin', ['exclude' => ['NBU']]]], ['exclude' => ['Theatre']]],
            // Row 208: whole group excluded from Theatre; "Salbutamol
            // inhaler" additionally excluded from NBU.
            ['Salbutamol', ['Salbutamol respirator solution', ['Salbutamol inhaler', ['exclude' => ['NBU']]]], ['exclude' => ['Theatre']]],
            ['Prednisone', ['exclude' => ['Skills lab', 'NBU', 'Theatre']]],
            ['Budesonide inhaler', ['exclude' => ['Skills lab', 'NBU', 'Theatre']]],
            'Distilled water',
        ],
        'OTHERS' => [
            ['Room thermometer', ['exclude' => ['Paediatric ward']]],
            'Wall clock',
            'Pen Torch',
            'Reference material (Guidelines, Drug index)',
            'complete MOH newborn inpatient file (EMR/ Physical)',
            ['complete MOH paediatric inpatient file (EMR/ Physical)', ['exclude' => ['NBU', 'Maternity', 'Theatre']]],
            ['Calibrated cup and saucer', ['exclude' => ['NBU', 'Maternity', 'Theatre', 'Paediatric ward']]],
            ['Nifty cup', ['exclude' => ['Paediatric ward']]],
            'flannels',
            'Space heater for warmth',
            'Phototherapy machine with a light meter',
            ['Procedure tray', ['exclude' => ['Paediatric ward']]],
            'Kidney dish',
            ['Bed/ couch and Linen', ['exclude' => ['NBU']]],
            ['Food colour- red and green', ['exclude' => ['NBU', 'Maternity', 'Theatre', 'Paediatric ward', 'Paediatric outpatient']]],
        ],
        // The following 3 categories aren't in the source spreadsheet's
        // Health Products matrix at all — they mirror the ChecklistsSeeder
        // reference content (shown as a read-only "View checklist" popup
        // on a single gate question) as real, individually answerable
        // commodities, scoped to one department each.
        'SKILLS LAB EQUIPMENT' => [
            ['EQUIPMENT', [
                'neonatal manikin with inflatable lungs',
                'preterm manikin with open nares and mouth for OGT ,NGT  and CPAP demonstration',
                'mama breast',
                'Radiant Warmer',
                'Suction Machine',
            ], ['only' => ['Skills lab']]],
            ['STATIONERY', [
                'Flip charts',
                'White board markers',
            ], ['only' => ['Skills lab']]],
        ],
        'TRIAGE' => [
            ['Triage', [
                'Table', 'Chairs', 'Paediatric stethoscopes', 'Vital signs monitor', 'Digital thermometer',
                'Handheld pulse oximeter with infant and paediatrics probes',
                'BP machines with a range of cuff sizes (newborns, infants, older children and adolescents)',
                'Weighing scales (infant and older children)', 'Stadiometer', 'Tape measures (MUAC tapes, Breslow tapes)',
                'Examination couch', 'Heating source', 'Computer', 'Storage cabinets', 'Hand washing point',
                'Alcohol-based hand rub (isopropyl alcohol 75%-500ml)', 'Disposable hand towels',
            ], ['only' => ['Paediatric outpatient']]],
        ],
        // "Paediatric Inpatient" in the request maps to the existing
        // 'Paediatric ward' department — the same list already seeded as
        // the ORT Corner checklist's reference content.
        'ORT CORNER' => [
            ['ORT Corner', [
                'Clean spoons', 'Plastic buckets (with lids for infection prevention)',
                'Buckets – for storing cups, spoons,', 'Small plastic cups (50-100ml & 100-200ml & 500mls)',
                '1 litre Calibrated measuring jars', 'Table Trays', 'Wash Basins', 'Water boiling equipment',
                'Waste Bin', 'Functinal Wall Clock', 'Table- for mixing ORS', 'Benches/chair(s), comfortable seats',
                'Hand Washing Facility/Point e.g. tippy taps and new technologies and soap', 'Safe water source',
                'Chlorine for disinfection', 'Low osmolarity ORS/Zinc copack /Resomal',
                'ORT monitoring tools (Register, summary sheets etc)',
            ], ['only' => ['Paediatric ward']]],
        ],
    ];

    /**
     * Ported from the 2025 "Standard Facility Assessment" template's
     * LABORATORY category — the 2026 spreadsheet has no Laboratory column
     * or section at all, but the facility still has a lab. Every item is
     * scoped to the Laboratory department alone, with no gate, matching
     * the 2025 source exactly.
     */
    private const LABORATORY_ITEMS = [
        'Blood collection tubes (EDTA, plain)',
        'Lancets',
        'Microscope',
        'Centrifuge',
        'Laboratory reagents for FBC, U&E, LFT',
        'Malaria rapid diagnostic test (mRDT)',
        'Blood culture bottles',
        'Urine dipsticks',
        'Specimen containers (urine, stool)',
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        AssessmentSection::firstOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'health_products'],
            ['name' => 'Health Products and Technologies', 'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'is_scored' => true, 'order' => 6, 'is_active' => true]
        );

        $allDepartmentNames = array_merge(self::DEPARTMENTS, [self::LABORATORY_DEPARTMENT]);
        $departments = collect($allDepartmentNames)->map(fn ($name, $order) => AssessmentDepartment::updateOrCreate(
            ['assessment_type_id' => $type->id, 'slug' => Str::slug($name)],
            [
                'name' => $name,
                'order' => $order + 1,
                'is_active' => true,
                'display_conditions' => $name === 'Skills lab' ? self::SKILLS_LAB_GATE : null,
            ]
        ));
        $deptIdsByName = $departments->pluck('id', 'name')->all();
        $allDeptIds = collect(self::DEPARTMENTS)->map(fn ($name) => $deptIdsByName[$name])->values()->all();

        $this->seededCommodityIds = [];

        $categoryOrder = 0;
        foreach (self::CATEGORIES as $categoryName => $rows) {
            $categoryOrder++;
            $category = CommodityCategory::updateOrCreate(
                ['assessment_type_id' => $type->id, 'slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'order' => $categoryOrder]
            );

            $order = 0;
            foreach ($rows as $row) {
                $order = $this->seedRow($category, $row, $order, $deptIdsByName, $allDeptIds);
            }
        }

        $categoryOrder++;
        $labCategory = CommodityCategory::updateOrCreate(
            ['assessment_type_id' => $type->id, 'slug' => 'laboratory'],
            ['name' => 'LABORATORY', 'order' => $categoryOrder]
        );
        $labDeptId = $deptIdsByName[self::LABORATORY_DEPARTMENT];
        $order = 0;
        foreach (self::LABORATORY_ITEMS as $name) {
            $this->createCommodity($labCategory, $name, null, 0, ++$order, null, [$labDeptId]);
        }

        $prunedCount = $this->pruneStaleCommodities($type);

        $commodityCount = Commodity::whereIn('commodity_category_id', CommodityCategory::where('assessment_type_id', $type->id)->pluck('id'))->count();
        $departmentCount = count($allDepartmentNames);
        $categoryCount = count(self::CATEGORIES) + 1;
        $pruneNote = $prunedCount > 0 ? ", pruned {$prunedCount} stale row(s)" : '';
        $this->command->info("  ✓ health_products: {$departmentCount} departments, {$categoryCount} categories, {$commodityCount} commodities{$pruneNote}.");
    }

    /**
     * Every category/item rename or reorder in CATEGORIES/LABORATORY_ITEMS
     * shifts createCommodity()'s natural key (category + name + group_label
     * + order) for everything seeded after the change, so updateOrCreate()
     * makes a fresh row instead of updating the old one — the old row is
     * never touched again and silently lingers (this is exactly how 70
     * stale rows accumulated in one revision of this seeder alone, e.g.
     * every item after the "Infant non-rebreather mask" insertion in
     * AIRWAY). Rather than hand-listing every renamed/shifted item here,
     * anything in this assessment type's health-products categories that
     * this run didn't touch is deleted outright — unless a real assessment
     * has already recorded a response against it, in which case it's left
     * in place (with a warning) rather than destroying that data.
     */
    private function pruneStaleCommodities(AssessmentType $type): int
    {
        $categoryIds = CommodityCategory::where('assessment_type_id', $type->id)->pluck('id');
        $staleIds = Commodity::whereIn('commodity_category_id', $categoryIds)
            ->whereNotIn('id', $this->seededCommodityIds)
            ->pluck('id');

        if ($staleIds->isEmpty()) {
            return 0;
        }

        $withResponses = AssessmentCommodityResponse::whereIn('commodity_id', $staleIds)->pluck('commodity_id')->unique();
        $deletable = $staleIds->diff($withResponses);

        if ($withResponses->isNotEmpty()) {
            $this->command->warn("  ! health_products: {$withResponses->count()} stale commodity row(s) kept — a real assessment has already recorded a response against them.");
        }

        Commodity::whereIn('id', $deletable)->each(fn (Commodity $c) => $c->applicableDepartments()->detach());

        return Commodity::whereIn('id', $deletable)->delete();
    }

    private function seedRow(CommodityCategory $category, mixed $row, int $order, array $deptIdsByName, array $allDeptIds): int
    {
        // Single item, plain: 'Name'
        if (is_string($row)) {
            $this->createCommodity($category, $row, null, 0, ++$order, null, $allDeptIds);

            return $order;
        }

        // Single item with a modifier: ['Name', modifier]
        if (count($row) === 2 && $this->isModifier($row[1])) {
            [$deptIds, $gate] = $this->resolveModifier($row[1], $deptIdsByName, $allDeptIds);
            $this->createCommodity($category, $row[0], null, 0, ++$order, $gate, $deptIds);

            return $order;
        }

        // Split group: [groupLabel, [items]] or [groupLabel, [items], modifier]
        [$groupLabel, $items] = $row;
        [$groupDeptIds, $groupGate] = $this->resolveModifier($row[2] ?? null, $deptIdsByName, $allDeptIds);

        foreach ($items as $item) {
            if (is_array($item)) {
                [$itemName, $itemModifier] = $item;
                // An item-level modifier narrows the group's already-resolved
                // set further — it never widens it back out.
                [$itemDeptIds] = $this->resolveModifier($itemModifier, $deptIdsByName, $groupDeptIds);
            } else {
                $itemName = $item;
                $itemDeptIds = $groupDeptIds;
            }

            $this->createCommodity($category, $itemName, $groupLabel, 1, ++$order, $groupGate, $itemDeptIds);
        }

        return $order;
    }

    private function isModifier(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return in_array($value, ['nicu', 'picu', 'nicu_or_picu'], true);
        }

        return is_array($value) && (isset($value['exclude']) || isset($value['only']) || isset($value['gate']));
    }

    /**
     * @return array{0: array<int, int>, 1: ?array}  [department ids, display_conditions]
     */
    private function resolveModifier(mixed $modifier, array $deptIdsByName, array $baseDeptIds): array
    {
        if ($modifier === null) {
            return [$baseDeptIds, null];
        }

        if (is_string($modifier)) {
            return [$baseDeptIds, $this->gateFor($modifier)];
        }

        $deptIds = $baseDeptIds;
        if (isset($modifier['only'])) {
            $onlyIds = array_map(fn ($name) => $deptIdsByName[$name], $modifier['only']);
            $deptIds = array_values(array_intersect($baseDeptIds, $onlyIds));
        } elseif (isset($modifier['exclude'])) {
            $excludeIds = array_map(fn ($name) => $deptIdsByName[$name], $modifier['exclude']);
            $deptIds = array_values(array_diff($baseDeptIds, $excludeIds));
        }

        $gate = isset($modifier['gate']) ? $this->gateFor($modifier['gate']) : null;

        return [$deptIds, $gate];
    }

    private function gateFor(string $flag): array
    {
        return match ($flag) {
            'nicu' => self::NICU_GATE,
            'picu' => self::PICU_GATE,
            'nicu_or_picu' => self::NICU_OR_PICU_GATE,
        };
    }

    private function createCommodity(CommodityCategory $category, string $name, ?string $groupLabel, int $indentLevel, int $order, ?array $displayConditions, array $departmentIds): void
    {
        // `order` is part of the natural key (not just category+name+group)
        // so that a name repeated verbatim within one category — e.g.
        // "Stethoscope" appears twice in CIRCULATION (rows 133 and 150 of
        // the source spreadsheet), and "surfactant"/"Midazolam" each appear
        // as two distinct rows (department-scoped vs Skills-lab+NICU-gated)
        // — seeds as separate rows rather than silently collapsing into one
        // on re-seed.
        $commodity = Commodity::updateOrCreate(
            ['commodity_category_id' => $category->id, 'name' => $name, 'group_label' => $groupLabel, 'order' => $order],
            [
                'indent_level' => $indentLevel,
                'is_active' => true,
                'display_conditions' => $displayConditions,
            ]
        );

        $commodity->applicableDepartments()->sync($departmentIds);

        $this->seededCommodityIds[] = $commodity->id;
    }
}
