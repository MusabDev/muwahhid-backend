<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BlockType;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookSentenceResource;
use App\Models\BookPage;
use App\Models\BookSentence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookSentenceController extends Controller
{
    /**
     * Add a sentence to the page. When "position" is given, later sentences shift down.
     */
    public function store(Request $request, BookPage $page): BookSentenceResource
    {
        $data = $request->validate([
            ...$this->rules('required'),
            'position' => ['nullable', 'integer', 'min:1'],
        ]);

        $sentence = DB::transaction(function () use ($page, $data) {
            $last = (int) $page->sentences()->max('position');
            $position = min($data['position'] ?? $last + 1, $last + 1);

            BookSentence::where('page_id', $page->id)->where('position', '>=', $position)->increment('position');

            return BookSentence::create([
                'block_type' => BlockType::Paragraph,
                'paragraph_break' => false,
                ...$data,
                'book_id' => $page->book_id,
                'page_id' => $page->id,
                'position' => $position,
            ]);
        });

        $page->book->bumpContentVersion();

        return new BookSentenceResource($sentence);
    }

    public function update(Request $request, BookSentence $sentence): BookSentenceResource
    {
        $sentence->update($request->validate($this->rules('sometimes')));
        $sentence->book->bumpContentVersion();

        return new BookSentenceResource($sentence);
    }

    public function destroy(BookSentence $sentence): Response
    {
        DB::transaction(function () use ($sentence) {
            $sentence->annotations()->delete();
            $sentence->delete();
            BookSentence::where('page_id', $sentence->page_id)->where('position', '>', $sentence->position)->decrement('position');
        });

        $sentence->book->bumpContentVersion();

        return response()->noContent();
    }

    /**
     * Set the order of the page's sentences from a list of ids.
     */
    public function reorder(Request $request, BookPage $page): AnonymousResourceCollection
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', Rule::exists('book_sentences', 'id')->where('page_id', $page->id)],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['ids']) as $index => $id) {
                BookSentence::whereKey($id)->update(['position' => $index + 1]);
            }
        });

        $page->book->bumpContentVersion();

        return BookSentenceResource::collection($page->sentences()->get());
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(string $required): array
    {
        return [
            'text_ar_md' => [$required, 'string'],
            'text_bn_md' => [$required, 'string'],
            'block_type' => ['sometimes', Rule::enum(BlockType::class)],
            'paragraph_break' => ['sometimes', 'boolean'],
        ];
    }
}
