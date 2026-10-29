<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookPageResource;
use App\Models\Annotation;
use App\Models\Book;
use App\Models\BookPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BookPageController extends Controller
{
    public function index(Book $book): AnonymousResourceCollection
    {
        return BookPageResource::collection($book->pages()->withCount('sentences')->get());
    }

    public function store(Request $request, Book $book): BookPageResource
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:0', Rule::unique('book_pages')->where('book_id', $book->id)],
        ]);

        $page = $book->pages()->create($data);
        $book->bumpContentVersion();

        return new BookPageResource($page->load(['sentences', 'footnotes']));
    }

    public function show(BookPage $page): BookPageResource
    {
        return new BookPageResource($page->load(['sentences', 'footnotes']));
    }

    public function update(Request $request, BookPage $page): BookPageResource
    {
        $page->update($request->validate([
            'number' => ['required', 'integer', 'min:0', Rule::unique('book_pages')->where('book_id', $page->book_id)->ignore($page)],
        ]));
        $page->book->bumpContentVersion();

        return new BookPageResource($page->load(['sentences', 'footnotes']));
    }

    public function destroy(BookPage $page): Response
    {
        Annotation::where('annotatable_type', 'book_sentence')
            ->whereIn('annotatable_id', $page->sentences()->select('id'))
            ->delete();
        $page->delete();
        $page->book->bumpContentVersion();

        return response()->noContent();
    }
}
