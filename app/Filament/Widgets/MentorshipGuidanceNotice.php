<?php

namespace App\Filament\Widgets;

use App\Models\Resource;
use Filament\Widgets\Widget;

class MentorshipGuidanceNotice extends Widget
{
    protected static string $view = 'filament.widgets.mentorship-guidance-notice';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /**
     * Each manual card is resolved against a real, currently-published
     * Resource instead of a hardcoded slug/URL — a hardcoded link 404s the
     * moment a resource is renamed, replaced, or unpublished. Falls back to
     * a resource-search link (which never 404s) when no match exists yet.
     */
    protected function getViewData(): array
    {
        return [
            'manualLinks' => [
                $this->resolveManualLink(
                    'Infant and Child Mentorship Manual',
                    'Use this to plan content and session flow.',
                    ['infant', 'child']
                ),
                $this->resolveManualLink(
                    "Newborn Mentorship Mentor's Manual",
                    'Use this when the mentorship focus includes newborn care.',
                    ['newborn']
                ),
                $this->resolveManualLink(
                    'EmONC Mentorship Manual',
                    'Use this when the mentorship focus includes emergency obstetric care.',
                    ['emonc']
                ),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $keywords  every keyword must appear in the title
     */
    private function resolveManualLink(string $label, string $description, array $keywords): array
    {
        $query = Resource::published()
            ->accessibleTo(auth()->user())
            ->where('title', 'like', '%manual%');

        foreach ($keywords as $keyword) {
            $query->where('title', 'like', "%{$keyword}%");
        }

        $resource = $query->latest('published_at')->first();

        return [
            'label' => $label,
            'description' => $description,
            'url' => $resource
                ? route('resources.show', $resource)
                : route('resources.search', ['q' => implode(' ', [...$keywords, 'manual'])]),
        ];
    }
}
