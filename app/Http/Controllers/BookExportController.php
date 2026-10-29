<?php

namespace App\Http\Controllers;

use App\Enums\ExportStatus;
use App\Enums\ExportVariant;
use App\Http\Resources\BookExportResource;
use App\Jobs\GenerateBookExport;
use App\Models\Book;
use App\Models\BookExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookExportController extends Controller
{
    /**
     * Return the export for the book's current version, queueing generation when needed.
     * The client polls this until status is "done", then follows download_url.
     */
    public function store(Request $request, Book $book): BookExportResource
    {
        BookController::ensureVisible($request, $book);

        $data = $request->validate([
            'variant' => ['required', Rule::enum(ExportVariant::class)],
            'font_ar' => ['nullable', Rule::in(array_keys(config('reader.fonts.ar')))],
            'font_bn' => ['nullable', Rule::in(array_keys(config('reader.fonts.bn')))],
        ]);

        $export = BookExport::firstOrCreate([
            'book_id' => $book->id,
            'variant' => $data['variant'],
            'version' => $book->content_version,
            'font_ar' => $data['font_ar'] ?? config('reader.default_fonts.ar'),
            'font_bn' => $data['font_bn'] ?? config('reader.default_fonts.bn'),
        ], ['status' => ExportStatus::Pending]);

        $isStale = $export->status === ExportStatus::Processing && $export->updated_at->lt(now()->subMinutes(15));

        if ($export->wasRecentlyCreated || $export->status === ExportStatus::Failed || $isStale) {
            $export->update(['status' => ExportStatus::Pending, 'error' => null]);
            GenerateBookExport::dispatch($export);
        }

        return new BookExportResource($export);
    }

    public function download(Request $request, Book $book, BookExport $export): StreamedResponse
    {
        BookController::ensureVisible($request, $book);
        abort_unless($export->book_id === $book->id && $export->status === ExportStatus::Done, 404);

        $suffix = match ($export->variant) {
            ExportVariant::Arabic => 'arabic',
            ExportVariant::Bangla => 'bangla',
            ExportVariant::Bilingual => 'bilingual',
        };

        return Storage::disk(config('reader.pdf.disk'))->download(
            $export->file_path,
            Str::slug($book->title_translit ?: $book->slug).'-'.$suffix.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
