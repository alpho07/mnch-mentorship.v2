<?php

namespace Tests\Feature\Api;

use App\Mail\AccountVerificationMail;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MainCadre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegisterApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // In-memory test DB: assessment_cadres.assessment_type_id has an FK to assessment_types.
        foreach ([1, 2] as $id) {
            \DB::table('assessment_types')->insertOrIgnore([
                'id' => $id, 'name' => "Type {$id}", 'code' => "T{$id}",
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function payload(array $override = []): array
    {
        $facility = Facility::factory()->create();
        $cadre = MainCadre::firstOrCreate(['name' => 'Nurse'], ['is_active' => true, 'order' => 1, 'assessment_type_id' => 2]);
        $dept = Department::firstOrCreate(['name' => 'Maternity']);

        return array_merge([
            'first_name' => 'jane',
            'middle_name' => null,
            'last_name' => 'DOE',
            'email' => 'jane@example.com',
            'phone' => '0712345678',
            'cadre_id' => $cadre->id,
            'department_id' => $dept->id,
            'role' => 'mentee',
            'county_id' => $facility->subcounty->county_id,
            'facility_id' => $facility->id,
        ], $override);
    }

    public function test_cadres_and_departments_are_public_and_populated(): void
    {
        MainCadre::create(['name' => 'Nurse', 'is_active' => true, 'order' => 1, 'assessment_type_id' => 2]);
        MainCadre::create(['name' => 'Retired', 'is_active' => false, 'order' => 2, 'assessment_type_id' => 2]);
        MainCadre::create(['name' => 'Type One', 'is_active' => true, 'order' => 3, 'assessment_type_id' => 1]);
        Department::create(['name' => 'Maternity']);

        $this->getJson('/api/v1/register-lookups/cadres')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Nurse');
        $this->getJson('/api/v1/register-lookups/departments')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Maternity');
    }

    public function test_counties_and_facilities_lookup(): void
    {
        $facility = Facility::factory()->create();
        $countyId = $facility->subcounty->county_id;

        $this->getJson('/api/v1/register-lookups/counties')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/register-lookups/counties/{$countyId}/facilities")
            ->assertOk()->assertJsonPath('data.0.id', $facility->id);
    }

    public function test_check_email_and_phone(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'phone' => '0700000000']);

        $this->getJson('/api/v1/register-lookups/check-email?email=taken@example.com')->assertJson(['exists' => true]);
        $this->getJson('/api/v1/register-lookups/check-email?email=free@example.com')->assertJson(['exists' => false]);
        $this->getJson('/api/v1/register-lookups/check-phone?phone=0700000000')->assertJson(['exists' => true]);
        $this->getJson('/api/v1/register-lookups/check-phone?phone=0799999999')->assertJson(['exists' => false]);
    }

    public function test_register_creates_pending_user_and_sends_mail(): void
    {
        Mail::fake();
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);
        $payload = $this->payload();
        // Migrations give users.cadre_id an FK to the legacy `cadres` table (production has none;
        // cadre_id points at assessment_cadres), so mirror the row to satisfy it in the test DB.
        \DB::table('cadres')->insert(['id' => $payload['cadre_id'], 'name' => 'Nurse', 'created_at' => now(), 'updated_at' => now()]);

        $this->postJson('/api/v1/auth/register', $payload)->assertCreated();

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertSame('Jane Doe', $user->name);
        $this->assertEquals($payload['cadre_id'], $user->cadre_id);
        $this->assertEquals($payload['department_id'], $user->department_id);
        $this->assertTrue($user->hasRole('mentee'));
        $this->assertTrue($user->counties->contains($payload['county_id']));
        $this->assertTrue($user->facilities->contains($payload['facility_id']));
        Mail::assertQueued(AccountVerificationMail::class);
    }

    public function test_register_validates_input(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'email', 'cadre_id', 'department_id', 'facility_id']);
    }

    public function test_register_rejects_duplicate_email_and_wrong_county(): void
    {
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors(['email']);

        $other = Facility::factory()->create();
        $this->postJson('/api/v1/auth/register', $this->payload([
            'email' => 'new@example.com', 'phone' => '0711111111',
            'county_id' => $other->subcounty->county_id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['facility_id']);
    }

    public function test_register_rejects_cadre_outside_assessment_type_2(): void
    {
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);
        $typeOne = MainCadre::create(['name' => 'Type One', 'is_active' => true, 'order' => 3, 'assessment_type_id' => 1]);

        $this->postJson('/api/v1/auth/register', $this->payload(['cadre_id' => $typeOne->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['cadre_id']);
    }
}
