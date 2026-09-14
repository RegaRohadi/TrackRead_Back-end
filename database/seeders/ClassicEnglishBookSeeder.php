<?php

namespace Database\Seeders;

use App\Models\Book;
use Database\Seeders\Concerns\GeneratesBookPdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ClassicEnglishBookSeeder extends Seeder
{
    use GeneratesBookPdf;

    public function run(): void
    {
        $books = [
            ['name' => 'Pride and Prejudice', 'author' => 'Jane Austen', 'publisher' => 'T. Egerton', 'release_date' => '1813-01-28', 'genre' => 'Classic Romance', 'description' => 'Elizabeth Bennet and Mr. Darcy navigate pride, prejudice, and love in Regency England.', 'pages' => 432, 'gutenberg' => 1342],
            ['name' => 'Moby-Dick', 'author' => 'Herman Melville', 'publisher' => 'Richard Bentley', 'release_date' => '1851-10-18', 'genre' => 'Adventure', 'description' => 'Captain Ahab hunts the white whale across the sea in Melville\'s epic of obsession.', 'pages' => 635, 'gutenberg' => 2701],
            ['name' => 'Frankenstein', 'author' => 'Mary Shelley', 'publisher' => 'Lackington, Hughes, Harding, Mavor & Jones', 'release_date' => '1818-01-01', 'genre' => 'Gothic Horror', 'description' => 'Victor Frankenstein creates life and is haunted by the creature he abandons.', 'pages' => 280, 'gutenberg' => 84],
            ['name' => 'Dracula', 'author' => 'Bram Stoker', 'publisher' => 'Archibald Constable and Company', 'release_date' => '1897-05-26', 'genre' => 'Gothic Horror', 'description' => 'Jonathan Harker and his allies confront the vampire Count Dracula.', 'pages' => 418, 'gutenberg' => 345],
            ['name' => 'The Adventures of Sherlock Holmes', 'author' => 'Arthur Conan Doyle', 'publisher' => 'George Newnes', 'release_date' => '1892-10-14', 'genre' => 'Detective Fiction', 'description' => 'Twelve short cases of the world\'s most famous consulting detective.', 'pages' => 307, 'gutenberg' => 1661],
            ['name' => 'Alice\'s Adventures in Wonderland', 'author' => 'Lewis Carroll', 'publisher' => 'Macmillan', 'release_date' => '1865-11-26', 'genre' => 'Fantasy', 'description' => 'Alice falls down a rabbit hole into a strange and nonsensical world.', 'pages' => 200, 'gutenberg' => 11],
            ['name' => 'Treasure Island', 'author' => 'Robert Louis Stevenson', 'publisher' => 'Cassell and Company', 'release_date' => '1883-01-01', 'genre' => 'Adventure', 'description' => 'Jim Hawkins sails in search of buried treasure and mutinous pirates.', 'pages' => 292, 'gutenberg' => 120],
            ['name' => 'The Picture of Dorian Gray', 'author' => 'Oscar Wilde', 'publisher' => 'Lippincott\'s Monthly Magazine', 'release_date' => '1890-07-01', 'genre' => 'Gothic Fiction', 'description' => 'A portrait ages while its subject stays young, hiding a life of excess.', 'pages' => 254, 'gutenberg' => 174],
            ['name' => 'The Time Machine', 'author' => 'H. G. Wells', 'publisher' => 'William Heinemann', 'release_date' => '1895-01-01', 'genre' => 'Science Fiction', 'description' => 'A Victorian inventor travels far into the future of humanity.', 'pages' => 118, 'gutenberg' => 35],
            ['name' => 'The Great Gatsby', 'author' => 'F. Scott Fitzgerald', 'publisher' => 'Charles Scribner\'s Sons', 'release_date' => '1925-04-10', 'genre' => 'Classic Fiction', 'description' => 'Jay Gatsby chases an impossible dream in the Jazz Age of Long Island.', 'pages' => 218, 'gutenberg' => 64317],
        ];

        foreach ($books as $index => $book) {
            $slug = 'classic_' . ($index + 1) . '_' . Str::slug($book['name']) . '.pdf';
            $pdfPath = 'platform_books/' . $slug;

            if (! Storage::disk('private')->exists($pdfPath)) {
                $body = $this->fetchGutenbergText($book['gutenberg'])
                    ?? $this->fallbackText($book['name'], $book['author']);

                Storage::disk('private')->put(
                    $pdfPath,
                    $this->renderTextPdf($book['name'], $book['author'], $body)
                );
            }

            if (Book::where('source', Book::SOURCE_PLATFORM)->where('pdf_path', $pdfPath)->exists()) {
                continue;
            }

            Book::create([
                'user_id' => null,
                'source' => Book::SOURCE_PLATFORM,
                'name' => $book['name'],
                'author' => $book['author'],
                'publisher' => $book['publisher'],
                'release_date' => $book['release_date'],
                'description' => $book['description'],
                'genre' => $book['genre'],
                'pages' => $book['pages'],
                'pdf_path' => $pdfPath,
                'pdf_original_name' => $book['name'] . '.pdf',
                'pdf_mime' => 'application/pdf',
                'pdf_size_bytes' => Storage::disk('private')->size($pdfPath),
                'pdf_total_pages' => $this->lastGeneratedPages ?: $book['pages'],
                'status' => Book::STATUS_TO_READ,
                'cover' => null,
            ]);
        }
    }

    private function fetchGutenbergText(int $id): ?string
    {
        $url = "https://www.gutenberg.org/cache/epub/{$id}/pg{$id}.txt";

        try {
            $response = Http::timeout(60)->retry(2, 1500)->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $text = preg_replace('/^\xEF\xBB\xBF/', '', $response->body());

        return $this->stripGutenbergBoilerplate($text);
    }

    private function stripGutenbergBoilerplate(string $text): string
    {
        if (preg_match('/\*\*\*\s*START OF (?:THE|THIS) PROJECT GUTENBERG EBOOK.*?\*\*\*/i', $text, $match, PREG_OFFSET_CAPTURE)) {
            $text = substr($text, $match[0][1] + strlen($match[0][0]));
        }

        if (preg_match('/\*\*\*\s*END OF (?:THE|THIS) PROJECT GUTENBERG EBOOK.*?\*\*\*/i', $text, $match, PREG_OFFSET_CAPTURE)) {
            $text = substr($text, 0, $match[0][1]);
        }

        return trim($text);
    }

    private function fallbackText(string $title, string $author): string
    {
        return $title . ' by ' . $author . "\n\n"
            . str_repeat('This classic work is in the public domain and is available to read freely. ', 200);
    }
}
