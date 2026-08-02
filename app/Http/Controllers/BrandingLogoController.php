<?php

namespace App\Http\Controllers;

use App\Models\BrandingSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class BrandingLogoController extends Controller
{
    public function __invoke(): Response
    {
        $branding = BrandingSetting::query()->first();

        abort_unless($branding?->logo_path && Storage::disk('local')->exists($branding->logo_path), 404);

        return response(Storage::disk('local')->get($branding->logo_path), 200, [
            'Content-Type' => $branding->logo_mime ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=300, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
