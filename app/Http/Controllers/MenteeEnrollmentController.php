<?php

namespace App\Http\Controllers;

use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\User;
use App\Services\ModuleUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenteeEnrollmentController extends Controller
{
    public function __construct(
        protected ModuleUsageService $moduleUsageService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 1 — Public landing page
    // GET /enroll/{token}
    // No auth required. Shows class details and email form.
    // ─────────────────────────────────────────────────────────────────────────

    public function show(string $token)
    {
        $class = $this->resolveClass($token);

        if (! $class) {
            return view('mentee.enroll-invalid', [
                'reason' => 'This enrollment link is no longer active or does not exist.',
            ]);
        }

        if (in_array($class->status, ['completed', 'cancelled'])) {
            return view('mentee.enroll-invalid', [
                'reason' => 'This class has already ended. Enrollment is closed.',
            ]);
        }

        // If already logged in and already enrolled, go straight to progress
        if (auth()->check()) {
            $existing = ClassParticipant::where('mentorship_class_id', $class->id)
                ->where('user_id', auth()->id())
                ->exists();

            if ($existing) {
                return redirect()->route('mentee.class.progress', ['class' => $class->id])
                    ->with('info', 'You are already enrolled in this class.');
            }
        }

        $class->loadMissing(['classModules.programModule', 'training']);

        $pendingIntent = session('enrollment_intent');
        $pendingForThisClass = is_array($pendingIntent)
            && ($pendingIntent['class_id'] ?? null) === $class->id
            && ($pendingIntent['user_id'] ?? null);

        return view('mentee.enroll', compact('class', 'pendingForThisClass', 'pendingIntent'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 2 — Process email submission
    // POST /enroll/{token}
    // No auth required. Looks up user by email, stores intent, redirects to login.
    // ─────────────────────────────────────────────────────────────────────────

    public function submit(Request $request, string $token)
    {
        $class = $this->resolveClass($token);

        if (! $class) {
            return redirect()->back()->with('error', 'This enrollment link is no longer valid.');
        }

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'No account was found with that email address. Please contact your mentor to be registered on the platform.');
        }

        // Check if already enrolled
        $existing = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($existing) {
            // Already enrolled — direct them to login and they'll see their progress
            session()->put('url.intended', route('mentee.class.progress', ['class' => $class->id]));

            return redirect()->route('filament.admin.auth.login')
                ->with('info', 'You are already enrolled. Please log in to view your progress.');
        }

        // Store enrollment intent in session — completed after login
        session()->put('enrollment_intent', [
            'token' => $token,
            'class_id' => $class->id,
            'user_id' => $user->id,
            'email' => $email,
        ]);

        // After login, Laravel will redirect to intended URL
        session()->put('url.intended', route('mentee.enroll.complete', ['token' => $token]));
        session()->put('login_email', $email);

        return redirect()->route('filament.admin.auth.login')
            ->with('info', 'Please log in to complete your enrollment in '.$class->name.'.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 3 — Complete enrollment after login
    // GET /enroll/{token}/complete
    // Auth required. Reads intent from session, creates enrollment.
    // ─────────────────────────────────────────────────────────────────────────

    public function complete(string $token)
    {
        // Must be authenticated at this point
        $userId = auth()->id();

        if (! $userId) {
            session()->put('url.intended', request()->fullUrl());

            return redirect()->route('filament.admin.auth.login')
                ->with('info', 'Please log in to complete your enrollment.');
        }

        $class = $this->resolveClass($token);

        if (! $class) {
            return redirect('/')->with('error', 'This enrollment link is no longer active.');
        }

        if (in_array($class->status, ['completed', 'cancelled'])) {
            return redirect('/')->with('error', 'This class has already ended.');
        }

        // Guard: session intent must match logged-in user
        // Prevents user A from completing enrollment for user B
        $intent = session()->get('enrollment_intent');

        if ($intent && isset($intent['user_id']) && $intent['user_id'] !== $userId) {
            session()->forget('enrollment_intent');

            return redirect()->route('filament.admin.auth.login')
                ->with('error', 'Enrollment was initiated for a different account. Please log in with the correct account.');
        }

        // Already enrolled check
        $existing = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            session()->forget('enrollment_intent');

            return redirect()->route('mentee.class.progress', ['class' => $class->id])
                ->with('info', 'You are already enrolled in this class.');
        }

        // Perform enrollment
        DB::transaction(function () use ($class, $userId) {
            $participant = ClassParticipant::create([
                'mentorship_class_id' => $class->id,
                'user_id' => $userId,
                'status' => 'enrolled',
                'enrolled_at' => now(),
            ]);

            $this->moduleUsageService->cascadeAllModulesToParticipant($class, $participant);
        });

        session()->forget('enrollment_intent');

        return redirect()->route('mentee.class.progress', ['class' => $class->id])
            ->with('success', 'You have successfully enrolled in '.$class->name.'! Welcome aboard.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveClass(string $token): ?MentorshipClass
    {
        return MentorshipClass::where('enrollment_token', $token)
            ->where('enrollment_link_active', true)
            ->first();
    }
}
