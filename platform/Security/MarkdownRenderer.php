<?php
declare(strict_types=1);

namespace Platform\Security;

final class MarkdownRenderer
{
    /**
     * Render Markdown to safe HTML.
     *
     * Strips all raw HTML tags first, then converts Markdown syntax.
     */
    public static function render(string $markdown): string
    {
        // Strip HTML tags first
        $text = strip_tags($markdown);

        return self::renderMarkdown($text);
    }

    private static function renderMarkdown(string $text): string
    {
        // Normalize line endings
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        $lines = explode("\n", $text);
        $out = [];
        $i = 0;
        $total = count($lines);

        while ($i < $total) {
            $line = $lines[$i];

            // Fenced code block
            if (preg_match('/^```(\w*)/', $line, $m)) {
                $lang = $m[1];
                $code = [];
                $i++;
                while ($i < $total && !preg_match('/^```/', $lines[$i])) {
                    $code[] = $lines[$i];
                    $i++;
                }
                $i++; // skip closing ```
                $codeText = implode("\n", $code);
                $codeHtml = htmlspecialchars($codeText, ENT_NOQUOTES, 'UTF-8');
                $langAttr = $lang !== '' ? ' class="language-' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"' : '';
                $out[] = '<pre><code' . $langAttr . '>' . $codeHtml . '</code></pre>';
                continue;
            }

            // Horizontal rule
            if (preg_match('/^[-*_]{3,}\s*$/', $line)) {
                $out[] = '<hr>';
                $i++;
                continue;
            }

            // Heading
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                $level = strlen($m[1]);
                $content = self::renderInline($m[2]);
                $out[] = "<h{$level}>{$content}</h{$level}>";
                $i++;
                continue;
            }

            // Table row
            if (preg_match('/^\|.+\|$/', $line)) {
                $rows = [];
                while ($i < $total && preg_match('/^\|.+\|$/', $lines[$i])) {
                    $rows[] = $lines[$i];
                    $i++;
                }
                $out[] = self::renderTable($rows);
                continue;
            }

            // Blockquote
            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $bq = [];
                while ($i < $total && preg_match('/^>\s?(.*)$/', $lines[$i], $bm)) {
                    $bq[] = $bm[1];
                    $i++;
                }
                $content = self::renderInline(implode("\n", $bq));
                $out[] = '<blockquote>' . nl2br($content) . '</blockquote>';
                continue;
            }

            // Unordered list
            if (preg_match('/^[\s]*[-*+]\s+(.+)$/', $line)) {
                $items = [];
                while ($i < $total && preg_match('/^[\s]*[-*+]\s+(.+)$/', $lines[$i], $lm)) {
                    $items[] = self::renderListItem($lm[1]);
                    $i++;
                }
                $out[] = '<ul>' . implode('', $items) . '</ul>';
                continue;
            }

            // Ordered list
            if (preg_match('/^\s*\d+\.\s+(.+)$/', $line)) {
                $items = [];
                while ($i < $total && preg_match('/^\s*\d+\.\s+(.+)$/', $lines[$i], $lm)) {
                    $items[] = self::renderListItem($lm[1]);
                    $i++;
                }
                $out[] = '<ol>' . implode('', $items) . '</ol>';
                continue;
            }

            // Empty line
            if (trim($line) === '') {
                $out[] = '';
                $i++;
                continue;
            }

            // Paragraph
            $para = [];
            while ($i < $total) {
                $l = $lines[$i];
                if (trim($l) === '') break;
                if (preg_match('/^[-*+]\s+/', $l) && !preg_match('/^[\s]*[-*+]\s+/', $l)) break;
                if (preg_match('/^\s*\d+\.\s+/', $l)) break;
                if (preg_match('/^[#>|`]/', $l)) break;
                if (preg_match('/^[-*_]{3,}\s*$/', $l)) break;
                if (preg_match('/^```/', $l)) break;
                $para[] = $l;
                $i++;
            }
            $content = self::renderInline(implode("\n", $para));
            $out[] = '<p>' . nl2br($content) . '</p>';
        }

        return implode("\n", $out);
    }

    private static function renderInline(string $text): string
    {
        // htmlspecialchars for safety
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Bold
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        // Italic
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        // Inline code
        $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);
        // Links [text](url)
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);

        return $text;
    }

    private static function renderListItem(string $text): string
    {
        if (preg_match('/^\[(x| )\]\s*(.*)$/i', $text, $m)) {
            $checked = strtolower($m[1]) === 'x' ? ' checked' : '';
            $content = self::renderInline($m[2]);
            return '<li><input type="checkbox" disabled' . $checked . '> ' . $content . '</li>';
        }
        return '<li>' . self::renderInline($text) . '</li>';
    }

    private static function renderTable(array $rows): string
    {
        // Separate header (first non-separator row), separator, and data rows
        $headerCells = null;
        $dataRows = [];
        $foundHeader = false;

        foreach ($rows as $row) {
            $row = trim($row, '|');
            $cells = array_map('trim', explode('|', $row));

            // Check if this is a separator row
            if (preg_match('/^[-:\s]+$/', implode('', $cells))) {
                continue;
            }

            if (!$foundHeader) {
                $headerCells = $cells;
                $foundHeader = true;
            } else {
                $dataRows[] = $cells;
            }
        }

        if ($headerCells === null) {
            return '';
        }

        $html = '<table><thead><tr>';
        foreach ($headerCells as $cell) {
            $html .= '<th>' . self::renderInline($cell) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($dataRows as $cells) {
            $html .= '<tr>';
            foreach ($cells as $cell) {
                $html .= '<td>' . self::renderInline($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }
}
