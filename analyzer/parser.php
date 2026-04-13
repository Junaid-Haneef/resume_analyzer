<?php
/**
 * parser.php — Resume text extraction
 * Supports: TXT, PDF (via smalot/pdfparser), DOCX (zip/XML)
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Extract plain text from a resume file.
 *
 * @param  string $filePath Absolute path to the uploaded file
 * @param  string $ext      Lowercase file extension (txt|pdf|docx)
 * @return string Extracted plain text
 * @throws RuntimeException on extraction failure
 */
function parseResume(string $filePath, string $ext): string {
    switch ($ext) {
        case 'txt':
            $text = file_get_contents($filePath);
            if ($text === false) {
                throw new RuntimeException('Could not read the text file.');
            }
            return $text;

        case 'pdf':
            return parsePdf($filePath);

        case 'docx':
            return parseDocx($filePath);

        default:
            throw new RuntimeException("Unsupported file extension: {$ext}");
    }
}

/**
 * Extract text from a PDF using smalot/pdfparser.
 */
function parsePdf(string $filePath): string {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf    = $parser->parseFile($filePath);
    $text   = $pdf->getText();

    if (empty(trim($text))) {
        // Fallback: raw byte scan for embedded ASCII strings (scanned/image PDFs)
        $raw  = file_get_contents($filePath);
        preg_match_all('/BT\s*(.*?)\s*ET/s', $raw, $matches);
        $text = implode(' ', $matches[1] ?? []);
        $text = preg_replace('/[^\x20-\x7E]/', ' ', $text);
    }

    return $text;
}

/**
 * Extract text from a DOCX by reading word/document.xml inside the ZIP archive.
 */
function parseDocx(string $filePath): string {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new RuntimeException('Could not open DOCX file as ZIP archive.');
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false) {
        throw new RuntimeException('Could not find word/document.xml inside DOCX.');
    }

    // Replace XML paragraph/run breaks with spaces before stripping tags
    $xml  = preg_replace('/<\/w:p>/', "\n", $xml);
    $xml  = preg_replace('/<\/w:r>/', ' ', $xml);
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $text;
}
