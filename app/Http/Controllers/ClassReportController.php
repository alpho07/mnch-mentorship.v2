<?php

namespace App\Http\Controllers;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use App\Services\CpdPointsService;
use App\Services\EmoncReportingService;
use App\Services\ProgramCertificationService;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\Auth;

class ClassReportController extends Controller
{
    // ── Auth guard ────────────────────────────────────────────────────────────
    private function authorizeClass(MentorshipClass $class): void
    {
        $user = Auth::user();

        // Senior admins always pass
        if ($user->hasRole(['super_admin', 'admin', 'division', 'national_mentor_lead'])) {
            return;
        }

        // Head DRMH can view any certificate (they are the final certifying authority)
        if ($user->hasRole('head_drmh')) {
            return;
        }

        // Assigned mentor or co-mentor
        if ($class->training->mentor_id === $user->id || $class->training->isCoMentor($user->id)) {
            return;
        }

        // The mentee themselves can view their own certificate
        if ($user->hasRole('mentee')) {
            abort_unless(
                $class->participants()->where('user_id', $user->id)->exists(),
                403,
                'You do not have access to this class report.'
            );

            return;
        }

        abort(403, 'You do not have access to this class report.');
    }

    // ── Shared data builder ───────────────────────────────────────────────────
    private function buildReportData(MentorshipClass $class): array
    {
        $class->load([
            'training.program',
            'training.facility',
            'training.mentor',
            'training.coMentors.user',
            'classModules.programModule',
            'participants.user.cadre',
        ]);

        $modules = $class->classModules->sortBy('order_sequence');

        $mentees = $class->participants
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->sortBy(fn ($p) => $p->user->name ?? '')
            ->map(function (ClassParticipant $participant) use ($modules) {
                $progress = MenteeModuleProgress::where('class_participant_id', $participant->id)
                    ->whereIn('class_module_id', $modules->pluck('id'))
                    ->get()
                    ->keyBy('class_module_id');

                $attended = $progress->whereIn('status', ['in_progress', 'completed', 'exempted'])->count();
                $total = $modules->count();
                $rate = $total > 0 ? round(($attended / $total) * 100) : 0;

                return [
                    'participant' => $participant,
                    'user' => $participant->user,
                    'progress' => $progress,
                    'attended' => $attended,
                    'completed' => $progress->where('status', 'completed')->count(),
                    'total_modules' => $total,
                    'attendance_pct' => $rate,
                    'class_complete' => $participant->status === 'completed',
                ];
            });

        $totalEnrolled = $mentees->count();
        $totalCompleted = $mentees->where('class_complete', true)->count();
        $avgAttendance = $mentees->avg('attendance_pct');
        $coMentors = $class->training->coMentors
            ->where('status', 'accepted')
            ->pluck('user.name')
            ->filter()
            ->values();

        $emoncData = $this->buildEmoncData($class);

        return array_merge(
            compact('class', 'modules', 'mentees', 'totalEnrolled', 'totalCompleted', 'avgAttendance', 'coMentors'),
            $emoncData
        );
    }

    private function buildEmoncData(MentorshipClass $class): array
    {
        $programName = strtolower($class->training->program?->name ?? '');
        $isEmonc = str_contains($programName, 'maternal') && str_contains($programName, 'emonc');

        if (! $isEmonc) {
            return [
                'isEmonc' => false,
                'emoncReport' => null,
            ];
        }

        return [
            'isEmonc' => true,
            'emoncReport' => app(EmoncReportingService::class)->buildClassReport($class),
        ];
    }

    public function certificateHtml(MentorshipClass $class, ClassParticipant $participant)
    {
        $this->authorizeClass($class);
        abort_unless($participant->mentorship_class_id === $class->id, 404);

        $class->load(['training.program', 'training.facility', 'training.mentor']);
        $participant->load('user');

        $this->ensureCanViewCertificate($class, $participant);

        $modules = $class->classModules()->with('programModule')->orderBy('order_sequence')->get();
        $cpd = app(CpdPointsService::class)->forMentee($participant->user);
        $qr = app(QrCodeService::class)->dataUri(route('certificates.verify', ['class' => $class->id, 'participant' => $participant->id]));
        $isPdf = false;

        return view('certificates.completion', compact('class', 'participant', 'modules', 'cpd', 'qr', 'isPdf'));
    }

    // ── HTML Report (web view) ────────────────────────────────────────────────
    public function html(MentorshipClass $class)
    {
        $this->authorizeClass($class);
        $data = $this->buildReportData($class);

        return view('reports.class-report', $data);
    }

    // ── PDF Report ───────────────────────────────────────────────────────────
    public function pdf(MentorshipClass $class)
    {
        $this->authorizeClass($class);
        $data = $this->buildReportData($class);

        $filename = 'MNCH-Report-'.str($class->name)->slug().'-'.now()->format('Y-m-d').'.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.class-report', array_merge($data, ['isPdf' => true]))
            ->setPaper('a4', 'landscape')
            ->setOption([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ]);

        return $pdf->download($filename);
    }

    public function certificate(MentorshipClass $class, ClassParticipant $participant)
    {
        $this->authorizeClass($class);
        abort_unless($participant->mentorship_class_id === $class->id, 404);
        $class->load(['training.program', 'training.facility', 'training.mentor']);
        $participant->load(['user', 'mentorApprovedBy', 'headDrmhApprovedBy']);
        $this->ensureCanViewCertificate($class, $participant);

        $modules = $class->classModules()->with('programModule')->orderBy('order_sequence')->get();
        $cpd = app(CpdPointsService::class)->forMentee($participant->user);
        $qr = app(QrCodeService::class)->dataUri(route('certificates.verify', ['class' => $class->id, 'participant' => $participant->id]));
        $isPdf = true;

        $name = str($participant->user->name ?? 'mentee')->slug();
        $filename = "MNCH-Certificate-{$name}-".now()->format('Y-m-d').'.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('certificates.completion', compact('class', 'participant', 'modules', 'cpd', 'qr', 'isPdf'))
            ->setPaper('a4', 'landscape')
            ->setOption([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'fontHeightRatio' => 1.1,
            ]);

        return $pdf->download($filename);
    }

    public function mentorCertificateHtml(MentorshipClass $class)
    {
        $this->authorizeClass($class);
        $class->load(['training.program', 'training.facility', 'training.mentor']);
        abort_unless($class->status === 'completed', 403, 'Class is not yet completed.');

        $modules = $class->classModules()->with('programModule')->orderBy('order_sequence')->get();
        $mentor = $class->training->mentor;
        $cpd = $mentor ? app(CpdPointsService::class)->forMentor($mentor) : ['total' => 0, 'level' => ['name' => 'Foundation', 'short' => 'F', 'color' => '#6B7280']];

        return view('certificates.mentor-completion', compact('class', 'modules', 'mentor', 'cpd'));
    }

    public function mentorCertificate(MentorshipClass $class)
    {
        $this->authorizeClass($class);
        $class->load(['training.program', 'training.facility', 'training.mentor']);
        abort_unless($class->status === 'completed', 403, 'Class is not yet completed.');

        $modules = $class->classModules()->with('programModule')->orderBy('order_sequence')->get();
        $mentor = $class->training->mentor;
        $cpd = $mentor ? app(CpdPointsService::class)->forMentor($mentor) : ['total' => 0, 'level' => ['name' => 'Foundation', 'short' => 'F', 'color' => '#6B7280']];

        $name = str($mentor?->name ?? 'mentor')->slug();
        $filename = "MNCH-Mentor-Certificate-{$name}-".now()->format('Y-m-d').'.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('certificates.mentor-completion', compact('class', 'modules', 'mentor', 'cpd'))
            ->setPaper('a4', 'landscape')
            ->setOption([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'fontHeightRatio' => 1.1,
            ]);

        return $pdf->download($filename);
    }

    public function mentorProgramCertificateHtml(Program $program)
    {
        $data = $this->buildMentorProgramCertificateData($program);
        $data['isPdf'] = false;

        return view('certificates.mentor-program-completion', $data);
    }

    public function mentorProgramCertificate(Program $program)
    {
        return $this->downloadMentorProgramCertificate($this->buildMentorProgramCertificateData($program));
    }

    /**
     * Admin-viewable versions of the two above — same eligibility/authorization
     * logic, just for a specific $mentor rather than the logged-in viewer.
     */
    public function mentorProgramCertificateHtmlFor(User $mentor, Program $program)
    {
        $data = $this->buildMentorProgramCertificateData($program, $mentor);
        $data['isPdf'] = false;

        return view('certificates.mentor-program-completion', $data);
    }

    public function mentorProgramCertificateFor(User $mentor, Program $program)
    {
        return $this->downloadMentorProgramCertificate($this->buildMentorProgramCertificateData($program, $mentor));
    }

    private function downloadMentorProgramCertificate(array $data)
    {
        $data['isPdf'] = true;

        $name = str($data['mentor']->name ?? 'mentor')->slug();
        $filename = "MNCH-Mentor-Certificate-{$name}-".str($data['program']->name)->slug().'-'.now()->format('Y-m-d').'.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('certificates.mentor-program-completion', $data)
            ->setPaper('a4', 'landscape')
            ->setOption([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'fontHeightRatio' => 1.1,
            ]);

        return $pdf->download($filename);
    }

    /**
     * Self-serve mentor program certificate: no persisted approval flag —
     * eligibility is computed live from ProgramCertificationService, same as
     * the progress shown on the Mentor Certificates page. An admin-tier
     * viewer (same role list as authorizeClass()) may pass $mentor to view
     * any mentor's certificate, mirroring how they can already view any
     * mentee's certificate; anyone else viewing someone other than
     * themselves is rejected.
     */
    private function buildMentorProgramCertificateData(Program $program, ?User $mentor = null): array
    {
        $viewer = Auth::user();

        if ($mentor && $mentor->id !== $viewer->id) {
            abort_unless($viewer->hasRole(['super_admin', 'admin', 'division', 'national_mentor_lead', 'head_drmh']), 403);
        }

        $mentor ??= $viewer;

        $progress = collect(app(ProgramCertificationService::class)->mentorProgress($mentor))
            ->firstWhere('program_id', $program->id);

        abort_unless($progress && $progress['is_certified'], 403, 'Not all modules of this program have been completed yet.');

        $trainings = Training::where('mentor_id', $mentor->id)
            ->where('program_id', $program->id)
            ->where('type', 'facility_mentorship')
            ->with('facility')
            ->get();

        $modules = ClassModule::whereHas('mentorshipClass', fn ($q) => $q->whereIn('training_id', $trainings->pluck('id')))
            ->where('status', 'completed')
            ->with('programModule')
            ->get()
            ->unique('program_module_id')
            ->sortBy(fn (ClassModule $cm) => $cm->programModule?->id)
            ->values();

        $cpd = app(CpdPointsService::class)->forMentor($mentor);
        $qr = app(QrCodeService::class)->dataUri(route('certificates.mentor-program.verify', ['mentor' => $mentor->id, 'program' => $program->id]));

        return compact('program', 'mentor', 'trainings', 'modules', 'cpd', 'qr');
    }

    public function verifyMentorProgramCertificate(User $mentor, Program $program)
    {
        $progress = collect(app(ProgramCertificationService::class)->mentorProgress($mentor))
            ->firstWhere('program_id', $program->id);

        $isValid = (bool) ($progress['is_certified'] ?? false);

        return view('certificates.mentor-program-verify', compact('mentor', 'program', 'isValid', 'progress'));
    }

    private function ensureCanViewCertificate(MentorshipClass $class, ClassParticipant $participant): void
    {
        if ($class->training->program?->usesPerProgramCertification()) {
            abort_unless($participant->isCertified(), 403, 'This mentee has not completed the mentor and Head DRMH approval process.');

            return;
        }

        abort_unless($participant->status === 'completed', 403, 'Mentee has not completed the class.');
    }

    public function verifyCertificate(MentorshipClass $class, ClassParticipant $participant)
    {
        abort_unless($participant->mentorship_class_id === $class->id, 404);

        $isValid = $participant->isCertified();

        $class->load(['training.program', 'training.facility', 'training.mentor']);
        $participant->load(['user', 'mentorApprovedBy', 'headDrmhApprovedBy']);

        return view('certificates.verify', compact('class', 'participant', 'isValid'));
    }

    public function badge(MentorshipClass $class, ClassParticipant $participant)
    {
        abort_unless($participant->mentorship_class_id === $class->id, 404);
        abort_unless($participant->isCertified(), 403);

        $participant->load('user');
        $class->load('training.program');

        $svg = view('certificates.badge', compact('class', 'participant'))->render();

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }
}
