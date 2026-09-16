<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            // SKILL LEVEL TAGS
            ['name' => 'Beginner', 'slug' => 'beginner', 'color' => '#22C55E'],
            ['name' => 'Intermediate', 'slug' => 'intermediate', 'color' => '#F59E0B'],
            ['name' => 'Advanced', 'slug' => 'advanced', 'color' => '#EF4444'],
            ['name' => 'Expert', 'slug' => 'expert', 'color' => '#8B5CF6'],

            // FORMAT TAGS
            ['name' => 'Interactive', 'slug' => 'interactive', 'color' => '#3B82F6'],
            ['name' => 'Video', 'slug' => 'video', 'color' => '#DC2626'],
            ['name' => 'Audio', 'slug' => 'audio', 'color' => '#7C3AED'],
            ['name' => 'Text', 'slug' => 'text', 'color' => '#374151'],
            ['name' => 'Downloadable', 'slug' => 'downloadable', 'color' => '#059669'],
            ['name' => 'Online Only', 'slug' => 'online-only', 'color' => '#0891B2'],

            // LEARNING TYPE TAGS
            ['name' => 'Self-Paced', 'slug' => 'self-paced', 'color' => '#16A34A'],
            ['name' => 'Instructor-Led', 'slug' => 'instructor-led', 'color' => '#2563EB'],
            ['name' => 'Hands-On', 'slug' => 'hands-on', 'color' => '#EA580C'],
            ['name' => 'Theory', 'slug' => 'theory', 'color' => '#6366F1'],
            ['name' => 'Practical', 'slug' => 'practical', 'color' => '#059669'],

            // CONTENT TAGS
            ['name' => 'Essential', 'slug' => 'essential', 'color' => '#DC2626'],
            ['name' => 'Recommended', 'slug' => 'recommended', 'color' => '#F59E0B'],
            ['name' => 'Optional', 'slug' => 'optional', 'color' => '#6B7280'],
            ['name' => 'Updated', 'slug' => 'updated', 'color' => '#10B981'],
            ['name' => 'New', 'slug' => 'new', 'color' => '#3B82F6'],
            ['name' => 'Popular', 'slug' => 'popular', 'color' => '#F59E0B'],

            // AUDIENCE TAGS
            ['name' => 'Staff', 'slug' => 'staff', 'color' => '#0891B2'],
            ['name' => 'Management', 'slug' => 'management', 'color' => '#7C3AED'],
            ['name' => 'Supervisors', 'slug' => 'supervisors', 'color' => '#059669'],
            ['name' => 'New Employees', 'slug' => 'new-employees', 'color' => '#22C55E'],
            ['name' => 'Experienced Staff', 'slug' => 'experienced-staff', 'color' => '#374151'],
            ['name' => 'Trainers', 'slug' => 'trainers', 'color' => '#8B5CF6'],
            ['name' => 'Mentors', 'slug' => 'mentors', 'color' => '#DB2777'],

            // DEPARTMENT TAGS
            ['name' => 'Clinical', 'slug' => 'clinical', 'color' => '#DC2626'],
            ['name' => 'Administrative', 'slug' => 'administrative', 'color' => '#059669'],
            ['name' => 'Support Services', 'slug' => 'support-services', 'color' => '#0891B2'],
            ['name' => 'Laboratory', 'slug' => 'laboratory', 'color' => '#7C3AED'],
            ['name' => 'Pharmacy', 'slug' => 'pharmacy', 'color' => '#16A34A'],
            ['name' => 'Nursing', 'slug' => 'nursing', 'color' => '#EC4899'],
            ['name' => 'Medical', 'slug' => 'medical', 'color' => '#DC2626'],

            // TOPIC TAGS
            ['name' => 'Patient Care', 'slug' => 'patient-care', 'color' => '#EF4444'],
            ['name' => 'Safety Protocols', 'slug' => 'safety-protocols', 'color' => '#F59E0B'],
            ['name' => 'Quality Improvement', 'slug' => 'quality-improvement', 'color' => '#3B82F6'],
            ['name' => 'Communication', 'slug' => 'communication', 'color' => '#8B5CF6'],
            ['name' => 'Leadership', 'slug' => 'leadership', 'color' => '#7C3AED'],
            ['name' => 'Emergency Response', 'slug' => 'emergency-response', 'color' => '#DC2626'],
            ['name' => 'Infection Control', 'slug' => 'infection-control', 'color' => '#059669'],
            ['name' => 'Documentation', 'slug' => 'documentation', 'color' => '#6B7280'],

            // TIME-BASED TAGS
            ['name' => 'Quick Read', 'slug' => 'quick-read', 'color' => '#10B981'],
            ['name' => '5 Minutes', 'slug' => '5-minutes', 'color' => '#22C55E'],
            ['name' => '15 Minutes', 'slug' => '15-minutes', 'color' => '#F59E0B'],
            ['name' => '30 Minutes', 'slug' => '30-minutes', 'color' => '#EF4444'],
            ['name' => '1 Hour', 'slug' => '1-hour', 'color' => '#7C3AED'],
            ['name' => 'Multi-Session', 'slug' => 'multi-session', 'color' => '#374151'],

            // CERTIFICATION TAGS
            ['name' => 'CPD Points', 'slug' => 'cpd-points', 'color' => '#3B82F6'],
            ['name' => 'Certification Required', 'slug' => 'certification-required', 'color' => '#DC2626'],
            ['name' => 'Annual Requirement', 'slug' => 'annual-requirement', 'color' => '#F59E0B'],
            ['name' => 'Compliance', 'slug' => 'compliance', 'color' => '#059669'],
            ['name' => 'Accredited', 'slug' => 'accredited', 'color' => '#7C3AED'],

            // PRIORITY TAGS
            ['name' => 'Urgent', 'slug' => 'urgent', 'color' => '#DC2626'],
            ['name' => 'High Priority', 'slug' => 'high-priority', 'color' => '#EF4444'],
            ['name' => 'Standard', 'slug' => 'standard', 'color' => '#6B7280'],
            ['name' => 'Low Priority', 'slug' => 'low-priority', 'color' => '#9CA3AF'],

            // ACCESSIBILITY TAGS
            ['name' => 'Screen Reader Compatible', 'slug' => 'screen-reader-compatible', 'color' => '#059669'],
            ['name' => 'Multilingual', 'slug' => 'multilingual', 'color' => '#3B82F6'],
            ['name' => 'Mobile Friendly', 'slug' => 'mobile-friendly', 'color' => '#EC4899'],
            ['name' => 'Offline Available', 'slug' => 'offline-available', 'color' => '#374151'],

            // HEALTH-SPECIFIC TAGS
            ['name' => 'Patient Safety', 'slug' => 'patient-safety', 'color' => '#DC2626'],
            ['name' => 'Clinical Skills', 'slug' => 'clinical-skills', 'color' => '#7C3AED'],
            ['name' => 'Medical Equipment', 'slug' => 'medical-equipment', 'color' => '#059669'],
            ['name' => 'Pharmaceuticals', 'slug' => 'pharmaceuticals', 'color' => '#16A34A'],
            ['name' => 'Diagnostics', 'slug' => 'diagnostics', 'color' => '#0891B2'],
            ['name' => 'Treatment Protocols', 'slug' => 'treatment-protocols', 'color' => '#EF4444'],

            // TECHNOLOGY TAGS
            ['name' => 'Digital Health', 'slug' => 'digital-health', 'color' => '#3B82F6'],
            ['name' => 'EMR/EHR', 'slug' => 'emr-ehr', 'color' => '#059669'],
            ['name' => 'Telemedicine', 'slug' => 'telemedicine', 'color' => '#8B5CF6'],
            ['name' => 'Software Training', 'slug' => 'software-training', 'color' => '#6366F1'],

            // SPECIAL CATEGORIES
            ['name' => 'Case Study', 'slug' => 'case-study', 'color' => '#0891B2'],
            ['name' => 'Best Practices', 'slug' => 'best-practices', 'color' => '#16A34A'],
            ['name' => 'Lessons Learned', 'slug' => 'lessons-learned', 'color' => '#F59E0B'],
            ['name' => 'Research-Based', 'slug' => 'research-based', 'color' => '#7C3AED'],
            ['name' => 'Evidence-Based', 'slug' => 'evidence-based', 'color' => '#059669'],
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(
                ['slug' => $tag['slug']],
                $tag
            );
        }

        $this->command->info('Tags seeded successfully!');
    }
}
