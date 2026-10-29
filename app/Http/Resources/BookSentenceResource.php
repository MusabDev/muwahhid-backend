<?php

namespace App\Http\Resources;

use App\Models\BookSentence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookSentence
 */
class BookSentenceResource extends JsonResource
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
            'page_id' => $this->page_id,
            'position' => $this->position,
            'block_type' => $this->block_type,
            'paragraph_break' => $this->paragraph_break,
            'text_ar_md' => $this->text_ar_md,
            'text_bn_md' => $this->text_bn_md,
            'text_ar_plain' => $this->text_ar_plain,
            'text_bn_plain' => $this->text_bn_plain,
        ];
    }
}
