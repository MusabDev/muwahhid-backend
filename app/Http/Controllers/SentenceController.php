<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookSentenceResource;
use App\Models\BookSentence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SentenceController extends Controller
{
    /**
     * Resolve a sentence id to its book and page, for sentence links and shared quotes.
     */
    public function show(Request $request, BookSentence $sentence): JsonResponse
    {
        $sentence->load('book', 'page');
        BookController::ensureVisible($request, $sentence->book);

        return response()->json([
            'sentence' => new BookSentenceResource($sentence),
            'book_slug' => $sentence->book->slug,
            'book_title_ar' => $sentence->book->title_ar,
            'book_title_bn' => $sentence->book->title_bn,
            'page_number' => $sentence->page->number,
        ]);
    }
}
