<?php

namespace App\Http\Controllers;

use App\Services\BookService;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            'status' => ['nullable', 'string', 'in:to_read,currently_reading,finished,dropped'],
            'source' => ['nullable', 'string', 'in:platform,upload'],
            'sort' => ['nullable', 'string', 'in:newest,oldest,title,author'],
        ]);

        $books = $this->bookService->getBooks(
            $validated['per_page'] ?? 10,
            [
                'genre' => $validated['genre'] ?? null,
                'status' => $validated['status'] ?? null,
                'source' => $validated['source'] ?? null,
                'sort' => $validated['sort'] ?? null,
            ]
        );

        return BookResource::collection($books)
            ->additional(['message' => 'Books fetched successfully']);
    }

    public function show(int $id)
    {
        $book = $this->bookService->findVisibleOrFail($id);
        return (new BookResource($book))->additional(['message' => 'Book fetched']);
    }

    public function continueReading(Request $request)
    {
        $books = $this->bookService->continueReading($request->user()->id);
        return BookResource::collection($books)->additional(['message' => 'Continue reading fetched']);
    }

    public function genres()
    {
        return response()->json([
            'data' => $this->bookService->getGenres(),
            'message' => 'Genres fetched successfully',
        ]);
    }

    public function stats(Request $request)
    {
        $stats = $this->bookService->getReadingStats($request->user()->id);

        return response()->json([
            'data' => $stats,
            'message' => 'Reading statistics fetched successfully',
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
            'status' => ['nullable', 'string', 'in:to_read,currently_reading,finished,dropped'],
            'source' => ['nullable', 'string', 'in:platform,upload'],
            'sort' => ['nullable', 'string', 'in:newest,oldest,title,author'],
        ]);

        $books = $this->bookService->searchBooks(
            $validated['q'],
            $validated['per_page'] ?? 10,
            [
                'genre' => $validated['genre'] ?? null,
                'status' => $validated['status'] ?? null,
                'source' => $validated['source'] ?? null,
                'sort' => $validated['sort'] ?? null,
            ]
        );

        return BookResource::collection($books)
            ->additional(['message' => 'Books searched successfully']);
    }

    public function fetchCover(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $url = $validated['url'];

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return response()->json(['message' => 'Invalid URL scheme.'], 422);
        }

        try {
            $response = Http::timeout(15)
                ->withOptions(['verify' => false])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; TrackRead/1.0)',
                    'Referer' => $scheme . '://' . parse_url($url, PHP_URL_HOST),
                ])
                ->get($url);

            if (!$response->successful()) {
                return response()->json(['message' => 'Failed to fetch cover.'], $response->status());
            }

            $body = $response->body();
            $contentType = $response->header('Content-Type') ?: 'image/jpeg';

            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $mime = strtolower(explode(';', $contentType)[0]);
            if (!in_array($mime, $allowed, true)) {
                return response()->json(['message' => 'Unsupported image type.'], 415);
            }

            if (strlen($body) > 2 * 1024 * 1024) {
                return response()->json(['message' => 'Cover exceeds 2MB.'], 413);
            }

            return response($body)
                ->header('Content-Type', $mime)
                ->header('Cache-Control', 'public, max-age=31536000, immutable')
                ->header('Content-Length', (string) strlen($body))
                ->header('X-Cover-Length', (string) strlen($body));
        } catch (\Exception $e) {
            Log::warning('fetchCover failed', ['url' => $url, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not download cover.'], 502);
        }
    }
}
