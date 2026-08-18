<?php

namespace App\Filament\GlobalSearch;

use Filament\Facades\Filament;
use Filament\GlobalSearch\Contracts\GlobalSearchProvider;
use Filament\GlobalSearch\DefaultGlobalSearchProvider;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Illuminate\Support\Collection;

/**
 * Wraps Filament's default (record-only) global search with an extra
 * "Navigation" category matching sidebar items by label — so searching
 * "certificate" surfaces the Certificate Center page itself, not just
 * records. Reuses Filament::getNavigation(), the same source the sidebar
 * renders from, so results only ever include items the current user can
 * actually see, and links always point at each item's real registered URL.
 */
class AppGlobalSearchProvider implements GlobalSearchProvider
{
    public function getResults(string $query): ?GlobalSearchResults
    {
        $results = app(DefaultGlobalSearchProvider::class)->getResults($query) ?? GlobalSearchResults::make();

        $navigationMatches = $this->searchNavigation($query);

        if ($navigationMatches->isNotEmpty()) {
            $results->category('Navigation', $navigationMatches);
        }

        return $results;
    }

    private function searchNavigation(string $query): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        $needle = mb_strtolower($query);
        $matches = collect();

        foreach (Filament::getNavigation() as $group) {
            $groupLabel = $group->getLabel();

            foreach ($group->getItems() as $item) {
                $label = $item->getLabel();
                $url = $item->getUrl();

                if (! $url || ! str_contains(mb_strtolower($label), $needle)) {
                    continue;
                }

                $matches->push(new GlobalSearchResult(
                    title: $label,
                    url: $url,
                    details: $groupLabel ? ['Section' => $groupLabel] : [],
                ));
            }
        }

        return $matches;
    }
}
