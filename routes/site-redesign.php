<?php

use App\Http\Controllers\Site\PublicSiteController;
use Illuminate\Support\Facades\Route;

/*
| Redesigned public pages. Loaded from routes/web.php with:
|     require __DIR__.'/site-redesign.php';
| (install.sh --wire-routes adds that line for you).
|
| These replace the old '/', '/resources' and '/categories' routes. Remove or comment out the old
| definitions in routes/web.php — when two routes share a URI, the one registered LAST wins, so this
| file must be required AFTER them if you keep both while testing.
|
| The resource detail page (/resources/{slug}) is unchanged and keeps its existing route.
*/

// routes/web.php already runs in the 'web' middleware group, so none is added here.
Route::middleware('scalar-query')->group(function () {
    Route::get('/', [PublicSiteController::class, 'home'])->name('home');
    Route::get('/resources', [PublicSiteController::class, 'resources'])->name('resources.index');
    Route::get('/categories', [PublicSiteController::class, 'categories'])->name('categories.index');
});

// Shown by the public service worker (public/sw-site.js) when the device is offline.
Route::view('/offline', 'site.offline')->name('offline');
