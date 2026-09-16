<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Services\ModuleUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MentorshipCreateController extends Controller
{
    public function __construct(private readonly ModuleUsageService $moduleUsageService) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'program_id'       => 'required|integer|exists:programs,id',
            'facility_id'      => 'required|integer|exists:facilities,id',
            'county_id'        => 'nullable|integer|exists:counties,id',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'max_participants' => 'nullable|integer|min:1',
            'is_pilot'         => 'nullable|boolean',
            'title'            => 'nullable|string|max:255',
            'class_name'       => 'nullable|string|max:255',
            'class_start_date' => 'nullable|date',
            'class_end_date'   => 'nullable|date|after_or_equal:class_start_date',
            'class_notes'      => 'nullable|string',
            'module_ids'       => 'nullable|array',
            'module_ids.*'     => 'integer|exists:program_modules,id',
        ]);

        $user = $request->user();

        $training = Training::create([
            'type'             => 'facility_mentorship',
            'program_id'       => $request->program_id,
            'facility_id'      => $request->facility_id,
            'county_id'        => $request->county_id,
            'mentor_id'        => $user->id,
            'start_date'       => $request->start_date,
            'end_date'         => $request->end_date,
            'max_participants' => $request->max_participants ?? 20,
            'is_pilot'         => $request->boolean('is_pilot', false),
            'status'           => 'draft',
            'identifier'       => 'MT-' . strtoupper(Str::random(6)),
            'title'            => $request->title ?? $this->autoTitle($request),
        ]);

        $class = MentorshipClass::create([
            'training_id' => $training->id,
            'name'        => $request->class_name ?: (($training->program?->name ?? 'Class') . ' - Class 1'),
            'status'      => 'draft',
            'start_date'  => $request->class_start_date ?: $request->start_date,
            'end_date'    => $request->class_end_date ?: $request->end_date,
            'notes'       => $request->class_notes,
            'created_by'  => $user->id,
            'enrollment_token' => Str::random(32),
        ]);

        if (!empty($request->module_ids)) {
            $this->moduleUsageService->assignModulesToClass($training, $class, $request->module_ids, $user->id);
            foreach ($class->classModules()->get() as $cm) {
                $cm->autoCreateSessions();
            }
        }

        $class->load(['classModules.programModule']);

        return response()->json([
            'data' => [
                'id'         => $training->id,
                'title'      => $training->title,
                'identifier' => $training->identifier,
                'status'     => $training->status,
                'start_date' => $training->start_date?->toDateString(),
                'end_date'   => $training->end_date?->toDateString(),
                'class'      => [
                    'id'      => $class->id,
                    'name'    => $class->name,
                    'status'  => $class->status,
                    'start_date' => $class->start_date?->toDateString(),
                    'end_date'   => $class->end_date?->toDateString(),
                    'notes'      => $class->notes,
                    'modules' => $class->classModules->map(fn($m) => [
                        'id'             => $m->id,
                        'name'           => $m->programModule?->name ?? 'Module',
                        'order_sequence' => $m->order_sequence,
                        'session_count'  => $m->sessions()->count(),
                    ]),
                ],
            ],
        ], 201);
    }

    public function update(Request $request, Training $training): JsonResponse
    {
        abort_if($training->type !== 'facility_mentorship', 404);
        abort_if($training->mentor_id !== $request->user()->id && !$request->user()->isAboveSite(), 403);

        $request->validate([
            'title'            => 'sometimes|string|max:255',
            'start_date'       => 'sometimes|date',
            'end_date'         => 'sometimes|date',
            'max_participants' => 'sometimes|integer|min:1',
            'is_pilot'         => 'sometimes|boolean',
            'program_id'       => 'sometimes|integer|exists:programs,id',
            'facility_id'      => 'sometimes|integer|exists:facilities,id',
            'county_id'        => 'nullable|integer|exists:counties,id',
        ]);

        $training->update($request->only(['title', 'start_date', 'end_date', 'max_participants', 'is_pilot', 'program_id', 'facility_id', 'county_id']));

        return response()->json(['data' => [
            'id'          => $training->id,
            'title'       => $training->title,
            'status'      => $training->status,
            'is_pilot'    => (bool) $training->is_pilot,
            'program_id'  => $training->program_id,
            'facility_id' => $training->facility_id,
            'county_id'   => $training->county_id,
            'start_date'  => $training->start_date?->toDateString(),
            'end_date'    => $training->end_date?->toDateString(),
        ]]);
    }

    public function submit(Request $request, Training $training): JsonResponse
    {
        abort_if($training->type !== 'facility_mentorship', 404);
        abort_if($training->mentor_id !== $request->user()->id, 403);

        $allCompleted = $training->mentorshipClasses()
            ->where('status', '!=', 'completed')
            ->doesntExist();

        abort_if(!$allCompleted, 422, 'All classes must be completed before submitting.');

        $training->update(['status' => 'submitted']);

        return response()->json(['message' => 'Mentorship submitted.', 'data' => ['id' => $training->id, 'status' => 'submitted']]);
    }

    private function autoTitle(Request $request): string
    {
        $facility = Facility::find($request->facility_id)?->name ?? 'Unknown';
        $program  = Program::find($request->program_id)?->name ?? 'Mentorship';
        $year     = date('Y', strtotime($request->start_date));
        return "{$program} — {$facility} {$year}";
    }
}
