<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class PlatformBookSeeder extends Seeder
{
    public function run(): void
    {
        $books = [
            ['name' => 'Platform: Panduan Membaca Efektif', 'author' => 'Tim TrackRead', 'genre' => 'Education', 'description' => 'Koleksi platform — panduan membaca cepat dan mencatat insight.', 'pages' => 42],
            ['name' => 'Platform: Dasar Desain Editorial', 'author' => 'Tim TrackRead', 'genre' => 'Design', 'description' => 'Prinsip layout, tipografi, dan hierarki visual untuk pembaca.', 'pages' => 36],
            ['name' => 'Platform: Pengantar Produktivitas', 'author' => 'Tim TrackRead', 'genre' => 'Self Help', 'description' => 'Strategi fokus dan ritme membaca harian.', 'pages' => 28],
            ['name' => 'Platform: Sejarah Buku & Kertas', 'author' => 'Tim TrackRead', 'genre' => 'History', 'description' => 'Perjalanan medium baca dari papirus hingga PDF.', 'pages' => 50],
            ['name' => 'Platform: Literasi Digital', 'author' => 'Tim TrackRead', 'genre' => 'Technology', 'description' => 'Memahami dokumen digital, arsip, dan akses terbuka.', 'pages' => 38],
            ['name' => 'Platform: Cerita Anak — Petualangan Kiko', 'author' => 'Tim TrackRead', 'genre' => 'Fiction', 'description' => 'Buku cerita bergambar untuk semua umur (sample PDF).', 'pages' => 18],
        ];

        foreach ($books as $idx => $b) {
            $slug = 'platform_' . ($idx + 1) . '_' . \Illuminate\Support\Str::slug($b['name']) . '.pdf';
            $pdfPath = 'platform_books/' . $slug;

            if (!Storage::disk('private')->exists($pdfPath)) {
                $abs = Storage::disk('private')->path($pdfPath);
                $dir = dirname($abs);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($abs, $this->minimalPdf($b['name'], $b['author'], (int) $b['pages']));
            }

            $size = Storage::disk('private')->size($pdfPath);
            $existing = Book::where('source', Book::SOURCE_PLATFORM)->where('pdf_path', $pdfPath)->first();
            if ($existing) continue;

            Book::create([
                'user_id' => null,
                'source' => Book::SOURCE_PLATFORM,
                'name' => $b['name'],
                'author' => $b['author'],
                'genre' => $b['genre'],
                'description' => $b['description'],
                'pages' => $b['pages'],
                'pdf_path' => $pdfPath,
                'pdf_original_name' => $slug,
                'pdf_mime' => 'application/pdf',
                'pdf_size_bytes' => $size,
                'pdf_total_pages' => $b['pages'],
                'status' => Book::STATUS_TO_READ,
                'cover' => null,
            ]);
        }
    }

    private function minimalPdf(string $title, string $author, int $pages): string
    {
        $objects = [];
        $offsets = [];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $add = function (string $obj) use (&$pdf, &$offsets, &$objects) {
            $offsets[] = strlen($pdf);
            $objects[] = $obj;
            $pdf .= $obj;
        };

        $catalogId = 1;
        $pagesId = 2;
        $fontId = 3;
        $nextId = 4;

        $pageIds = [];
        for ($i = 0; $i < $pages; $i++) {
            $pageIds[] = $nextId++;
        }
        $contentIds = [];
        for ($i = 0; $i < $pages; $i++) {
            $contentIds[] = $nextId++;
        }

        $add("1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n");
        $kids = implode(' ', array_map(fn($id) => "$id 0 R", $pageIds));
        $add("2 0 obj\n<< /Type /Pages /Kids [$kids] /Count $pages >>\nendobj\n");
        $add("3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n");

        for ($i = 0; $i < $pages; $i++) {
            $pid = $pageIds[$i];
            $cid = $contentIds[$i];
            $add("$pid 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $cid 0 R >>\nendobj\n");
        }

        for ($i = 0; $i < $pages; $i++) {
            $cid = $contentIds[$i];
            $text = $this->escapePdfText(($i === 0 ? $title . ' — ' . $author : 'Halaman ' . ($i + 1) . ' — ' . $title));
            $stream = "BT /F1 18 Tf 50 770 Td ($text) Tj ET\n";
            if ($i === 0) {
                $stream .= "BT /F1 11 Tf 50 740 Td (Koleksi Platform TrackRead — storage/app/private/platform_books/) Tj ET\n";
                $stream .= "BT /F1 10 Tf 50 720 Td (Halaman 1/$pages — sample generated server-side) Tj ET\n";
            } else {
                $stream .= "BT /F1 10 Tf 50 740 Td (Sample content — lorem ipsum dolor sit amet, consectetur adipiscing elit.) Tj ET\n";
                $stream .= "BT /F1 10 Tf 50 720 Td (Halaman " . ($i + 1) . "/$pages) Tj ET\n";
            }
            $stream .= "BT /F1 9 Tf 50 30 Td (TrackRead Platform • " . ($i + 1) . "/$pages) Tj ET\n";
            $len = strlen($stream);
            $add("$cid 0 obj\n<< /Length $len >>\nstream\n$stream\nendstream\nendobj\n");
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF";
        return $pdf;
    }

    private function escapePdfText(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], substr($s, 0, 90));
    }
}
