<?php

namespace App\Http\Controllers;

use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ModuleAttendanceController extends Controller {
    // ─────────────────────────────────────────────────────────────────────────
    // Guest / Phone-based Attendance (attend/{token})
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Handle attendance link access.
     * If logged in → mark attendance immediately.
     * If guest     → show phone entry form.
     */
    public function attend(Request $request, string $token) {
        $classModule = ClassModule::where('attendance_token', $token)
                ->where('attendance_link_active', true)
                ->with(['programModule', 'mentorshipClass.training'])
                ->firstOrFail();

        if ($classModule->status === 'completed') {
            return view('mentee.attendance-closed', [
                'module' => $classModule,
                'message' => 'This module has been completed. Attendance is no longer accepted.',
            ]);
        }

        if (Auth::check()) {
            return $this->markAttendance(Auth::user(), $classModule);
        }

        return view('mentee.attendance-form', [
            'module' => $classModule,
            'token' => $token,
        ]);
    }

    /**
     * Process phone-based attendance form submission.
     */
    public function processAttendance(Request $request, string $token) {
        $request->validate(['phone' => 'required|string']);

        $classModule = ClassModule::where('attendance_token', $token)
                ->where('attendance_link_active', true)
                ->with(['programModule', 'mentorshipClass.training'])
                ->firstOrFail();

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return back()->withErrors([
                        'phone' => 'Phone number not found. Please contact your mentor.',
            ]);
        }

        return $this->markAttendance($user, $classModule);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Authenticated Confirmation (attend/{token} — logged-in flow)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Confirm attendance for an authenticated mentee via the attendance link.
     *
     * Flow:
     *  1. Validate token & module state.
     *  2. Verify mentee is enrolled.
     *  3. Check not already confirmed (scoped to this module via class_module_id).
     *  4. Create ClassAttendance with class_module_id set.
     *  5. Update mentee_module_progress → 'in_progress' (confirmed present).
     *     The mentor will move them to 'completed' when closing the module.
     */
    public function confirm(string $token) {
        $module = ClassModule::where('attendance_token', $token)
                ->where('attendance_link_active', true)
                ->with(['mentorshipClass', 'programModule'])
                ->first();

        if (!$module) {
            return redirect()->route('mentee.dashboard')
                            ->with('error', 'This attendance link is no longer active or is invalid.');
        }

        if ($module->status !== 'in_progress') {
            return redirect()->route('mentee.dashboard')
                            ->with('error', 'This module is not currently in progress.');
        }

        $class = $module->mentorshipClass;
        $userId = Auth::id();

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
                ->where('user_id', $userId)
                ->whereIn('status', ['enrolled', 'active'])
                ->first();

        if (!$participant) {
            return redirect()->route('mentee.dashboard')
                            ->with('error', 'You are not enrolled in this class.');
        }

        // ✅ Scope ONLY to this module's attendance records (class_module_id).
        // The class-level auto-enrollment record has class_module_id = null — it must NOT match here.
        $alreadyConfirmed = ClassAttendance::where('class_id', $class->id)
                ->where('class_module_id', $module->id)
                ->where('user_id', $userId)
                ->exists();

        if ($alreadyConfirmed) {
            return redirect()->route('mentee.class.progress', ['class' => $class->id])
                            ->with('info', 'You have already confirmed your attendance for this module.');
        }

        DB::transaction(function () use ($module, $class, $participant, $userId) {
            // 1. Create the module-level attendance record.
            //    class_module_id is REQUIRED — this is what the mentor's page queries.
            ClassAttendance::create([
                'class_id' => $class->id,
                'class_module_id' => $module->id, // ← must always be set
                'session_id' => null,
                'user_id' => $userId,
                'marked_by' => $userId,
                'marked_at' => now(),
                'source' => 'link', // self-confirmed via attendance link
            ]);

            // 2. Move progress to 'in_progress' = "confirmed present".
            //    Do NOT set completed yet — the mentor closes the module when done teaching.
            MenteeModuleProgress::where('class_participant_id', $participant->id)
                    ->where('class_module_id', $module->id)
                    ->whereIn('status', ['not_started']) // only advance if still pending
                    ->update([
                        'status' => 'in_progress',
                        'started_at' => now(),
            ]);
        });

        return redirect()->route('mentee.class.progress', ['class' => $class->id])
                        ->with('success', 'Your attendance for "' . ($module->programModule->name ?? 'this module') . '" has been confirmed! The mentor will close the module when the session ends.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mark attendance for a user (used by attend() and processAttendance()).
     */
    private function markAttendance(User $user, ClassModule $classModule) {
        if ($this->hasCompletedModuleBefore($user, $classModule->program_module_id)) {
            return view('mentee.attendance-already-completed', [
                'module' => $classModule,
                'class' => $classModule->mentorshipClass,
                'user' => $user,
                'message' => 'You have already completed this module in a previous class.',
            ]);
        }

        $participant = ClassParticipant::where('mentorship_class_id', $classModule->mentorship_class_id)
                ->where('user_id', $user->id)
                ->first();

        if (!$participant) {
            return back()->withErrors(['error' => 'You are not enrolled in this class.']);
        }

        // Check if already confirmed for this specific module.
        $alreadyAttended = ClassAttendance::where('class_id', $classModule->mentorship_class_id)
                ->where('class_module_id', $classModule->id)
                ->where('user_id', $user->id)
                ->exists();

        $progress = MenteeModuleProgress::where('class_participant_id', $participant->id)
                ->where('class_module_id', $classModule->id)
                ->first();

        if ($alreadyAttended) {
            return view('mentee.attendance-confirmed', [
                'module' => $classModule,
                'class' => $classModule->mentorshipClass,
                'user' => $user,
                'progress' => $progress,
                'already_marked' => true,
            ]);
        }

        DB::transaction(function () use ($user, $classModule, $participant, &$progress) {
            ClassAttendance::create([
                'class_id' => $classModule->mentorship_class_id,
                'class_module_id' => $classModule->id,
                'session_id' => null,
                'user_id' => $user->id,
                'marked_by' => $user->id,
                'marked_at' => now(),
                'source' => 'link',
            ]);

            $progress = MenteeModuleProgress::firstOrCreate(
                    [
                        'class_participant_id' => $participant->id,
                        'class_module_id' => $classModule->id,
                    ],
                    ['status' => 'not_started']
            );

            if (in_array($progress->status, ['not_started'])) {
                $progress->update([
                    'status' => 'in_progress',
                    'started_at' => now(),
                ]);
                $progress->refresh();
            }
        });

        return view('mentee.attendance-confirmed', [
            'module' => $classModule,
            'class' => $classModule->mentorshipClass,
            'user' => $user,
            'progress' => $progress,
            'already_marked' => false,
        ]);
    }

    /**
     * Check if user has completed this module in any previous class.
     */
    private function hasCompletedModuleBefore(User $user, int $programModuleId): bool {
        return DB::table('class_participants')
                        ->join('mentee_module_progress', 'class_participants.id', '=', 'mentee_module_progress.class_participant_id')
                        ->join('class_modules', 'mentee_module_progress.class_module_id', '=', 'class_modules.id')
                        ->where('class_participants.user_id', $user->id)
                        ->where('class_modules.program_module_id', $programModuleId)
                        ->where('mentee_module_progress.status', 'completed')
                        ->exists();
    }
}
