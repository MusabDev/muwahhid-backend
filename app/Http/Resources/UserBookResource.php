<?php

namespace App\Http\Resources;

use App\Models\UserBook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserBook
 */
class UserBookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'book_id' => $this->book_id,
            'status' => $this->status,
            'last_page' => $this->last_page,
            'last_sentence_id' => $this->last_sentence_id,
            'progress' => $this->progress,
            'last_read_at' => $this->last_read_at,
            'updated_at' => $this->updated_at,
            'book' => new BookResource($this->whenLoaded('book')),
        ];
    }
}
