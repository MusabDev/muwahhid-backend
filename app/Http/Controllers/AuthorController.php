<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthorResource;
use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuthorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $authors = Author::query()
            ->withCount([
                'books' => fn ($query) => $query->published(),
                'explainedBooks' => fn ($query) => $query->published(),
                'darsSeries' => fn ($query) => $query->published(),
            ])
            ->orderBy('name_translit')
            ->paginate($request->integer('per_page', 30));

        return AuthorResource::collection($authors);
    }

    public function show(Author $author): AuthorResource
    {
        $author->load([
            'books' => fn ($query) => $query->published()->with(['explainer', 'category'])->withCount('pages')->orderBy('title_translit'),
            'explainedBooks' => fn ($query) => $query->published()->with(['author', 'category'])->withCount('pages')->orderBy('title_translit'),
            'darsSeries' => fn ($query) => $query->published()->withCardStats()->orderBy('title_translit'),
        ]);

        return new AuthorResource($author);
    }
}
