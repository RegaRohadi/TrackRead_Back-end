<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FetchPlatformCovers extends Command
{
    protected $signature = 'covers:platform {--force : Refetch even when a cover already exists}';

    protected $description = 'Fetch Open Library cover art for platform books that have none.';

    public function handle(): int
    {
        $books = Book::where('source', Book::SOURCE_PLATFORM)
            ->when(! $this->option('force'), fn ($query) => $query->whereNull('cover'))
            ->get();

        if ($books->isEmpty()) {
            $this->info('No platform books need a cover.');
            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($books as $book) {
            $query = trim($book->name . ' ' . ($book->author ?? ''));

            try {
                $search = Http::timeout(20)
                    ->withHeaders(['User-Agent' => 'TrackRead/1.0 (cover fetch)'])
                    ->get('https://openlibrary.org/search.json', [
                        'q' => $query,
                        'limit' => 1,
                        'fields' => 'cover_i,key',
                    ]);

                $coverId = $search->successful() ? data_get($search->json(), 'docs.0.cover_i') : null;

                if (! $coverId) {
                    $this->warn("No cover found: {$book->name}");
                    continue;
                }

                $image = Http::timeout(30)
                    ->withHeaders(['User-Agent' => 'TrackRead/1.0 (cover fetch)'])
                    ->get("https://covers.openlibrary.org/b/id/{$coverId}-L.jpg");

                if (! $image->successful() || strlen($image->body()) < 1024) {
                    $this->warn("Download failed: {$book->name}");
                    continue;
                }

                $path = 'BookCovers/platform_' . $book->id . '.jpg';
                Storage::disk('public')->put($path, $image->body());

                if ($book->cover && $book->cover !== $path && str_starts_with($book->cover, 'BookCovers/platform_')) {
                    Storage::disk('public')->delete($book->cover);
                }

                $book->update(['cover' => $path]);
                $updated++;
                $this->info("Cover set: {$book->name}");
            } catch (Throwable $e) {
                $this->warn("Error for {$book->name}: {$e->getMessage()}");
            }
        }

        $this->info("Done. {$updated} cover(s) updated.");
        return self::SUCCESS;
    }
}
