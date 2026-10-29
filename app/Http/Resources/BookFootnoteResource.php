<?php

namespace App\Http\Resources;

use App\Models\BookFootnote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookFootnote
 */
class BookFootnoteResource extends JsonResource
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
            'lang' => $this->lang,
            'marker' => $this->marker,
            'text_md' => $this->text_md,
        ];
    }
}
