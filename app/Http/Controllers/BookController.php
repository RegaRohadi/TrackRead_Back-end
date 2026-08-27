<?php

namespace App\Http\Controllers;


use App\Services\BookService;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use Illuminate\Http\Request;


class BookController extends Controller
{
    public function __construct(
        protected BookService $bookService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'genre' => ['nullable', 'string'],
        ]);

        $books = $this->bookService->getBooks(
            $validated['per_page'] ?? 10,
            ['genre' => $validated['genre'] ?? null]
        );

        return BookResource::collection($books)
            ->additional(['message' => 'Books fetched successfully']);
    }

    public function genres()
    {
        return response()->json([
            'data' => $this->bookService->getGenres(),
            'message' => 'Genres fetched successfully',
        ]);
    }

    public function store(StoreBookRequest $request)
    {
        $book = $this->bookService->createBook(
            $request->validated()
        );

        return (new BookResource($book))
            ->additional(['message' => 'Book created successfully']);
    }

    public function update(UpdateBookRequest $request, int $id)
    {
        $book = $this->bookService->updateBook(
            $id,
            $request->validated()
        );

        return (new BookResource($book))
            ->additional(['message' => 'Book updated successfully']);
    }

    public function delete(int $id)
    {
        $this->bookService->deleteBook($id);

        return response()->json([
            'message' => 'Book deleted successfully'
        ]);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'genre' => ['nullable', 'string'],
        ]);

        $books = $this->bookService->searchBooks(
            $validated['q'],
            $validated['per_page'] ?? 10,
            ['genre' => $validated['genre'] ?? null]
        );

        return BookResource::collection($books)
            ->additional(['message' => 'Books searched successfully']);
    }
}
