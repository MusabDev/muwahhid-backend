<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ReadsImportFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkRequest;
use App\Http\Requests\Admin\DarsSeriesRequest;
use App\Http\Resources\DarsSeriesResource;
use App\Models\DarsSeries;
use App\Services\SeriesImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DarsSeriesController extends Controller
{
    use ReadsImportFile;

    public function index(Request $request): AnonymousResourceCollection
    {
        $series = DarsSeries::query()
            ->with(['author', 'category'])
            ->withCount('durus')
            ->when($request->filled('author_id'), fn ($query) => $query->where('author_id', $request->integer('author_id')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('is_published'), fn ($query) => $query->where('is_published', $request->boolean('is_published')))
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

    /**
     * Publish, unpublish, recategorize, reassign or delete several series (with their durus) at once.
     */
    public function bulk(BulkRequest $request): JsonResponse
    {
        $series = DarsSeries::whereIn('id', $request->validated('ids'))->get();

        DB::transaction(fn () => $series->each(fn (DarsSeries $item) => match ($request->validated('action')) {
            'publish' => $item->update(['is_published' => true]),
            'unpublish' => $item->update(['is_published' => false]),
            'category' => $item->update(['category_id' => $request->validated('category_id')]),
            'author' => $item->update(['author_id' => $request->validated('author_id')]),
            'delete' => $item->delete(),
        }));

        return response()->json(['affected' => $series->count()]);
    }

    /**
     * Create a new series with its durus and transcripts from a series file.
     * With dry_run=1 nothing is written and only a summary is returned.
     */
    public function importNew(Request $request, SeriesImporter $importer): JsonResponse
    {
        $data = $this->readImportFile($request, SeriesImporter::FORMAT, SeriesImporter::rules(withMeta: true));
        $result = $importer->createFromJson($data, $request->boolean('dry_run'));

        return response()->json([
            'series' => $result['series'] ? new DarsSeriesResource($result['series']) : null,
            'imported' => $result['imported'],
            'will_create' => $result['will_create'],
        ]);
    }

    /**
     * Add or update durus (matched by number) in this series from a series file.
     * Its "series" block is ignored.
     */
    public function import(Request $request, DarsSeries $darsSeries, SeriesImporter $importer): JsonResponse
    {
        $data = $this->readImportFile($request, SeriesImporter::FORMAT, SeriesImporter::rules(withMeta: false));

        return response()->json($importer->importInto($darsSeries, $data, $request->boolean('dry_run')));
    }

    /**
     * The series in the import file format.
     */
    public function export(DarsSeries $darsSeries, SeriesImporter $importer): JsonResponse
    {
        return response()->json($importer->export($darsSeries), options: JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
