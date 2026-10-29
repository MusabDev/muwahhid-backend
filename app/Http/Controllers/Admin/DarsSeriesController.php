<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DarsSeriesRequest;
use App\Http\Resources\DarsSeriesResource;
use App\Models\DarsSeries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DarsSeriesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $series = DarsSeries::query()
            ->with(['author', 'category'])
            ->withCount('durus')
            ->when($request->filled('author_id'), fn ($query) => $query->where('author_id', $request->integer('author_id')))
            ->when($request->filled('q'), fn ($query) => $query->whereRaw(
                "(title_ar || ' ' || title_bn || ' ' || title_translit) ILIKE ?",
                ['%'.$request->string('q').'%'],
            ))
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return DarsSeriesResource::collection($series);
    }

    public function store(DarsSeriesRequest $request): DarsSeriesResource
    {
        return new DarsSeriesResource(DarsSeries::create($request->validated())->load(['author', 'category']));
    }

    public function show(DarsSeries $darsSeries): DarsSeriesResource
    {
        return new DarsSeriesResource($darsSeries->load(['author', 'category', 'book', 'durus' => fn ($query) => $query->withCount('segments')]));
    }

    public function update(DarsSeriesRequest $request, DarsSeries $darsSeries): DarsSeriesResource
    {
        $darsSeries->update($request->validated());

        return new DarsSeriesResource($darsSeries->load(['author', 'category']));
    }

    public function destroy(DarsSeries $darsSeries): Response
    {
        $darsSeries->delete();

        return response()->noContent();
    }
}
