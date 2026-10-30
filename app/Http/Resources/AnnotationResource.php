<?php

namespace App\Http\Resources;

use App\Models\Annotation;
use App\Models\BookSentence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Annotation
 */
class AnnotationResource extends JsonResource
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
            'target_type' => $this->annotatable_type,
            'target_id' => $this->annotatable_id,
            'book_id' => $this->book_id,
            'dars_id' => $this->dars_id,
            'lang' => $this->lang,
            'start_offset' => $this->start_offset,
            'end_offset' => $this->end_offset,
            'quote' => $this->quote,
            'color' => $this->color,
            'note' => $this->note,
            'group_id' => $this->group_id,
            'page_number' => $this->when(
                $this->relationLoaded('annotatable'),
                fn () => $this->annotatable instanceof BookSentence ? $this->annotatable->page?->number : null,
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'book' => new BookResource($this->whenLoaded('book')),
        ];
    }
}
