<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Lang;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DarsRequest;
use App\Http\Resources\DarsResource;
use App\Models\Dars;
use App\Models\DarsSeries;
use App\Services\TranscriptImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DarsController extends Controller
{
    public function store(DarsRequest $request, DarsSeries $darsSeries): DarsResource
    {
        return new DarsResource($darsSeries->durus()->create($request->validated()));
    }

    public function show(Dars $dars): DarsResource
    {
        return new DarsResource($dars->load(['series', 'segments']));
    }

    public function update(DarsRequest $request, Dars $dars): DarsResource
    {
        $dars->update($request->validated());

        return new DarsResource($dars);
    }

    public function destroy(Dars $dars): Response
    {
        $dars->delete();

        return response()->noContent();
    }

    /**
     * Import transcript segments from JSON, CSV or SRT.
     * SRT holds one language ("lang"); with merge=true it fills that language into
     * the existing segments instead of replacing them.
     */
    public function import(Request $request, Dars $dars, TranscriptImporter $importer): JsonResponse
    {
        $request->validate([
            'file' => ['required_without:segments', 'file', 'max:20480'],
            'segments' => ['required_without:file', 'array'],
            'lang' => ['nullable', Rule::enum(Lang::class)],
            'merge' => ['sometimes', 'boolean'],
            'replace' => ['sometimes', 'boolean'],
        ]);

        $replace = $request->boolean('replace', true);

        if (! $request->hasFile('file')) {
            return response()->json(['imported' => $importer->importJson($dars, $request->input('segments'), $replace)]);
        }

        $file = $request->file('file');
        $count = match (strtolower($file->getClientOriginalExtension())) {
            'srt' => $importer->importSrt(
                $dars,
                file_get_contents($file->getRealPath()),
                Lang::tryFrom((string) $request->input('lang')) ?? throw ValidationException::withMessages(['lang' => 'Choose the SRT language (ar or bn).']),
                $request->boolean('merge'),
            ),
            'csv', 'txt' => $importer->importCsv($dars, $file->getRealPath(), $replace),
            'json' => $importer->importJson($dars, $this->decodeJson($file->getRealPath()), $replace),
            default => throw ValidationException::withMessages(['file' => 'Upload a .json, .csv or .srt file.']),
        };

        return response()->json(['imported' => $count]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeJson(string $path): array
    {
        $data = json_decode(file_get_contents($path), true);
        $data = $data['segments'] ?? $data;

        if (! is_array($data) || ! array_is_list($data)) {
            throw ValidationException::withMessages(['file' => 'Expected a JSON array of segments.']);
        }

        return $data;
    }
}
