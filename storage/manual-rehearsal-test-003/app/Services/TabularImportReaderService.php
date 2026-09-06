<?php
declare(strict_types=1);

namespace App\Services;

use ZipArchive;

final class TabularImportReaderService
{
    /**
     * @return array<int,array<int,string>>
     */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower(trim($extension));
        return match ($extension) {
            'csv' => $this->readCsvRows($path),
            'xlsx' => $this->readXlsxRows($path),
            default => throw new \RuntimeException('Unsupported file type: ' . $extension),
        };
    }

    /**
     * @return array<int,array<int,string>>
     */
    private function readCsvRows(string $path): array
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        $rows = [];
        $firstLine = fgets($fh);
        if ($firstLine === false) {
            fclose($fh);
            return [];
        }
        $delimiter = $this->detectDelimiter($firstLine);
        rewind($fh);

        while (($line = fgetcsv($fh, 0, $delimiter)) !== false) {
            $rows[] = array_map(static fn($value): string => trim((string)$value), $line);
        }
        fclose($fh);
        return $rows;
    }

    /**
     * @return array<int,array<int,string>>
     */
    private function readXlsxRows(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive extension is required for XLSX import.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open XLSX archive.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $this->readFirstWorksheetXml($zip);
        $zip->close();

        if ($sheetXml === '') {
            return [];
        }

        $xml = simplexml_load_string($sheetXml);
        if ($xml === false || !isset($xml->sheetData)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $rowNode) {
            $row = [];
            $maxCol = -1;
            foreach ($rowNode->c as $cell) {
                $ref = (string)($cell['r'] ?? '');
                $colIndex = $this->columnIndexFromRef($ref);
                if ($colIndex > $maxCol) {
                    $maxCol = $colIndex;
                }

                $type = (string)($cell['t'] ?? '');
                $value = '';
                if ($type === 's') {
                    $sharedIdx = (int)($cell->v ?? 0);
                    $value = (string)($sharedStrings[$sharedIdx] ?? '');
                } elseif ($type === 'inlineStr') {
                    $value = isset($cell->is->t) ? (string)$cell->is->t : '';
                } else {
                    $value = isset($cell->v) ? (string)$cell->v : '';
                }

                if ($colIndex < 0) {
                    $row[] = trim($value);
                } else {
                    $row[$colIndex] = trim($value);
                }
            }

            if ($maxCol >= 0) {
                for ($i = 0; $i <= $maxCol; $i++) {
                    if (!array_key_exists($i, $row)) {
                        $row[$i] = '';
                    }
                }
                ksort($row);
                $rows[] = array_values($row);
            }
        }

        return $rows;
    }

    /**
     * @return array<int,string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (!is_string($xml) || $xml === '') {
            return [];
        }

        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        $strings = [];
        foreach ($doc->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string)$si->t;
                continue;
            }

            $text = '';
            foreach ($si->r as $run) {
                $text .= (string)($run->t ?? '');
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function readFirstWorksheetXml(ZipArchive $zip): string
    {
        $firstSheet = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $firstSheet = $name;
                break;
            }
        }

        return $firstSheet !== '' ? (string)$zip->getFromName($firstSheet) : '';
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [',', ';', "\t", '|'];
        $best = ',';
        $bestCount = -1;
        foreach ($candidates as $candidate) {
            $count = substr_count($line, $candidate);
            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function columnIndexFromRef(string $ref): int
    {
        if (!preg_match('/([A-Z]+)/', strtoupper($ref), $matches)) {
            return -1;
        }

        $letters = $matches[1];
        $index = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }
}
