<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Lang;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookFootnoteResource;
use App\Models\BookFootnote;
use App\Models\BookPage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BookFootnoteController extends Controller
{
    public function store(Request $request, BookPage $page): BookFootnoteResource
    {
        $data = $request->validate([
            'lang' => ['required', Rule::enum(Lang::class)],
            'marker' => ['required', 'string', 'max:32', Rule::unique('book_footnotes')->where('page_id', $page->id)->where('lang', $request->input('lang'))],
            'text_md' => ['required', 'string'],
        ]);

        $footnote = BookFootnote::create([...$data, 'page_id' => $page->id, 'book_id' => $page->book_id]);
        $page->book->bumpContentVersion();

        return new BookFootnoteResource($footnote);
    }

    public function update(Request $request, BookFootnote $footnote): BookFootnoteResource
    {
        $footnote->update($request->validate([
            'marker' => ['sometimes', 'string', 'max:32', Rule::unique('book_footnotes')->where('page_id', $footnote->page_id)->where('lang', $footnote->lang->value)->ignore($footnote)],
            'text_md' => ['sometimes', 'string'],
        ]));
        $footnote->page->book->bumpContentVersion();

        return new BookFootnoteResource($footnote);
    }

    public function destroy(BookFootnote $footnote): Response
    {
        $footnote->delete();
        $footnote->page->book->bumpContentVersion();

        return response()->noContent();
    }
}
