<?php

use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AssessmentResponseController;
use App\Http\Controllers\Api\AssessmentSectionController;
use App\Http\Controllers\Api\AssessmentTeamController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\FacilityController;
use App\Http\Controllers\Api\HealthProductsController;
use App\Http\Controllers\Api\HumanResourceController;
use App\Http\Controllers\Api\MentorshipController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Middleware\MobileApiCors;

/*
  |--------------------------------------------------------------------------
  | MNCH Mobile API Routes
  |--------------------------------------------------------------------------
  |
  | All routes are versioned under /api/v1/
  | Authentication uses Laravel Sanctum token-based auth.
  | Unauthenticated requests to protected routes return 401.
  |
 */

// MobileApiCors is the outermost middleware — it runs on every request
// including OPTIONS preflight, and unconditionally sets CORS headers.
// This covers: emulator (https://localhost), real device over WiFi (no Origin
// header), iOS (capacitor://localhost), and browser dev (localhost:5173).
Route::prefix('v1')->name('api.v1.')->middleware(MobileApiCors::class)->group(function () {

    // =========================================================================
    // PUBLIC — No authentication required
    // =========================================================================
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });

    Route::get('health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'MNCH Assessment API',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ]))->name('health');

    // =========================================================================
    // PROTECTED — Requires Sanctum token (Authorization: Bearer {token})
    // =========================================================================
    Route::middleware(['auth:sanctum', 'api.active'])->group(function () {

        // ── Auth ──────────────────────────────────────────────────────────────
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
        });

        // ── Profile ───────────────────────────────────────────────────────────
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'show'])->name('show');
            Route::put('/', [ProfileController::class, 'update'])->name('update');
            Route::put('password', [ProfileController::class, 'changePassword'])->name('change-password');
            Route::post('avatar', [ProfileController::class, 'uploadAvatar'])->name('avatar');
            Route::get('stats', [ProfileController::class, 'stats'])->name('stats');
        });

        // ── Facilities ────────────────────────────────────────────────────────
        Route::prefix('facilities')->name('facilities.')->group(function () {
            Route::get('/', [FacilityController::class, 'index'])->name('index');
            Route::get('{facility}', [FacilityController::class, 'show'])->name('show');
            Route::get('county/{countyId}', [FacilityController::class, 'byCounty'])->name('by-county');
        });

        // ── Assessment Sections & Questions (form schema) ─────────────────────
        Route::prefix('sections')->name('sections.')->group(function () {
            Route::get('/', [AssessmentSectionController::class, 'index'])->name('index');
            Route::get('schema/full', [AssessmentSectionController::class, 'fullSchema'])->name('schema');
            Route::get('{section}', [AssessmentSectionController::class, 'show'])->name('show');
        });

        // ── Assessments ───────────────────────────────────────────────────────
        Route::prefix('assessments')->name('assessments.')->group(function () {
            Route::get('/', [AssessmentController::class, 'index'])->name('index');
            Route::post('/', [AssessmentController::class, 'store'])->name('store');
            Route::get('{assessment}', [AssessmentController::class, 'show'])->name('show');
            Route::put('{assessment}', [AssessmentController::class, 'update'])->name('update');
            Route::delete('{assessment}', [AssessmentController::class, 'destroy'])->name('destroy');
            Route::post('{assessment}/submit', [AssessmentController::class, 'submit'])->name('submit');

            // ── Human Resources ───────────────────────────────────────────────
            Route::get('{assessment}/human-resources', [HumanResourceController::class, 'index'])->name('human-resources.index');
            Route::post('{assessment}/human-resources', [HumanResourceController::class, 'store'])->name('human-resources.store');

            // ── Health Products ───────────────────────────────────────────────
            Route::get('{assessment}/health-products', [HealthProductsController::class, 'index'])->name('health-products.index');
            Route::post('{assessment}/health-products', [HealthProductsController::class, 'store'])->name('health-products.store');

            // ── Section progress ──────────────────────────────────────────────
            Route::put('{assessment}/sections/{sectionCode}/progress',
                [AssessmentController::class, 'updateSectionProgress'])->name('section-progress');

            // ── Team ──────────────────────────────────────────────────────────
            Route::get('{assessment}/team', [AssessmentTeamController::class, 'show'])->name('team.show');
            Route::get('{assessment}/team/eligible', [AssessmentTeamController::class, 'eligible'])->name('team.eligible');
            Route::post('{assessment}/team', [AssessmentTeamController::class, 'store'])->name('team.store');

            // ── Responses ─────────────────────────────────────────────────────
            Route::prefix('{assessment}/responses')->name('responses.')->group(function () {
                Route::get('/', [AssessmentResponseController::class, 'index'])->name('index');
                Route::post('/', [AssessmentResponseController::class, 'bulkStore'])->name('bulk-store');
                Route::get('{questionCode}', [AssessmentResponseController::class, 'show'])->name('show');
            });

            // ── Reports ───────────────────────────────────────────────────────
            Route::prefix('{assessment}/report')->name('report.')->group(function () {
                Route::get('/', [ReportController::class, 'show'])->name('show');
                Route::get('pdf', [ReportController::class, 'downloadPdf'])->name('pdf');
                Route::get('summary', [ReportController::class, 'summary'])->name('summary');
                Route::post('email', [ReportController::class, 'emailReport'])->name('email');
                Route::get('email/{emailJob}', [ReportController::class, 'emailJobStatus'])->name('email-status');
            });
        });

        // ── Analytics Summary (mobile dashboard) ─────────────────────────────
        Route::get('analytics/summary', [\App\Http\Controllers\Api\AnalyticsSummaryController::class, 'summary'])->name('analytics.summary');

        // ── Aggregate Reports ─────────────────────────────────────────────────
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('dashboard', [ReportController::class, 'dashboard'])->name('dashboard');
            Route::get('section-averages', [ReportController::class, 'sectionAverages'])->name('section-averages');
            Route::get('email-jobs', [ReportController::class, 'emailJobs'])->name('email-jobs');
        });

        // ── Lookup ────────────────────────────────────────────────────────────────
        Route::prefix('programs')->name('programs.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\LookupController::class, 'programs'])->name('index');
            Route::get('{program}/modules', [\App\Http\Controllers\Api\LookupController::class, 'programModules'])->name('modules');
        });
        Route::get('counties', [\App\Http\Controllers\Api\LookupController::class, 'counties'])->name('counties');
        Route::get('cadres', [\App\Http\Controllers\Api\LookupController::class, 'cadres'])->name('cadres');
        Route::get('departments', [\App\Http\Controllers\Api\LookupController::class, 'departments'])->name('departments');
        Route::get('counties/{county}/facilities', [\App\Http\Controllers\Api\LookupController::class, 'facilitiesByCounty'])->name('counties.facilities');
        Route::get('users/by-email', [\App\Http\Controllers\Api\LookupController::class, 'userByEmail'])->name('users.by-email');
        Route::get('users/search', [\App\Http\Controllers\Api\LookupController::class, 'userSearch'])->name('users.search');
        Route::get('users/lookup-index', [\App\Http\Controllers\Api\LookupController::class, 'userLookupIndex'])->name('users.lookup-index');

        // ── Mentorships ───────────────────────────────────────────────────────
        Route::prefix('mentorships')->name('mentorships.')->group(function () {
            Route::get('/', [MentorshipController::class, 'index'])->name('index');
            Route::get('{training}', [MentorshipController::class, 'show'])->name('show');
            Route::get('{training}/classes', [MentorshipController::class, 'classes'])->name('classes');
            Route::get('{training}/classes/{class}', [MentorshipController::class, 'classDetail'])->name('class-detail');
            Route::post('/', [\App\Http\Controllers\Api\MentorshipCreateController::class, 'store'])->name('store');
            Route::put('{training}', [\App\Http\Controllers\Api\MentorshipCreateController::class, 'update'])->name('update');
            Route::post('{training}/submit', [\App\Http\Controllers\Api\MentorshipCreateController::class, 'submit'])->name('submit');
            // ── Class CRUD ────────────────────────────────────────────────────
            Route::post('{training}/classes', [MentorshipController::class, 'createClass'])->name('classes.store');
            Route::put('{training}/classes/{class}', [MentorshipController::class, 'updateClass'])->name('classes.update');
            Route::delete('{training}/classes/{class}', [MentorshipController::class, 'deleteClass'])->name('classes.destroy');
        });

        // ── Class modules ─────────────────────────────────────────────────────────
        Route::prefix('classes/{class}/modules')->name('class.modules.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\ClassModuleController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Api\ClassModuleController::class, 'store'])->name('store');
        });
        Route::prefix('modules/{module}')->name('module.')->group(function () {
            Route::delete('/', [\App\Http\Controllers\Api\ClassModuleController::class, 'destroy'])->name('destroy');
            Route::post('start', [\App\Http\Controllers\Api\ClassModuleController::class, 'start'])->name('start');
            Route::post('complete', [\App\Http\Controllers\Api\ClassModuleController::class, 'complete'])->name('complete');
            Route::get('sessions', [\App\Http\Controllers\Api\ClassModuleController::class, 'sessions'])->name('sessions');
            Route::get('sessions/available-templates', [\App\Http\Controllers\Api\ClassSessionController::class, 'availableTemplates'])->name('sessions.templates');
            Route::post('sessions', [\App\Http\Controllers\Api\ClassSessionController::class, 'store'])->name('sessions.store');
            Route::get('attendance', [\App\Http\Controllers\Api\AttendanceApiController::class, 'roster'])->name('attendance.roster');
            Route::post('attendance/bulk', [\App\Http\Controllers\Api\AttendanceApiController::class, 'bulk'])->name('attendance.bulk');
            Route::post('attendance/{participant}', [\App\Http\Controllers\Api\AttendanceApiController::class, 'mark'])->name('attendance.mark');
        });

        // ── Classes / participants ─────────────────────────────────────────────────
        Route::get('classes/{class}/participants', [\App\Http\Controllers\Api\ParticipantController::class, 'index'])->name('classes.participants');
        Route::get('participants/{participant}/progress', [\App\Http\Controllers\Api\ParticipantController::class, 'progress'])->name('participants.progress');

        // ── Class lifecycle + mentee enrollment ───────────────────────────────────
        Route::prefix('classes/{class}')->name('class.')->group(function () {
            Route::post('start', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'start'])->name('start');
            Route::post('end', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'end'])->name('end');
            Route::get('report', [\App\Http\Controllers\Api\ClassReportApiController::class, 'show'])->name('report');
            Route::post('mentees', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'enrollMentee'])->name('mentees.store');
            Route::post('mentees/create', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'createMentee'])->name('mentees.create');
            Route::patch('mentees/{participant}', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'updateMentee'])->name('mentees.update');
            Route::post('mentees/{participant}/invite', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'markInvited'])->name('mentees.invite');
            Route::delete('mentees/{participant}', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'removeMentee'])->name('mentees.destroy');
            Route::post('regenerate-token', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'regenerateToken'])->name('regenerate-token');
            Route::get('enrollment-link', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'enrollmentLink'])->name('enrollment-link');
        });

        // ── Session notes / lifecycle ─────────────────────────────────────────────
        Route::put('sessions/{session}', [\App\Http\Controllers\Api\ClassSessionController::class, 'update'])->name('sessions.update');
        Route::delete('sessions/{session}', [\App\Http\Controllers\Api\ClassSessionController::class, 'destroy'])->name('sessions.destroy');

        // ── Mentee (self) ─────────────────────────────────────────────────────────
        Route::prefix('me/classes')->name('me.classes.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\MenteeApiController::class, 'index'])->name('index');
            Route::get('{class}', [\App\Http\Controllers\Api\MenteeApiController::class, 'show'])->name('show');
            Route::post('{class}/modules/{module}/attend', [\App\Http\Controllers\Api\MenteeApiController::class, 'attend'])->name('attend');
            Route::get('{class}/modules/{module}', [\App\Http\Controllers\Api\MenteeApiController::class, 'moduleDetail'])->name('module-detail');
            Route::post('{class}/modules/{module}/quiz/{quiz}/start', [\App\Http\Controllers\Api\MenteeApiController::class, 'startQuiz'])->name('quiz.start');
            Route::post('{class}/modules/{module}/video', [\App\Http\Controllers\Api\MenteeApiController::class, 'uploadVideo'])->name('video.upload');
        });
        Route::post('me/quiz-attempts/{attempt}/submit', [\App\Http\Controllers\Api\MenteeApiController::class, 'submitQuiz'])->name('me.quiz.submit');

        // ── Global trainings ──────────────────────────────────────────────────────
        Route::prefix('trainings')->name('trainings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'index'])->name('index');
            Route::get('{training}', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'show'])->name('show');
            Route::get('{training}/participants', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'participants'])->name('participants');
            Route::post('{training}/enroll', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'enroll'])->name('enroll');
            Route::post('{training}/attendance', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'attendance'])->name('attendance');
        });

        // ── Scope config ──────────────────────────────────────────────────────────
        Route::get('scope-config', [\App\Http\Controllers\Api\ScopeConfigController::class, 'index'])
            ->name('scope-config');

        // ── Resources ─────────────────────────────────────────────────────────────
        Route::get('resources', [\App\Http\Controllers\Api\ResourceController::class, 'index'])->name('resources.index');

        // ── Chat Assistant ──────────────────────────────────────────────────
        Route::post('chat/assistant', [ChatController::class, 'assistant'])->name('chat.assistant');
    });
});
