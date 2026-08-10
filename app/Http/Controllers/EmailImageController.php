<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmailImageController extends Controller
{
    public const string DIRECTORY = 'email-attachments';

    /**
     * Deliberately unauthenticated — this URL is embedded in outbound emails
     * and loaded by an external recipient's mail client, which has no
     * session with the app. Safety comes from the route's "signed"
     * middleware (see routes/web.php): only a URL minted by us, with a
     * matching signature, is accepted. The directory check below is a
     * second line of defence, so this route can never be repurposed to
     * serve anything outside the dedicated email-images folder — in
     * particular, never a candidate's private compliance documents.
     */
    public function show(string $path): RedirectResponse
    {
        abort_unless(Str::startsWith($path, self::DIRECTORY.'/'), 404);

        $disk = Storage::disk(config('filesystems.default'));

        abort_unless($disk->exists($path), 404);

        return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(10)));
    }
}
