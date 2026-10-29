<?php

namespace App\Http\Controllers\Admin;

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
use Illuminate\Validation\ValidationException;

class BookController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $books = Book::query()
            ->with(['author', 'explainer', 'category'])
            ->withCount('pages')
            ->when($request->filled('author_id'), fn ($query) => $query->where('author_id', $request->integer('author_id')))
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
     * Bulk-import pages, sentences, footnotes and TOC from a JSON or CSV file
     * (or a JSON body). With replace=true, existing content is removed first.
     */
    public function import(Request $request, Book $book, BookImporter $importer): JsonResponse
    {
        $request->validate([
            'file' => ['required_without:pages', 'file', 'mimes:json,csv,txt', 'max:51200'],
            'pages' => ['required_without:file', 'array'],
            'replace' => ['sometimes', 'boolean'],
        ]);

        $replace = $request->boolean('replace');

        if (! $request->hasFile('file')) {
            return response()->json(['imported' => $importer->importJson($book, $request->only(['pages', 'toc']), $replace)]);
        }

        $file = $request->file('file');

        if (strtolower($file->getClientOriginalExtension()) === 'csv') {
            return response()->json(['imported' => $importer->importCsv($book, $file->getRealPath(), $replace)]);
        }

        $data = json_decode(file_get_contents($file->getRealPath()), true);

        if (! is_array($data)) {
            throw ValidationException::withMessages(['file' => 'The file is not valid JSON.']);
        }

        return response()->json(['imported' => $importer->importJson($book, $data, $replace)]);
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
