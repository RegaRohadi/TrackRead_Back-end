<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BookFileController extends Controller
{
    public function show(Request $request, int $id)
    {
        $book = Book::findOrFail($id);
        $uid = Auth::id();
        if ($book->source === Book::SOURCE_UPLOAD && (int) $book->user_id !== (int) $uid) {
            abort(403, 'Forbidden');
        }
        if (empty($book->pdf_path)) {
            return response()->json(['message' => 'PDF not available for this book.'], 404);
        }
        if (!Storage::disk('private')->exists($book->pdf_path)) {
            return response()->json(['message' => 'File not found on storage.'], 404);
        }
        $path = Storage::disk('private')->path($book->pdf_path);
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($book->pdf_original_name ?: basename($book->pdf_path)) . '"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
