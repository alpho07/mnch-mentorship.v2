<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\ResourceCategory;
use App\Models\Tag;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User; 
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;


class ResourceController extends Controller
{
    /**
     * Display the homepage/landing page with featured content
     */
    public function home(): View
    {
        // Cache the home page data for better performance
        $cacheKey = 'homepage_data_' . (auth()->check() ? auth()->id() : 'guest');
        
        $data = Cache::remember($cacheKey, 300, function () { // 5 minutes cache
            $now = now();
            // Apply consistent access control for authenticated users
            $baseQuery = Resource::published()->with(['resourceType', 'category', 'author', 'tags']);
            
            if (auth()->check()) {
                $baseQuery->accessibleTo(auth()->user());
            } else {
                $baseQuery->public();
            }

            $featuredResources = (clone $baseQuery)->featured()->limit(6)->get();
            $recentResources = (clone $baseQuery)->latest('published_at')->limit(8)->get();
            $popularResources = (clone $baseQuery)->popular(30)->limit(6)->get();

            // Categories and types should also respect access
            $categories = ResourceCategory::active()
                ->parent()
                ->withCount(['resources' => function($q) {
                    $q->published();
                    if (auth()->check()) {
                        $q->accessibleTo(auth()->user());
                    } else {
                        $q->public();
                    }
                }])
                ->orderBy('sort_order')
                ->get();

            $resourceTypes = ResourceType::active()
                ->withCount(['resources' => function($q) {
                    $q->published();
                    if (auth()->check()) {
                        $q->accessibleTo(auth()->user());
                    } else {
                        $q->public();
                    }
                }])
                ->orderBy('sort_order')
                ->get();

            $upcomingTrainings = Training::where('type', 'global_training')
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->where('start_date', '>=', $now)
                ->with(['county'])
                ->orderBy('start_date')
                ->limit(4)
                ->get();

            $ongoingMentorships = Training::where('type', 'facility_mentorship')
                ->where('status', '!=', 'cancelled')
                ->where('start_date', '<=', $now)
                ->where('end_date', '>=', $now)
                ->with(['county', 'facility'])
                ->orderBy('end_date')
                ->limit(6)
                ->get();

            $upcomingMentorships = Training::where('type', 'facility_mentorship')
                ->where('status', '!=', 'cancelled')
                ->where('start_date', '>', $now)
                ->with(['county', 'facility'])
                ->orderBy('start_date')
                ->limit(4)
                ->get();

            $closedMentorships = Training::where('type', 'facility_mentorship')
                ->where('status', '!=', 'cancelled')
                ->where('end_date', '<', $now)
                ->where('end_date', '>=', $now->copy()->subDays(30))
                ->with(['county', 'facility'])
                ->orderBy('end_date', 'desc')
                ->limit(4)
                ->get();

            // EmONC mentorships with null dates — derive status from classes
            $emoncNoDates = Training::where('type', 'facility_mentorship')
                ->where('status', '!=', 'cancelled')
                ->whereNull('start_date')
                ->whereNull('end_date')
                ->whereHas('program', fn ($q) => $q->whereRaw("LOWER(name) LIKE '%emonc%'")
                    ->orWhereRaw("LOWER(name) LIKE '%maternal%'"))
                ->with(['county', 'facility', 'mentorshipClasses'])
                ->get();

            foreach ($emoncNoDates as $t) {
                $classStatuses = $t->mentorshipClasses->pluck('status');
                $hasActive     = $classStatuses->contains('active');
                $hasCompleted  = $classStatuses->contains('completed');
                $allCompleted  = $classStatuses->count() > 0 && $classStatuses->every(fn ($s) => $s === 'completed');

                if ($hasActive) {
                    $ongoingMentorships->push($t);
                } elseif ($allCompleted) {
                    $closedMentorships->push($t);
                } elseif (!$hasActive && !$hasCompleted) {
                    $upcomingMentorships->push($t);
                }
            }

            $trainingInsights = $this->buildTrainingInsights();

            return compact('featuredResources', 'recentResources', 'popularResources', 'categories', 'resourceTypes', 'upcomingTrainings', 'ongoingMentorships', 'upcomingMentorships', 'closedMentorships', 'trainingInsights');
        });

        return view('frontend.home', $data);
    }

    private function buildTrainingInsights(): array
    {
        $today = now()->startOfDay();
        $nextThirtyDays = now()->addDays(30)->endOfDay();

        $upcomingBase = Training::query()
            ->whereIn('type', ['global_training', 'facility_mentorship'])
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->whereDate('start_date', '>=', $today);

        $upcomingTrainingsCount = (clone $upcomingBase)
            ->where('type', 'global_training')
            ->count();

        $upcomingMentorshipsCount = (clone $upcomingBase)
            ->where('type', 'facility_mentorship')
            ->count();

        $nextThirtyDayPrograms = (clone $upcomingBase)
            ->whereDate('start_date', '<=', $nextThirtyDays)
            ->count();

        $countiesCovered = (clone $upcomingBase)
            ->whereNotNull('county_id')
            ->distinct('county_id')
            ->count('county_id');

        $activeMentorships = Training::query()
            ->where('type', 'facility_mentorship')
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where(function ($query) {
                $query->whereIn('status', ['active', 'ongoing'])
                    ->orWhereHas('mentorshipClasses', fn($classQuery) => $classQuery->where('status', 'active'));
            })
            ->count();

        $activeClasses = MentorshipClass::query()
            ->where('status', 'active')
            ->whereHas('training', fn($query) => $query->where('type', 'facility_mentorship'))
            ->count();

        $mentorshipMentees = ClassParticipant::query()
            ->whereHas('mentorshipClass.training', fn($query) => $query->where('type', 'facility_mentorship'))
            ->distinct('class_participants.user_id')
            ->count('class_participants.user_id');

        $mentoredFacilities = Training::query()
            ->where('type', 'facility_mentorship')
            ->whereNotNull('facility_id')
            ->distinct('facility_id')
            ->count('facility_id');

        $trainingParticipants = TrainingParticipant::query()
            ->whereHas('training', fn($query) => $query->where('type', 'global_training'))
            ->distinct('training_participants.user_id')
            ->count('training_participants.user_id');

        $completedParticipants = TrainingParticipant::query()
            ->whereHas('training', fn($query) => $query->where('type', 'global_training'))
            ->where('completion_status', 'completed')
            ->count();

        $totalParticipantRecords = TrainingParticipant::query()
            ->whereHas('training', fn($query) => $query->where('type', 'global_training'))
            ->count();

        $completionRate = $totalParticipantRecords > 0
            ? round(($completedParticipants / $totalParticipantRecords) * 100)
            : 0;

        $topCounty = (clone $upcomingBase)
            ->whereNotNull('county_id')
            ->select('county_id', DB::raw('COUNT(*) as scheduled_count'))
            ->with('county:id,name')
            ->groupBy('county_id')
            ->orderByDesc('scheduled_count')
            ->first();

        $topProgram = (clone $upcomingBase)
            ->whereNotNull('program_id')
            ->select('program_id', DB::raw('COUNT(*) as scheduled_count'))
            ->with('program:id,name')
            ->groupBy('program_id')
            ->orderByDesc('scheduled_count')
            ->first();

        $nextProgram = (clone $upcomingBase)
            ->with(['county:id,name', 'facility:id,name'])
            ->orderBy('start_date')
            ->first();

        $pipelineTotal = $upcomingTrainingsCount + $upcomingMentorshipsCount;
        $mentorshipShare = $pipelineTotal > 0 ? round(($upcomingMentorshipsCount / $pipelineTotal) * 100) : 0;

        return [
            'has_data' => $pipelineTotal > 0 || $activeMentorships > 0 || $mentorshipMentees > 0 || $trainingParticipants > 0,
            'cards' => [
                [
                    'label' => 'Learning Pipeline',
                    'value' => number_format($pipelineTotal),
                    'detail' => number_format($nextThirtyDayPrograms) . ' scheduled in the next 30 days across ' . number_format($countiesCovered) . ' counties.',
                    'icon' => 'fas fa-calendar-check',
                    'accent' => '#0097A7',
                    'bg' => '#E0F7FA',
                ],
                [
                    'label' => 'Mentorship Reach',
                    'value' => number_format($mentorshipMentees),
                    'detail' => number_format($activeMentorships) . ' active mentorships, ' . number_format($activeClasses) . ' active classes, ' . number_format($mentoredFacilities) . ' facilities reached.',
                    'icon' => 'fas fa-user-md',
                    'accent' => '#059669',
                    'bg' => '#D1FAE5',
                ],
                [
                    'label' => 'Training Coverage',
                    'value' => number_format($trainingParticipants),
                    'detail' => $completionRate . '% completion across global training participant records.',
                    'icon' => 'fas fa-users',
                    'accent' => '#2563EB',
                    'bg' => '#DBEAFE',
                ],
                [
                    'label' => 'Mentorship Mix',
                    'value' => $mentorshipShare . '%',
                    'detail' => number_format($upcomingMentorshipsCount) . ' of ' . number_format($pipelineTotal) . ' upcoming programs are facility-based mentorships.',
                    'icon' => 'fas fa-chart-pie',
                    'accent' => '#F59E0B',
                    'bg' => '#FEF3C7',
                ],
            ],
            'signals' => [
                [
                    'title' => 'Highest upcoming demand',
                    'text' => $topCounty?->county
                        ? "{$topCounty->county->name} leads the upcoming schedule with {$topCounty->scheduled_count} planned program" . ($topCounty->scheduled_count == 1 ? '.' : 's.')
                        : 'No county-level upcoming schedule has been mapped yet.',
                ],
                [
                    'title' => 'Program focus',
                    'text' => $topProgram?->program
                        ? "{$topProgram->program->name} is the strongest upcoming program focus with {$topProgram->scheduled_count} scheduled " . ($topProgram->scheduled_count == 1 ? 'activity.' : 'activities.')
                        : 'Upcoming activities are not yet consistently linked to programs.',
                ],
                [
                    'title' => 'Next action window',
                    'text' => $nextProgram
                        ? ($nextProgram->type === 'facility_mentorship' ? 'Next mentorship: ' : 'Next training: ') . $nextProgram->title . ' starts ' . $nextProgram->start_date?->format('M d, Y') . '.'
                        : 'No upcoming training or mentorship has a future start date.',
                ],
            ],
        ];
    }

    /**
     * Display a listing of resources with filtering and search
     */
    public function index(Request $request): View
    {
        $query = Resource::published()
            ->with(['resourceType', 'category', 'author', 'tags']);

        // Apply visibility filter based on authentication
        if (auth()->check()) {
            $query->accessibleTo(auth()->user());
        } else {
            $query->public();
        }

        // Apply filters
        $this->applyFilters($query, $request);

        // Apply sorting
        $this->applySorting($query, $request);

        $resources = $query->paginate(12)->withQueryString();

        // Get filter options with access control
        $filterData = $this->getFilterData();

        return view('frontend.resources.index', array_merge(
            compact('resources'),
            $filterData
        ));
    }

    /**
     * Display the specified resource with enhanced security and tracking
     */
    public function show(Request $request, Resource $resource): View
    {
        // Check published status first
        if ($resource->status !== 'published') {
            abort(404, 'Resource not found or not published');
        }

        // Check access permissions
        $user = auth()->user();
        if (!$resource->canUserAccess($user)) {
            if (!$user) {
                return redirect()->route('login')
                    ->with('message', 'Please log in to access this resource.');
            }
            abort(403, 'You do not have permission to access this resource.');
        }

        // Load relationships efficiently
        $resource->load([
            'resourceType',
            'category',
            'author',
            'tags',
            'primaryFile',
            'comments' => fn($q) => $q->approved()
                ->parent()
                ->latest()
                ->with(['user', 'replies' => fn($r) => $r->approved()->latest()->with('user')])
        ]);

        // Track view (only for legitimate access)
        $this->trackView($resource, $user, $request);

        // Get related resources with same access control
        $relatedResources = $this->getRelatedResources($resource, $user);

        // Get user interactions efficiently
        $userInteractions = [];
        if ($user) {
            $userInteractions = $user->resourceInteractions()
                ->where('resource_id', $resource->id)
                ->pluck('type')
                ->toArray();
        }

        return view('frontend.resources.show', compact(
            'resource',
            'relatedResources',
            'userInteractions'
        ));
    }

    /**
     * Download a resource file with comprehensive security and tracking
     */
    public function download(Resource $resource): StreamedResponse
    {
        // Comprehensive access and security checks
        $this->validateDownloadAccess($resource);

        // Resolve the file path — prefer the resource's own file_path, fall back to primary ResourceFile
        $filePath = null;
        $fileName = null;
        $mimeType = null;

        if ($resource->hasValidFile()) {
            $filePath = $resource->file_path;
            $fileName = $this->generateSecureFilename($resource);
            $mimeType = Storage::disk('resources')->mimeType($filePath);
        } else {
            $primaryFile = $resource->primaryFile;
            if ($primaryFile && $primaryFile->exists()) {
                $filePath = $primaryFile->file_path;
                $fileName = $primaryFile->original_name ?: $primaryFile->file_name ?: basename($filePath);
                $mimeType = $primaryFile->file_type ?: Storage::disk('resources')->mimeType($filePath);
            }
        }

        if (!$filePath) {
            abort(404, 'The requested file could not be found or is corrupted.');
        }

        // Track download with detailed logging
        $this->trackDownload($resource);

        // Set appropriate headers for security and performance
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
        ];

        // Stream the file for better memory usage with large files
        return response()->stream(function () use ($filePath) {
            $stream = Storage::disk('resources')->readStream($filePath);

            if (!$stream) {
                abort(500, 'Unable to read file');
            }

            // Stream in chunks for better memory management
            while (!feof($stream)) {
                echo fread($stream, 8192); // 8KB chunks
                flush();
            }

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }

    /**
     * Preview a resource file (for PDFs, images, videos, etc.)
     */
    public function preview(Resource $resource): StreamedResponse
    {
        // Access checks
        if (!$resource->canUserAccess(auth()->user()) || $resource->status !== 'published') {
            abort(403, 'You do not have permission to preview this resource.');
        }

        // Resolve file — prefer resource's own file_path, fall back to primaryFile
        $filePath = null;
        $mimeType = null;

        if ($resource->hasValidFile()) {
            $filePath = $resource->file_path;
            $mimeType = Storage::disk('resources')->mimeType($filePath);
        } else {
            $primaryFile = $resource->primaryFile ?? $resource->load('primaryFile')->primaryFile;
            if ($primaryFile && $primaryFile->exists()) {
                $filePath = $primaryFile->file_path;
                $mimeType = $primaryFile->file_type ?: Storage::disk('resources')->mimeType($filePath);
            }
        }

        if (!$filePath) {
            abort(404, 'File not found');
        }

        $previewableMimes = [
            'application/pdf',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'video/mp4', 'video/webm',
            'audio/mpeg', 'audio/wav', 'audio/ogg',
            'text/plain', 'text/csv',
        ];

        if (!in_array($mimeType, $previewableMimes)) {
            abort(400, 'This file type cannot be previewed');
        }

        $this->trackView($resource, auth()->user(), request(), 'preview');

        $filename = basename($filePath);

        return response()->stream(function () use ($filePath) {
            $stream = Storage::disk('resources')->readStream($filePath);
            if (!$stream) {
                abort(500, 'Unable to read file');
            }
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Get resource thumbnail/featured image with proper caching
     */
    public function thumbnail(Resource $resource): Response
    {
        // Featured images are public, but check if resource is accessible
        if ($resource->status !== 'published') {
            return $this->getPlaceholderImage();
        }

        // Check if featured image exists
        if (!$resource->featured_image || !Storage::disk('thumbnails')->exists($resource->featured_image)) {
            return $this->getPlaceholderImage();
        }

        $path = Storage::disk('thumbnails')->path($resource->featured_image);
        $mimeType = Storage::disk('thumbnails')->mimeType($resource->featured_image);
        
        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=86400', // Cache for 1 day
            'ETag' => md5_file($path),
        ]);
    }

    /**
     * Display resources by type with enhanced filtering
     */
    public function byType(ResourceType $type, Request $request): View
    {
        $query = Resource::published()
            ->byType($type->slug)
            ->with(['resourceType', 'category', 'author', 'tags']);

        // Apply visibility filter
        $user = auth()->user();
        if ($user) {
            $query->accessibleTo($user);
        } else {
            $query->public();
        }

        // Apply additional filters
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $resources = $query->paginate(12)->withQueryString();

        return view('frontend.resources.by-type', compact('type', 'resources'));
    }

    /**
     * Display resources by tag with related suggestions
     */
    public function byTag(Tag $tag, Request $request): View
    {
        $query = Resource::published()
            ->whereHas('tags', fn($q) => $q->where('tags.id', $tag->id))
            ->with(['resourceType', 'category', 'author', 'tags']);

        // Apply visibility filter
        $user = auth()->user();
        if ($user) {
            $query->accessibleTo($user);
        } else {
            $query->public();
        }

        // Apply sorting
        $this->applySorting($query, $request);

        $resources = $query->paginate(12)->withQueryString();

        // Get related tags for suggestions
        $relatedTags = $this->getRelatedTags($tag, $user);

        return view('frontend.resources.by-tag', compact('tag', 'resources', 'relatedTags'));
    }

    /**
     * Browse resources with advanced filtering capabilities
     */
    public function browse(Request $request): View
    {
        $query = Resource::published()
            ->with(['resourceType', 'category', 'author', 'tags'])
            ->select('resources.*');

        // Apply visibility filter
        $user = auth()->user();
        if ($user) {
            $query->accessibleTo($user);
        } else {
            $query->public();
        }

        // Advanced filtering
        $this->applyAdvancedFilters($query, $request);
        $this->applySorting($query, $request);

        $resources = $query->paginate(12)->withQueryString();

        // Get comprehensive filter data
        $filterData = $this->getAdvancedFilterData($user);

        return view('frontend.resources.browse', array_merge(
            compact('resources'),
            $filterData
        ));
    }

    /**
     * Get user's bookmarked resources
     */
    public function bookmarks(Request $request): View
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login')->with('message', 'Please log in to view your bookmarks.');
        }

        $query = Resource::published()
            ->whereHas('interactions', function($q) use ($user) {
                $q->where('user_id', $user->id)->where('type', 'bookmark');
            })
            ->with(['resourceType', 'category', 'author', 'tags']);

        // Apply sorting
        $this->applySorting($query, $request);

        $resources = $query->paginate(12)->withQueryString();

        return view('frontend.resources.bookmarks', compact('resources'));
    }

    /**
     * Get user's download history
     */
    public function downloads(Request $request): View
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login')->with('message', 'Please log in to view your downloads.');
        }

        $downloads = $user->resourceDownloads()
            ->with(['resource' => function($q) {
                $q->with(['resourceType', 'category', 'author']);
            }])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('frontend.resources.downloads', compact('downloads'));
    }

    // === PRIVATE HELPER METHODS ===

    /**
     * Apply standard filters to resource query
     */
    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }

        if ($request->filled('type')) {
            $query->whereHas('resourceType', fn($q) => $q->where('slug', $request->type));
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', fn($q) => $q->where('slug', $request->tag));
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('author')) {
            $query->whereHas('author', fn($q) => $q->where('id', $request->author));
        }

        if ($request->boolean('downloadable')) {
            $query->where('is_downloadable', true)->whereNotNull('file_path');
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }
    }

    /**
     * Apply advanced filters for browse page
     */
    private function applyAdvancedFilters($query, Request $request): void
    {
        // Standard filters
        $this->applyFilters($query, $request);

        // Multi-select filters
        if ($request->filled('categories')) {
            $query->whereIn('category_id', $request->categories);
        }

        if ($request->filled('types')) {
            $query->whereIn('resource_type_id', $request->types);
        }

        if ($request->filled('tags')) {
            $query->whereHas('tags', fn($q) => $q->whereIn('tags.id', $request->tags));
        }

        if ($request->filled('difficulty_levels')) {
            $query->whereIn('difficulty_level', $request->difficulty_levels);
        }

        // Date range filters
        if ($request->filled('date_from')) {
            $query->where('published_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('published_at', '<=', $request->date_to);
        }

        // File type filters
        if ($request->filled('file_types')) {
            $mimeTypes = $this->getFileTypeMimeMapping($request->file_types);
            $query->whereIn('file_type', $mimeTypes);
        }

        // Size filters
        if ($request->filled('min_size')) {
            $query->where('file_size', '>=', $request->min_size * 1024 * 1024); // Convert MB to bytes
        }

        if ($request->filled('max_size')) {
            $query->where('file_size', '<=', $request->max_size * 1024 * 1024);
        }
    }

    /**
     * Apply sorting to resource query
     */
    private function applySorting($query, Request $request): void
    {
        $sortBy = $request->get('sort', 'latest');
        
        match ($sortBy) {
            'popular' => $query->popular(),
            'oldest' => $query->oldest('published_at'),
            'title' => $query->orderBy('title'),
            'views' => $query->orderByDesc('view_count'),
            'downloads' => $query->orderByDesc('download_count'),
            'likes' => $query->orderByDesc('like_count'),
            'comments' => $query->withCount('comments')->orderByDesc('comments_count'),
            'size' => $query->orderByDesc('file_size'),
            'author' => $query->join('users', 'resources.author_id', '=', 'users.id')
                             ->orderBy('users.first_name'),
            default => $query->latest('published_at'),
        };
    }

    /**
     * Get filter data for resource listings
     */
    private function getFilterData(): array
    {
        $user = auth()->user();
        
        $categories = ResourceCategory::active()
            ->withCount(['resources' => function($q) use ($user) {
                $q->published();
                if ($user) {
                    $q->accessibleTo($user);
                } else {
                    $q->public();
                }
            }])
            ->having('resources_count', '>', 0)
            ->orderBy('name')
            ->get();

        $resourceTypes = ResourceType::active()
            ->withCount(['resources' => function($q) use ($user) {
                $q->published();
                if ($user) {
                    $q->accessibleTo($user);
                } else {
                    $q->public();
                }
            }])
            ->having('resources_count', '>', 0)
            ->orderBy('name')
            ->get();

        $popularTags = Tag::withCount(['resources' => function($q) use ($user) {
                $q->published();
                if ($user) {
                    $q->accessibleTo($user);
                } else {
                    $q->public();
                }
            }])
            ->having('resources_count', '>', 0)
            ->orderByDesc('resources_count')
            ->limit(20)
            ->get();

        return compact('categories', 'resourceTypes', 'popularTags');
    }

    /**
     * Get advanced filter data for browse page
     */
    private function getAdvancedFilterData(?User $user): array
    {
        $filterData = $this->getFilterData();

        // Add nested categories
        $filterData['categories'] = ResourceCategory::active()
            ->with(['children' => function($q) use ($user) {
                $q->active()->withCount(['resources' => function($rq) use ($user) {
                    $rq->published();
                    if ($user) {
                        $rq->accessibleTo($user);
                    } else {
                        $rq->public();
                    }
                }]);
            }])
            ->withCount(['resources' => function($q) use ($user) {
                $q->published();
                if ($user) {
                    $q->accessibleTo($user);
                } else {
                    $q->public();
                }
            }])
            ->having('resources_count', '>', 0)
            ->orderBy('name')
            ->get();

        // Add all tags for advanced filtering
        $filterData['tags'] = Tag::withCount(['resources' => function($q) use ($user) {
                $q->published();
                if ($user) {
                    $q->accessibleTo($user);
                } else {
                    $q->public();
                }
            }])
            ->having('resources_count', '>', 0)
            ->orderBy('name')
            ->get();

        return $filterData;
    }

    /**
     * Validate download access with comprehensive checks
     */
    private function validateDownloadAccess(Resource $resource): void
    {
        // Check access permissions
        if (!$resource->canUserAccess(auth()->user())) {
            abort(403, 'You do not have permission to download this resource.');
        }

        // Check if resource is published and downloadable
        if ($resource->status !== 'published') {
            abort(404, 'This resource is not available.');
        }

        if (!$resource->is_downloadable) {
            abort(403, 'This resource is not available for download.');
        }

        if (!$resource->file_path && !$resource->hasPrimaryFile()) {
            abort(404, 'No file is attached to this resource.');
        }
    }

    /**
     * Track resource view with detailed analytics
     */
    private function trackView(Resource $resource, ?User $user, Request $request, string $type = 'view'): void
    {
        // Prevent duplicate views from same user/IP in short time
        $cacheKey = "resource_view_{$resource->id}_" . ($user?->id ?? $request->ip());
        
        if (!Cache::has($cacheKey)) {
            $resource->incrementViews($user, $request->ip());
            Cache::put($cacheKey, true, 300); // 5 minutes
            
            // Enhanced analytics tracking
            if ($user) {
                activity()
                    ->performedOn($resource)
                    ->causedBy($user)
                    ->withProperties([
                        'type' => $type,
                        'referrer' => $request->header('referer'),
                        'user_agent' => $request->userAgent(),
                    ])
                    ->log('resource_viewed');
            }
        }
    }

    /**
     * Track download with comprehensive logging
     */
    private function trackDownload(Resource $resource): void
    {
        $user = auth()->user();
        
        // Increment download counter
        $resource->incrementDownloads($user);

        // Log detailed download activity
        activity()
            ->performedOn($resource)
            ->causedBy($user)
            ->withProperties([
                'resource_title' => $resource->title,
                'file_size' => $resource->file_size,
                'file_type' => $resource->file_type,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'referrer' => request()->header('referer'),
            ])
            ->log('resource_downloaded');
    }

    /**
     * Generate secure filename for downloads
     */
    private function generateSecureFilename(Resource $resource): string
    {
        $extension = $resource->getFileExtension();
        $baseName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $resource->title);
        $baseName = trim($baseName, '_');
        
        return $extension ? "{$baseName}.{$extension}" : $baseName;
    }

    /**
     * Get related resources using intelligent matching
     */
    private function getRelatedResources(Resource $resource, ?User $user, int $limit = 4)
    {
        $query = Resource::published()
            ->where('id', '!=', $resource->id)
            ->with(['resourceType', 'category', 'author']);

        // Apply access control
        if ($user) {
            $query->accessibleTo($user);
        } else {
            $query->public();
        }

        // Prioritize related content
        $query->where(function ($q) use ($resource) {
            // Same category gets highest priority
            $q->where('category_id', $resource->category_id)
              // Same resource type gets medium priority  
              ->orWhere('resource_type_id', $resource->resource_type_id)
              // Same tags get lower priority
              ->orWhereHas('tags', fn($tagQ) => $tagQ->whereIn('tags.id', $resource->tags->pluck('id')));
        });

        return $query->limit($limit)->get();
    }

    /**
     * Get related tags for tag suggestion
     */
    private function getRelatedTags(Tag $tag, ?User $user, int $limit = 10)
    {
        // Find tags that frequently appear together with the current tag
        $query = Tag::whereHas('resources', function($resourceQuery) use ($tag, $user) {
            $resourceQuery->published()
                ->whereHas('tags', fn($tagQuery) => $tagQuery->where('tags.id', $tag->id));
            
            if ($user) {
                $resourceQuery->accessibleTo($user);
            } else {
                $resourceQuery->public();
            }
        })
        ->where('id', '!=', $tag->id)
        ->withCount('resources')
        ->orderByDesc('resources_count');

        return $query->limit($limit)->get();
    }

    /**
     * Get placeholder image response
     */
    private function getPlaceholderImage(): Response
    {
        $placeholderPath = public_path('images/placeholder-resource.png');
        
        if (file_exists($placeholderPath)) {
            return response()->file($placeholderPath);
        }
        
        // Generate a simple SVG placeholder if no image exists
        $svg = '<svg width="400" height="300" xmlns="http://www.w3.org/2000/svg">
            <rect width="100%" height="100%" fill="#f3f4f6"/>
            <text x="50%" y="50%" text-anchor="middle" dy="0.3em" font-family="Arial" font-size="16" fill="#6b7280">
                No Image Available
            </text>
        </svg>';
        
        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    /**
     * Map file type names to MIME types
     */
    private function getFileTypeMimeMapping(array $fileTypes): array
    {
        $mapping = [
            'pdf' => ['application/pdf'],
            'document' => [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            'presentation' => [
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation'
            ],
            'spreadsheet' => [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ],
            'image' => ['image/jpeg', 'image/png', 'image/gif'],
            'video' => ['video/mp4', 'video/mpeg'],
            'audio' => ['audio/mpeg', 'audio/wav'],
            'archive' => ['application/zip', 'application/x-rar-compressed'],
            'text' => ['text/plain', 'text/csv'],
        ];

        $mimeTypes = [];
        foreach ($fileTypes as $type) {
            if (isset($mapping[$type])) {
                $mimeTypes = array_merge($mimeTypes, $mapping[$type]);
            }
        }

        return $mimeTypes;
    }
}
