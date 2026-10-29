<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DarsSegmentResource;
use App\Models\Dars;
use App\Models\DarsSegment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DarsSegmentController extends Controller
{
    public function store(Request $request, Dars $dars): DarsSegmentResource
    {
        $data = $this->withEmptyText($request->validate($this->rules('required')));

        $segment = DB::transaction(function () use ($dars, $data) {
            $segment = $dars->segments()->create([...$data, 'position' => 0]);
            $this->renumber($dars);

            return $segment->refresh();
        });

        return new DarsSegmentResource($segment);
    }

    public function update(Request $request, DarsSegment $segment): DarsSegmentResource
    {
        $segment->update($this->withEmptyText($request->validate($this->rules('sometimes'))));

        if ($segment->wasChanged('start_time')) {
            $this->renumber($segment->dars);
        }

        return new DarsSegmentResource($segment->refresh());
    }

    public function destroy(DarsSegment $segment): Response
    {
        $segment->annotations()->delete();
        $segment->delete();
        $this->renumber($segment->dars);

        return response()->noContent();
    }

    /**
     * Keep segment positions in start-time order.
     */
    private function renumber(Dars $dars): void
    {
        DarsSegment::where('dars_id', $dars->id)
            ->orderBy('start_time')
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $id, int $index) => DarsSegment::whereKey($id)->update(['position' => $index + 1]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withEmptyText(array $data): array
    {
        foreach (['text_ar', 'text_bn'] as $column) {
            if (array_key_exists($column, $data) && $data[$column] === null) {
                $data[$column] = '';
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(string $required): array
    {
        return [
            'start_time' => [$required, 'numeric', 'min:0'],
            'end_time' => ['nullable', 'numeric', 'gte:start_time'],
            'text_ar' => ['nullable', 'string'],
            'text_bn' => ['nullable', 'string'],
        ];
    }
}
