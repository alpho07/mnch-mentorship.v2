<?php
namespace App\Filament\Resources\TrainingResource\Pages;

use App\Filament\Resources\TrainingResource;
use App\Models\Training;
use App\Models\User;
use App\Models\Facility;
use App\Models\Department;
use App\Models\Cadre;
use App\Models\TrainingParticipant;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class CreateTraining extends CreateRecord
{
    protected static string $resource = TrainingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set training type
        $data['type'] = 'global_training';

        // Auto-generate identifier if not provided
        if (empty($data['identifier'])) {
            $data['identifier'] = $this->generateTrainingIdentifier($data['title'], 'GT');
        }

        // Set default organizer
        if (empty($data['organizer_id'])) {
            $data['organizer_id'] = auth()->id();
        }

        // Remove participants from main data (will be processed separately)
        $participants = $data['participants'] ?? [];
        unset($data['participants']);

        // Store participants for later processing
        $this->participantsData = $participants;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        // Sync many-to-many relationships
        if ($this->data['programs'] ?? false) {
            $record->programs()->sync($this->data['programs']);
        }

        if ($this->data['modules'] ?? false) {
            $record->modules()->sync($this->data['modules']);
        }

        if ($this->data['methodologies'] ?? false) {
            $record->methodologies()->sync($this->data['methodologies']);
        }

        // Process participants
        if (!empty($this->participantsData)) {
            $this->createParticipants($record, $this->participantsData);
        }

        Notification::make()
            ->title('Global Training Created Successfully')
            ->body("Training '{$record->title}' has been created with " . count($this->participantsData) . " participants.")
            ->success()
            ->duration(5000)
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('View Training')
                    ->url($this->getResource()::getUrl('view', ['record' => $record]))
                    ->button(),
            ])
            ->send();
    }

    private function createParticipants(Training $training, array $participantsData): void
    {
        foreach ($participantsData as $participantData) {
            if (empty($participantData['name']) || empty($participantData['phone'])) {
                continue;
            }

            // Find or create user
            $user = User::where('phone', $participantData['phone'])->first();

            if (!$user) {
                // Find or create facility
                $facility = null;
                if (!empty($participantData['facility_name'])) {
                    $facility = Facility::where('name', 'like', "%{$participantData['facility_name']}%")->first();

                    if (!$facility && !empty($participantData['mfl_code'])) {
                        $facility = Facility::where('mfl_code', $participantData['mfl_code'])->first();
                    }
                }

                // Find or create department
                $department = Department::where('name', 'like', "%{$participantData['department']}%")->first();
                if (!$department) {
                    $department = Department::create(['name' => $participantData['department']]);
                }

                // Find or create cadre
                $cadre = Cadre::where('name', 'like', "%{$participantData['cadre']}%")->first();
                if (!$cadre) {
                    $cadre = Cadre::create(['name' => $participantData['cadre']]);
                }

                // Parse name
                $nameParts = explode(' ', trim($participantData['name']));
                $firstName = array_shift($nameParts);
                $lastName = array_pop($nameParts) ?: '';
                $middleName = implode(' ', $nameParts);

                // Create user
                $user = User::create([
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'phone' => $participantData['phone'],
                    'email' => $participantData['email'] ?? $this->generateEmail($participantData['name'], $participantData['phone']),
                    'facility_id' => $facility?->id,
                    'department_id' => $department->id,
                    'cadre_id' => $cadre->id,
                    'status' => 'active',
                    'password' => bcrypt('default123'),
                ]);

                $user->assignRole('Mentee');
            }

            // Create training participant
            TrainingParticipant::create([
                'training_id' => $training->id,
                'user_id' => $user->id,
                'facility_id' => $user->facility_id,
                'department_id' => $user->department_id,
                'cadre_id' => $user->cadre_id,
                'registration_date' => now(),
                'attendance_status' => 'registered',
                'completion_status' => 'pending',
            ]);
        }
    }

    private function generateTrainingIdentifier(string $title, string $prefix = 'GT'): string
    {
        $baseId = $prefix . '-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $title), 0, 6));
        $suffix = '-' . date('y') . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);

        $identifier = $baseId . $suffix;

        while (Training::where('identifier', $identifier)->exists()) {
            $suffix = '-' . date('y') . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
            $identifier = $baseId . $suffix;
        }

        return $identifier;
    }

    private function generateEmail(string $name, string $phone): string
    {
        $emailName = Str::slug(Str::lower($name)) . '.' . substr($phone, -4);
        return $emailName . '@mentee.system';
    }

    private $participantsData = [];
}
