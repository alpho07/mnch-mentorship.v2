<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentChecklist;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class ChecklistsSeeder extends Seeder
{
    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        $this->seedOrtCorner($type);
        $this->seedTriage($type);
        $this->seedSkillsLab($type);

        $this->command->info('  ✓ 3 checklists seeded (ORT Corner, Triage requirements, Skills Lab).');
    }

    private function seedOrtCorner(AssessmentType $type): void
    {
        $checklist = AssessmentChecklist::firstOrCreate(
            ['assessment_type_id' => $type->id, 'title' => 'ORT Corner checklist']
        );

        $items = [
            ['Clean spoons', 6], ['Plastic buckets (with lids for infection prevention)', 3],
            ['Buckets – for storing cups, spoons,', 1], ['Small plastic cups (50-100ml & 100-200ml & 500mls)', 6],
            ['1 litre Calibrated measuring jars', 2], ['Table Trays', 2], ['Wash Basins', 2],
            ['Water boiling equipment', 1], ['Waste Bin', 1], ['Functinal Wall Clock', 1],
            ['Table- for mixing ORS', 1], ['Benches/chair(s), comfortable seats', 6],
            ['Hand Washing Facility/Point e.g. tippy taps and new technologies and soap', 1],
            ['Safe water source', 1], ['Chlorine for disinfection', null],
            ['Low osmolarity ORS/Zinc copack /Resomal', null], ['ORT monitoring tools (Register, summary sheets etc)', 1],
        ];

        foreach ($items as $order => [$label, $qty]) {
            $checklist->items()->updateOrCreate(
                ['label' => $label],
                ['qty' => $qty, 'order' => $order + 1]
            );
        }
    }

    private function seedTriage(AssessmentType $type): void
    {
        $checklist = AssessmentChecklist::firstOrCreate(
            ['assessment_type_id' => $type->id, 'title' => 'Triage requirements']
        );

        $items = [
            'Table', 'Chairs', 'Paediatric stethoscopes', 'Vital signs monitor', 'Digital thermometer',
            'Handheld pulse oximeter with infant and paediatrics probes',
            'BP machines with a range of cuff sizes (newborns, infants, older children and adolescents)',
            'Weighing scales (infant and older children)', 'Stadiometer', 'Tape measures (MUAC tapes, Breslow tapes)',
            'Examination couch', 'Heating source', 'Computer', 'Storage cabinets', 'Hand washing point',
            'Alcohol-based hand rub (isopropyl alcohol 75%-500ml)', 'Disposable hand towels',
        ];

        foreach ($items as $order => $label) {
            $checklist->items()->updateOrCreate(
                ['label' => $label],
                ['qty' => null, 'order' => $order + 1]
            );
        }
    }

    private function seedSkillsLab(AssessmentType $type): void
    {
        $checklist = AssessmentChecklist::firstOrCreate(
            ['assessment_type_id' => $type->id, 'title' => 'Skills Lab Checklist Requirements']
        );

        $equipment = [
            'neonatal manikin with inflatable lungs',
            'preterm manikin with open nares and mouth for OGT, NGT and CPAP demonstration',
            'mama breast',
            'Radiant Warmer',
            'Suction Machine',
        ];
        $stationery = ['Flip charts', 'White board markers'];

        foreach ($equipment as $order => $label) {
            $checklist->items()->updateOrCreate(
                ['label' => $label, 'group_label' => 'EQUIPMENT'],
                ['qty' => null, 'order' => $order + 1]
            );
        }
        foreach ($stationery as $order => $label) {
            $checklist->items()->updateOrCreate(
                ['label' => $label, 'group_label' => 'STATIONERY'],
                ['qty' => null, 'order' => $order + 10]
            );
        }
    }
}
