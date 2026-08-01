<?php

namespace App\Services\Chat;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\County;
use App\Models\Facility;

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
        ];
    }
}
