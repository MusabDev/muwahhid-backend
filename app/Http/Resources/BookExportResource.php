<?php

namespace App\Http\Resources;

use App\Enums\ExportStatus;
use App\Models\BookExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookExport
 */
class BookExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'variant' => $this->variant,
            'version' => $this->version,
            'font_ar' => $this->font_ar,
            'font_bn' => $this->font_bn,
            'status' => $this->status,
            'error' => $this->error,
            'download_url' => $this->status === ExportStatus::Done ? route('books.exports.download', [$this->book_id, $this->id]) : null,
            'updated_at' => $this->updated_at,
        ];
    }
}
