<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookPageResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookPageController extends Controller
{
    public function show(Request $request, Book $book, int $number): JsonResponse
    {
        BookController::ensureVisible($request, $book);

        $page = $book->pages()
            ->where('number', $number)
            ->with(['sentences', 'footnotes'])
            ->firstOrFail();

        return response()->json([
            ...(new BookPageResource($page))->resolve($request),
            'book_id' => $book->id,
            'content_version' => $book->content_version,
            'prev_page' => $book->pages()->where('number', '<', $number)->max('number'),
            'next_page' => $book->pages()->where('number', '>', $number)->min('number'),
        ]);
    }
}
