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
            ->with(['author', 'category'])
            ->when($request->filled('category'), fn (Builder $query) => $query->whereHas('category', fn (Builder $category) => $category->where('slug', $request->string('category'))))
            ->withCardStats()
            ->when($request->filled('author'), fn (Builder $query) => $query->whereHas('author', fn (Builder $author) => $author->where('slug', $request->string('author'))))
            ->when($request->filled('q'), fn (Builder $query) => $query->matching((string) $request->string('q')))
            ->when($request->input('sort') === 'title', fn (Builder $query) => $query->orderBy('title_translit'), fn (Builder $query) => $query->latest())
            ->paginate($request->integer('per_page', 24));

        return DarsSeriesResource::collection($series);
    }

    public function show(Request $request, DarsSeries $darsSeries): DarsSeriesResource
    {
        abort_unless($darsSeries->is_published || $request->user('sanctum')?->is_admin, 404);

        return new DarsSeriesResource($darsSeries->load(['author', 'category', 'book.author', 'book.explainer', 'durus' => fn ($query) => $query->withCount('segments')]));
    }
}
