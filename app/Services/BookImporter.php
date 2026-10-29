<?php

namespace App\Services;

use App\Enums\BlockType;
use App\Enums\Lang;
use App\Models\Annotation;
use App\Models\Book;
use App\Models\BookFootnote;
use App\Models\BookPage;
use App\Models\BookSentence;
use App\Models\TocEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookImporter
{
    /**
     * Import book content from the JSON format:
     *
     * {
     *   "pages": [{
     *     "number": 1,
     *     "sentences": [{"ar": "...", "bn": "...", "block_type": "paragraph", "paragraph_break": false}],
     *     "footnotes": {"ar": [{"marker": "1", "text": "..."}], "bn": [...]}
     *   }],
     *   "toc": [{"title_ar": "...", "title_bn": "...", "page": 1, "children": [...]}]
     * }
     *
     * @param  array{pages?: array<int, array<string, mixed>>, toc?: array<int, array<string, mixed>>}  $data
     * @return array{pages: int, sentences: int, footnotes: int, toc_entries: int}
     */
    public function importJson(Book $book, array $data, bool $replace = false): array
    {
        return DB::transaction(function () use ($book, $data, $replace) {
            if ($replace) {
                Annotation::where('book_id', $book->id)->where('annotatable_type', 'book_sentence')->delete();
                $book->tocEntries()->delete();
                BookPage::where('book_id', $book->id)->delete();
            }

            $counts = ['pages' => 0, 'sentences' => 0, 'footnotes' => 0, 'toc_entries' => 0];

            foreach ($data['pages'] ?? [] as $index => $pageData) {
                $page = $this->page($book, (int) ($pageData['number'] ?? $index + 1));
                $counts['pages']++;
                $counts['sentences'] += $this->insertSentences($book, $page, $pageData['sentences'] ?? []);

                foreach (['ar', 'bn'] as $lang) {
                    foreach ($pageData['footnotes'][$lang] ?? [] as $footnote) {
                        BookFootnote::updateOrCreate(
                            ['page_id' => $page->id, 'lang' => $lang, 'marker' => (string) $footnote['marker']],
                            ['book_id' => $book->id, 'text_md' => (string) $footnote['text']],
                        );
                        $counts['footnotes']++;
                    }
                }
            }

            if (! empty($data['toc'])) {
                $book->tocEntries()->delete();
                $counts['toc_entries'] = $this->insertToc($book, $data['toc']);
            }

            $book->bumpContentVersion();

            return $counts;
        });
    }

    /**
     * Import sentences from CSV with a header row: page, ar, bn, block_type, paragraph_break.
     * Footnotes rows may be included with columns: page, footnote_lang, marker, ar|bn (text).
     *
     * @return array{pages: int, sentences: int, footnotes: int, toc_entries: int}
     */
    public function importCsv(Book $book, string $path, bool $replace = false): array
    {
        $handle = fopen($path, 'r');
        $header = array_map(fn ($column) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))), fgetcsv($handle, escape: '\\') ?: []);

        foreach (['page', 'ar', 'bn'] as $required) {
            if (! in_array($required, $header, true)) {
                throw ValidationException::withMessages(['file' => "CSV is missing the \"{$required}\" column."]);
            }
        }

        $pages = [];

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }

            $record = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
            $number = (int) $record['page'];
            $pages[$number] ??= ['number' => $number, 'sentences' => [], 'footnotes' => ['ar' => [], 'bn' => []]];

            if (! empty($record['footnote_lang'])) {
                $lang = Lang::tryFrom($record['footnote_lang'])?->value;

                if ($lang === null) {
                    continue;
                }

                $pages[$number]['footnotes'][$lang][] = ['marker' => $record['marker'] ?? '', 'text' => $record[$lang] ?? ''];

                continue;
            }

            $pages[$number]['sentences'][] = [
                'ar' => $record['ar'],
                'bn' => $record['bn'],
                'block_type' => $record['block_type'] ?? null,
                'paragraph_break' => filter_var($record['paragraph_break'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        fclose($handle);
        ksort($pages);

        return $this->importJson($book, ['pages' => array_values($pages)], $replace);
    }

    private function page(Book $book, int $number): BookPage
    {
        return BookPage::firstOrCreate(['book_id' => $book->id, 'number' => $number]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sentences
     */
    private function insertSentences(Book $book, BookPage $page, array $sentences): int
    {
        $position = (int) BookSentence::where('page_id', $page->id)->max('position');
        $now = now();
        $rows = [];

        foreach ($sentences as $sentence) {
            $arabic = (string) ($sentence['ar'] ?? $sentence['text_ar_md'] ?? '');
            $bangla = (string) ($sentence['bn'] ?? $sentence['text_bn_md'] ?? '');

            $rows[] = [
                'book_id' => $book->id,
                'page_id' => $page->id,
                'position' => ++$position,
                'block_type' => (BlockType::tryFrom((string) ($sentence['block_type'] ?? '')) ?? BlockType::Paragraph)->value,
                'paragraph_break' => (bool) ($sentence['paragraph_break'] ?? false),
                'text_ar_md' => $arabic,
                'text_bn_md' => $bangla,
                ...BookSentence::derivedText($arabic, $bangla),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            BookSentence::insert($chunk);
        }

        return count($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function insertToc(Book $book, array $entries, ?int $parentId = null): int
    {
        $count = 0;

        foreach (array_values($entries) as $position => $entry) {
            $toc = TocEntry::create([
                'book_id' => $book->id,
                'parent_id' => $parentId,
                'position' => $position,
                'title_ar' => (string) ($entry['title_ar'] ?? ''),
                'title_bn' => (string) ($entry['title_bn'] ?? ''),
                'page_number' => (int) ($entry['page'] ?? $entry['page_number'] ?? 1),
                'sentence_id' => $entry['sentence_id'] ?? null,
            ]);
            $count++;

            $count += $this->insertToc($book, $entry['children'] ?? [], $toc->id);
        }

        return $count;
    }

    /**
     * Replace the whole table of contents.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    public function replaceToc(Book $book, array $entries): int
    {
        return DB::transaction(function () use ($book, $entries) {
            $book->tocEntries()->delete();
            $count = $this->insertToc($book, $entries);
            $book->bumpContentVersion();

            return $count;
        });
    }
}
