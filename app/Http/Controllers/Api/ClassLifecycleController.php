<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClassAccess;
use App\Http\Controllers\Controller;
use App\Mail\MenteeEnrollmentInvitationMail;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassLifecycleController extends Controller
{
    use AuthorizesClassAccess;

    public function __construct(private readonly EnrollmentService $enrollmentService) {}

    /**
     * POST /api/v1/classes/{class}/start
     */
    public function start(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        if (!$class->canStart()) {
            return response()->json([
                'message' => "Class cannot be started. Status: {$class->status}. Ensure it has modules and enrolled mentees.",
            ], 422);
        }

        $class->start();
        $class->refresh();

        return response()->json(['data' => ['id' => $class->id, 'status' => $class->status]]);
    }

    /**
     * POST /api/v1/classes/{class}/end
     */
    public function end(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        if (!$class->canEnd()) {
            return response()->json([
                'message' => "Class cannot be ended. Status: {$class->status}.",
            ], 422);
        }

        $class->complete();
        $class->refresh();

        return response()->json(['data' => ['id' => $class->id, 'status' => $class->status]]);
    }

    /**
     * POST /api/v1/classes/{class}/mentees
     */
    public function enrollMentee(Request $request, MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $already = $this->enrollmentService->isEnrolled((int) $request->user_id, $class->id);

        abort_if($already, 409, 'User is already enrolled in this class.');

        $user = User::findOrFail((int) $request->user_id);
        $participant = $this->enrollmentService->enrollInClass($user, $class, 'manual');
        $participant->load('user');

        return response()->json([
            'data' => [
                'participant_id' => $participant->id,
                'user_id'        => $participant->user_id,
                'name'           => $participant->user?->full_name ?? $participant->user?->name,
                'email'          => $participant->user?->email,
                'phone'          => $participant->user?->phone,
            ],
        ], 201);
    }

    /**
     * POST /api/v1/classes/{class}/mentees/create
     */
    public function createMentee(Request $request, MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        $data = $request->validate([
            'email'         => 'required|email|max:255',
            'first_name'    => 'nullable|string|max:100',
            'middle_name'   => 'nullable|string|max:100',
            'last_name'     => 'nullable|string|max:100',
            'phone'         => 'nullable|string|max:20',
            'cadre_id'      => 'nullable|integer',
            'department_id' => 'nullable|integer|exists:departments,id',
            'facility_id'   => 'nullable|integer|exists:facilities,id',
        ]);

        if (!empty($data['cadre_id'])) {
            $cadreId = (int) $data['cadre_id'];
            $existsInCadres = DB::table('cadres')->where('id', $cadreId)->exists();
            $existsInAssessmentCadres = DB::table('assessment_cadres')->where('id', $cadreId)->exists();
            if (!$existsInCadres && !$existsInAssessmentCadres) {
                throw ValidationException::withMessages([
                    'cadre_id' => ['The selected cadre is invalid.'],
                ]);
            }
        }

        $normalizedEmail = mb_strtolower(trim((string) $data['email']));
        $existingUser = User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

        // Match web class flow: if email exists, enroll existing user instead of failing.
        if ($existingUser) {
            $already = $this->enrollmentService->isEnrolled($existingUser->id, $class->id);
            abort_if($already, 409, 'User is already enrolled in this class.');

            $participant = $this->enrollmentService->enrollInClass($existingUser, $class, 'manual');
            $participant->load('user');

            return response()->json([
                'data' => [
                    'participant_id' => $participant->id,
                    'user_id'        => $participant->user_id,
                    'name'           => $participant->user?->full_name ?? $participant->user?->name,
                    'email'          => $participant->user?->email,
                    'phone'          => $participant->user?->phone,
                    'created'        => false,
                ],
            ], 201);
        }

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        if ($firstName === '' || $lastName === '') {
            throw ValidationException::withMessages([
                'first_name' => ['First and last name are required to create a new user.'],
            ]);
        }

        $participant = DB::transaction(function () use ($data, $class, $normalizedEmail, $firstName, $lastName) {
            $displayName = trim(implode(' ', array_filter([
                $firstName,
                $data['middle_name'] ?? null,
                $lastName,
            ])));

            $user = User::create([
                'first_name'    => $firstName,
                'middle_name'   => $data['middle_name'] ?? null,
                'last_name'     => $lastName,
                'name'          => $displayName,
                'email'         => $normalizedEmail,
                'phone'         => $data['phone'] ?? null,
                'cadre_id'      => $data['cadre_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'facility_id'   => $data['facility_id'] ?? null,
                'password'      => Hash::make('123456'),
                'status'        => 'active',
                'role'          => 'mentee',
            ]);

            if (method_exists($user, 'assignRole')) {
                try {
                    $user->assignRole('mentee');
                } catch (\Throwable) {
                    // Some installations use the role column without Spatie roles.
                }
            }

            return $this->enrollmentService->enrollInClass($user, $class, 'manual');
        });

        $participant->load('user');

        $invitationSent = false;
        if ($participant->user?->email) {
            $this->ensureEnrollmentToken($class);
            try {
                Mail::to($participant->user->email)
                    ->send(new MenteeEnrollmentInvitationMail($participant->user, $class, $participant));
                $participant->update(['invitation_sent_at' => now()]);
                $invitationSent = true;
            } catch (\Throwable) {
                // Mail failure must not break the enrollment response.
            }
        }

        return response()->json([
            'data' => [
                'participant_id'   => $participant->id,
                'user_id'          => $participant->user_id,
                'name'             => $participant->user?->full_name ?? $participant->user?->name,
                'email'            => $participant->user?->email,
                'phone'            => $participant->user?->phone,
                'default_password' => '123456',
                'created'          => true,
                'invitation_sent'  => $invitationSent,
            ],
        ], 201);
    }

    /**
     * GET /api/v1/classes/{class}/enrollment-link
     */
    public function enrollmentLink(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        if (!$class->enrollment_token) {
            $class->update(['enrollment_token' => Str::random(32), 'enrollment_link_active' => true]);
            $class->refresh();
        }

        $url = url('/enroll/' . $class->enrollment_token);

        return response()->json([
            'data' => [
                'token'  => $class->enrollment_token,
                'url'    => $url,
                'active' => (bool) $class->enrollment_link_active,
            ],
        ]);
    }

    /**
     * POST /api/v1/classes/{class}/regenerate-token
     */
    public function regenerateToken(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);
        abort_if($class->status === 'completed' || $class->status === 'cancelled', 422, 'Cannot regenerate token for a completed class.');

        $token = Str::random(32);
        $class->update(['enrollment_token' => $token, 'enrollment_link_active' => true]);

        return response()->json([
            'data' => [
                'token'  => $token,
                'url'    => url('/enroll/' . $token),
                'active' => true,
            ],
        ]);
    }

    /**
     * DELETE /api/v1/classes/{class}/mentees/{participant}
     */
    public function removeMentee(MentorshipClass $class, ClassParticipant $participant): JsonResponse
    {
        $this->authorizeClassAccess($class);

        abort_if($participant->mentorship_class_id !== $class->id, 422, 'Participant does not belong to this class.');
        abort_if($participant->status !== 'enrolled', 422, 'Cannot remove a mentee who has started modules.');

        $this->enrollmentService->removeFromClass($participant);

        return response()->json(['message' => 'Mentee removed.']);
    }

    /**
     * PATCH /api/v1/classes/{class}/mentees/{participant}
     * Update a mentee's email address.
     */
    public function updateMentee(Request $request, MentorshipClass $class, ClassParticipant $participant): JsonResponse
    {
        $this->authorizeClassAccess($class);
        abort_if($participant->mentorship_class_id !== $class->id, 404);

        $data = $request->validate(['email' => 'required|email|max:255|unique:users,email,' . $participant->user_id]);

        $participant->user()->update(['email' => $data['email']]);

        return response()->json(['data' => ['email' => $data['email']]]);
    }

    /**
     * POST /api/v1/classes/{class}/mentees/{participant}/invite
     * Send (or resend) the enrollment invitation email to this participant.
     */
    public function markInvited(MentorshipClass $class, ClassParticipant $participant): JsonResponse
    {
        $this->authorizeClassAccess($class);
        abort_if($participant->mentorship_class_id !== $class->id, 404);

        $participant->load('user');

        $emailSent = false;
        if ($participant->user?->email) {
            $this->ensureEnrollmentToken($class);
            $isResend = (bool) $participant->invitation_sent_at;
            try {
                Mail::to($participant->user->email)
                    ->send(new MenteeEnrollmentInvitationMail($participant->user, $class, $participant, $isResend));
                $emailSent = true;
            } catch (\Throwable) {
                // Fall through — still stamp the timestamp so the caller knows we attempted.
            }
        }

        $participant->update(['invitation_sent_at' => now()]);

        return response()->json([
            'data' => [
                'invitation_sent_at' => $participant->invitation_sent_at->toIso8601String(),
                'email_sent'         => $emailSent,
            ],
        ]);
    }

    private function ensureEnrollmentToken(MentorshipClass $class): void
    {
        if (!$class->enrollment_token) {
            $class->update([
                'enrollment_token'       => Str::random(32),
                'enrollment_link_active' => true,
            ]);
            $class->refresh();
        } elseif (!$class->enrollment_link_active) {
            $class->update(['enrollment_link_active' => true]);
            $class->refresh();
        }
    }
}
