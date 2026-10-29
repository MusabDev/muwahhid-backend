<?php

namespace App\Http\Controllers\Me;

use App\Enums\BookStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserBookResource;
use App\Models\Book;
use App\Models\UserBook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class LibraryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::enum(BookStatus::class)]]);

        $entries = $request->user()->library()
            ->with('book.author')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('last_read_at')
            ->orderByDesc('updated_at')
            ->get();

        return UserBookResource::collection($entries);
    }

    public function show(Request $request, Book $book): UserBookResource
    {
        return new UserBookResource($this->entry($request, $book));
    }

    /**
     * Set the book's library status (null removes the status but keeps progress).
     */
    public function updateStatus(Request $request, Book $book): UserBookResource
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(BookStatus::class)]]);

        $entry = $this->entry($request, $book);
        $entry->status = $data['status'] ?? null;
        $entry->save();

        return new UserBookResource($entry);
    }

    /**
     * Save reading progress; called automatically by the reader.
     */
    public function updateProgress(Request $request, Book $book): UserBookResource
    {
        $data = $request->validate([
            'last_page' => ['required', 'integer', 'min:0'],
            'last_sentence_id' => ['nullable', 'integer', Rule::exists('book_sentences', 'id')->where('book_id', $book->id)],
            'progress' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $entry = $this->entry($request, $book);
        $entry->fill([
            'last_page' => $data['last_page'],
            'last_sentence_id' => $data['last_sentence_id'] ?? null,
            'progress' => $data['progress'] ?? $this->progressFor($book, $data['last_page']),
            'last_read_at' => now(),
        ]);

        if ($entry->status === null) {
            $entry->status = BookStatus::Reading;
        }

        $entry->save();

        return new UserBookResource($entry);
    }

    public function destroy(Request $request, Book $book): Response
    {
        $request->user()->library()->where('book_id', $book->id)->delete();

        return response()->noContent();
    }

    private function entry(Request $request, Book $book): UserBook
    {
        return UserBook::firstOrNew(['user_id' => $request->user()->id, 'book_id' => $book->id]);
    }

    public static function progressFor(Book $book, int $page): float
    {
        $total = $book->pages()->count();

        if ($total === 0) {
            return 0;
        }

        $position = $book->pages()->where('number', '<=', $page)->count();

        return round($position / $total * 100, 2);
    }
}
