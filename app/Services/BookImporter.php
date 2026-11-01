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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookImporter
{
    public const FORMAT = 'muwahhid.book/v1';

    /**
     * Validation rules for a book file. With $withMeta the "book" block is required.
     *
     * @return array<string, mixed>
     */
    public static function rules(bool $withMeta): array
    {
        $meta = $withMeta ? [
            'book' => ['required', 'array'],
            'book.title_ar' => ['required', 'string', 'max:255'],
            'book.title_bn' => ['required', 'string', 'max:255'],
            'book.title_translit' => ['required', 'string', 'max:255'],
            'book.slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'book.about_md' => ['nullable', 'string'],
            'book.is_published' => ['sometimes', 'boolean'],
            ...ReferenceResolver::authorRules('book.author'),
            ...ReferenceResolver::authorRules('book.explainer', required: false),
            ...ReferenceResolver::categoryRules('book.category'),
        ] : [];

        return [
            ...$meta,
            'pages' => [$withMeta ? 'sometimes' : 'required_without:toc', 'array'],
            'pages.*.number' => ['sometimes', 'integer', 'min:0'],
            'pages.*.sentences' => ['sometimes', 'array'],
            'pages.*.sentences.*.ar' => ['present', 'nullable', 'string'],
            'pages.*.sentences.*.bn' => ['present', 'nullable', 'string'],
            'pages.*.sentences.*.block_type' => ['nullable', Rule::enum(BlockType::class)],
            'pages.*.sentences.*.paragraph_break' => ['sometimes', 'boolean'],
            'pages.*.footnotes' => ['sometimes', 'array'],
            'pages.*.footnotes.ar.*.marker' => ['required', 'max:20'],
            'pages.*.footnotes.ar.*.text' => ['required', 'string'],
            'pages.*.footnotes.bn.*.marker' => ['required', 'max:20'],
            'pages.*.footnotes.bn.*.text' => ['required', 'string'],
            'toc' => ['sometimes', 'array'],
            'toc.*.title_ar' => ['required', 'string', 'max:255'],
            'toc.*.title_bn' => ['required', 'string', 'max:255'],
            'toc.*.page' => ['required_without:toc.*.page_number', 'integer', 'min:0'],
            'toc.*.children' => ['sometimes', 'array'],
        ];
    }

    /**
     * Count what a book file holds, without writing anything.
     *
     * @param  array<string, mixed>  $data
     * @return array{pages: int, sentences: int, footnotes: int, toc_entries: int}
     */
    public static function count(array $data): array
    {
        $pages = $data['pages'] ?? [];

        return [
            'pages' => count($pages),
            'sentences' => array_sum(array_map(fn ($page) => count($page['sentences'] ?? []), $pages)),
            'footnotes' => array_sum(array_map(fn ($page) => count($page['footnotes']['ar'] ?? []) + count($page['footnotes']['bn'] ?? []), $pages)),
            'toc_entries' => self::countToc($data['toc'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private static function countToc(array $entries): int
    {
        return array_sum(array_map(fn (array $entry) => 1 + self::countToc($entry['children'] ?? []), $entries));
    }

    /**
     * Create a new book (and any missing author / category) from a full book file.
     * In dry-run mode only the summary is returned.
     *
     * @param  array<string, mixed>  $data
     * @return array{book: ?Book, imported: array{pages: int, sentences: int, footnotes: int, toc_entries: int}, will_create: array{authors: list<string>, categories: list<string>}}
     */
    public function createFromJson(array $data, bool $dryRun = false): array
    {
        $meta = $data['book'];
        $slug = $meta['slug'] ?? Str::slug($meta['title_translit']);

        if (Book::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['book.slug' => "A book with slug \"{$slug}\" already exists. Import into that book instead, or set a different slug."]);
        }

        $refs = new ReferenceResolver($dryRun);

        $run = function () use ($data, $meta, $slug, $refs, $dryRun) {
            $author = $refs->author($meta['author'], 'book.author');
            $explainer = $refs->author($meta['explainer'] ?? null, 'book.explainer');
            $category = $refs->category($meta['category'] ?? null, 'book.category');

            if ($dryRun) {
                return ['book' => null, 'imported' => self::count($data), 'will_create' => $refs->created];
            }

            $book = Book::create([
                'slug' => $slug,
                'title_ar' => $meta['title_ar'],
                'title_bn' => $meta['title_bn'],
                'title_translit' => $meta['title_translit'],
                'about_md' => $meta['about_md'] ?? null,
                'is_published' => (bool) ($meta['is_published'] ?? false),
                'author_id' => $author->id,
                'is_sharh' => $explainer !== null,
                'explainer_id' => $explainer?->id,
                'category_id' => $category?->id,
            ]);

            return ['book' => $book, 'imported' => $this->importJson($book, $data), 'will_create' => $refs->created];
        };

        return $dryRun ? $run() : DB::transaction($run);
    }

    /**
     * The book in the import file format, so it can be edited and imported again.
     *
     * @return array<string, mixed>
     */
    public function export(Book $book): array
    {
        $book->load(['author', 'explainer', 'category', 'pages.sentences', 'pages.footnotes', 'tocEntries' => fn ($query) => $query->whereNull('parent_id')->with('children.children.children')]);

        $author = fn ($author) => $author ? ['slug' => $author->slug, 'name_ar' => $author->name_ar, 'name_bn' => $author->name_bn, 'name_translit' => $author->name_translit] : null;

        return [
            'format' => self::FORMAT,
            'book' => [
                'title_ar' => $book->title_ar,
                'title_bn' => $book->title_bn,
                'title_translit' => $book->title_translit,
                'slug' => $book->slug,
                'about_md' => $book->about_md,
                'is_published' => $book->is_published,
                'author' => $author($book->author),
                'explainer' => $book->is_sharh ? $author($book->explainer) : null,
                'category' => $book->category ? ['slug' => $book->category->slug, 'name_bn' => $book->category->name_bn, 'name_ar' => $book->category->name_ar] : null,
            ],
            'pages' => $book->pages->sortBy('number')->values()->map(fn (BookPage $page) => [
                'number' => $page->number,
                'sentences' => $page->sentences->sortBy('position')->values()->map(fn (BookSentence $sentence) => [
                    'ar' => $sentence->text_ar_md,
                    'bn' => $sentence->text_bn_md,
                    'block_type' => $sentence->block_type->value,
                    'paragraph_break' => $sentence->paragraph_break,
                ])->all(),
                'footnotes' => collect(['ar', 'bn'])->mapWithKeys(fn ($lang) => [
                    $lang => $page->footnotes->filter(fn (BookFootnote $footnote) => $footnote->lang->value === $lang)
                        ->map(fn (BookFootnote $footnote) => ['marker' => $footnote->marker, 'text' => $footnote->text_md])->values()->all(),
                ])->all(),
            ])->all(),
            'toc' => $this->exportToc($book->tocEntries),
        ];
    }

    /**
     * @param  Collection<int, TocEntry>  $entries
     * @return list<array<string, mixed>>
     */
    private function exportToc(Collection $entries): array
    {
        return $entries->map(fn (TocEntry $entry) => [
            'title_ar' => $entry->title_ar,
            'title_bn' => $entry->title_bn,
            'page' => $entry->page_number,
            ...($entry->relationLoaded('children') && $entry->children->isNotEmpty() ? ['children' => $this->exportToc($entry->children)] : []),
        ])->values()->all();
    }

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
