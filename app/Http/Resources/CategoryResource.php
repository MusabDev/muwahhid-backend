<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
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
            'slug' => $this->slug,
            'name_bn' => $this->name_bn,
            'name_ar' => $this->name_ar,
            'position' => $this->position,
            'books_count' => $this->whenCounted('books'),
            'dars_series_count' => $this->whenCounted('darsSeries'),
        ];
    }
}
