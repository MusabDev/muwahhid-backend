<?php

namespace App\Http\Resources;

use App\Enums\Lang;
use App\Models\BookPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookPage
 */
class BookPageResource extends JsonResource
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
            'number' => $this->number,
            'book_id' => $this->book_id,
            'sentences_count' => $this->whenCounted('sentences'),
            'sentences' => BookSentenceResource::collection($this->whenLoaded('sentences')),
            'footnotes' => $this->whenLoaded('footnotes', fn () => [
                'ar' => BookFootnoteResource::collection($this->footnotes->where('lang', Lang::Arabic)->values()),
                'bn' => BookFootnoteResource::collection($this->footnotes->where('lang', Lang::Bangla)->values()),
            ]),
        ];
    }
}
