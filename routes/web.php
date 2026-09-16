<?php

use App\Http\Controllers\AccountVerificationController;
use App\Http\Controllers\Analytics\KenyaHeatmapController;
use App\Http\Controllers\Analytics\ProgressiveDashboardController;
use App\Http\Controllers\Analytics\TrainingExplorerController;
use App\Http\Controllers\AnalyticsDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Frontend\CategoryController;
use App\Http\Controllers\Frontend\CommentController;
use App\Http\Controllers\Frontend\InteractionController;
use App\Http\Controllers\Frontend\ResourceController;
use App\Http\Controllers\Frontend\SearchController;
use App\Http\Controllers\MenteeClassProgressController;
use App\Http\Controllers\MenteeEnrollmentController;
use App\Http\Controllers\ModuleAttendanceController;
use App\Http\Controllers\RagChatStreamController;
use App\Http\Controllers\RagDocumentDownloadController;
use App\Http\Controllers\RagMediaController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
/*
  |--------------------------------------------------------------------------
  | Web Routes - Complete Resource Management System
  |--------------------------------------------------------------------------
 */
use League\Csv\Writer;

Route::get('/test-mail', function () {
    Mail::raw('Gmail is working!', function ($message) {
        $message->to('alpho07@gmail.com')
            ->subject('Test Email');
    });

    return 'Email sent!';
});

Route::middleware(['auth'])->prefix('admin/reports')->name('reports.')->group(function () {
    Route::get('/class/{class}/html', [\App\Http\Controllers\ClassReportController::class, 'html'])->name('class.html');
    Route::get('/class/{class}/pdf', [\App\Http\Controllers\ClassReportController::class, 'pdf'])->name('class.pdf');
    Route::get('/class/{class}/certificate/{participant}', [\App\Http\Controllers\ClassReportController::class, 'certificate'])->name('class.certificate');
    Route::get('/class/{class}/certificate/{participant}/preview', [\App\Http\Controllers\ClassReportController::class, 'certificateHtml'])->name('class.certificate.preview');
    Route::get('/class/{class}/mentor-certificate', [\App\Http\Controllers\ClassReportController::class, 'mentorCertificate'])->name('class.mentor-certificate');
    Route::get('/class/{class}/mentor-certificate/preview', [\App\Http\Controllers\ClassReportController::class, 'mentorCertificateHtml'])->name('class.mentor-certificate.preview');
    Route::get('/mentor-certificate/program/{program}', [\App\Http\Controllers\ClassReportController::class, 'mentorProgramCertificate'])->name('mentor.program-certificate');
    Route::get('/mentor-certificate/program/{program}/preview', [\App\Http\Controllers\ClassReportController::class, 'mentorProgramCertificateHtml'])->name('mentor.program-certificate.preview');
    // Admin-viewable — same shape as class.certificate above, for viewing any mentor's certificate without impersonation.
    Route::get('/mentor-certificate/{mentor}/program/{program}', [\App\Http\Controllers\ClassReportController::class, 'mentorProgramCertificateFor'])->name('mentor.program-certificate.for');
    Route::get('/mentor-certificate/{mentor}/program/{program}/preview', [\App\Http\Controllers\ClassReportController::class, 'mentorProgramCertificateHtmlFor'])->name('mentor.program-certificate.preview.for');
});

Route::middleware(['auth', 'throttle:downloads'])
    ->get('/admin/rag/documents/{document}/download', RagDocumentDownloadController::class)
    ->name('rag.documents.download');

Route::middleware(['auth', 'throttle:downloads'])
    ->get('/admin/rag/media/{externalDocumentId}/{filename}', RagMediaController::class)
    ->whereUuid('externalDocumentId')
    ->where('filename', '[A-Za-z0-9._-]+')
    ->name('rag.media.show');

Route::middleware(['auth'])
    ->post('/admin/rag/chat/stream', RagChatStreamController::class)
    ->name('rag.chat.stream');

Route::get('/certificates/{class}/{participant}/verify', [\App\Http\Controllers\ClassReportController::class, 'verifyCertificate'])->name('certificates.verify');
Route::get('/certificates/{class}/{participant}/badge', [\App\Http\Controllers\ClassReportController::class, 'badge'])->name('certificates.badge');
Route::get('/certificates/mentor/{mentor}/{program}/verify', [\App\Http\Controllers\ClassReportController::class, 'verifyMentorProgramCertificate'])->name('certificates.mentor-program.verify');

Route::get('/admin/set-password/{token}', App\Livewire\Auth\CustomResetPassword::class)
    ->middleware(['web', 'guest'])
    ->name('password.reset.custom');

Route::middleware('guest')->group(function () {

    // GET  /account/verify/{user}?expires=...&signature=...
    // Shows the "Hello {name}, set your password" page
    Route::get('/account/verify/{user}', [AccountVerificationController::class, 'show'])
        ->name('account.verify.show');

    // POST /account/verify/{user}?expires=...&signature=...
    // Validates password, marks verified, auto-logs in, redirects to /admin
    Route::post('/account/verify/{user}', [AccountVerificationController::class, 'update'])
        ->name('account.verify.update');
});

// Livewire upload bypass — extends FileUploadController, skips signature check
Route::post('/livewire/upload-file', [\App\Http\Controllers\LivewireUploadController::class, 'handle'])
    ->name('livewire.upload-file');

Route::get('/check-upload-config', function () {
    return [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_file_uploads' => ini_get('max_file_uploads'),
        'livewire_tmp_exists' => is_dir(storage_path('app/livewire-tmp')),
        'livewire_tmp_writable' => is_writable(storage_path('app/livewire-tmp')),
        'thumbnails_exists' => is_dir(storage_path('app/public/thumbnails')),
        'thumbnails_writable' => is_writable(storage_path('app/public/thumbnails')),
    ];
});

Route::get('/test-signature', function () {
    $url = URL::temporarySignedRoute('test', now()->addMinutes(1));

    return [
        'generated_url' => $url,
        'current_app_url' => config('app.url'),
        'request_is_secure' => request()->isSecure() ? 'Yes' : 'No',
        'signature_valid' => request()->hasValidSignature() ? 'Yes' : 'No',
    ];
})->name('test');

// Override Livewire upload to bypass signature
// Module attendance routes (public/guest access)
Route::get('/module/attend/{token}', [ModuleAttendanceController::class, 'attend'])
    ->name('module.attend');

Route::post('/module/attend/{token}', [ModuleAttendanceController::class, 'processAttendance'])
    ->name('module.attend.submit');

// Mentee enrollment routes (public/guest access)
Route::get('/enroll/{token}', [MenteeEnrollmentController::class, 'show'])
    ->name('mentee.enroll');

Route::post('/enroll/{token}', [MenteeEnrollmentController::class, 'submit'])
    ->name('mentee.enroll.submit');

// Mentee authenticated routes
Route::middleware(['auth'])->group(function () {
    Route::get('/my-class/{class}', [MenteeClassProgressController::class, 'show'])
        ->name('mentee.class.progress');

    Route::get('/my-class/{class}/module/{classModule}', [MenteeClassProgressController::class, 'module'])
        ->name('mentee.class.module');

    Route::post('/my-class/{class}/module/{classModule}/quiz/{quiz}/start', [MenteeClassProgressController::class, 'startQuiz'])
        ->name('mentee.class.quiz.start');

    Route::post('/my-class/{class}/module/{classModule}/quiz/{attempt}/submit', [MenteeClassProgressController::class, 'submitQuiz'])
        ->name('mentee.class.quiz.submit');

    Route::post('/my-class/{class}/module/{classModule}/video', [MenteeClassProgressController::class, 'uploadHandsOnVideo'])
        ->name('mentee.class.video.upload');
});

Route::get('/attend/{token}', [App\Http\Controllers\ModuleAttendanceController::class, 'confirm'])
    ->name('module.attendance');

// ── Auth required — complete enrollment after login ───────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::get('/enroll/{token}/complete', [MenteeEnrollmentController::class, 'complete'])
        ->name('mentee.enroll.complete');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/assessments/{assessment}/executive', [\App\Http\Controllers\AssessmentExecutiveDashboardController::class, 'show'])
        ->name('assessment.executive');

    Route::get('/assessments/{assessment}/executive/export', [\App\Http\Controllers\AssessmentExecutiveDashboardController::class, 'export'])
        ->name('assessment.executive.export');
});

Route::prefix('analytics/dashboard')->name('analytics.dashboard.')->group(function () {
    // Main dashboard
    Route::get('/', [AnalyticsDashboardController::class, 'index'])->name('index');

    // GeoJSON endpoint for map data (REQUIRED for map to work)
    Route::get('/geojson', [AnalyticsDashboardController::class, 'geojson'])->name('heatmap.geojson');
    Route::get('/training-data', [AnalyticsDashboardController::class, 'getTrainingData'])->name('training-data');

    // County level
    Route::get('/county/{county}', [AnalyticsDashboardController::class, 'county'])->name('county');

    // ADD THESE NEW ROUTES FOR MENTORSHIP FLOW:
    // County -> Mentorships API (for sidebar)
    Route::get('/county/{county}/mentorships', [AnalyticsDashboardController::class, 'countyMentorships'])->name('county-mentorships');

    // Facility -> Mentorships List (for mentorships)
    Route::get('/county/{county}/facility/{facility}/mentorships', [AnalyticsDashboardController::class, 'facilityMentorships'])->name('facility-mentorships');

    // Program level (Training or Mentorship)
    Route::get('/county/{county}/program/{program}', [AnalyticsDashboardController::class, 'program'])->name('program');

    Route::get('/county/{county}/facilities', [AnalyticsDashboardController::class, 'countyFacilities'])
        ->name('analytics.dashboard.county.facilities');

    Route::get('/county/{county}/facility/{facility}/programs', [AnalyticsDashboardController::class, 'facilityPrograms'])
        ->name('analytics.dashboard.facility.programs');

    // Add this route for mentorship participants (without facility level)
    Route::get('/county/{county}/program/{program}/participant/{participant}',
        [AnalyticsDashboardController::class, 'mentorshipParticipant'])
        ->name('analytics.dashboard.mentorship.participant');
    // Facility level
    Route::get('/county/{county}/program/{program}/facility/{facility}', [AnalyticsDashboardController::class, 'facility'])->name('facility');

    // Participant/Mentee profile
    Route::get('/county/{county}/program/{program}/facility/{facility}/participant/{participant}', [AnalyticsDashboardController::class, 'participant'])->name('participant');

    // AJAX endpoints for dynamic data
    Route::post('/ajax/county-data', [AnalyticsDashboardController::class, 'getCountyData'])->name('ajax.county-data');
    Route::post('/ajax/coverage-charts', [AnalyticsDashboardController::class, 'getCoverageCharts'])->name('ajax.coverage-charts');
    Route::post('/ajax/export-data', [AnalyticsDashboardController::class, 'exportData'])->name('ajax.export-data');

    Route::get('/assessment/export-readiness', [AnalyticsDashboardController::class, 'exportReadiness'])->name('assessment.export-readiness');

    Route::get('/facility/{facility}/mentorship-breakdown',
        [AnalyticsDashboardController::class, 'facilityMentorshipBreakdown'])
        ->name('facility.mentorship-breakdown');

    Route::get('/emonc', [\App\Http\Controllers\EmoncAnalyticsDashboardController::class, 'index'])
        ->middleware('auth')
        ->name('emonc');
});
// Healthcare Training Dashboard Routes
Route::prefix('training-dashboard')->name('dashboard.')->group(function () {

    // Main dashboard view
    Route::get('/', [ProgressiveDashboardController::class, 'index'])->name('index');

    // API Routes for AJAX calls
    Route::prefix('api')->name('api.')->group(function () {

        // Core Data Endpoints
        Route::get('overview', [ProgressiveDashboardController::class, 'getNationalOverview'])->name('overview');
        Route::get('county/{id}', [ProgressiveDashboardController::class, 'getCountyAnalysis'])->name('county');
        Route::get('facility-type/{countyId}/{typeId}', [ProgressiveDashboardController::class, 'getFacilityTypeAnalysis'])->name('facility.type');
        Route::get('facility/{id}', [ProgressiveDashboardController::class, 'getFacilityAnalysis'])->name('facility');
        Route::get('participant/{id}', [ProgressiveDashboardController::class, 'getParticipantProfile'])->name('participant');

        // Enhanced Drill-down Routes
        Route::get('training/{id}/participants', [ProgressiveDashboardController::class, 'getTrainingParticipants'])->name('training.participants');
        Route::get('department/{countyId}/{name}/staff', [ProgressiveDashboardController::class, 'getDepartmentParticipants'])->name('department.staff');
        Route::get('facility/{id}/all-users', [ProgressiveDashboardController::class, 'getFacilityUsers'])->name('facility.users');

        // Filter Data
        Route::get('years', [ProgressiveDashboardController::class, 'getAvailableYears'])->name('years');
        Route::get('stats', [ProgressiveDashboardController::class, 'getDashboardStats'])->name('stats');

        // Search & Compare
        Route::get('search', [ProgressiveDashboardController::class, 'searchFacilities'])->name('search');
        Route::post('compare', [ProgressiveDashboardController::class, 'getCountyComparison'])->name('compare');

        // System
        Route::post('cache/clear', [ProgressiveDashboardController::class, 'clearCache'])->name('cache.clear');
        Route::get('health', [ProgressiveDashboardController::class, 'getApiStatus'])->name('health');
    });

    // Export Routes
    Route::prefix('export')->name('export.')->group(function () {
        Route::get('county/{id}', [ProgressiveDashboardController::class, 'exportCountyData'])->name('county');
        Route::get('facility/{id}', [ProgressiveDashboardController::class, 'exportFacilityData'])->name('facility');
    });
});

// ==========================================
// ADMIN ROUTES (Optional)
// ==========================================

Route::prefix('admin/analytics/progressive-dashboard')->middleware(['auth', 'admin'])->name('admin.analytics.progressive-dashboard.')->group(function () {

    Route::get('system-info', function () {
        return response()->json([
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'cache_driver' => config('cache.default'),
            'database_connection' => config('database.default'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'disk_space' => disk_free_space('/'),
        ]);
    })->name('system-info');

    Route::post('rebuild-cache', function () {
        \Illuminate\Support\Facades\Cache::flush();

        return response()->json(['message' => 'All caches rebuilt successfully']);
    })->name('rebuild-cache');

    Route::get('performance-metrics', function () {
        return response()->json([
            'cache_hit_rate' => 85.4,
            'average_response_time' => 245,
            'active_users' => 23,
            'total_requests_today' => 1247,
        ]);
    })->name('performance-metrics');
});

Route::get('/dashboard/national', [DashboardController::class, 'national'])->name('dashboard.national');

Route::prefix('dashboard')->name('main-dashboard.')->group(function () {
    Route::get('/', [App\Http\Controllers\Dashboard\DashboardController::class, 'index'])->name('index');

    // API endpoints for dashboard data
    Route::get('/api/overview', [App\Http\Controllers\Dashboard\DashboardController::class, 'getOverviewStats'])->name('api.overview');
    Route::get('/api/counties-heatmap', [App\Http\Controllers\Dashboard\DashboardController::class, 'getCountiesHeatmapData'])->name('api.counties');
    Route::get('/api/enhanced-insights', [App\Http\Controllers\Dashboard\DashboardController::class, 'getEnhancedInsights'])->name('api.enhanced-insights');
    Route::get('/api/coverage/facility-type', [App\Http\Controllers\Dashboard\DashboardController::class, 'getCoverageByFacilityType'])->name('api.facility-type');
    Route::get('/api/coverage/department', [App\Http\Controllers\Dashboard\DashboardController::class, 'getCoverageByDepartment'])->name('api.department');
    Route::get('/api/coverage/cadre', [App\Http\Controllers\Dashboard\DashboardController::class, 'getCoverageByCadre'])->name('api.cadre');
    Route::get('/api/county/{county}', [App\Http\Controllers\Dashboard\DashboardController::class, 'getCountyDetails'])->name('api.county-details');
    Route::get('/api/years', [App\Http\Controllers\Dashboard\DashboardController::class, 'getAvailableYears'])->name('api.years');

    // Drill-down routes
    Route::get('/county/{county}', function ($county) {
        return view('dashboard.county', compact('county'));
    })->name('county');

    Route::get('/county/{county}/facility/{facility}', function ($county, $facility) {
        return view('dashboard.facility', compact('county', 'facility'));
    })->name('facility');
});

Route::prefix('co-mentor')->name('co-mentor.accept')->group(function () {
    Route::get('/accept/{token}', [\App\Http\Controllers\CoMentorController::class, 'show']);
    Route::post('/accept/{token}', [\App\Http\Controllers\CoMentorController::class, 'process'])
        ->middleware('auth')
        ->name('.process');
});

Route::get('login-v1', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

// Signed temporary URL for external viewers (Google Docs / Office Online) — no auth, expires quickly
Route::get('resource-files/{file}/temp-view', [App\Http\Controllers\Admin\ResourceFileController::class, 'tempView'])
    ->name('resource-files.temp-view')
    ->middleware('signed');

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Resource file operations
    Route::get('resource-files/{file}/download', [App\Http\Controllers\Admin\ResourceFileController::class, 'download'])
        ->name('resource-files.download');
    Route::get('resource-files/{file}/preview', [App\Http\Controllers\Admin\ResourceFileController::class, 'preview'])
        ->name('resource-files.preview');

    // Resource operations (for primary file or single resource)
    Route::get('resources/{resource}/download', [App\Http\Controllers\Admin\ResourceController::class, 'download'])
        ->name('resources.download');
    Route::get('resources/{resource}/preview', [App\Http\Controllers\Admin\ResourceController::class, 'preview'])
        ->name('resources.preview');
});

// Alternative: If you want to handle it within Filament's context
Route::middleware(['auth', 'web'])->group(function () {
    Route::get('/training-export/download/{export_id}', [\App\Http\Controllers\TrainingExportController::class, 'download'])
        ->name('training-export.download');
});

// Training participant template download
Route::get('/{training}/participants/template', function ($trainingId) {
    $csv = Writer::createFromString('');

    // Add headers
    $csv->insertOne([
        'first_name',
        'last_name',
        'phone',
        'email',
        'facility_name',
        'facility_mfl_code',
        'department_name',
        'cadre_name',
    ]);

    // Add sample data
    $csv->insertOne([
        'John',
        'Doe',
        '+254700123456',
        'john.doe@example.com',
        'Kenyatta National Hospital',
        'KNH001',
        'Nursing',
        'Registered Nurse',
    ]);

    $csv->insertOne([
        'Jane',
        'Smith',
        '+254711234567',
        'jane.smith@example.com',
        'Moi Teaching Hospital',
        'MTH002',
        'Laboratory',
        'Lab Technician',
    ]);

    $filename = 'participants_import_template.csv';

    return response($csv->toString(), 200, [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    ]);
})->name('participants.template');

Route::get('/', [ResourceController::class, 'home'])->name('home');
Route::get('/user-manual', fn () => view('frontend.user-manual'))->name('manual');

// ===== RESOURCE CENTER ROUTES =====
Route::prefix('resources')->name('resources.')->group(function () {

    // === MAIN RESOURCE ROUTES ===
    Route::get('/', [ResourceController::class, 'index'])->name('index');
    Route::get('/search', [SearchController::class, 'index'])
        ->name('search')
        ->middleware('throttle:search');
    Route::get('/browse', [ResourceController::class, 'browse'])->name('browse');

    // === USER-SPECIFIC ROUTES (AUTHENTICATED) ===
    Route::middleware('auth')->group(function () {
        Route::get('/bookmarks', [ResourceController::class, 'bookmarks'])->name('bookmarks');
        Route::get('/downloads', [ResourceController::class, 'downloads'])->name('downloads');
    });

    // === FILTER & BROWSE ROUTES ===
    Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('category');
    Route::get('/type/{type:slug}', [ResourceController::class, 'byType'])->name('type');
    Route::get('/tag/{tag:slug}', [ResourceController::class, 'byTag'])->name('tag');

    // === INDIVIDUAL RESOURCE ROUTES ===
    Route::get('/{resource:slug}', [ResourceController::class, 'show'])->name('show');

    // === FILE HANDLING ROUTES ===
    Route::get('/{resource:slug}/download', [ResourceController::class, 'download'])
        ->name('download');
    // ->middleware('throttle:downloads');

    Route::get('/{resource:slug}/preview', [ResourceController::class, 'preview'])
        ->name('preview');
    // ->middleware('throttle:previews');

    Route::get('/{resource:slug}/thumbnail', [ResourceController::class, 'thumbnail'])
        ->name('thumbnail');

    // === RESOURCE INTERACTIONS (AUTHENTICATED USERS) ===
    Route::middleware(['auth', 'throttle:interactions'])->group(function () {
        Route::post('/{resource:slug}/like', [InteractionController::class, 'like'])->name('like');
        Route::post('/{resource:slug}/dislike', [InteractionController::class, 'dislike'])->name('dislike');
        Route::post('/{resource:slug}/bookmark', [InteractionController::class, 'bookmark'])->name('bookmark');
    });

    // === COMMENT ROUTES ===
    Route::middleware('auth')->group(function () {
        Route::post('/{resource:slug}/comment', [CommentController::class, 'store'])
            ->name('comment.store')
            ->middleware('throttle:comments');

        Route::put('/comment/{comment}', [CommentController::class, 'update'])
            ->name('comment.update')
            ->middleware('throttle:comment-updates');

        Route::delete('/comment/{comment}', [CommentController::class, 'destroy'])
            ->name('comment.destroy');
    });

    // Public comment routes for guests
    Route::post('/{resource:slug}/comment/guest', [CommentController::class, 'storeGuest'])
        ->name('comment.guest')
        ->middleware('throttle:guest-comments');
});

// ===== CATEGORY ROUTES =====
Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->name('index');
    Route::get('/{category:slug}', [CategoryController::class, 'show'])->name('show');
});

// ===== API ROUTES FOR AJAX FUNCTIONALITY =====
Route::prefix('api/v1')->name('api.')->middleware('throttle:api')->group(function () {

    // === AUTHENTICATED API ENDPOINTS ===
    Route::middleware('auth')->group(function () {
        Route::post('/resources/{resource:slug}/interactions/{type}', [InteractionController::class, 'toggle'])
            ->name('interactions.toggle')
            ->where('type', 'like|dislike|bookmark|share');

        Route::get('/resources/{resource:slug}/stats', function (\App\Models\Resource $resource) {
            if (! $resource->canUserAccess(auth()->user())) {
                abort(403);
            }

            return response()->json([
                'views' => $resource->view_count,
                'downloads' => $resource->download_count,
                'likes' => $resource->like_count,
                'comments' => $resource->comments()->approved()->count(),
            ]);
        })->name('resource.stats');
    });
});

// ===== UTILITY & SEO ROUTES =====
Route::get('/health', function () {
    return cache()->remember('health_check', 60, function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now(),
            'resources_count' => \App\Models\Resource::published()->count(),
            'categories_count' => \App\Models\ResourceCategory::active()->count(),
        ]);
    });
})->name('health');

Route::get('/sitemap.xml', function () {
    return cache()->remember('sitemap', 3600, function () {
        $resources = \App\Models\Resource::published()
            ->public()
            ->select(['slug', 'updated_at'])
            ->get();

        $categories = \App\Models\ResourceCategory::active()
            ->select(['slug', 'updated_at'])
            ->get();

        return response()->view('sitemap', compact('resources', 'categories'))
            ->header('Content-Type', 'text/xml');
    });
})->name('sitemap');

Route::get('/feed', function () {
    return cache()->remember('rss_feed', 1800, function () {
        $resources = \App\Models\Resource::published()
            ->public()
            ->latest('published_at')
            ->limit(20)
            ->with(['author', 'category'])
            ->get();

        return response()->view('feed.rss', compact('resources'))
            ->header('Content-Type', 'application/rss+xml');
    });
})->name('feed');

Route::get('/robots.txt', function () {
    $content = "User-agent: *\n";
    $content .= "Allow: /\n";
    $content .= 'Sitemap: '.route('sitemap')."\n";

    return response($content, 200)
        ->header('Content-Type', 'text/plain');
})->name('robots');

// ===== ADMIN ROUTES =====
Route::middleware(['auth', 'throttle:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/stats', function () {
        $stats = cache()->remember('admin_stats', 300, function () {
            return [
                'resources' => [
                    'total' => \App\Models\Resource::count(),
                    'published' => \App\Models\Resource::published()->count(),
                    'draft' => \App\Models\Resource::where('status', 'draft')->count(),
                ],
                'activity' => [
                    'views_today' => \App\Models\ResourceView::whereDate('created_at', today())->count(),
                    'downloads_today' => \App\Models\ResourceDownload::whereDate('created_at', today())->count(),
                ],
            ];
        });

        return response()->json($stats);
    })->name('stats');
});

// ===== REDIRECTS & FALLBACKS =====
Route::redirect('/admin', '/admin/login')->name('admin');
Route::redirect('/resource/{slug}', '/resources/{slug}', 301);
Route::redirect('/category/{slug}', '/resources/category/{slug}', 301);

Route::middleware(['web'])->prefix('analytics')->name('analytics.')->group(function () {
    // Heatmap (controller-based replacement for the widget usage)
    Route::get('/heatmap', [KenyaHeatmapController::class, 'index'])->name('heatmap');
    Route::get('/heatmap/data', [KenyaHeatmapController::class, 'data'])->name('heatmap.data');
    Route::get('/geo/kenyan-counties.geojson', [KenyaHeatmapController::class, 'geojson'])->name('heatmap.geojson');

    // Explorer (County → Trainings → Facility → Participants → Profile)
    Route::get('/training-explorer', [TrainingExplorerController::class, 'index'])->name('training-explorer');

    // JSON APIs used by Explorer
    Route::get('/counties', [TrainingExplorerController::class, 'apiCounties'])->name('counties');
    Route::get('/{county}/trainings', [TrainingExplorerController::class, 'apiCountyTrainings'])->name('county.trainings');
    Route::get('/{county}/trainings/{training}/facilities', [TrainingExplorerController::class, 'apiTrainingFacilities'])->name('training.facilities');
    Route::get('/trainings/{training}/facilities/{facility}/participants', [TrainingExplorerController::class, 'apiFacilityParticipants'])->name('facility.participants');
    Route::get('/participants/{user}', [TrainingExplorerController::class, 'apiParticipantProfile'])->name('participant.profile');

    Route::get('/participants/{user}', [TrainingExplorerController::class, 'apiParticipantProfile'])->name('participant.profile');
    Route::get('/participants/{user}/attrition-logs', [TrainingExplorerController::class, 'apiAttritionLogs'])->name('participant.attrition.logs');
    Route::post('/participants/{user}/attrition-logs', [TrainingExplorerController::class, 'storeAttritionLog'])->name('participant.attrition.store');

    Route::get('/county/{countyId}/summary', [KenyaHeatmapController::class, 'countySummary']);

    Route::get('/county/{countyId}/trainings', [TrainingExplorerController::class, 'trainingsByCounty']);
    Route::get('/training/{trainingId}/facilities', [TrainingExplorerController::class, 'facilitiesByTraining']);
    Route::get('/facility/{facilityId}/participants', [TrainingExplorerController::class, 'participantsByFacility']);
    Route::get('/participant/{participantId}/profile', [TrainingExplorerController::class, 'participantProfile']);
});

// include 'api.php';
