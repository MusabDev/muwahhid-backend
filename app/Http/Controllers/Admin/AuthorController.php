<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuthorRequest;
use App\Http\Resources\AuthorResource;
use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AuthorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $authors = Author::query()
            ->withCount(['books', 'explainedBooks', 'darsSeries'])
            ->when($request->filled('q'), fn ($query) => $query->whereRaw(
                "(name_ar || ' ' || name_bn || ' ' || name_translit) ILIKE ?",
                ['%'.$request->string('q').'%'],
            ))
            ->orderBy('name_translit')
            ->paginate($request->integer('per_page', 50));

        return AuthorResource::collection($authors);
    }

    public function store(AuthorRequest $request): AuthorResource
    {
        return new AuthorResource(Author::create($request->validated()));
    }

    public function show(Author $author): AuthorResource
    {
        return new AuthorResource($author->load(['books', 'darsSeries'])->loadCount(['books', 'darsSeries']));
    }

    public function update(AuthorRequest $request, Author $author): AuthorResource
    {
        $author->update($request->validated());

        return new AuthorResource($author);
    }

    public function destroy(Author $author): Response
    {
        abort_if($author->books()->exists() || $author->explainedBooks()->exists() || $author->darsSeries()->exists(), 409, 'Delete or reassign this author\'s books, sharh books and dars series first.');

        $author->delete();

        return response()->noContent();
    }
}
