<?php

namespace App\Services;

use App\Enums\MediaType;
use App\Models\Dars;
use App\Models\DarsSegment;
use App\Models\DarsSeries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeriesImporter
{
    public const FORMAT = 'muwahhid.series/v1';

    public function __construct(private TranscriptImporter $transcripts) {}

    /**
     * Validation rules for a series file. With $withMeta the "series" block is required.
     *
     * @return array<string, mixed>
     */
    public static function rules(bool $withMeta): array
    {
        $meta = $withMeta ? [
            'series' => ['required', 'array'],
            'series.title_ar' => ['required', 'string', 'max:255'],
            'series.title_bn' => ['required', 'string', 'max:255'],
            'series.title_translit' => ['required', 'string', 'max:255'],
            'series.slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'series.about_md' => ['nullable', 'string'],
            'series.is_published' => ['sometimes', 'boolean'],
            'series.book' => ['nullable', 'string'],
            ...ReferenceResolver::authorRules('series.author'),
            ...ReferenceResolver::categoryRules('series.category'),
        ] : [];

        return [
            ...$meta,
            'durus' => [$withMeta ? 'sometimes' : 'required', 'array'],
            'durus.*.number' => ['required', 'integer', 'min:0', 'distinct'],
            'durus.*.title_bn' => ['required', 'string', 'max:255'],
            'durus.*.title_ar' => ['nullable', 'string', 'max:255'],
            'durus.*.media_type' => ['required', Rule::enum(MediaType::class)],
            'durus.*.media_url' => ['required', 'url', 'max:2048'],
            'durus.*.duration_seconds' => ['nullable', 'integer', 'min:0'],
            'durus.*.segments' => ['sometimes', 'array'],
            'durus.*.segments.*.start' => ['required_without:durus.*.segments.*.start_time'],
            'durus.*.segments.*.ar' => ['nullable', 'string'],
            'durus.*.segments.*.bn' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{durus: int, segments: int}
     */
    public static function count(array $data): array
    {
        $durus = $data['durus'] ?? [];

        return [
            'durus' => count($durus),
            'segments' => array_sum(array_map(fn ($dars) => count($dars['segments'] ?? []), $durus)),
        ];
    }

    /**
     * Create a new series (and any missing author / category) with its durus.
     *
     * @param  array<string, mixed>  $data
     * @return array{series: ?DarsSeries, imported: array{durus: int, segments: int}, will_create: array{authors: list<string>, categories: list<string>}}
     */
    public function createFromJson(array $data, bool $dryRun = false): array
    {
        $meta = $data['series'];
        $slug = $meta['slug'] ?? Str::slug($meta['title_translit']);

        if (DarsSeries::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['series.slug' => "A series with slug \"{$slug}\" already exists. Import into that series instead, or set a different slug."]);
        }

        $refs = new ReferenceResolver($dryRun);

        $run = function () use ($data, $meta, $slug, $refs, $dryRun) {
            $author = $refs->author($meta['author'], 'series.author');
            $category = $refs->category($meta['category'] ?? null, 'series.category');
            $book = $refs->book($meta['book'] ?? null, 'series.book');

            if ($dryRun) {
                return ['series' => null, 'imported' => self::count($data), 'will_create' => $refs->created];
            }

            $series = DarsSeries::create([
                'slug' => $slug,
                'title_ar' => $meta['title_ar'],
                'title_bn' => $meta['title_bn'],
                'title_translit' => $meta['title_translit'],
                'about_md' => $meta['about_md'] ?? null,
                'is_published' => (bool) ($meta['is_published'] ?? false),
                'author_id' => $author->id,
                'category_id' => $category?->id,
                'book_id' => $book?->id,
            ]);

            return ['series' => $series, 'imported' => $this->importDurus($series, $data['durus'] ?? []), 'will_create' => $refs->created];
        };

        return $dryRun ? $run() : DB::transaction($run);
    }

    /**
     * Add durus to an existing series. A dars with the same number is updated, and its
     * transcript is replaced when the file gives segments for it.
     *
     * @param  array<string, mixed>  $data
     * @return array{imported: array{durus: int, segments: int}, updated: int}
     */
    public function importInto(DarsSeries $series, array $data, bool $dryRun = false): array
    {
        $numbers = array_column($data['durus'] ?? [], 'number');
        $updated = $series->durus()->whereIn('number', $numbers)->count();

        if ($dryRun) {
            return ['imported' => self::count($data), 'updated' => $updated];
        }

        return DB::transaction(fn () => ['imported' => $this->importDurus($series, $data['durus'] ?? []), 'updated' => $updated]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $durus
     * @return array{durus: int, segments: int}
     */
    private function importDurus(DarsSeries $series, array $durus): array
    {
        $counts = ['durus' => 0, 'segments' => 0];

        foreach ($durus as $item) {
            $dars = Dars::updateOrCreate(
                ['series_id' => $series->id, 'number' => (int) $item['number']],
                [
                    'title_bn' => $item['title_bn'],
                    'title_ar' => $item['title_ar'] ?? null,
                    'media_type' => $item['media_type'],
                    'media_url' => $item['media_url'],
                    'duration_seconds' => $item['duration_seconds'] ?? null,
                ],
            );
            $counts['durus']++;

            if (! empty($item['segments'])) {
                $counts['segments'] += $this->transcripts->importJson($dars, $item['segments'], true);
            }
        }

        return $counts;
    }

    /**
     * The series in the import file format.
     *
     * @return array<string, mixed>
     */
    public function export(DarsSeries $series): array
    {
        $series->load(['author', 'category', 'book', 'durus.segments']);

        return [
            'format' => self::FORMAT,
            'series' => [
                'title_ar' => $series->title_ar,
                'title_bn' => $series->title_bn,
                'title_translit' => $series->title_translit,
                'slug' => $series->slug,
                'about_md' => $series->about_md,
                'is_published' => $series->is_published,
                'author' => ['slug' => $series->author->slug, 'name_ar' => $series->author->name_ar, 'name_bn' => $series->author->name_bn, 'name_translit' => $series->author->name_translit],
                'category' => $series->category ? ['slug' => $series->category->slug, 'name_bn' => $series->category->name_bn, 'name_ar' => $series->category->name_ar] : null,
                'book' => $series->book?->slug,
            ],
            'durus' => $series->durus->sortBy('number')->values()->map(fn (Dars $dars) => [
                'number' => $dars->number,
                'title_bn' => $dars->title_bn,
                'title_ar' => $dars->title_ar,
                'media_type' => $dars->media_type->value,
                'media_url' => $dars->media_url,
                'duration_seconds' => $dars->duration_seconds,
                'segments' => $dars->segments->sortBy('position')->values()->map(fn (DarsSegment $segment) => [
                    'start' => $segment->start_time,
                    'end' => $segment->end_time,
                    'ar' => $segment->text_ar,
                    'bn' => $segment->text_bn,
                ])->all(),
            ])->all(),
        ];
    }
}
