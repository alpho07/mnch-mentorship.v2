<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Cadre;
use App\Models\Department;
use App\Models\Facility;
use Spatie\Permission\Models\Role;

class MenteeSeeder extends Seeder
{
    /**
     * Map of cadre abbreviations/codes to their full names.
     * Keys are uppercase for case-insensitive matching.
     */
    private array $cadreMap = [
        'CO'        => 'County Officer',
        'NO'        => 'National Officer',
        'MO'        => 'Medical Officer',
        'MO INTERN' => 'Medical Officer Intern',
        'M.O.I'     => 'Medical Officer Intern',
        'PAED'      => 'Paediatrician',
    ];

    public function run(): void
    {
        $menteeRole = Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);

      

        $handle = fopen('/var/www/html/mnch-master/public/mentee.csv', 'r');

        // Skip BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        } 

        // Skip header row
        fgetcsv($handle);

        // Pre-cache cadres by code and name for fast lookup
        $cadresByCode = Cadre::pluck('id', 'code')->mapWithKeys(fn($id, $code) => [strtoupper($code) => $id])->toArray();
        $cadresByName = Cadre::pluck('id', 'name')->mapWithKeys(fn($id, $name) => [strtoupper($name) => $id])->toArray();

        // Pre-cache departments by name
        $departmentsByName = Department::pluck('id', 'name')->mapWithKeys(fn($id, $name) => [strtoupper(trim($name)) => $id])->toArray();

        // Pre-cache facilities by mfl_code
        $facilitiesByMfl = Facility::whereNotNull('mfl_code')->pluck('id', 'mfl_code')->toArray();

        // Pre-cache existing users by phone for duplicate detection
        $existingPhones = User::whereNotNull('phone')->pluck('id', 'phone')->toArray();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 7) {
                $skipped++;
                continue;
            }

            $name       = trim($row[2] ?? '');
            $department = trim($row[3] ?? '');
            $cadreRaw   = trim($row[4] ?? '');
            $idNumber   = trim($row[5] ?? '');
            $phoneRaw   = trim($row[6] ?? '');
            $emailRaw   = strtolower(trim($row[7] ?? ''));

            // Skip rows without a name
            if (empty($name)) {
                $skipped++;
                continue;
            }

            // --- Phone cleaning ---
            $phone = $this->cleanPhone($phoneRaw);
            if (!$phone) {
                $skipped++;
                continue;
            }

            // --- MFL Code / Facility ---
            $mflCode    = trim($row[1] ?? '');
            $facilityId = $facilitiesByMfl[$mflCode] ?? null;

            // --- Email cleaning ---
            $email = $this->cleanEmail($emailRaw);

            // --- Department resolution ---
            $departmentId = $this->resolveDepartment($department, $departmentsByName);

            // --- Cadre resolution ---
            $cadreId = $this->resolveCadre($cadreRaw, $cadresByCode, $cadresByName);

            // --- ID Number ---
            $idNumber = preg_replace('/\D/', '', $idNumber);
            $idNumber = !empty($idNumber) ? $idNumber : null;

            // --- Check if user exists by phone ---
            if (isset($existingPhones[$phone])) {
                $existingUser = User::find($existingPhones[$phone]);
                if ($existingUser) {
                    $updateData = [];
                    if ($email && empty($existingUser->email)) {
                        $updateData['email'] = $email;
                    } elseif ($email) {
                        $updateData['email'] = $email;
                    }
                    if ($facilityId && !$existingUser->facility_id) {
                        $updateData['facility_id'] = $facilityId;
                    }
                    if ($departmentId && !$existingUser->department_id) {
                        $updateData['department_id'] = $departmentId;
                    }
                    if ($cadreId && !$existingUser->cadre_id) {
                        $updateData['cadre_id'] = $cadreId;
                    }
                    if ($idNumber && !$existingUser->id_number) {
                        $updateData['id_number'] = $idNumber;
                    }

                    if (!empty($updateData)) {
                        $existingUser->update($updateData);
                    }

                    if (!$existingUser->hasRole('mentee')) {
                        $existingUser->assignRole($menteeRole);
                    }
                    $updated++;
                }
                continue;
            }

            // --- Create new user ---
            $user = User::create([
                'name'              => $name,
                'phone'             => $phone,
                'email'             => $email,
                'id_number'         => $idNumber,
                'facility_id'       => $facilityId,
                'department_id'     => $departmentId,
                'cadre_id'          => $cadreId,
                'password'          => Hash::make('123456'),
                'status'            => 'active',
                'email_verified_at' => now(),
            ]);

            $user->assignRole($menteeRole);

            // Track for duplicate detection within this run
            $existingPhones[$phone] = $user->id;
            $created++;
        }

        fclose($handle);

        $this->command->info("Mentee seeding complete: {$created} created, {$updated} updated, {$skipped} skipped.");
    }

    /**
     * Clean and validate a phone number.
     * Returns a 10-digit string starting with 0, or null if invalid.
     */
    private function cleanPhone(string $raw): ?string
    {
        // Remove all non-digit characters (dashes, spaces, etc.)
        $phone = preg_replace('/\D/', '', $raw);

        if (empty($phone)) {
            return null;
        }

        // Handle country code 254
        if (str_starts_with($phone, '254') && strlen($phone) >= 12) {
            $phone = '0' . substr($phone, 3);
        }

        // If 9 digits and doesn't start with 0, prepend 0
        if (strlen($phone) === 9 && $phone[0] !== '0') {
            $phone = '0' . $phone;
        }

        // Validate: must be exactly 10 digits starting with 0
        if (strlen($phone) !== 10 || $phone[0] !== '0') {
            return null;
        }

        return $phone;
    }

    /**
     * Clean and validate an email address.
     */
    private function cleanEmail(string $raw): ?string
    {
        $email = strtolower(trim($raw));

        if (empty($email) || $email === 'not availed' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    /**
     * Resolve a department name to its ID, creating if necessary.
     */
    private function resolveDepartment(string $name, array &$cache): ?int
    {
        $name = strtoupper(trim($name));

        if (empty($name) || in_array($name, ['N/A', 'NOT SPECIFIED', 'NOT AVAILED', ''])) {
            return null;
        }

        if (isset($cache[$name])) {
            return $cache[$name];
        }

        $dept = Department::firstOrCreate(['name' => $name]);
        $cache[$name] = $dept->id;

        return $dept->id;
    }

    /**
     * Resolve a cadre abbreviation/name to its ID.
     * Checks by code, name, and known abbreviation mappings.
     * Creates the cadre if it doesn't exist.
     */
    private function resolveCadre(string $raw, array &$codeCache, array &$nameCache): ?int
    {
        $key = strtoupper(trim($raw));

        if (empty($key) || in_array($key, ['CADRE', 'N/A', ''])) {
            return null;
        }

        // 1. Check by code
        if (isset($codeCache[$key])) {
            return $codeCache[$key];
        }

        // 2. Check by name
        if (isset($nameCache[$key])) {
            return $nameCache[$key];
        }

        // 3. Check if it's a known abbreviation
        $fullName = $this->cadreMap[$key] ?? null;
        if ($fullName) {
            $fullNameUpper = strtoupper($fullName);

            // Check if the full name already exists
            if (isset($nameCache[$fullNameUpper])) {
                $codeCache[$key] = $nameCache[$fullNameUpper];
                return $nameCache[$fullNameUpper];
            }

            // Create with abbreviation as code and full name
            $cadre = Cadre::firstOrCreate(
                ['code' => strtolower($key)],
                [
                    'name'      => $fullName,
                    'order'     => 0,
                    'is_active' => true,
                ]
            );

            $codeCache[$key] = $cadre->id;
            $nameCache[$fullNameUpper] = $cadre->id;

            return $cadre->id;
        }

        // 4. Not a known abbreviation — try to create as-is
        $cadre = Cadre::firstOrCreate(
            ['code' => strtolower($key)],
            [
                'name'      => ucwords(strtolower($key)),
                'order'     => 0,
                'is_active' => true,
            ]
        );

        $codeCache[$key] = $cadre->id;
        $nameCache[strtoupper($cadre->name)] = $cadre->id;

        return $cadre->id;
    }
}
