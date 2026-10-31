<?php

namespace App\Http\Controllers;

use App\Http\Resources\DarsResource;
use App\Models\Dars;
use App\Models\DarsSeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DarsController extends Controller
{
    public function show(Request $request, Dars $dars): JsonResponse
    {
        return $this->respond($request, $dars);
    }

    /**
     * A dars by its series slug and number: the public URL is /durus/{series}/{number}.
     */
    public function showByNumber(Request $request, DarsSeries $darsSeries, int $number): JsonResponse
    {
        $dars = $darsSeries->durus()->where('number', $number)->firstOrFail();
        $dars->setRelation('series', $darsSeries);

        return $this->respond($request, $dars);
    }

    private function respond(Request $request, Dars $dars): JsonResponse
    {
        $dars->load(['series.author', 'segments']);
        abort_unless($dars->series->is_published || $request->user('sanctum')?->is_admin, 404);

        $siblings = Dars::where('series_id', $dars->series_id);
        $prev = (clone $siblings)->where('number', '<', $dars->number)->orderByDesc('number')->first(['id', 'number']);
        $next = (clone $siblings)->where('number', '>', $dars->number)->orderBy('number')->first(['id', 'number']);

        return response()->json([
            ...(new DarsResource($dars))->resolve($request),
            'prev_id' => $prev?->id,
            'next_id' => $next?->id,
            'prev_number' => $prev?->number,
            'next_number' => $next?->number,
        ]);
    }
}
