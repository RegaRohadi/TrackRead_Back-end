<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'release_date' => $this->release_date,
            'description' => $this->description,
            'isbn' => $this->isbn,
            'genre' => $this->genre,
            'pages' => $this->pages,
            'cover' => $this->cover,
            'pages_read' => $this->pages_read,
        ];
    }
}
