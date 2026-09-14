<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class XlsxTabularReader
{
    /**
     * Read the first worksheet from an XLSX byte string as header-keyed rows.
     * The legacy Jotform exports are simple tabular workbooks, so keeping this
     * reader narrow avoids adding a heavyweight spreadsheet dependency.
     *
     * @return array<int, array<string, string|float|int|null>>
     */
    public function rows(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'areterra-xlsx-');

        if ($path === false || file_put_contents($path, $bytes) === false) {
            throw new RuntimeException('Could not prepare the spreadsheet for reading.');
        }

        $zip = new ZipArchive;
        $opened = false;

        try {
            if ($zip->open($path) !== true) {
                throw new RuntimeException('One of the spreadsheets is not a valid XLSX file.');
            }
            $opened = true;

            $sharedStrings = $this->sharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

            if ($sheetXml === false) {
                throw new RuntimeException('A spreadsheet does not contain its first worksheet.');
            }

            $rows = $this->worksheetRows($sheetXml, $sharedStrings);
        } finally {
            if ($opened) {
                $zip->close();
            }
            @unlink($path);
        }

        if ($rows === []) {
            return [];
        }

        $headers = array_map(fn ($value) => trim((string) $value), array_shift($rows));
        $records = [];

        foreach ($rows as $values) {
            $values = array_pad($values, count($headers), null);
            $record = array_combine($headers, array_slice($values, 0, count($headers)));

            if ($record !== false && collect($record)->contains(fn ($value) => $value !== null && $value !== '')) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        [$document, $xpath] = $this->document($xml);
        $strings = [];

        foreach ($xpath->query('//main:si') ?: [] as $item) {
            $text = '';
            foreach ($xpath->query('.//main:t', $item) ?: [] as $part) {
                $text .= $part->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string|float|int|null>>
     */
    private function worksheetRows(string $xml, array $sharedStrings): array
    {
        [, $xpath] = $this->document($xml);
        $rows = [];

        foreach ($xpath->query('//main:sheetData/main:row') ?: [] as $rowNode) {
            $row = [];
            foreach ($xpath->query('./main:c', $rowNode) ?: [] as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }

                $reference = $cell->getAttribute('r');
                $column = $this->columnIndex($reference);
                $row[$column] = $this->cellValue($cell, $xpath, $sharedStrings);
            }

            if ($row !== []) {
                $width = max(array_keys($row)) + 1;
                $rows[] = array_replace(array_fill(0, $width, null), $row);
            }
        }

        return $rows;
    }

    /** @param array<int, string> $sharedStrings */
    private function cellValue(DOMElement $cell, DOMXPath $xpath, array $sharedStrings): string|float|int|null
    {
        $type = $cell->getAttribute('t');

        if ($type === 'inlineStr') {
            $text = '';
            foreach ($xpath->query('.//main:is/main:t', $cell) ?: [] as $part) {
                $text .= $part->textContent;
            }

            return $text;
        }

        $valueNode = $xpath->query('./main:v', $cell)?->item(0);
        if ($valueNode === null) {
            return null;
        }

        $value = $valueNode->textContent;
        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? null;
        }
        if ($type === 'str') {
            return $value;
        }
        if ($type === 'b') {
            return $value === '1' ? 1 : 0;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    private function columnIndex(string $reference): int
    {
        if (! preg_match('/^([A-Z]+)/i', $reference, $match)) {
            throw new RuntimeException("Invalid spreadsheet cell reference: {$reference}");
        }

        $index = 0;
        foreach (str_split(strtoupper($match[1])) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function document(string $xml): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('A spreadsheet contains invalid XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return [$document, $xpath];
    }
}
