<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]);

        $books = [
            ['name' => 'The Silent Patient', 'author' => 'Alex Michaelides', 'publisher' => 'Celadon Books', 'release_date' => '2019-02-05', 'description' => 'A psychological thriller.', 'genre' => 'Mystery', 'pages' => 336],
            ['name' => 'Pride and Prejudice', 'author' => 'Jane Austen', 'publisher' => 'T. Egerton', 'release_date' => '1813-01-28', 'description' => 'Timeless romance.', 'genre' => 'Romance', 'pages' => 432],
            ['name' => 'The Hobbit', 'author' => 'J.R.R. Tolkien', 'publisher' => 'George Allen & Unwin', 'release_date' => '1937-09-21', 'description' => 'Fantasy adventure.', 'genre' => 'Fantasy', 'pages' => 310],
            ['name' => 'Dracula', 'author' => 'Bram Stoker', 'publisher' => 'Archibald Constable', 'release_date' => '1897-05-26', 'description' => 'Gothic horror.', 'genre' => 'Horror', 'pages' => 418],
            ['name' => 'Dune', 'author' => 'Frank Herbert', 'publisher' => 'Chilton Books', 'release_date' => '1965-08-01', 'description' => 'Epic sci-fi.', 'genre' => 'Science Fiction', 'pages' => 688],
        ];

        foreach ($books as $book) {
            Book::create(array_merge($book, [
                'user_id' => $user->id,
                'source' => Book::SOURCE_UPLOAD,
                'cover' => null,
            ]));
        }
    }
}
