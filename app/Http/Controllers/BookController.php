<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookResource;
use App\Http\Resources\TocEntryResource;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $books = Book::published()
            ->with(['author', 'explainer', 'category'])
            ->when($request->filled('category'), fn (Builder $query) => $query->whereHas('category', fn (Builder $category) => $category->where('slug', $request->string('category'))))
            ->withCount('pages')
            ->when($request->filled('author'), fn (Builder $query) => $query->whereHas('author', fn (Builder $author) => $author->where('slug', $request->string('author'))))
            ->when($request->filled('q'), fn (Builder $query) => $query->matching((string) $request->string('q')))
            ->when($request->input('sort') === 'title', fn (Builder $query) => $query->orderBy('title_translit'), fn (Builder $query) => $query->latest())
            ->paginate($request->integer('per_page', 24));

        return BookResource::collection($books);
    }

    public function show(Request $request, Book $book): BookResource
    {
        $this->ensureVisible($request, $book);

        $book->load([
            'author',
            'explainer',
            'category',
            'tocEntries' => fn ($query) => $query->whereNull('parent_id')->with('children.children.children'),
            'darsSeries' => fn ($query) => $query->published()->with('author')->withCardStats(),
        ])->loadCount('pages');

        $book->setAttribute('first_page', $book->pages()->min('number'));
        $book->setAttribute('last_page', $book->pages()->max('number'));

        return new BookResource($book);
    }

    public function toc(Request $request, Book $book): AnonymousResourceCollection
    {
        $this->ensureVisible($request, $book);

        return TocEntryResource::collection(
            $book->tocEntries()->whereNull('parent_id')->with('children.children.children')->get()
        );
    }

    /**
     * Unpublished books are only visible to admins.
     */
    public static function ensureVisible(Request $request, Book $book): void
    {
        abort_unless($book->is_published || $request->user('sanctum')?->is_admin, 404);
    }
}
