<?php

namespace App\Services\Chat;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\County;
use App\Models\Facility;
use App\Models\Program;
use App\Services\MentorshipWizardService;
use Illuminate\Support\Carbon;

/**
 * Declares every slot the chat assistant can ask about, grouped into the
 * same five persistence stages the guided wizard already uses (see
 * docs/GUIDED-MENTORSHIP-SETUP-REFERENCE.md §6). Built fresh per request
 * from the live page instance so closures can read $page->training /
 * $page->class once those exist.
 */
class MentorshipChatScript
{
    public const STAGES = ['training_details', 'first_class', 'modules', 'enroll_mentees', 'send_invitations'];

    /**
     * @return Slot[]
     */
    public static function build(ChatMentorshipSetup $page): array
    {
        return [
            Slot::make('is_pilot')
                ->stage('training_details')
                ->render(Render::CARDS)
                ->question(fn () => 'Is this a real live mentorship or a pilot/test run?')
                ->optionsFrom(fn () => [0 => 'Live Mentorship', 1 => 'Pilot Run'])
                ->echoUsing(fn ($v) => ((int) $v === 1) ? 'Pilot Run' : 'Live Mentorship'),

            Slot::make('county_id')
                ->stage('training_details')
                ->render(Render::CARDS)
                ->question(fn () => 'Which county?')
                ->optionsFrom(fn () => County::orderBy('name')->pluck('name', 'id')->all())
                ->echoUsing(fn ($v) => County::find($v)?->name ?? (string) $v),

            Slot::make('facility_id')
                ->stage('training_details')
                ->render(Render::CARDS)
                ->dependsOn('county_id')
                ->question(fn ($a) => 'Which facility in '.(County::find($a['county_id'] ?? null)?->name ?? 'this county').'?')
                ->optionsFrom(fn ($a) => Facility::whereHas('subcounty', fn ($q) => $q->where('county_id', $a['county_id'] ?? null))
                    ->get()
                    ->mapWithKeys(fn ($f) => [$f->id => "{$f->mfl_code} — {$f->name}"])
                    ->all())
                ->echoUsing(fn ($v) => Facility::find($v)?->name ?? (string) $v),

            Slot::make('program_id')
                ->stage('training_details')
                ->render(Render::CARDS)
                ->question(fn () => 'What program is being mentored?')
                ->optionsFrom(fn () => Program::query()
                    ->get()
                    ->filter(fn ($p) => $p->isSelectableBy(auth()->user()))
                    ->mapWithKeys(fn ($p) => [$p->id => $p->name])
                    ->all())
                ->echoUsing(fn ($v) => Program::find($v)?->name ?? (string) $v)
                ->rule(function ($value) {
                    $program = $value ? Program::find($value) : null;

                    if (! $program || ! $program->isSelectableBy(auth()->user())) {
                        return 'That program is not active — pick a different one.';
                    }

                    return null;
                }),

            Slot::make('start_date')
                ->stage('training_details')
                ->render(Render::WIDGET)
                ->visibleWhen(fn ($a) => ! app(MentorshipWizardService::class)->isEmoncProgram($a['program_id'] ?? null))
                ->question(fn () => 'When does it start?')
                ->echoUsing(fn ($v) => Carbon::parse($v)->format('M j, Y')),

            Slot::make('end_date')
                ->stage('training_details')
                ->render(Render::WIDGET)
                ->visibleWhen(fn ($a) => ! app(MentorshipWizardService::class)->isEmoncProgram($a['program_id'] ?? null))
                ->dependsOn('start_date')
                ->question(fn () => 'And when does it end?')
                ->echoUsing(fn ($v) => Carbon::parse($v)->format('M j, Y'))
                ->rule(function ($value, $a) {
                    if (! empty($a['start_date']) && Carbon::parse($value)->lt(Carbon::parse($a['start_date']))) {
                        return 'End date must be on or after the start date.';
                    }

                    return null;
                }),

            Slot::make('max_participants')
                ->stage('training_details')
                ->render(Render::WIDGET)
                ->question(fn () => 'How many mentees, at most (2–10)?')
                ->echoUsing(fn ($v) => "{$v} mentees")
                ->rule(function ($value) {
                    if (! is_numeric($value) || $value < 2 || $value > 10) {
                        return 'Must be between 2 and 10 mentees.';
                    }

                    return null;
                }),

            Slot::make('class_name')
                ->stage('first_class')
                ->render(Render::FREE_TEXT)
                ->question(fn () => "Let's create your first class or cohort — what should we call it?")
                ->rule(fn ($value) => (is_string($value) && strlen($value) > 255) ? 'Keep it under 255 characters.' : null),

            Slot::make('class_start_date')
                ->stage('first_class')
                ->render(Render::WIDGET)
                ->visibleWhen(fn ($a) => ! app(MentorshipWizardService::class)->isEmoncProgram($a['program_id'] ?? null))
                ->question(fn () => 'When does this class start?')
                ->echoUsing(fn ($v) => Carbon::parse($v)->format('M j, Y')),

            Slot::make('class_end_date')
                ->stage('first_class')
                ->render(Render::WIDGET)
                ->visibleWhen(fn ($a) => ! app(MentorshipWizardService::class)->isEmoncProgram($a['program_id'] ?? null))
                ->question(fn () => 'And when does it end?')
                ->echoUsing(fn ($v) => Carbon::parse($v)->format('M j, Y'))
                ->rule(function ($value, $a) {
                    if (! empty($a['class_start_date']) && Carbon::parse($value)->lt(Carbon::parse($a['class_start_date']))) {
                        return 'End date must be on or after the start date.';
                    }

                    return null;
                }),

            Slot::make('class_description')
                ->stage('first_class')
                ->render(Render::FREE_TEXT)
                ->required(false)
                ->question(fn () => 'Want to describe the gap identified and how this class will be delivered? (optional — just say "skip")'),

            Slot::make('recipients')
                ->stage('send_invitations')
                ->render(Render::CARDS)
                ->question(fn () => 'Who should receive the email?')
                ->optionsFrom(fn () => ['all' => 'All mentees with email addresses', 'not_sent' => 'Only those not yet invited']),
        ];
    }
}
