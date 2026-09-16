<?php

namespace App\Services;

use App\Models\Training;
use App\Models\User;
use App\Models\Facility;
use App\Models\Department;
use App\Models\Cadre;
use App\Models\TrainingParticipant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Exception;

class BulkParticipantImportService
{
    private array $errors = [];
    private array $warnings = [];
    private int $successCount = 0;
    private int $skippedCount = 0;

    /**
     * Import participants from uploaded file (CSV only)
     */
    public function importFromFile(string $filePath, Training $training): array
    {
        $this->resetCounters();

        try {
            $data = $this->parseFile($filePath);
            $validatedData = $this->validateData($data, $training);
            $this->processImport($validatedData, $training);

            return $this->getImportSummary();
        } catch (Exception $e) {
            $this->errors[] = "Import failed: " . $e->getMessage();
            return $this->getImportSummary();
        }
    }

    /**
     * Parse uploaded file (CSV only)
     */
    private function parseFile(string $filePath): Collection
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if ($extension === 'csv') {
            return $this->parseCsvFile($filePath);
        }

        throw new Exception('Only CSV files are supported. Please convert Excel files to CSV format.');
    }

    /**
     * Parse CSV file
     */
    private function parseCsvFile(string $filePath): Collection
    {
        $data = collect();
        $headers = null;

        if (($handle = fopen($filePath, 'r')) !== false) {
            $lineNumber = 0;
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $lineNumber++;
                if ($lineNumber === 1) {
                    $headers = array_map('strtolower', $row);
                    $headers = array_map(fn($h) => str_replace(' ', '_', trim($h)), $headers);
                    continue;
                }

                if (!empty(array_filter($row))) {
                    $data->push(array_combine($headers, $row));
                }
            }
            fclose($handle);
        }

        return $data;
    }

    /**
     * Validate imported data
     */
    private function validateData(Collection $data, Training $training): Collection
    {
        $validatedData = collect();
        $rowNumber = 1; // Start from 1 (after header)

        foreach ($data as $row) {
            $rowNumber++;
            $validationResult = $this->validateRow($row, $training, $rowNumber);

            if ($validationResult['valid']) {
                $validatedData->push($validationResult['data']);
            }
        }

        return $validatedData;
    }

    /**
     * Validate individual row
     */
    private function validateRow(array $row, Training $training, int $rowNumber): array
    {
        // Define validation rules
        $rules = [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'facility_name' => $training->isFacilityMentorship() ? 'nullable' : 'required|string',
            'mfl_code' => 'nullable|string',
            'department' => 'required|string',
            'cadre' => 'required|string',
        ];

        $validator = Validator::make($row, $rules);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->errors[] = "Row {$rowNumber}: {$error}";
            }
            return ['valid' => false, 'data' => null];
        }

        // Additional business logic validation
        return $this->performBusinessValidation($row, $training, $rowNumber);
    }

    /**
     * Perform business logic validation
     */
    private function performBusinessValidation(array $row, Training $training, int $rowNumber): array
    {
        $data = $row;
        $isValid = true;

        // Validate facility
        if ($training->isGlobalTraining() && !empty($row['facility_name'])) {
            $facility = $this->findFacility($row['facility_name'], $row['mfl_code'] ?? null);
            if (!$facility) {
                $this->errors[] = "Row {$rowNumber}: Facility '{$row['facility_name']}' not found";
                $isValid = false;
            } else {
                $data['facility_id'] = $facility->id;
            }
        } elseif ($training->isFacilityMentorship()) {
            $data['facility_id'] = $training->facility_id;
        }

        // Validate department
        $department = Department::where('name', 'like', "%{$row['department']}%")->first();
        if (!$department) {
            // Create department if it doesn't exist
            $department = Department::create(['name' => $row['department']]);
            $this->warnings[] = "Row {$rowNumber}: Created new department '{$row['department']}'";
        }
        $data['department_id'] = $department->id;

        // Validate cadre
        $cadre = Cadre::where('name', 'like', "%{$row['cadre']}%")->first();
        if (!$cadre) {
            // Create cadre if it doesn't exist
            $cadre = Cadre::create(['name' => $row['cadre']]);
            $this->warnings[] = "Row {$rowNumber}: Created new cadre '{$row['cadre']}'";
        }
        $data['cadre_id'] = $cadre->id;

        // Check for existing participant
        $existingParticipant = TrainingParticipant::where('training_id', $training->id)
            ->whereHas('user', function ($query) use ($row) {
                $query->where('phone', $row['phone']);
            })
            ->first();

        if ($existingParticipant) {
            $this->warnings[] = "Row {$rowNumber}: Participant with phone '{$row['phone']}' already registered";
            $this->skippedCount++;
            return ['valid' => false, 'data' => null];
        }

        return ['valid' => $isValid, 'data' => $data];
    }

    /**
     * Find facility by name or MFL code
     */
    private function findFacility(?string $name, ?string $mflCode): ?Facility
    {
        if ($mflCode) {
            $facility = Facility::where('mfl_code', $mflCode)->first();
            if ($facility) return $facility;
        }

        if ($name) {
            return Facility::where('name', 'like', "%{$name}%")->first();
        }

        return null;
    }

    /**
     * Process validated data and create participants
     */
    private function processImport(Collection $validatedData, Training $training): void
    {
        DB::transaction(function () use ($validatedData, $training) {
            foreach ($validatedData as $data) {
                try {
                    $this->createParticipant($data, $training);
                    $this->successCount++;
                } catch (Exception $e) {
                    $this->errors[] = "Failed to create participant '{$data['name']}': " . $e->getMessage();
                }
            }
        });
    }

    /**
     * Create participant and user if needed
     */
    private function createParticipant(array $data, Training $training): void
    {
        // Find or create user
        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            // Parse name
            $nameParts = explode(' ', trim($data['name']));
            $firstName = array_shift($nameParts);
            $lastName = array_pop($nameParts);
            $middleName = implode(' ', $nameParts);

            $user = User::create([
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName ?: '',
                'phone' => $data['phone'],
                'email' => $data['email'] ?? $this->generateEmail($data['name'], $data['phone']),
                'facility_id' => $data['facility_id'] ?? null,
                'department_id' => $data['department_id'],
                'cadre_id' => $data['cadre_id'],
                'status' => 'active',
                'password' => bcrypt('default123'), // Default password
            ]);

            // Assign mentee role
            $user->assignRole('Mentee');
        } else {
            // Update user information if needed
            $user->update([
                'facility_id' => $user->facility_id ?? $data['facility_id'],
                'department_id' => $user->department_id ?? $data['department_id'],
                'cadre_id' => $user->cadre_id ?? $data['cadre_id'],
            ]);
        }

        // Create training participant
        TrainingParticipant::create([
            'training_id' => $training->id,
            'user_id' => $user->id,
            'facility_id' => $data['facility_id'] ?? $training->facility_id,
            'department_id' => $data['department_id'],
            'cadre_id' => $data['cadre_id'],
            'registration_date' => now(),
            'attendance_status' => 'registered',
            'completion_status' => 'pending',
        ]);
    }

    /**
     * Generate email for new users
     */
    private function generateEmail(string $name, string $phone): string
    {
        $emailName = Str::slug(Str::lower($name)) . '.' . substr($phone, -4);
        return $emailName . '@mentee.system';
    }

    /**
     * Reset counters for new import
     */
    private function resetCounters(): void
    {
        $this->errors = [];
        $this->warnings = [];
        $this->successCount = 0;
        $this->skippedCount = 0;
    }

    /**
     * Get import summary
     */
    private function getImportSummary(): array
    {
        return [
            'success' => empty($this->errors),
            'imported_count' => $this->successCount,
            'skipped_count' => $this->skippedCount,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'total_processed' => $this->successCount + $this->skippedCount + count($this->errors),
        ];
    }

    /**
     * Validate import file without actually importing
     */
    public function validateImportFile(string $filePath, Training $training): array
    {
        $this->resetCounters();

        try {
            $data = $this->parseFile($filePath);
            $validatedData = $this->validateData($data, $training);

            return [
                'success' => empty($this->errors),
                'valid_rows' => $validatedData->count(),
                'total_rows' => $data->count(),
                'errors' => $this->errors,
                'warnings' => $this->warnings,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'valid_rows' => 0,
                'total_rows' => 0,
                'errors' => ["Validation failed: " . $e->getMessage()],
                'warnings' => [],
            ];
        }
    }

    /**
     * Generate CSV template for participant import
     */
    public function generateTemplate(Training $training): string
    {
        $headers = [
            'name',
            'phone',
            'email',
            'department',
            'cadre',
        ];

        // Add facility columns for global trainings
        if ($training->isGlobalTraining()) {
            array_splice($headers, 3, 0, ['facility_name', 'mfl_code']);
        }

        // Create sample data
        $sampleData = [];
        if ($training->isGlobalTraining()) {
            $sampleData = [
                ['John Doe', '+254712345678', 'john@example.com', 'Example Hospital', 'EH001', 'Clinical', 'Nurse'],
                ['Jane Smith', '+254723456789', 'jane@example.com', 'Health Center ABC', 'HC002', 'Laboratory', 'Lab Technician'],
            ];
        } else {
            $sampleData = [
                ['John Doe', '+254712345678', 'john@example.com', 'Clinical', 'Nurse'],
                ['Jane Smith', '+254723456789', 'jane@example.com', 'Laboratory', 'Lab Technician'],
            ];
        }

        // Generate CSV content
        $content = implode(',', $headers) . "\n";
        foreach ($sampleData as $row) {
            $content .= implode(',', $row) . "\n";
        }

        return $content;
    }
}
