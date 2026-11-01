<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ReadsImportFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Services\BookImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    use ReadsImportFile;

    public function index(Request $request): AnonymousResourceCollection
    {
        $books = Book::query()
            ->with(['author', 'explainer', 'category'])
            ->withCount('pages')
            ->when($request->filled('author_id'), fn ($query) => $query->where('author_id', $request->integer('author_id')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('is_published'), fn ($query) => $query->where('is_published', $request->boolean('is_published')))
            ->when($request->filled('q'), fn ($query) => $query->whereRaw(
                "(title_ar || ' ' || title_bn || ' ' || title_translit) ILIKE ?",
                ['%'.$request->string('q').'%'],
            ))
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return BookResource::collection($books);
    }

    public function store(BookRequest $request): BookResource
    {
        return new BookResource(Book::create($request->validated())->load(['author', 'explainer', 'category']));
    }

    public function show(Book $book): BookResource
    {
        $book->load(['author', 'explainer', 'category', 'tocEntries' => fn ($query) => $query->whereNull('parent_id')->with('children.children.children')])
            ->loadCount('pages');

        return new BookResource($book);
    }

    public function update(BookRequest $request, Book $book): BookResource
    {
        $book->fill($request->validated());
        $contentChanged = $book->isDirty(['title_ar', 'title_bn', 'author_id']);
        $book->save();

        if ($contentChanged) {
            $book->bumpContentVersion();
        }

        return new BookResource($book->load(['author', 'explainer', 'category']));
    }

    public function destroy(Book $book): Response
    {
        if ($book->cover_path) {
            Storage::disk('public')->delete($book->cover_path);
        }

        $book->exports->each(fn ($export) => $export->file_path && Storage::disk(config('reader.pdf.disk'))->delete($export->file_path));
        $book->delete();

        return response()->noContent();
    }

    public function uploadCover(Request $request, Book $book): BookResource
    {
        $request->validate(['cover' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);

        if ($book->cover_path) {
            Storage::disk('public')->delete($book->cover_path);
        }

        $book->update(['cover_path' => $request->file('cover')->store("covers/{$book->id}", 'public')]);

        return new BookResource($book);
    }

    public function deleteCover(Book $book): BookResource
    {
        if ($book->cover_path) {
            Storage::disk('public')->delete($book->cover_path);
            $book->update(['cover_path' => null]);
        }

        return new BookResource($book);
    }

    /**
     * Create a new book, with its content, from a full book file. With dry_run=1 nothing
     * is written and only a summary is returned.
     */
    public function importNew(Request $request, BookImporter $importer): JsonResponse
    {
        $data = $this->readImportFile($request, BookImporter::FORMAT, BookImporter::rules(withMeta: true));
        $result = $importer->createFromJson($data, $request->boolean('dry_run'));

        return response()->json([
            'book' => $result['book'] ? new BookResource($result['book']->load(['author', 'category'])) : null,
            'imported' => $result['imported'],
            'will_create' => $result['will_create'],
        ]);
    }

    /**
     * Bulk-import pages, sentences, footnotes and TOC into this book from a JSON or CSV
     * file (or a JSON body). A full book file works too; its "book" block is ignored.
     * With replace=true, existing content is removed first; with dry_run=1 nothing is written.
     */
    public function import(Request $request, Book $book, BookImporter $importer): JsonResponse
    {
        $request->validate(['replace' => ['sometimes', 'boolean'], 'dry_run' => ['sometimes', 'boolean']]);

        $replace = $request->boolean('replace');
        $file = $request->file('file');

        if ($file && strtolower($file->getClientOriginalExtension()) === 'csv') {
            $request->validate(['file' => ['file', 'max:51200']]);

            if ($request->boolean('dry_run')) {
                return response()->json(['imported' => null]);
            }

            return response()->json(['imported' => $importer->importCsv($book, $file->getRealPath(), $replace)]);
        }

        $data = $this->readImportFile($request, BookImporter::FORMAT, BookImporter::rules(withMeta: false));

        if ($request->boolean('dry_run')) {
            return response()->json(['imported' => BookImporter::count($data), 'existing_pages' => $book->pages()->count()]);
        }

        return response()->json(['imported' => $importer->importJson($book, $data, $replace)]);
    }

    /**
     * The book in the import file format.
     */
    public function export(Book $book, BookImporter $importer): JsonResponse
    {
        return response()->json($importer->export($book), options: JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Replace the table of contents with a nested list of entries.
     */
    public function updateToc(Request $request, Book $book, BookImporter $importer): JsonResponse
    {
        $request->validate([
            'toc' => ['present', 'array'],
            'toc.*.title_ar' => ['required', 'string', 'max:255'],
            'toc.*.title_bn' => ['required', 'string', 'max:255'],
            'toc.*.page_number' => ['required', 'integer', 'min:0'],
            'toc.*.sentence_id' => ['nullable', 'integer', 'exists:book_sentences,id'],
            'toc.*.children' => ['sometimes', 'array'],
        ]);

        return response()->json(['toc_entries' => $importer->replaceToc($book, $request->input('toc'))]);
    }
}
