<?php

namespace App\Services;

use App\Enums\Lang;
use App\Models\Annotation;
use App\Models\Dars;
use App\Models\DarsSegment;
use App\Support\ArabicNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TranscriptImporter
{
    /**
     * Import segments from JSON: [{"start": "00:01.5" | 1.5, "end": ..., "ar": "...", "bn": "..."}].
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function importJson(Dars $dars, array $items, bool $replace = true): int
    {
        $segments = array_map(fn (array $item) => [
            'start_time' => $this->parseTime($item['start'] ?? $item['start_time'] ?? 0),
            'end_time' => isset($item['end']) || isset($item['end_time']) ? $this->parseTime($item['end'] ?? $item['end_time']) : null,
            'text_ar' => (string) ($item['ar'] ?? $item['text_ar'] ?? ''),
            'text_bn' => (string) ($item['bn'] ?? $item['text_bn'] ?? ''),
        ], $items);

        return $this->store($dars, $segments, $replace);
    }

    /**
     * Import segments from CSV with a header row: start, end, ar, bn.
     */
    public function importCsv(Dars $dars, string $path, bool $replace = true): int
    {
        $handle = fopen($path, 'r');
        $header = array_map(fn ($column) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))), fgetcsv($handle, escape: '\\') ?: []);

        if (! in_array('start', $header, true)) {
            throw ValidationException::withMessages(['file' => 'CSV is missing the "start" column.']);
        }

        $items = [];

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if ($row !== [null]) {
                $items[] = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
            }
        }

        fclose($handle);

        return $this->importJson($dars, array_map(fn (array $item) => [...$item, 'end' => ($item['end'] ?? '') !== '' ? $item['end'] : null], $items), $replace);
    }

    /**
     * Import one language from an SRT file. With $merge, the text is written into the
     * existing segments by order (keeping their times), so an Arabic SRT and a Bangla
     * SRT can be uploaded one after the other.
     */
    public function importSrt(Dars $dars, string $contents, Lang $lang, bool $merge = false): int
    {
        $blocks = preg_split('/\R{2,}/u', trim(str_replace("\r\n", "\n", preg_replace('/^\xEF\xBB\xBF/', '', $contents))));
        $cues = [];

        foreach ($blocks as $block) {
            $lines = array_values(array_filter(explode("\n", trim($block)), fn ($line) => $line !== ''));
            $timeIndex = isset($lines[0]) && str_contains($lines[0], '-->') ? 0 : 1;

            if (! isset($lines[$timeIndex]) || ! preg_match('/([\d:.,]+)\s*-->\s*([\d:.,]+)/', $lines[$timeIndex], $match)) {
                continue;
            }

            $cues[] = [
                'start_time' => $this->parseTime($match[1]),
                'end_time' => $this->parseTime($match[2]),
                'text' => trim(strip_tags(implode(' ', array_slice($lines, $timeIndex + 1)))),
            ];
        }

        $column = $lang === Lang::Arabic ? 'text_ar' : 'text_bn';

        if ($merge && $dars->segments()->exists()) {
            return DB::transaction(function () use ($dars, $cues, $column) {
                $existing = $dars->segments()->get()->values();

                foreach ($cues as $index => $cue) {
                    $segment = $existing[$index] ?? new DarsSegment([
                        'dars_id' => $dars->id,
                        'position' => $index + 1,
                        'start_time' => $cue['start_time'],
                        'end_time' => $cue['end_time'],
                    ]);
                    $segment->{$column} = $cue['text'];
                    $segment->save();
                }

                return count($cues);
            });
        }

        return $this->store($dars, array_map(fn (array $cue) => [
            'start_time' => $cue['start_time'],
            'end_time' => $cue['end_time'],
            'text_ar' => $column === 'text_ar' ? $cue['text'] : '',
            'text_bn' => $column === 'text_bn' ? $cue['text'] : '',
        ], $cues), true);
    }

    /**
     * Parse "HH:MM:SS,mmm", "MM:SS.s" or plain seconds into seconds.
     */
    public function parseTime(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $parts = array_reverse(explode(':', str_replace(',', '.', trim((string) $value))));
        $seconds = 0.0;

        foreach ($parts as $index => $part) {
            $seconds += (float) $part * (60 ** $index);
        }

        return round($seconds, 3);
    }

    /**
     * @param  array<int, array{start_time: float, end_time: ?float, text_ar: string, text_bn: string}>  $segments
     */
    private function store(Dars $dars, array $segments, bool $replace): int
    {
        usort($segments, fn (array $a, array $b) => $a['start_time'] <=> $b['start_time']);

        return DB::transaction(function () use ($dars, $segments, $replace) {
            if ($replace) {
                Annotation::where('dars_id', $dars->id)->delete();
                $dars->segments()->delete();
            }

            $position = (int) $dars->segments()->max('position');
            $now = now();
            $rows = array_map(fn (array $segment) => [
                ...$segment,
                'dars_id' => $dars->id,
                'position' => ++$position,
                'text_ar_normalized' => ArabicNormalizer::normalize($segment['text_ar']),
                'created_at' => $now,
                'updated_at' => $now,
            ], $segments);

            foreach (array_chunk($rows, 500) as $chunk) {
                DarsSegment::insert($chunk);
            }

            return count($rows);
        });
    }
}
