<?php

namespace App\Services\Site;

use App\Http\Controllers\Site\PublicSiteController;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** Turns Eloquent resources (a paginator or collection) into the card arrays the public site views render. */
class SiteCards
{
    /** @param iterable<\App\Models\Resource> $resources */
    public static function make(iterable $resources): array
    {
        $models = $resources instanceof LengthAwarePaginator ? $resources->getCollection() : Collection::make($resources);
        $rows = $models->map(fn ($r) => DatabaseSiteData::row($r))->values()->all();

        return PublicSiteController::makePresenter()->cards($rows, now());
    }
}
