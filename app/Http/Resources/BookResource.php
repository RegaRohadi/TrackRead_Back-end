<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $progress = null;
        $topCurrent = null;
        $topPercent = null;
        $topLast = null;
        $topTotal = null;
        if ($this->relationLoaded('readingProgress') && $this->readingProgress) {
            $rp = $this->readingProgress;
            if ($rp instanceof \Illuminate\Database\Eloquent\Model) {
                $progress = [
                    'current_page' => $rp->current_page,
                    'total_pages' => $rp->total_pages ?? $this->pdf_total_pages,
                    'progress_percent' => $this->progress_percent ?? ($rp->total_pages ? round(min(100, ($rp->current_page / ($rp->total_pages ?: $this->pdf_total_pages ?: 1)) * 100), 1) : 0),
                    'last_read_at' => $rp->last_read_at,
                ];
            } else {
                $progress = $rp;
            }
            $topCurrent = $progress['current_page'];
            $topPercent = $progress['progress_percent'];
            $topLast = $progress['last_read_at'];
            $topTotal = $progress['total_pages'];
        } elseif (isset($this->current_page) || isset($this->progress_percent)) {
            $progress = [
                'current_page' => $this->current_page,
                'total_pages' => $this->total_pages_progress ?? $this->pdf_total_pages,
                'progress_percent' => $this->progress_percent,
                'last_read_at' => $this->last_read_at,
            ];
            $topCurrent = $this->current_page;
            $topPercent = $this->progress_percent;
            $topLast = $this->last_read_at;
            $topTotal = $this->total_pages_progress ?? $this->pdf_total_pages;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'release_date' => $this->release_date,
            'description' => $this->description,
            'genre' => $this->genre,
            'pages' => $this->pages,
            'cover' => $this->cover,
            'cover_url' => $this->cover ? rtrim(config('filesystems.disks.public.url'), '/') . '/' . ltrim($this->cover, '/') : null,
            'pages_read' => $this->pages_read,
            'status' => $this->status ?? 'to_read',
            'source' => $this->source ?? 'upload',
            'pdf_path' => $this->pdf_path,
            'pdf_original_name' => $this->pdf_original_name,
            'pdf_size_bytes' => $this->pdf_size_bytes,
            'pdf_total_pages' => $this->pdf_total_pages ?? $this->pages,
            'has_pdf' => !empty($this->pdf_path),
            'progress' => $progress,
            'current_page' => $topCurrent,
            'progress_percent' => $topPercent,
            'last_read_at' => $topLast,
            'total_pages_progress' => $topTotal,
        ];
    }
}
