<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\ResourceFileDownloadController;
use App\Models\ClassModule;
use App\Models\MentorshipClass;
use App\Models\ProgramModule;
use App\Models\ProgramModuleContent;
use App\Models\Resource;
use App\Models\ResourceFile;
use App\Models\ResourceType;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Mentors can see a module's resources (like the web "Module Resources" page)
 * and download the attached files through short-lived signed links.
 */
class ModuleResourcesApiTest extends TestCase
{
    use RefreshDatabase;

    private User $mentor;

    private ClassModule $module;

    private ProgramModule $programModule;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('resources');

        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $this->mentor = User::factory()->create();
        $this->mentor->assignRole('facility_mentor');

        $training = Training::factory()->facilityMentorship()->create(['mentor_id' => $this->mentor->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $this->programModule = ProgramModule::factory()->create(['objectives' => ['Recognise danger signs']]);
        $this->module = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $this->programModule->id,
            'status' => 'in_progress',
        ]);
    }

    private function resourceWithFile(string $visibility = 'authenticated'): array
    {
        $resource = Resource::create([
            'title' => 'Newborn manual',
            'resource_type_id' => ResourceType::create(['name' => 'Manual', 'slug' => 'manual-'.uniqid(), 'is_active' => true])->id, 'slug' => 'newborn-manual-'.uniqid(), 'status' => 'published',
            'visibility' => $visibility, 'author_id' => User::factory()->create()->id,
        ]);
        Storage::disk('resources')->put('manual.pdf', 'PDFDATA');
        $file = ResourceFile::create([
            'resource_id' => $resource->id, 'original_name' => 'manual.pdf', 'file_name' => 'manual.pdf',
            'file_path' => 'manual.pdf', 'file_type' => 'application/pdf', 'file_size' => 7, 'is_primary' => true,
        ]);
        $this->programModule->resources()->attach($resource->id);

        return [$resource, $file];
    }

    private function asMentor(?User $user = null): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.($user ?? $this->mentor)->createToken('t')->plainTextToken);
    }

    public function test_mentor_sees_module_content_and_attached_resources_with_download_link(): void
    {
        $this->resourceWithFile();
        ProgramModuleContent::create([
            'program_module_id' => $this->programModule->id, 'type' => 'introduction',
            'title' => 'Welcome', 'content' => 'Intro text', 'order_sequence' => 1, 'is_active' => true,
        ]);

        $this->asMentor()->getJson("/api/v1/modules/{$this->module->id}/resources")
            ->assertOk()
            ->assertJsonPath('data.objectives.0', 'Recognise danger signs')
            ->assertJsonPath('data.introduction.0.title', 'Welcome')
            ->assertJsonPath('data.resources.0.title', 'Newborn manual')
            ->assertJsonPath('data.resources.0.file.name', 'manual.pdf')
            ->assertJsonStructure(['data' => ['resources' => [['file' => ['download_url']]]]]);
    }

    public function test_signed_link_downloads_the_file_without_a_token(): void
    {
        [, $file] = $this->resourceWithFile();

        $this->app['auth']->forgetGuards();
        $url = ResourceFileDownloadController::signedUrl($file, $this->mentor);

        $this->get($url)->assertOk()->assertDownload('manual.pdf');
        $this->assertSame(1, $file->resource->fresh()->download_count);
    }

    public function test_unsigned_or_tampered_links_are_rejected(): void
    {
        [, $file] = $this->resourceWithFile();

        $this->get("/api/v1/resource-files/{$file->id}/download?u={$this->mentor->id}")->assertForbidden();

        $url = ResourceFileDownloadController::signedUrl($file, $this->mentor);
        $this->get(str_replace("u={$this->mentor->id}", 'u='.User::factory()->create()->id, $url))->assertForbidden();
    }

    public function test_restricted_resource_has_no_download_link_for_users_without_access(): void
    {
        $this->resourceWithFile('restricted');

        $this->asMentor()->getJson("/api/v1/modules/{$this->module->id}/resources")
            ->assertOk()
            ->assertJsonPath('data.resources.0.can_access', false)
            ->assertJsonPath('data.resources.0.file', null);
    }

    public function test_user_outside_the_class_cannot_view_module_resources(): void
    {
        $outsider = User::factory()->create();
        $outsider->assignRole('facility_mentor');

        $this->asMentor($outsider)->getJson("/api/v1/modules/{$this->module->id}/resources")->assertForbidden();
    }

    public function test_enrolled_mentee_can_read_resources_but_not_the_answer_key(): void
    {
        $this->resourceWithFile();
        $quiz = \App\Models\ProgramModuleQuiz::create([
            'program_module_id' => $this->programModule->id, 'title' => 'Pre', 'type' => 'pre_test', 'is_active' => true,
        ]);
        $mentee = User::factory()->create();
        \App\Models\ClassParticipant::factory()->create([
            'mentorship_class_id' => $this->module->mentorship_class_id, 'user_id' => $mentee->id,
        ]);

        $this->asMentor($mentee)->getJson("/api/v1/modules/{$this->module->id}/resources")
            ->assertOk()
            ->assertJsonPath('data.resources.0.title', 'Newborn manual')
            ->assertJsonPath('data.pre_tests.0.questions', []);
    }
}
