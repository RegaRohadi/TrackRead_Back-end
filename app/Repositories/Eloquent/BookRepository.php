<?php

namespace App\Repositories\Eloquent;

use App\Models\Book;
use App\Models\ReadingProgress;
use App\Repositories\Interfaces\BookRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BookRepository implements BookRepositoryInterface
{
    private function visibleQuery()
    {
        $uid = Auth::id();
        return Book::query()->where(function ($q) use ($uid) {
            $q->where(function ($qq) {
                $qq->where('source', Book::SOURCE_PLATFORM)->whereNull('user_id');
            })->orWhere('user_id', $uid);
        });
    }

    private function attachProgress($paginator)
    {
        $uid = Auth::id();
        $bookIds = collect($paginator->items())->pluck('id')->toArray();
        if (empty($bookIds)) return $paginator;
        $progress = ReadingProgress::where('user_id', $uid)->whereIn('book_id', $bookIds)->get()->keyBy('book_id');
        foreach ($paginator as $book) {
            $rp = $progress->get($book->id);
            if ($rp) {
                $book->setAttribute('current_page', $rp->current_page);
                $book->setAttribute('total_pages_progress', $rp->total_pages);
                $tp = $rp->total_pages ?: ($book->pdf_total_pages ?: $book->pages);
                $pct = $tp ? round(min(100, ($rp->current_page / $tp) * 100), 1) : 0;
                $book->setAttribute('progress_percent', $pct);
                $book->setAttribute('last_read_at', $rp->last_read_at);
                $book->setRelation('readingProgress', $rp);
            } else {
                $book->setAttribute('current_page', null);
                $book->setAttribute('progress_percent', 0);
                $book->setAttribute('last_read_at', null);
            }
        }
        return $paginator;
    }

    public function all(): Collection
    {
        return $this->visibleQuery()->orderByDesc('created_at')->get();
    }

    private function applySort($query, ?string $sort)
    {
        switch ($sort) {
            case 'oldest':
                return $query->orderBy('created_at');
            case 'title':
                return $query->orderBy('name');
            case 'author':
                return $query->orderBy('author');
            case 'newest':
            default:
                return $query->orderByDesc('created_at');
        }
    }

    public function paginate(int $perPage = 10, array $filters = [])
    {
        $query = $this->applySort($this->visibleQuery(), $filters['sort'] ?? null);

        if (!empty($filters['source']) && in_array($filters['source'], Book::SOURCES, true)) {
            if ($filters['source'] === Book::SOURCE_PLATFORM) {
                $query->where('source', Book::SOURCE_PLATFORM)->whereNull('user_id');
            } else {
                $query->where('source', Book::SOURCE_UPLOAD)->where('user_id', Auth::id());
            }
        }

        if (!empty($filters['genre'])) {
            $query->where('genre', $filters['genre']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $paginator = $query->paginate($perPage);
        return $this->attachProgress($paginator);
    }

    public function genres(): array
    {
        return $this->visibleQuery()
            ->whereNotNull('genre')
            ->where('genre', '!=', '')
            ->distinct()
            ->orderBy('genre')
            ->pluck('genre')
            ->toArray();
    }

    public function find(int $id): ?Book
    {
        $book = Book::find($id);
        if (!$book) return null;
        $uid = Auth::id();
        if ($book->source === Book::SOURCE_PLATFORM && is_null($book->user_id)) return $book;
        if ((int) $book->user_id === (int) $uid) return $book;
        return null;
    }

    public function findVisibleOrFail(int $id): Book
    {
        $book = Book::findOrFail($id);
        $uid = Auth::id();
        if ($book->source === Book::SOURCE_PLATFORM && is_null($book->user_id)) {
        } elseif ((int) $book->user_id !== (int) $uid) {
            abort(403, 'Forbidden');
        }
        $rp = ReadingProgress::where('user_id', $uid)->where('book_id', $book->id)->first();
        if ($rp) {
            $tp = $rp->total_pages ?: ($book->pdf_total_pages ?: $book->pages);
            $pct = $tp ? round(min(100, ($rp->current_page / $tp) * 100), 1) : 0;
            $book->setAttribute('current_page', $rp->current_page);
            $book->setAttribute('total_pages_progress', $rp->total_pages);
            $book->setAttribute('progress_percent', $pct);
            $book->setAttribute('last_read_at', $rp->last_read_at);
            $book->setRelation('readingProgress', $rp);
        } else {
            $book->setAttribute('current_page', null);
            $book->setAttribute('progress_percent', 0);
            $book->setAttribute('last_read_at', null);
        }
        return $book;
    }

    public function create(array $data): Book
    {
        $data['user_id'] = Auth::id();
        $data['source'] = Book::SOURCE_UPLOAD;

        if (empty($data['status'])) {
            $pages = isset($data['pdf_total_pages']) ? (int) $data['pdf_total_pages'] : (isset($data['pages']) ? (int) $data['pages'] : 0);
            $pagesRead = isset($data['pages_read']) ? (int) $data['pages_read'] : 0;
            if ($pages > 0 && $pagesRead >= $pages) {
                $data['status'] = Book::STATUS_FINISHED;
            } elseif ($pagesRead > 0) {
                $data['status'] = Book::STATUS_CURRENTLY_READING;
            } else {
                $data['status'] = Book::STATUS_TO_READ;
            }
        }

        if (request()->hasFile('cover')) {
            $data['cover'] = request()->file('cover')->store('BookCovers', 'public');
        }

        if (request()->hasFile('pdf')) {
            $pdf = request()->file('pdf');
            $content = file_get_contents($pdf->getRealPath());
            if (strpos($content, '%PDF') !== 0 && strpos($content, '%PDF') === false) {
                abort(422, 'File is not a valid PDF.');
            }
            $path = $pdf->store('user_uploads/' . Auth::id(), 'private');
            $data['pdf_path'] = $path;
            $data['pdf_original_name'] = $pdf->getClientOriginalName();
            $data['pdf_mime'] = 'application/pdf';
            $data['pdf_size_bytes'] = $pdf->getSize();
            $data['pdf_total_pages'] = $this->countPdfPages($pdf->getRealPath());
            $data['pages'] = $data['pdf_total_pages'];
            if (empty($data['cover'])) {
                $thumb = $this->generateThumbnail($path, $data['pdf_total_pages']);
                if ($thumb) $data['cover'] = $thumb;
            }
        }

        return Book::create($data);
    }

    public function update(int $id, array $data): Book
    {
        $book = Book::where('user_id', Auth::id())->findOrFail($id);

        if (empty($data['status'])) {
            $pages = isset($data['pages']) ? (int) $data['pages'] : (isset($data['pdf_total_pages']) ? (int) $data['pdf_total_pages'] : (int) ($book->pdf_total_pages ?: $book->pages));
            $pagesRead = isset($data['pages_read']) ? (int) $data['pages_read'] : (int) $book->pages_read;
            if ($pages > 0 && $pagesRead >= $pages) {
                $data['status'] = Book::STATUS_FINISHED;
            } elseif ($pagesRead > 0 && $book->status === Book::STATUS_TO_READ) {
                $data['status'] = Book::STATUS_CURRENTLY_READING;
            }
        }

        if (request()->hasFile('cover')) {
            if ($book->cover) Storage::disk('public')->delete($book->cover);
            $data['cover'] = request()->file('cover')->store('BookCovers', 'public');
        }

        if (request()->hasFile('pdf')) {
            $pdf = request()->file('pdf');
            $content = file_get_contents($pdf->getRealPath());
            if (strpos($content, '%PDF') === false) {
                abort(422, 'File is not a valid PDF.');
            }
            if ($book->pdf_path) Storage::disk('private')->delete($book->pdf_path);
            $path = $pdf->store('user_uploads/' . Auth::id(), 'private');
            $data['pdf_path'] = $path;
            $data['pdf_original_name'] = $pdf->getClientOriginalName();
            $data['pdf_mime'] = 'application/pdf';
            $data['pdf_size_bytes'] = $pdf->getSize();
            $data['pdf_total_pages'] = $this->countPdfPages($pdf->getRealPath());
            $data['pages'] = $data['pdf_total_pages'];
            if (empty($data['cover']) && empty($book->cover)) {
                $thumb = $this->generateThumbnail($path, $data['pdf_total_pages']);
                if ($thumb) $data['cover'] = $thumb;
            }
        }

        $book->update($data);

        return $book->fresh();
    }

    public function delete(int $id): bool
    {
        $book = Book::where('user_id', Auth::id())->findOrFail($id);
        if ($book->cover) Storage::disk('public')->delete($book->cover);
        if ($book->pdf_path) Storage::disk('private')->delete($book->pdf_path);
        ReadingProgress::where('book_id', $book->id)->delete();
        return $book->delete();
    }

    public function search(string $keyword, int $perPage = 10, array $filters = [])
    {
        $query = $this->visibleQuery()
            ->when(!empty($filters['source']), function ($q) use ($filters) {
                if ($filters['source'] === Book::SOURCE_PLATFORM) {
                    $q->where('source', Book::SOURCE_PLATFORM)->whereNull('user_id');
                } else {
                    $q->where('source', Book::SOURCE_UPLOAD)->where('user_id', Auth::id());
                }
            })
            ->when(!empty($filters['genre']), function ($query) use ($filters) {
                $query->where('genre', $filters['genre']);
            })
            ->when(!empty($filters['status']), function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'ilike', "%{$keyword}%")
                    ->orWhere('author', 'ilike', "%{$keyword}%")
                    ->orWhere('genre', 'ilike', "%{$keyword}%");
            });

        $this->applySort($query, $filters['sort'] ?? null);

        $paginator = $query->paginate($perPage);
        return $this->attachProgress($paginator);
    }

    public function getStats(int $userId): array
    {
        $visible = fn () => Book::query()->where(function ($q) use ($userId) {
            $q->where(function ($qq) {
                $qq->where('source', Book::SOURCE_PLATFORM)->whereNull('user_id');
            })->orWhere('user_id', $userId);
        });

        $totalBooks = $visible()->count();

        $breakdown = $visible()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalPages = (int) $visible()
            ->selectRaw('COALESCE(SUM(COALESCE(pdf_total_pages, pages)), 0) as total_pages')
            ->value('total_pages');

        $progressPages = ReadingProgress::where('user_id', $userId)->sum('current_page');
        $completionPercentage = $totalPages > 0 ? round((min($progressPages, $totalPages) / $totalPages) * 100, 1) : 0;

        return [
            'total_books' => $totalBooks,
            'status_breakdown' => [
                'to_read' => (int) ($breakdown['to_read'] ?? 0),
                'currently_reading' => (int) ($breakdown['currently_reading'] ?? 0),
                'finished' => (int) ($breakdown['finished'] ?? 0),
                'dropped' => (int) ($breakdown['dropped'] ?? 0),
            ],
            'total_pages' => $totalPages,
            'total_pages_read' => (int) $progressPages,
            'completion_percentage' => $completionPercentage,
        ];
    }

    public function continueReading(int $userId, int $limit = 6)
    {
        $progress = ReadingProgress::where('user_id', $userId)
            ->whereHas('book', function ($q) use ($userId) {
                $q->where(function ($qq) use ($userId) {
                    $qq->where(function ($qqq) {
                        $qqq->where('source', Book::SOURCE_PLATFORM)->whereNull('user_id');
                    })->orWhere('user_id', $userId);
                });
            })
            ->with('book')
            ->orderByDesc('last_read_at')
            ->limit($limit)
            ->get();

        return $progress->map(function ($rp) {
            $book = $rp->book;
            if (!$book) return null;
            $tp = $rp->total_pages ?: ($book->pdf_total_pages ?: $book->pages);
            $pct = $tp ? round(min(100, ($rp->current_page / $tp) * 100), 1) : 0;
            if ($pct >= 100) return null;
            $book->setAttribute('current_page', $rp->current_page);
            $book->setAttribute('progress_percent', $pct);
            $book->setAttribute('last_read_at', $rp->last_read_at);
            $book->setAttribute('total_pages_progress', $rp->total_pages);
            $book->setRelation('readingProgress', $rp);
            return $book;
        })->filter()->values();
    }

    private function countPdfPages(string $path): ?int
    {
        try {
            $content = file_get_contents($path);
            if (preg_match_all('/\/Type\s*\/Page[^s]/', $content, $m)) {
                return count($m[0]);
            }
            if (preg_match('/\/Count\s+(\d+)/', $content, $m)) {
                return (int) $m[1];
            }
        } catch (\Throwable $e) {}
        return null;
    }

    private function generateThumbnail(string $pdfPath, ?int $totalPages): ?string
    {
        try {
            if (!extension_loaded('imagick')) return null;
            $abs = Storage::disk('private')->path($pdfPath);
            if (!file_exists($abs)) return null;
            $imagick = new \Imagick();
            $imagick->setResolution(120, 120);
            $imagick->readImage($abs . '[0]');
            $imagick->setImageFormat('jpg');
            $imagick->setImageCompressionQuality(75);
            $imagick->thumbnailImage(400, 560, true, true);
            $filename = 'BookCovers/thumb_' . md5($pdfPath) . '.jpg';
            $tmp = Storage::disk('public')->path($filename);
            $dir = dirname($tmp);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $imagick->writeImage($tmp);
            $imagick->clear();
            return $filename;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
