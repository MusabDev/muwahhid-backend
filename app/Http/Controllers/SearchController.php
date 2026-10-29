<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthorResource;
use App\Http\Resources\BookResource;
use App\Http\Resources\DarsSeriesResource;
use App\Models\Book;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private SearchService $search) {}

    public function global(Request $request): JsonResponse
    {
        $query = $request->validate(['q' => ['required', 'string', 'min:2', 'max:200']])['q'];
        $results = $this->search->global($query, $request->integer('limit', 20));

        return response()->json([
            'query' => $query,
            'books' => BookResource::collection($results['books']),
            'authors' => AuthorResource::collection($results['authors']),
            'dars_series' => DarsSeriesResource::collection($results['dars_series']),
            'sentences' => $results['sentences'],
            'segments' => $results['segments'],
        ]);
    }

    public function book(Request $request, Book $book): JsonResponse
    {
        BookController::ensureVisible($request, $book);
        $query = $request->validate(['q' => ['required', 'string', 'min:2', 'max:200']])['q'];
        $limit = min($request->integer('limit', 100), 500);

        return response()->json([
            'query' => $query,
            'sentences' => $this->search->sentences($query, $limit, $book),
            'footnotes' => $this->search->footnotes($query, $book, $limit),
        ]);
    }
}
