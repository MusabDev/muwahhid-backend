<?php

namespace App\Http\Resources;

use App\Models\Bookmark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bookmark
 */
class BookmarkResource extends JsonResource
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
            'book_id' => $this->book_id,
            'page_number' => $this->page_number,
            'sentence_id' => $this->sentence_id,
            'label' => $this->label,
            'created_at' => $this->created_at,
            'book' => new BookResource($this->whenLoaded('book')),
        ];
    }
}
