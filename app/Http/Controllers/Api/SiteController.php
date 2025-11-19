<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Concerns\HandlesSiteAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    use HandlesSiteAccess;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $sites = $this->accessibleSitesQuery($user)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description'])
            ->map(fn ($site) => [
                'id' => $site->id,
                'name' => $site->name,
                'slug' => $site->slug,
                'description' => $site->description,
            ])
            ->values();

        return response()->json([
            'data' => $sites,
        ]);
    }
}
