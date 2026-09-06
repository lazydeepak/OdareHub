<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\EngineeringWorkspace\Services;

final class WorkTaskParser
{
    /**
     * Parse all supported task-list lines from markdown content.
     * Only `- [ ]` and `- [x]` patterns are recognized (with optional leading
     * whitespace for nesting). Lines inside fenced code blocks are skipped.
     *
     * @return array<int, array{ordinal:int,line_number:int,original_line:string,indent:string,task_text:string,checked:bool,marker:string,line_hash:string}>
     */
    public static function parseSupportedTaskLines(string $content): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
        $tasks = [];
        $inCodeBlock = false;
        $codeBlockFence = '';

        foreach ($lines as $lineNumber => $line) {
            $trimmed = ltrim($line);

            if (preg_match('/^(`{3,}|~{3,})/', $trimmed, $fenceMatch)) {
                if (!$inCodeBlock) {
                    $inCodeBlock = true;
                    $codeBlockFence = $fenceMatch[1][0];
                } elseif ($trimmed[0] === $codeBlockFence && strlen($trimmed) >= 3) {
                    $inCodeBlock = false;
                    $codeBlockFence = '';
                }
                continue;
            }

            if ($inCodeBlock) {
                continue;
            }

            if (preg_match('/^(\s*)-\s+\[\s*(x?)\s*\]\s+(.*)$/', $line, $matches)) {
                $isChecked = $matches[2] !== '';
                $normalizedMarker = $isChecked ? '[x]' : '[ ]';
                $lineHash = hash('sha256', $line);

                $tasks[] = [
                    'ordinal' => count($tasks),
                    'line_number' => $lineNumber,
                    'original_line' => $line,
                    'indent' => $matches[1],
                    'task_text' => $matches[3],
                    'checked' => $isChecked,
                    'marker' => $normalizedMarker,
                    'line_hash' => $lineHash,
                ];
            }
        }

        return $tasks;
    }

    /**
     * @param array<int, array<string, mixed>> $tasks
     * @return array<string, mixed>|null
     */
    public static function findTaskByOrdinal(array $tasks, int $ordinal): ?array
    {
        foreach ($tasks as $task) {
            if ((int)$task['ordinal'] === $ordinal) {
                return $task;
            }
        }
        return null;
    }

    /**
     * Toggle a specific task in the content. Returns the new content on success.
     *
     * @return array{ok:bool,error?:string,content?:string,fingerprint?:string,toggled?:array<string,mixed>}
     */
    public static function toggleTaskLine(string $content, int $targetOrdinal, bool $targetChecked): array
    {
        $tasks = self::parseSupportedTaskLines($content);

        $task = self::findTaskByOrdinal($tasks, $targetOrdinal);
        if ($task === null) {
            return ['ok' => false, 'error' => 'Task ordinal not found'];
        }

        $currentChecked = $task['checked'];
        if ($currentChecked === $targetChecked) {
            return ['ok' => false, 'error' => 'Task already in target state'];
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
        $lineNumber = $task['line_number'];

        if (!isset($lines[$lineNumber])) {
            return ['ok' => false, 'error' => 'Line number out of bounds'];
        }

        $originalLine = $lines[$lineNumber];
        $currentLineHash = hash('sha256', $originalLine);
        $submittedLineHash = $task['line_hash'];

        if (!hash_equals($submittedLineHash, $currentLineHash)) {
            return ['ok' => false, 'error' => 'Task line content has changed'];
        }

        $newMarker = $targetChecked ? '[x]' : '[ ]';
        $newLine = preg_replace(
            '/^(\s*-\s+)\[\s*x?\s*\]/',
            '$1' . $newMarker,
            $originalLine,
            1
        );
        $lines[$lineNumber] = $newLine;

        $newContent = implode("\n", $lines);

        return [
            'ok' => true,
            'content' => $newContent,
            'fingerprint' => self::fingerprint($newContent),
            'toggled' => [
                'ordinal' => $targetOrdinal,
                'from' => $currentChecked ? '[x]' : '[ ]',
                'to' => $newMarker,
                'task_text' => $task['task_text'],
            ],
        ];
    }

    /**
     * Compute a Markdown-context fingerprint that replaces task markers
     * with placeholders before hashing, so that toggling a checkbox changes
     * neither the fingerprint nor the toggled content in unexpected ways.
     *
     * For toggle validation we use direct SHA-256 of the raw content,
     * matching EngineeringWorkspaceResolver::fingerprint().
     */
    public static function fingerprint(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Replace supported task markers with HTML comment placeholders for
     * safe passage through the Markdown renderer.
     *
     * @param array<int, array<string, mixed>> $tasks
     * @return array{modified_content:string, markers:array<int, array<string, mixed>>}
     */
    public static function replaceMarkersWithPlaceholders(string $content, array $tasks): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
        $markers = [];

        foreach ($tasks as $task) {
            $ordinal = $task['ordinal'];
            $lineNumber = $task['line_number'];
            $line = $lines[$lineNumber] ?? '';

            $placeholder = '<!--EWT:' . $ordinal . '-->';
            $replacementLine = preg_replace(
                '/^(\s*-\s+)(\[\s*x?\s*\])/',
                '$1' . $placeholder,
                $line,
                1
            );
            $lines[$lineNumber] = $replacementLine;

            $markers[$ordinal] = $task;
        }

        return [
            'modified_content' => implode("\n", $lines),
            'markers' => $markers,
        ];
    }
}