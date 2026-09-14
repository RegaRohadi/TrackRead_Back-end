<?php

namespace Database\Seeders\Concerns;

trait GeneratesBookPdf
{
    protected int $lastGeneratedPages = 0;

    /**
     * Render a simple, paginated PDF (title page + wrapped body text).
     *
     * @return string Raw PDF bytes.
     */
    protected function renderTextPdf(string $title, string $author, string $body, int $maxBodyPages = 24): string
    {
        $pageWidth = 595.0;
        $pageHeight = 842.0;
        $marginX = 56.0;
        $startY = 780.0;
        $leading = 16.0;
        $maxChars = 80;
        $linesPerPage = 44;

        $title = $this->toAscii($title);
        $author = $this->toAscii($author);
        $body = $this->toAscii($body);

        $bodyLines = [];
        foreach (preg_split('/\r\n|\r|\n/', $body) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $bodyLines[] = '';
                continue;
            }
            foreach (explode("\n", wordwrap($paragraph, $maxChars, "\n", false)) as $line) {
                $bodyLines[] = $line;
            }
        }

        $pages = [[
            ['', 12.0],
            ['', 12.0],
            ['', 12.0],
            ['', 12.0],
            [$title, 20.0],
            [$author, 13.0],
            ['', 12.0],
            ['', 12.0],
            ['Public Domain - Project Gutenberg', 11.0],
        ]];

        foreach (array_chunk($bodyLines, $linesPerPage) as $chunk) {
            if (count($pages) - 1 >= $maxBodyPages) {
                break;
            }
            $pages[] = array_map(fn ($line) => [$line, 12.0], $chunk);
        }

        $this->lastGeneratedPages = count($pages);

        return $this->buildPdf($pages, $marginX, $startY, $leading, $pageWidth, $pageHeight);
    }

    /**
     * @param  array<int, array<int, array{0: string, 1: float}>>  $pages
     */
    private function buildPdf(array $pages, float $marginX, float $startY, float $leading, float $pageWidth, float $pageHeight): string
    {
        $offsets = [];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";

        $add = function (int $id, string $object) use (&$pdf, &$offsets): void {
            $offsets[$id] = strlen($pdf);
            $pdf .= $object;
        };

        $nextId = 4;
        $pageIds = [];
        $contentIds = [];
        foreach ($pages as $ignored) {
            $pageIds[] = $nextId++;
            $contentIds[] = $nextId++;
        }

        $add(1, "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n");
        $kids = implode(' ', array_map(fn ($id) => "$id 0 R", $pageIds));
        $add(2, "2 0 obj\n<< /Type /Pages /Kids [$kids] /Count " . count($pages) . " >>\nendobj\n");
        $add(3, "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n");

        foreach ($pages as $index => $ignored) {
            $pid = $pageIds[$index];
            $cid = $contentIds[$index];
            $add($pid, "$pid 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . (int) $pageWidth . ' ' . (int) $pageHeight . "] /Resources << /Font << /F1 3 0 R >> >> /Contents $cid 0 R >>\nendobj\n");
        }

        foreach ($pages as $index => $lines) {
            $cid = $contentIds[$index];
            $stream = '';
            $y = $startY;
            foreach ($lines as [$text, $size]) {
                if ($text !== '') {
                    $stream .= 'BT /F1 ' . $size . ' Tf ' . $marginX . ' ' . $y . ' Td (' . $this->escapePdfText($text) . ") Tj ET\n";
                }
                $y -= $leading;
            }
            $add($cid, "$cid 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream\nendobj\n");
        }

        $xrefOffset = strlen($pdf);
        $size = count($offsets) + 1;
        $pdf .= "xref\n0 $size\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size $size /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function toAscii(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted === false) {
            $converted = $text;
        }

        $converted = preg_replace('/[^\x09\x0A\x20-\x7E]/', '', $converted);

        return str_replace("\t", '    ', $converted ?? '');
    }
}
