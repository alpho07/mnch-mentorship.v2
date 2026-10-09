<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResourceFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lets the mobile app download a resource file. The app authenticates with a
 * Bearer token, which a plain link / WebView download can't send, so the API
 * hands out short-lived signed URLs bound to the requesting user and this
 * controller verifies the signature (route middleware) and the user's access.
 */
class ResourceFileDownloadController extends Controller
{
    public static function signedUrl(ResourceFile $file, User $user): string
    {
        return URL::temporarySignedRoute(
            'api.v1.resource-files.signed-download',
            now()->addMinutes(15),
            ['file' => $file->id, 'u' => $user->id]
        );
    }

    public function download(Request $request, ResourceFile $file): StreamedResponse
    {
        $user = User::find((int) $request->query('u'));

        abort_unless($user, 403, 'Access denied');
        abort_unless($file->exists(), 404, 'File not found');
        abort_unless($file->resource?->canUserAccess($user), 403, 'Access denied');

        $file->resource->incrementDownloads($user);

        return Storage::disk('resources')->download($file->file_path, $file->original_name);
    }
}
