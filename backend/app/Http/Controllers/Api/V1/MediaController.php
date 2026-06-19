<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public function upload(Request $request): JsonResponse
    {
        $file = $request->file('file') ?? $request->file('image');
        if (! $file || ! $file->isValid()) {
            return response()->json(svp_err('invalid_file'), 400);
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return response()->json(svp_err('file_too_large'), 400);
        }
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIME, true)) {
            return response()->json(svp_err('invalid_mime'), 400);
        }

        $name = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('dashboard-uploads', $name, 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json(svp_ok(['url' => $url, 'path' => $path]));
    }
}
