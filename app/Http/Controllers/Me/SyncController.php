<?php

namespace App\Http\Controllers\Me;

use App\Enums\BookStatus;
use App\Http\Controllers\Controller;
use App\Models\Annotation;
use App\Models\Book;
use App\Models\Bookmark;
use App\Models\UserBook;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SyncController extends Controller
{
    /**
     * Merge a guest's localStorage data (library, annotations, bookmarks, settings)
     * into the account after login. Existing account data wins on conflicts,
     * except reading progress, where the most recent read wins.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'library' => ['sometimes', 'array', 'max:2000'],
            'library.*.book_id' => ['required', 'integer'],
            'library.*.status' => ['nullable', Rule::enum(BookStatus::class)],
            'library.*.last_page' => ['nullable', 'integer', 'min:0'],
            'library.*.last_sentence_id' => ['nullable', 'integer'],
            'library.*.progress' => ['nullable', 'numeric', 'between:0,100'],
            'library.*.last_read_at' => ['nullable', 'date'],
            'annotations' => ['sometimes', 'array', 'max:5000'],
            'bookmarks' => ['sometimes', 'array', 'max:2000'],
            'bookmarks.*.book_id' => ['required', 'integer'],
            'bookmarks.*.page_number' => ['required', 'integer', 'min:0'],
            'bookmarks.*.sentence_id' => ['nullable', 'integer'],
            'bookmarks.*.label' => ['nullable', 'string', 'max:255'],
            'settings' => ['sometimes', 'array'],
        ]);

        $user = $request->user();
        $counts = ['library' => 0, 'annotations' => 0, 'bookmarks' => 0, 'skipped' => 0];

        DB::transaction(function () use ($request, $user, &$counts) {
            $bookIds = Book::whereIn('id', collect($request->input('library', []))->pluck('book_id')
                ->merge(collect($request->input('bookmarks', []))->pluck('book_id')))
                ->pluck('id')
                ->flip();

            foreach ($request->input('library', []) as $item) {
                if (! isset($bookIds[$item['book_id']])) {
                    $counts['skipped']++;

                    continue;
                }

                $entry = UserBook::firstOrNew(['user_id' => $user->id, 'book_id' => $item['book_id']]);
                $guestReadAt = isset($item['last_read_at']) ? Carbon::parse($item['last_read_at']) : null;

                $entry->status ??= isset($item['status']) ? BookStatus::from($item['status']) : null;

                if ($guestReadAt && (! $entry->last_read_at || $guestReadAt->gt($entry->last_read_at))) {
                    $entry->fill([
                        'last_page' => $item['last_page'] ?? $entry->last_page,
                        'last_sentence_id' => $item['last_sentence_id'] ?? null,
                        'progress' => $item['progress'] ?? $entry->progress ?? 0,
                        'last_read_at' => $guestReadAt,
                    ]);
                }

                if ($entry->last_sentence_id && ! DB::table('book_sentences')->where('id', $entry->last_sentence_id)->exists()) {
                    $entry->last_sentence_id = null;
                }

                $entry->save();
                $counts['library']++;
            }

            foreach ($request->input('annotations', []) as $item) {
                $validator = Validator::make((array) $item, AnnotationController::rules());

                if ($validator->fails()) {
                    $counts['skipped']++;

                    continue;
                }

                try {
                    $attributes = AnnotationController::resolve($validator->validated());
                } catch (ValidationException) {
                    $counts['skipped']++;

                    continue;
                }

                $annotation = Annotation::firstOrNew([
                    'user_id' => $user->id,
                    'annotatable_type' => $attributes['annotatable_type'],
                    'annotatable_id' => $attributes['annotatable_id'],
                    'lang' => $attributes['lang'],
                    'start_offset' => $attributes['start_offset'],
                    'end_offset' => $attributes['end_offset'],
                ]);

                if (! $annotation->exists) {
                    $annotation->fill($attributes)->save();
                    $counts['annotations']++;
                }
            }

            foreach ($request->input('bookmarks', []) as $item) {
                if (! isset($bookIds[$item['book_id']])) {
                    $counts['skipped']++;

                    continue;
                }

                $sentenceId = isset($item['sentence_id']) && DB::table('book_sentences')->where('id', $item['sentence_id'])->exists()
                    ? $item['sentence_id']
                    : null;

                $bookmark = Bookmark::firstOrCreate(
                    ['user_id' => $user->id, 'book_id' => $item['book_id'], 'page_number' => $item['page_number'], 'sentence_id' => $sentenceId],
                    ['label' => $item['label'] ?? null],
                );

                if ($bookmark->wasRecentlyCreated) {
                    $counts['bookmarks']++;
                }
            }

            if ($request->filled('settings')) {
                $setting = UserSetting::firstOrNew(['user_id' => $user->id]);
                $setting->settings = array_replace_recursive($request->input('settings'), $setting->settings ?? []);
                $setting->save();
            }
        });

        return response()->json(['merged' => $counts]);
    }
}
