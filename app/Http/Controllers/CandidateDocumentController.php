<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class CandidateDocumentController extends Controller
{
    /**
     * "View document" links render with a temporary S3 URL baked into the page's HTML,
     * so a link left unclicked for longer than its TTL would 404 with an expired
     * signature. Routing every click through here generates the signed URL fresh at
     * the moment it's actually needed, no matter how long the page has been open.
     */
    public function show(Request $request): RedirectResponse
    {
        try {
            $path = Crypt::decryptString((string) $request->query('path'));
        } catch (DecryptException) {
            abort(404);
        }

        $disk = Storage::disk(config('filesystems.default'));

        abort_unless($disk->exists($path), 404);

        return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(10)));
    }
}
