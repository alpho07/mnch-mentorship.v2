<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentDepartment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HealthProductsSeeder extends Seeder
{
    private const DEPARTMENTS = ['Skills lab', 'NBU', 'Maternity', 'Theatre', 'Paediatric ward'];

    private const NICU_GATE = ['question_code' => 'INFRA_HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'];

    private const PICU_GATE = ['question_code' => 'INFRA_HAS_PICU', 'operator' => 'equals', 'value' => 'Yes'];

    private const NICU_OR_PICU_GATE = ['operator' => 'or', 'conditions' => [self::NICU_GATE, self::PICU_GATE]];

    private const SKILLS_LAB_GATE = ['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'Yes'];

    /**
     * Category => ordered list of rows. A row is either:
     *   - a plain string (single commodity, no split)
     *   - [groupLabel, [item, item, ...]] (split into lettered/indented commodities)
     *   - [name, gateFlag] (single commodity, individually gated — see GATE_FLAGS)
     *   - [groupLabel, [item, ...], gateFlag] (split AND gated on every item)
     * Within a split group's item list, an item is either a plain string, or
     * [name, 'no_nbu'] to exclude that one item from the NBU/Newborn
     * department while every other item in the group still applies there.
     */
    private const CATEGORIES = [
        'AIRWAY' => [
            'Functional suction machine (including ability for pressure adjustment)',
            ['Suction catheters size', ['Fr-6', 'Fr-8', 'Fr-10', 'Fr-12']],
            'Penguin Sucker',
            ['Oropharyngeal Airway of appropriate sizes', ['00', '0', '1', '2', '3', '4']],
            // Sizes 4.0 and up are for PICU-age patients, not newborns —
            // excluded from NBU specifically; the group as a whole only
            // shows at all once NICU or PICU is confirmed available.
            ['ETT', ['2.5', '3.0', '3.5', ['4.0', 'no_nbu'], ['4.5', 'no_nbu'], ['5.0', 'no_nbu'], ['5.5', 'no_nbu'], ['6.0', 'no_nbu']], 'nicu_or_picu'],
            ['Magill forceps', 'nicu'],
            ['Umbilical vein catheters', 'nicu'],
            ['Umbilical artery catheters', 'nicu'],
            ['Oxygen source', ['piped', 'Cylinder', 'Concentrator']],
            'Can each child receive oxygen individually. If no, are there Oxygen splitters',
            'BVM device 200-300mls',
            'BVM device 500ml and 750ml',
            ["BVM masks' sizes", ['00', '0', '1']],
            'neonatal non rebreather mask',
            'Pulse oximeter with neonatal probes and paediatric probes',
            'Neonatal nasal prongs',
            'Paediatric non rebreather masks',
            'Do you have CPAP. If yes: Number of complete functional CPAP units, Are accessories available',
            'Metered Dose Inhaler',
            'Spacer and mask',
            'Paediatric Nebulising Kit',
        ],
        'CIRCULATION' => [
            'Stethoscope',
            'Patient monitor with neonatal cuffs',
            'Cardiac monitor with paediatric BP cuffs',
            ['IV cannulas-Gauge', ['26', '24', '22']],
            ['Syringes', ['2cc', '5cc', '10cc', '20cc']],
            ['Needles', ['G21', 'G22', 'G23', 'G24', 'G25']],
            'Intraosseuos needle or bone marrow needle 15-18G',
            '3-way stop cock',
            'Solusets',
            'giving sets',
            'blood transfusion set',
            'Perfuser lines',
            ['Sample bottles', ['EDTA', 'Biochemistry', 'Blood culture bottle', 'urine', 'stool', 'CSF bottles']],
            'IV line dressing (transparent)',
            'Medical adhesive',
            ['Urinary catheters', ['4', '6']],
            'Urine bag',
            'Stethoscope',
        ],
        'DISABILITY' => [
            'Functional glucometer with strips',
            'Lancets',
            ['NG tube (newborn sizes)', ['4', '5', '6']],
            ['NG tube', ['8', '10', '12']],
        ],
        'EXPOSURE' => [
            'Digital Thermometer',
            'Radiant warmer/rescuscitaire with a temperature probe',
            '2 dry baby wraps/towel',
            'Plastic wrap',
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
            'MUAC Tape',
            'Weighing Scale',
            'Infantometer and Stadiometer',
            'Tape measure',
        ],
        'MEDICINE/DRUGS' => [
            'Adrenaline',
            'Vitamin K 2mg',
            'TEO',
            'Chlorhexidine digluconate 7.1%',
            'Caffeine citrate',
            ['surfactant', 'nicu_or_picu'],
            ['Preterm supplements', ['Multivitamins', 'Vitamin D 400 IU', 'Folate tabs', 'Iron', 'Calcium']],
            'Phenobarbital',
            ['Midazolam', 'nicu_or_picu'],
            'Diazepam',
            'Leviteracetam',
            'Phenytoin',
            'Artesunate',
            'Crystalline penicilln',
            'Gentamycin',
            'Ceftriaxone',
            'Ceftazidime/cefepime/cefotaxime',
            'Amoxicillin DT',
            'Metronidazole',
            'Amikacin',
            'AL tablets',
            'Paracetamol',
            'Lasix',
            '10%Dextrose',
            'Ringer lactate',
            '50% Dextrose',
            'Normal saline',
            'Water for injection',
            'KCl',
            'Resomal',
            'Zinc sulphate/ORS-copack',
            ['Therapeutic feeds', ['F75', 'F100', 'RUTF']],
            ['Insulin', ['Soluble insulin', ['long acting insulin', 'no_nbu']]],
            ['Salbutamol respirator solution', [['Salbutamol inhaler', 'no_nbu']]],
            'Prednisone',
            'Budesonide inhaler',
            'Ipratropium bromide',
            'Distilled water',
        ],
        'OTHERS' => [
            'Room thermometer',
            'Wall clock',
            'Pen Torch',
            'Reference material (Guidelines, Drug index)',
            'complete MOH newborn inpatient file (EMR/ Physical)',
            'Calibrated cup and saucer',
            'Nifty cup',
            'flannels',
            'Space heater for warmth',
            'Phototherapy machine with a light meter',
            'Procedure tray',
            'Kidney dish',
            'Bed/ couch and Linen',
            'Food colour- red and green',
        ],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        AssessmentSection::firstOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'health_products'],
            ['name' => 'Health Products and Technologies', 'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'is_scored' => true, 'order' => 6, 'is_active' => true]
        );

        $departments = collect(self::DEPARTMENTS)->map(fn ($name, $order) => AssessmentDepartment::updateOrCreate(
            ['assessment_type_id' => $type->id, 'slug' => Str::slug($name)],
            [
                'name' => $name,
                'order' => $order + 1,
                'is_active' => true,
                'display_conditions' => $name === 'Skills lab' ? self::SKILLS_LAB_GATE : null,
            ]
        ));
        $departmentIds = $departments->pluck('id')->all();
        $nbuId = $departments->firstWhere('name', 'NBU')?->id;
        $departmentIdsExcludingNbu = array_values(array_diff($departmentIds, [$nbuId]));

        // One-time cleanup: 'Fr12' was renamed to 'Fr-12', and the 'ETT'
        // group label was briefly renamed to 'ETT (Size 2 - Size4)' then
        // reverted back to plain 'ETT' — createCommodity()'s natural key
        // includes name/group_label, so each rename creates a new row via
        // updateOrCreate rather than updating the old one, leaving the
        // stale rows orphaned on any environment that ran an earlier
        // revision of this seeder.
        Commodity::whereIn('commodity_category_id', CommodityCategory::where('assessment_type_id', $type->id)->pluck('id'))
            ->where(fn ($q) => $q->where('name', 'Fr12')->orWhere('group_label', 'ETT (Size 2 - Size4)'))
            ->delete();

        $categoryOrder = 0;
        foreach (self::CATEGORIES as $categoryName => $rows) {
            $categoryOrder++;
            $category = CommodityCategory::updateOrCreate(
                ['assessment_type_id' => $type->id, 'slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'order' => $categoryOrder]
            );

            $order = 0;
            foreach ($rows as $row) {
                $order = $this->seedRow($category, $row, $order, $departmentIds, $departmentIdsExcludingNbu);
            }
        }

        $commodityCount = Commodity::whereIn('commodity_category_id', CommodityCategory::where('assessment_type_id', $type->id)->pluck('id'))->count();
        $this->command->info("  ✓ health_products: 5 departments, 8 categories, {$commodityCount} commodities.");
    }

    private function seedRow(CommodityCategory $category, mixed $row, int $order, array $departmentIds, array $departmentIdsExcludingNbu): int
    {
        // Single item, plain: 'Name'
        if (is_string($row)) {
            $this->createCommodity($category, $row, null, 0, ++$order, null, $departmentIds);

            return $order;
        }

        // Single item, gated: ['Name', 'nicu'|'picu'|'nicu_or_picu']
        if (count($row) === 2 && is_string($row[1]) && $this->isGateFlag($row[1])) {
            $this->createCommodity($category, $row[0], null, 0, ++$order, $this->gateFor($row[1]), $departmentIds);

            return $order;
        }

        // Split group: [groupLabel, [items]] or [groupLabel, [items], gateFlag]
        [$groupLabel, $items] = $row;
        $gate = isset($row[2]) && $this->isGateFlag($row[2]) ? $this->gateFor($row[2]) : null;

        foreach ($items as $item) {
            if (is_array($item)) {
                [$itemName, $itemFlag] = $item;
                $itemDepartmentIds = $itemFlag === 'no_nbu' ? $departmentIdsExcludingNbu : $departmentIds;
            } else {
                $itemName = $item;
                $itemDepartmentIds = $departmentIds;
            }

            $this->createCommodity($category, $itemName, $groupLabel, 1, ++$order, $gate, $itemDepartmentIds);
        }

        return $order;
    }

    private function isGateFlag(string $value): bool
    {
        return in_array($value, ['nicu', 'picu', 'nicu_or_picu'], true);
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
        // the source spreadsheet) — seeds as two distinct rows rather than
        // silently collapsing into one on re-seed. Preserves the literal
        // spreadsheet content instead of assuming it's a duplication error.
        $commodity = Commodity::updateOrCreate(
            ['commodity_category_id' => $category->id, 'name' => $name, 'group_label' => $groupLabel, 'order' => $order],
            [
                'indent_level' => $indentLevel,
                'is_active' => true,
                'display_conditions' => $displayConditions,
            ]
        );

        $commodity->applicableDepartments()->sync($departmentIds);
    }
}
