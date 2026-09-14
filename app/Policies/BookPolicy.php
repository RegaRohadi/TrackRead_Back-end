<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function view(User $user, Book $book): bool
    {
        if ($book->source === Book::SOURCE_PLATFORM) {
            return true;
        }
        return (int) $book->user_id === (int) $user->id;
    }

    public function update(User $user, Book $book): bool
    {
        if ($book->source === Book::SOURCE_PLATFORM) {
            return false;
        }
        return (int) $book->user_id === (int) $user->id;
    }

    public function delete(User $user, Book $book): bool
    {
        if ($book->source === Book::SOURCE_PLATFORM) {
            return false;
        }
        return (int) $book->user_id === (int) $user->id;
    }

    public function viewFile(User $user, Book $book): bool
    {
        return $this->view($user, $book);
    }
}
