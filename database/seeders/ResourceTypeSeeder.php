<?php

namespace Database\Seeders;

use App\Models\ResourceType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ResourceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resourceTypes = [
            // 1. PRINT RESOURCES
            [
                'name' => 'PDF Document',
                'slug' => 'pdf-document',
                'description' => 'Portable Document Format files including manuals, guides, forms, reports, and reference materials that can be downloaded and printed.',
                'icon' => 'document-text',
                'color' => '#DC2626',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Textbook',
                'slug' => 'textbook',
                'description' => 'Comprehensive educational books and learning materials covering specific subjects or training modules in detail.',
                'icon' => 'book-open',
                'color' => '#7C3AED',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Workbook',
                'slug' => 'workbook',
                'description' => 'Interactive learning materials with exercises, activities, and practice sessions for hands-on skill development.',
                'icon' => 'pencil-square',
                'color' => '#059669',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Handout',
                'slug' => 'handout',
                'description' => 'Quick reference materials, summaries, and supplementary documents distributed during training sessions.',
                'icon' => 'document-duplicate',
                'color' => '#0891B2',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Manual',
                'slug' => 'manual',
                'description' => 'Step-by-step instructional guides for procedures, equipment operation, and standard operating procedures.',
                'icon' => 'clipboard-document-list',
                'color' => '#EA580C',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Checklist',
                'slug' => 'checklist',
                'description' => 'Systematic verification lists for ensuring all required steps or items are completed in processes.',
                'icon' => 'clipboard-document-check',
                'color' => '#16A34A',
                'is_active' => true,
                'sort_order' => 6,
            ],

            // 2. DIGITAL RESOURCES
            [
                'name' => 'Video Tutorial',
                'slug' => 'video-tutorial',
                'description' => 'Visual learning content including instructional videos, demonstrations, recorded lectures, and training sessions.',
                'icon' => 'play-circle',
                'color' => '#DC2626',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Audio Recording',
                'slug' => 'audio-recording',
                'description' => 'Audio-based learning materials including podcasts, recorded discussions, lectures, and audio books.',
                'icon' => 'speaker-wave',
                'color' => '#7C3AED',
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Interactive Module',
                'slug' => 'interactive-module',
                'description' => 'Engaging digital learning modules with quizzes, simulations, and interactive elements for enhanced learning.',
                'icon' => 'cursor-arrow-ripple',
                'color' => '#059669',
                'is_active' => true,
                'sort_order' => 9,
            ],
            [
                'name' => 'E-Book',
                'slug' => 'e-book',
                'description' => 'Digital books and publications optimized for electronic reading devices and online access.',
                'icon' => 'device-tablet',
                'color' => '#0891B2',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Online Course',
                'slug' => 'online-course',
                'description' => 'Structured learning programs delivered through web platforms with modules, assignments, and assessments.',
                'icon' => 'academic-cap',
                'color' => '#7C2D12',
                'is_active' => true,
                'sort_order' => 11,
            ],
            [
                'name' => 'Website Link',
                'slug' => 'website-link',
                'description' => 'External web resources, online tools, databases, and educational websites for additional learning.',
                'icon' => 'link',
                'color' => '#3B82F6',
                'is_active' => true,
                'sort_order' => 12,
            ],
            [
                'name' => 'Mobile App',
                'slug' => 'mobile-app',
                'description' => 'Educational mobile applications and tools for learning on smartphones and tablets.',
                'icon' => 'device-phone-mobile',
                'color' => '#EC4899',
                'is_active' => true,
                'sort_order' => 13,
            ],

            // 3. PRESENTATION MATERIALS
            [
                'name' => 'Presentation Slides',
                'slug' => 'presentation-slides',
                'description' => 'PowerPoint presentations, slide decks, and visual aids used in training sessions and meetings.',
                'icon' => 'presentation-chart-bar',
                'color' => '#F59E0B',
                'is_active' => true,
                'sort_order' => 14,
            ],
            [
                'name' => 'Infographic',
                'slug' => 'infographic',
                'description' => 'Visual representations of information, data, and knowledge designed for quick comprehension.',
                'icon' => 'chart-pie',
                'color' => '#06B6D4',
                'is_active' => true,
                'sort_order' => 15,
            ],
            [
                'name' => 'Poster',
                'slug' => 'poster',
                'description' => 'Large format visual displays for awareness campaigns, educational content, and reference materials.',
                'icon' => 'photo',
                'color' => '#8B5CF6',
                'is_active' => true,
                'sort_order' => 16,
            ],

            // 4. ASSESSMENT MATERIALS
            [
                'name' => 'Quiz',
                'slug' => 'quiz',
                'description' => 'Short assessments and knowledge checks to evaluate understanding and retention of learned material.',
                'icon' => 'question-mark-circle',
                'color' => '#EF4444',
                'is_active' => true,
                'sort_order' => 17,
            ],
            [
                'name' => 'Exam',
                'slug' => 'exam',
                'description' => 'Comprehensive assessments and formal evaluations for certification and competency measurement.',
                'icon' => 'clipboard-document-check',
                'color' => '#DC2626',
                'is_active' => true,
                'sort_order' => 18,
            ],
            [
                'name' => 'Assignment',
                'slug' => 'assignment',
                'description' => 'Learning tasks and projects assigned to participants for practical application of knowledge.',
                'icon' => 'pencil',
                'color' => '#059669',
                'is_active' => true,
                'sort_order' => 19,
            ],

            // 5. SIMULATION & PRACTICE
            [
                'name' => 'Simulation',
                'slug' => 'simulation',
                'description' => 'Virtual environments and scenarios that mimic real-world situations for safe practice and learning.',
                'icon' => 'computer-desktop',
                'color' => '#7C3AED',
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'Case Study',
                'slug' => 'case-study',
                'description' => 'Real-world scenarios and examples used for analysis, discussion, and problem-solving practice.',
                'icon' => 'document-magnifying-glass',
                'color' => '#0891B2',
                'is_active' => true,
                'sort_order' => 21,
            ],
            [
                'name' => 'Exercise',
                'slug' => 'exercise',
                'description' => 'Practical activities and drills designed to reinforce learning and develop specific skills.',
                'icon' => 'play',
                'color' => '#16A34A',
                'is_active' => true,
                'sort_order' => 22,
            ],

            // 6. REFERENCE MATERIALS
            [
                'name' => 'Job Aid',
                'slug' => 'job-aid',
                'description' => 'Quick reference tools and performance support materials used during actual work performance.',
                'icon' => 'wrench-screwdriver',
                'color' => '#EA580C',
                'is_active' => true,
                'sort_order' => 23,
            ],
            [
                'name' => 'Template',
                'slug' => 'template',
                'description' => 'Standardized formats and frameworks for documents, reports, and other work deliverables.',
                'icon' => 'document-chart-bar',
                'color' => '#6366F1',
                'is_active' => true,
                'sort_order' => 24,
            ],
            [
                'name' => 'Policy Document',
                'slug' => 'policy-document',
                'description' => 'Official policies, procedures, guidelines, and regulatory documents governing organizational practices.',
                'icon' => 'shield-check',
                'color' => '#7C2D12',
                'is_active' => true,
                'sort_order' => 25,
            ],
            [
                'name' => 'Research Paper',
                'slug' => 'research-paper',
                'description' => 'Academic and scientific research publications, studies, and evidence-based findings.',
                'icon' => 'beaker',
                'color' => '#0F766E',
                'is_active' => true,
                'sort_order' => 26,
            ],

            // 7. MULTIMEDIA CONTENT
            [
                'name' => 'Podcast',
                'slug' => 'podcast',
                'description' => 'Audio programs and series covering educational topics, interviews, and discussions.',
                'icon' => 'microphone',
                'color' => '#DB2777',
                'is_active' => true,
                'sort_order' => 27,
            ],
            [
                'name' => 'Webinar',
                'slug' => 'webinar',
                'description' => 'Live or recorded online seminars and workshops with interactive elements and Q&A sessions.',
                'icon' => 'video-camera',
                'color' => '#2563EB',
                'is_active' => true,
                'sort_order' => 28,
            ],
            [
                'name' => 'Animation',
                'slug' => 'animation',
                'description' => 'Animated educational content and explainer videos for complex concepts and processes.',
                'icon' => 'film',
                'color' => '#C2410C',
                'is_active' => true,
                'sort_order' => 29,
            ],

            // 8. TOOLS & SOFTWARE
            [
                'name' => 'Software Tool',
                'slug' => 'software-tool',
                'description' => 'Educational software, applications, and digital tools for learning and skill development.',
                'icon' => 'cog-6-tooth',
                'color' => '#374151',
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'Calculator',
                'slug' => 'calculator',
                'description' => 'Specialized calculators and computational tools for specific training domains and calculations.',
                'icon' => 'calculator',
                'color' => '#4B5563',
                'is_active' => true,
                'sort_order' => 31,
            ],

            // 9. GAMES & INTERACTIVE
            [
                'name' => 'Educational Game',
                'slug' => 'educational-game',
                'description' => 'Gamified learning experiences that combine education with engaging gameplay mechanics.',
                'icon' => 'puzzle-piece',
                'color' => '#F97316',
                'is_active' => true,
                'sort_order' => 32,
            ],
            [
                'name' => 'Role-Play Scenario',
                'slug' => 'role-play-scenario',
                'description' => 'Structured scenarios for practicing interpersonal skills and handling various workplace situations.',
                'icon' => 'user-group',
                'color' => '#8B5CF6',
                'is_active' => true,
                'sort_order' => 33,
            ],

            // 10. SPECIALIZED FORMATS
            [
                'name' => 'Microlearning',
                'slug' => 'microlearning',
                'description' => 'Short, focused learning modules designed for quick consumption and just-in-time learning.',
                'icon' => 'clock',
                'color' => '#10B981',
                'is_active' => true,
                'sort_order' => 34,
            ],
            [
                'name' => 'Virtual Reality',
                'slug' => 'virtual-reality',
                'description' => 'Immersive VR experiences for training in safe, controlled virtual environments.',
                'icon' => 'eye',
                'color' => '#6366F1',
                'is_active' => true,
                'sort_order' => 35,
            ],
            [
                'name' => 'Augmented Reality',
                'slug' => 'augmented-reality',
                'description' => 'AR applications that overlay digital information onto real-world environments for enhanced learning.',
                'icon' => 'viewfinder-circle',
                'color' => '#EC4899',
                'is_active' => true,
                'sort_order' => 36,
            ],
        ];

        foreach ($resourceTypes as $type) {
            ResourceType::updateOrCreate(
                ['slug' => $type['slug']],
                $type
            );
        }

        $this->command->info('Resource types seeded successfully!');
    }
}
