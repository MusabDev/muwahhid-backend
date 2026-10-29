<?php

namespace App\Http\Controllers;

use App\Http\Resources\DarsResource;
use App\Models\Dars;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DarsController extends Controller
{
    public function show(Request $request, Dars $dars): JsonResponse
    {
        $dars->load(['series.author', 'segments']);
        abort_unless($dars->series->is_published || $request->user('sanctum')?->is_admin, 404);

        $siblings = Dars::where('series_id', $dars->series_id);

        return response()->json([
            ...(new DarsResource($dars))->resolve($request),
            'prev_id' => (clone $siblings)->where('number', '<', $dars->number)->orderByDesc('number')->value('id'),
            'next_id' => (clone $siblings)->where('number', '>', $dars->number)->orderBy('number')->value('id'),
        ]);
    }
}
