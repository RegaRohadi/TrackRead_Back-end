<?php

namespace App\Repositories\Eloquent;

use App\Models\Book;
use App\Repositories\Interfaces\BookRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class BookRepository implements BookRepositoryInterface
{
    public function all(): Collection
    {
        return Book::query()
            ->orderByDesc('created_at')
            ->get();
    }

    public function paginate(int $perPage = 10, array $filters = [])
    {
        $query = Book::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at');

        if (!empty($filters['genre'])) {
            $query->where('genre', $filters['genre']);
        }

        return $query->paginate($perPage);
    }

    public function genres(): array
    {
        return Book::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('genre')
            ->where('genre', '!=', '')
            ->distinct()
            ->orderBy('genre')
            ->pluck('genre')
            ->toArray();
    }

    public function find(int $id): ?Book
    {
        return Book::find($id);
    }

    public function create(array $data): Book
    {
        $data['user_id'] = Auth::id();

        if (request()->hasFile('cover')) {
            $coverPath = request()->file('cover')->store('BookCovers', 'public');
            $data['cover'] = $coverPath;
        }
        return Book::create($data);
    }

    public function update(int $id, array $data): Book
    {
        $book = Book::where('user_id', Auth::id())->findOrFail($id);

        if (request()->hasFile('cover')) {
            $coverPath = request()->file('cover')->store('BookCovers', 'public');
            $data['cover'] = $coverPath;
        }

        $book->update($data);

        return $book->fresh();
    }

    public function delete(int $id): bool
    {
        $book = Book::where('user_id', Auth::id())->findOrFail($id);

        return $book->delete();
    }

    public function search(string $keyword, int $perPage = 10, array $filters = [])
    {
        $query = Book::query()
            ->where('user_id', Auth::id())
            ->when(!empty($filters['genre']), function ($query) use ($filters) {
                $query->where('genre', $filters['genre']);
            })
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%")
                    ->orWhere('isbn', 'like', "%{$keyword}%")
                    ->orWhere('genre', 'like', "%{$keyword}%");
            })
            ->orderByDesc('created_at');

        return $query->paginate($perPage);
    }
}
