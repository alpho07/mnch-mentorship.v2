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

    /**
     * Category => ordered list of rows. A row is either:
     *   - a plain string (single commodity, no split)
     *   - [groupLabel, [item, item, ...]] (split into lettered/indented commodities)
     *   - [name, 'nicu'] (single commodity, individually NICU-gated)
     *   - [groupLabel, [item, ...], 'nicu'] (split AND NICU-gated on every item)
     */
    private const CATEGORIES = [
        'AIRWAY' => [
            'Functional suction machine (including ability for pressure adjustment)',
            ['Suction catheters size', ['Fr-6', 'Fr-8', 'Fr-10', 'Fr12']],
            'Penguin Sucker',
            ['Oropharyngeal Airway of appropriate sizes', ['00', '0', '1', '2', '3', '4']],
            ['ETT', ['2.5', '3.0', '3.5', '4.0', '4.5', '5.0', '5.5', '6.0'], 'nicu'],
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
            ['surfactant', 'nicu'],
            ['Preterm supplements', ['Multivitamins', 'Vitamin D 400 IU', 'Folate tabs', 'Iron', 'Calcium']],
            'Phenobarbital',
            ['Midazolam', 'nicu'],
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
            ['Insulin', ['Soluble insulin', 'long acting insulin']],
            ['Salbutamol respirator solution', ['Salbutamol inhaler']],
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
            ['name' => $name, 'order' => $order + 1, 'is_active' => true]
        ));
        $departmentIds = $departments->pluck('id')->all();

        $categoryOrder = 0;
        foreach (self::CATEGORIES as $categoryName => $rows) {
            $categoryOrder++;
            $category = CommodityCategory::updateOrCreate(
                ['assessment_type_id' => $type->id, 'slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'order' => $categoryOrder]
            );

            $order = 0;
            foreach ($rows as $row) {
                $order = $this->seedRow($category, $row, $order, $departmentIds);
            }
        }

        $commodityCount = Commodity::whereIn('commodity_category_id', CommodityCategory::where('assessment_type_id', $type->id)->pluck('id'))->count();
        $this->command->info("  ✓ health_products: 5 departments, 8 categories, {$commodityCount} commodities.");
    }

    private function seedRow(CommodityCategory $category, mixed $row, int $order, array $departmentIds): int
    {
        // Single item, plain: 'Name'
        if (is_string($row)) {
            $this->createCommodity($category, $row, null, 0, ++$order, null, $departmentIds);

            return $order;
        }

        // Single item, NICU-gated: ['Name', 'nicu']
        if (count($row) === 2 && $row[1] === 'nicu') {
            $this->createCommodity($category, $row[0], null, 0, ++$order, self::NICU_GATE, $departmentIds);

            return $order;
        }

        // Split group: [groupLabel, [items]] or [groupLabel, [items], 'nicu']
        [$groupLabel, $items] = $row;
        $nicuGated = ($row[2] ?? null) === 'nicu';

        foreach ($items as $item) {
            $this->createCommodity($category, $item, $groupLabel, 1, ++$order, $nicuGated ? self::NICU_GATE : null, $departmentIds);
        }

        return $order;
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
