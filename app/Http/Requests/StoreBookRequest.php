<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'release_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'isbn' => ['nullable', 'string', 'unique:books,isbn'],
            'genre' => ['nullable', 'string', 'max:100'],
            'pages' => ['nullable', 'integer', 'min:1'],
            'cover' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048'
            ],
            'pages_read' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
