<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookmarkResource;
use App\Models\Bookmark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BookmarkController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $bookmarks = $request->user()->bookmarks()
            ->with(['book.author', 'book.explainer'])
            ->when($request->filled('book_id'), fn ($query) => $query->where('book_id', $request->integer('book_id')))
            ->orderBy('book_id')
            ->orderBy('page_number')
            ->get();

        return BookmarkResource::collection($bookmarks);
    }

    public function store(Request $request): BookmarkResource
    {
        $data = $request->validate(self::rules());

        return new BookmarkResource($request->user()->bookmarks()->create($data));
    }

    public function update(Request $request, Bookmark $bookmark): BookmarkResource
    {
        abort_unless($bookmark->user_id === $request->user()->id, 404);

        $bookmark->update($request->validate(['label' => ['nullable', 'string', 'max:255']]));

        return new BookmarkResource($bookmark);
    }

    public function destroy(Request $request, Bookmark $bookmark): Response
    {
        abort_unless($bookmark->user_id === $request->user()->id, 404);

        $bookmark->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'page_number' => ['required', 'integer', 'min:0'],
            'sentence_id' => ['nullable', 'integer', 'exists:book_sentences,id'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
