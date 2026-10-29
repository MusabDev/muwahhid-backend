<?php

namespace App\Http\Resources;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Book
 */
class BookResource extends JsonResource
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
            'title_ar' => $this->title_ar,
            'title_bn' => $this->title_bn,
            'title_translit' => $this->title_translit,
            'about_md' => $this->about_md,
            'cover_url' => $this->coverUrl(),
            'content_version' => $this->content_version,
            'is_published' => $this->is_published,
            'pages_count' => $this->whenCounted('pages'),
            'first_page' => $this->whenHas('first_page'),
            'last_page' => $this->whenHas('last_page'),
            'author' => new AuthorResource($this->whenLoaded('author')),
            'toc' => TocEntryResource::collection($this->whenLoaded('tocEntries')),
            'dars_series' => DarsSeriesResource::collection($this->whenLoaded('darsSeries')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
