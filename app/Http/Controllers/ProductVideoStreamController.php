<?php

namespace App\Http\Controllers;

use App\Models\ProductVideo;
use Illuminate\Support\Facades\Storage;

class ProductVideoStreamController extends Controller
{
    /**
     * Stream a product video with byte-range support.
     *
     * Browsers request media with a Range header so they can start playing an MP4
     * whose metadata (moov atom) sits at the end of the file. Static files served
     * by PHP's built-in server ignore that header and always push the whole file,
     * so playback stalls until the complete download finishes. Symfony's file
     * response (used by response()->file()) answers with 206 partial content,
     * which makes playback and seeking work everywhere.
     */
    public function __invoke(ProductVideo $productVideo)
    {
        $disk = Storage::disk('public');
        $path = $productVideo->path;

        abort_if(empty($path) || ! $disk->exists($path), 404);

        return response()->file(
            $disk->path($path),
            [
                'Content-Type'  => $disk->mimeType($path) ?: 'video/mp4',
                'Cache-Control' => 'public, max-age=86400',
            ],
            'inline'
        );
    }
}
