<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\ReadingProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReadingProgressController extends Controller
{
    public function show(Request $request, int $id)
    {
        $book = Book::findOrFail($id);
        $uid = Auth::id();
        if ($book->source === Book::SOURCE_UPLOAD && (int) $book->user_id !== (int) $uid) {
            abort(403);
        }
        $progress = ReadingProgress::where('user_id', $uid)->where('book_id', $id)->first();
        $tp = $progress?->total_pages ?: ($book->pdf_total_pages ?: $book->pages);
        $cp = $progress?->current_page ?: 1;
        return response()->json([
            'data' => [
                'book_id' => $book->id,
                'current_page' => $cp,
                'total_pages' => $tp,
                'progress_percent' => $tp ? round(min(100, ($cp / $tp) * 100), 1) : 0,
                'last_read_at' => $progress?->last_read_at,
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $book = Book::findOrFail($id);
        $uid = Auth::id();
        if ($book->source === Book::SOURCE_UPLOAD && (int) $book->user_id !== (int) $uid) {
            abort(403);
        }
        $tp = $book->pdf_total_pages ?: $book->pages;
        $max = $tp ?: 10000;
        $validated = $request->validate([
            'current_page' => ['required', 'integer', 'min:1', 'max:' . $max],
        ]);
        $current = (int) $validated['current_page'];
        if ($tp && $current > $tp) {
            return response()->json(['message' => 'Halaman melebihi total halaman.'], 422);
        }
        $progress = ReadingProgress::updateOrCreate(
            ['user_id' => $uid, 'book_id' => $id],
            ['current_page' => $current, 'total_pages' => $tp, 'last_read_at' => now()]
        );
        return response()->json([
            'data' => [
                'book_id' => $id,
                'current_page' => $progress->current_page,
                'total_pages' => $progress->total_pages,
                'progress_percent' => $tp ? round(min(100, ($current / $tp) * 100), 1) : 0,
                'last_read_at' => $progress->last_read_at,
            ],
            'message' => 'Progress saved',
        ]);
    }
}
