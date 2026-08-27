<?php

namespace App\Services;

use App\Repositories\Interfaces\BookRepositoryInterface;

class BookService
{
    public function __construct(
        protected BookRepositoryInterface $bookRepository
    ) {}

    public function getAllBooks()
    {
        return $this->bookRepository->all();
    }

    public function getBooks(int $perPage = 10, array $filters = [])
    {
        return $this->bookRepository->paginate($perPage, $filters);
    }

    public function getGenres(): array
    {
        return $this->bookRepository->genres();
    }

    public function findBook(int $id)
    {
        return $this->bookRepository->find($id);
    }

    public function createBook(array $data)
    {
        return $this->bookRepository->create($data);
    }

    public function updateBook(int $id, array $data)
    {
        return $this->bookRepository->update($id, $data);
    }

    public function deleteBook(int $id)
    {
        return $this->bookRepository->delete($id);
    }

    public function searchBooks(string $keyword, int $perPage = 10, array $filters = [])
    {
        return $this->bookRepository->search($keyword, $perPage, $filters);
    }
}
