<?php

namespace App\Http\Controllers\Me;

use App\Enums\Lang;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnnotationResource;
use App\Models\Annotation;
use App\Models\BookSentence;
use App\Models\DarsSegment;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnnotationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'book_id' => ['nullable', 'integer'],
            'dars_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer'],
            'has_note' => ['nullable', 'boolean'],
        ]);

        $annotations = $request->user()->annotations()
            ->when($request->filled('book_id'), fn ($query) => $query->where('book_id', $request->integer('book_id')))
            ->when($request->filled('dars_id'), fn ($query) => $query->where('dars_id', $request->integer('dars_id')))
            ->when($request->filled('page') && $request->filled('book_id'), fn ($query) => $query
                ->where('annotatable_type', 'book_sentence')
                ->whereIn('annotatable_id', BookSentence::query()
                    ->select('book_sentences.id')
                    ->join('book_pages', 'book_pages.id', '=', 'book_sentences.page_id')
                    ->where('book_sentences.book_id', $request->integer('book_id'))
                    ->where('book_pages.number', $request->integer('page'))))
            // Library-wide notes carry their book so they can be listed by kitab
            ->when($request->boolean('has_note'), fn ($query) => $query
                ->whereNotNull('note')
                ->where('note', '!=', '')
                ->with(['book.author', 'book.explainer']))
            ->with(self::withPage())
            ->latest()
            ->get();

        return AnnotationResource::collection($annotations);
    }

    public function store(Request $request): AnnotationResource
    {
        $data = $request->validate(self::rules());

        return new AnnotationResource($request->user()->annotations()->create(self::resolve($data))->load(self::withPage()));
    }

    public function update(Request $request, Annotation $annotation): AnnotationResource
    {
        abort_unless($annotation->user_id === $request->user()->id, 404);

        $annotation->update($request->validate([
            'color' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:10000'],
        ]));

        return new AnnotationResource($annotation->load(self::withPage()));
    }

    public function destroy(Request $request, Annotation $annotation): Response
    {
        abort_unless($annotation->user_id === $request->user()->id, 404);

        $annotation->delete();

        return response()->noContent();
    }

    /**
     * The annotated text, with the page a book sentence sits on.
     *
     * @return array<string, mixed>
     */
    private static function withPage(): array
    {
        return ['annotatable' => fn (MorphTo $morph) => $morph->morphWith([BookSentence::class => ['page']])];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in(['book_sentence', 'dars_segment'])],
            'target_id' => ['required', 'integer'],
            'lang' => ['required', Rule::enum(Lang::class)],
            'start_offset' => ['required', 'integer', 'min:0'],
            'end_offset' => ['required', 'integer', 'gt:start_offset'],
            'quote' => ['required', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:10000'],
            'group_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * Look up the annotated sentence/segment and fill in its book or dars.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function resolve(array $data): array
    {
        $target = $data['target_type'] === 'book_sentence'
            ? BookSentence::find($data['target_id'])
            : DarsSegment::find($data['target_id']);

        if (! $target) {
            throw ValidationException::withMessages(['target_id' => 'The annotated text no longer exists.']);
        }

        return [
            'annotatable_type' => $data['target_type'],
            'annotatable_id' => $target->id,
            'book_id' => $target instanceof BookSentence ? $target->book_id : null,
            'dars_id' => $target instanceof DarsSegment ? $target->dars_id : null,
            'lang' => $data['lang'],
            'start_offset' => $data['start_offset'],
            'end_offset' => $data['end_offset'],
            'quote' => $data['quote'],
            'color' => $data['color'] ?? null,
            'note' => $data['note'] ?? null,
            'group_id' => $data['group_id'] ?? null,
        ];
    }
}
