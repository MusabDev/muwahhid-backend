<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Models\BookExport;
use App\Services\BookPdfRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateBookExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(public BookExport $export) {}

    /**
     * Execute the job.
     */
    public function handle(BookPdfRenderer $renderer): void
    {
        $export = $this->export->load('book');
        $export->update(['status' => ExportStatus::Processing, 'error' => null]);

        $pdf = $renderer->render($export->book, $export->variant, $export->font_ar, $export->font_bn);

        $path = sprintf(
            'exports/%d/%s-v%d-%s-%s.pdf',
            $export->book_id,
            $export->variant->value,
            $export->version,
            $export->font_ar,
            $export->font_bn,
        );

        $disk = Storage::disk(config('reader.pdf.disk'));
        $disk->put($path, $pdf);

        $export->update(['status' => ExportStatus::Done, 'file_path' => $path]);

        $this->deleteOutdatedExports($export);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->export->update([
            'status' => ExportStatus::Failed,
            'error' => $exception ? mb_substr($exception->getMessage(), 0, 1000) : 'Unknown error',
        ]);
    }

    /**
     * Remove PDFs built from older content versions of the book.
     */
    private function deleteOutdatedExports(BookExport $export): void
    {
        $disk = Storage::disk(config('reader.pdf.disk'));

        BookExport::where('book_id', $export->book_id)
            ->where('version', '<', $export->version)
            ->get()
            ->each(function (BookExport $old) use ($disk) {
                if ($old->file_path) {
                    $disk->delete($old->file_path);
                }
                $old->delete();
            });
    }
}
