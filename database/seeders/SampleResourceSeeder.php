<?php
namespace Database\Seeders;

use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\ResourceCategory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SampleResourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some sample data
        $pdfType = ResourceType::where('slug', 'pdf-document')->first();
        $videoType = ResourceType::where('slug', 'video-tutorial')->first();
        $manualType = ResourceType::where('slug', 'manual')->first();

        $trainingCategory = ResourceCategory::where('slug', 'training-materials')->first();
        $safetyCategory = ResourceCategory::where('slug', 'safety-compliance')->first();
        $orientationCategory = ResourceCategory::where('slug', 'orientation-onboarding')->first();

        $beginnerTag = Tag::where('slug', 'beginner')->first();
        $essentialTag = Tag::where('slug', 'essential')->first();
        $staffTag = Tag::where('slug', 'staff')->first();
        $safetyTag = Tag::where('slug', 'safety-protocols')->first();

        // Get first user or create a sample one
        $author = User::first();
        if (!$author) {
            $this->command->warn('No users found. Please create a user first or run user seeders.');
            return;
        }

        $sampleResources = [
            [
                'title' => 'New Employee Orientation Guide',
                'slug' => 'new-employee-orientation-guide',
                'excerpt' => 'Comprehensive guide for new employees covering organizational structure, policies, and initial training requirements.',
                'content' => '<h2>Welcome to Our Organization</h2><p>This comprehensive orientation guide will help you understand our organizational culture, values, and operational procedures.</p><h3>What You Will Learn</h3><ul><li>Organizational structure and reporting lines</li><li>Core values and mission statement</li><li>Basic policies and procedures</li><li>Safety requirements and protocols</li><li>Communication channels and tools</li></ul><h3>Getting Started</h3><p>Your first week will include meetings with your supervisor, HR orientation sessions, and initial training modules. Please review this material before your first day.</p>',
                'meta_description' => 'Essential orientation guide for new employees covering organizational basics, policies, and initial training requirements.',
                'resource_type_id' => $pdfType?->id,
                'category_id' => $orientationCategory?->id ?? $trainingCategory?->id,
                'author_id' => $author->id,
                'status' => 'published',
                'visibility' => 'authenticated',
                'is_featured' => true,
                'is_downloadable' => true,
                'published_at' => now()->subDays(10),
                'difficulty_level' => 'beginner',
                'prerequisites' => ['None'],
                'learning_outcomes' => [
                    'Understand organizational structure',
                    'Know basic policies and procedures',
                    'Identify key contacts and resources',
                    'Complete initial safety training'
                ],
                'sort_order' => 1,
            ],
            [
                'title' => 'Fire Safety and Emergency Procedures',
                'slug' => 'fire-safety-emergency-procedures',
                'excerpt' => 'Critical safety information including fire prevention, evacuation procedures, and emergency contact information.',
                'content' => '<h2>Fire Safety Protocol</h2><p>Fire safety is everyone\'s responsibility. This guide covers prevention, response, and evacuation procedures.</p><h3>Prevention</h3><ul><li>Keep fire exits clear</li><li>Report electrical hazards immediately</li><li>Proper storage of flammable materials</li><li>Regular inspection of fire equipment</li></ul><h3>In Case of Fire</h3><ol><li>Sound the alarm</li><li>Evacuate immediately using nearest exit</li><li>Assist others if safe to do so</li><li>Proceed to assembly point</li><li>Do not use elevators</li></ol><h3>Emergency Contacts</h3><p>Fire Department: 999<br>Security: Ext. 2000<br>First Aid: Ext. 2100</p>',
                'meta_description' => 'Essential fire safety and emergency procedures for all staff including prevention tips and evacuation protocols.',
                'resource_type_id' => $manualType?->id ?? $pdfType?->id,
                'category_id' => $safetyCategory?->id,
                'author_id' => $author->id,
                'status' => 'published',
                'visibility' => 'public',
                'is_featured' => true,
                'is_downloadable' => true,
                'published_at' => now()->subDays(5),
                'difficulty_level' => 'beginner',
                'prerequisites' => ['None'],
                'learning_outcomes' => [
                    'Understand fire prevention measures',
                    'Know evacuation procedures',
                    'Identify emergency contacts',
                    'Recognize fire safety equipment'
                ],
                'sort_order' => 2,
            ],
            [
                'title' => 'Effective Communication Skills Training',
                'slug' => 'effective-communication-skills-training',
                'excerpt' => 'Interactive training module on developing professional communication skills for workplace success.',
                'content' => '<h2>Communication Excellence</h2><p>Effective communication is fundamental to professional success and positive workplace relationships.</p><h3>Key Communication Skills</h3><ul><li>Active listening techniques</li><li>Clear and concise verbal communication</li><li>Professional written communication</li><li>Non-verbal communication awareness</li><li>Conflict resolution strategies</li></ul><h3>Best Practices</h3><p>Always consider your audience, choose appropriate channels, provide clear context, and follow up when necessary.</p><h3>Interactive Exercises</h3><p>This module includes role-playing scenarios, communication assessments, and practical exercises to enhance your skills.</p>',
                'meta_description' => 'Interactive communication skills training covering verbal, written, and non-verbal communication techniques.',
                'resource_type_id' => $videoType?->id ?? $pdfType?->id,
                'category_id' => $trainingCategory?->id,
                'author_id' => $author->id,
                'status' => 'published',
                'visibility' => 'authenticated',
                'is_featured' => false,
                'is_downloadable' => false,
                'published_at' => now()->subDays(3),
                'duration' => 1800, // 30 minutes
                'difficulty_level' => 'intermediate',
                'prerequisites' => ['Basic workplace experience'],
                'learning_outcomes' => [
                    'Apply active listening techniques',
                    'Communicate clearly and professionally',
                    'Handle difficult conversations',
                    'Use appropriate communication channels'
                ],
                'sort_order' => 3,
            ],
            [
                'title' => 'Quality Improvement Fundamentals',
                'slug' => 'quality-improvement-fundamentals',
                'excerpt' => 'Introduction to quality improvement methodologies and tools for continuous organizational enhancement.',
                'content' => '<h2>Quality Improvement Overview</h2><p>Quality improvement is a systematic approach to enhancing organizational processes and outcomes.</p><h3>Core Principles</h3><ul><li>Patient/customer focus</li><li>Data-driven decision making</li><li>Continuous improvement culture</li><li>Team-based approach</li><li>Leadership commitment</li></ul><h3>Common Tools</h3><ul><li>Plan-Do-Study-Act (PDSA) cycles</li><li>Root cause analysis</li><li>Process mapping</li><li>Statistical process control</li><li>Benchmarking</li></ul><h3>Implementation Steps</h3><p>Start with small, manageable projects and gradually build capacity for larger improvements.</p>',
                'meta_description' => 'Fundamentals of quality improvement including methodologies, tools, and implementation strategies.',
                'resource_type_id' => $pdfType?->id,
                'category_id' => $trainingCategory?->id,
                'author_id' => $author->id,
                'status' => 'published',
                'visibility' => 'restricted',
                'is_featured' => false,
                'is_downloadable' => true,
                'published_at' => now()->subDays(1),
                'difficulty_level' => 'intermediate',
                'prerequisites' => ['Basic understanding of organizational processes'],
                'learning_outcomes' => [
                    'Understand QI principles',
                    'Apply basic QI tools',
                    'Develop improvement plans',
                    'Measure improvement outcomes'
                ],
                'sort_order' => 4,
            ],
            [
                'title' => 'Equipment Maintenance Checklist',
                'slug' => 'equipment-maintenance-checklist',
                'excerpt' => 'Standardized checklist for routine equipment maintenance and safety inspections.',
                'content' => '<h2>Equipment Maintenance Protocol</h2><p>Regular maintenance ensures equipment reliability, safety, and longevity.</p><h3>Daily Checks</h3><ul><li>Visual inspection for damage</li><li>Check safety devices</li><li>Verify operational status</li><li>Clean and sanitize as required</li></ul><h3>Weekly Checks</h3><ul><li>Calibration verification</li><li>Moving parts lubrication</li><li>Filter replacement if needed</li><li>Performance testing</li></ul><h3>Monthly Checks</h3><ul><li>Comprehensive safety inspection</li><li>Preventive maintenance tasks</li><li>Documentation update</li><li>Service scheduling</li></ul><h3>Documentation</h3><p>All maintenance activities must be recorded with date, time, and technician signature.</p>',
                'meta_description' => 'Comprehensive equipment maintenance checklist covering daily, weekly, and monthly inspection procedures.',
                'resource_type_id' => ResourceType::where('slug', 'checklist')->first()?->id ?? $pdfType?->id,
                'category_id' => ResourceCategory::where('slug', 'technical-documentation')->first()?->id ?? $trainingCategory?->id,
                'author_id' => $author->id,
                'status' => 'published',
                'visibility' => 'authenticated',
                'is_featured' => false,
                'is_downloadable' => true,
                'published_at' => now(),
                'difficulty_level' => 'beginner',
                'prerequisites' => ['Basic equipment familiarity'],
                'learning_outcomes' => [
                    'Perform routine inspections',
                    'Complete maintenance documentation',
                    'Identify maintenance issues',
                    'Follow safety protocols'
                ],
                'sort_order' => 5,
            ],
        ];

        foreach ($sampleResources as $resourceData) {
            $resource = Resource::create($resourceData);

            // Attach relevant tags
            $tagsToAttach = [];

            if ($resourceData['difficulty_level'] === 'beginner' && $beginnerTag) {
                $tagsToAttach[] = $beginnerTag->id;
            }

            if ($resourceData['is_featured'] && $essentialTag) {
                $tagsToAttach[] = $essentialTag->id;
            }

            if ($staffTag) {
                $tagsToAttach[] = $staffTag->id;
            }

            if (Str::contains($resourceData['title'], ['Safety', 'Emergency']) && $safetyTag) {
                $tagsToAttach[] = $safetyTag->id;
            }

            if (!empty($tagsToAttach)) {
                $resource->tags()->attach($tagsToAttach);
            }

            // Add some sample views and downloads
            $viewCount = rand(10, 100);
            $downloadCount = rand(5, 50);

            $resource->update([
                'view_count' => $viewCount,
                'download_count' => $downloadCount,
                'like_count' => rand(1, 20),
            ]);
        }
    }
}
