<?php

namespace App\Http\Controllers;

use App\Http\Resources\DarsSeriesResource;
use App\Models\DarsSeries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DarsSeriesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $series = DarsSeries::published()
            ->with('author')
            ->withCount('durus')
            ->when($request->filled('author'), fn (Builder $query) => $query->whereHas('author', fn (Builder $author) => $author->where('slug', $request->string('author'))))
            ->latest()
            ->paginate($request->integer('per_page', 24));

        return DarsSeriesResource::collection($series);
    }

    public function show(Request $request, DarsSeries $darsSeries): DarsSeriesResource
    {
        abort_unless($darsSeries->is_published || $request->user('sanctum')?->is_admin, 404);

        return new DarsSeriesResource($darsSeries->load(['author', 'book', 'durus' => fn ($query) => $query->withCount('segments')]));
    }
}
