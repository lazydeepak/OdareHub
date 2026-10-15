<?php
declare(strict_types=1);

namespace Platform\Updates;

use RuntimeException;

/**
 * Append-only JSONL journal confined to a disposable temporary root.
 * It never deletes, rewrites, or silently repairs transaction evidence.
 */
final class DisposableReleaseTransactionJournal
{
    public function __construct(private readonly string $root)
    {
        $temporaryRoot = realpath(sys_get_temp_dir());
        $parent = realpath(dirname($root));
        if ($temporaryRoot === false || $parent === false
            || !str_starts_with($parent . DIRECTORY_SEPARATOR, $temporaryRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('transaction journal must be under the system temporary root');
        }
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new RuntimeException('transaction journal root could not be created');
        }
    }

    /** @param array<string,mixed> $record */
    public function create(string $transactionId, array $record): array
    {
        $path = $this->path($transactionId);
        if (file_exists($path)) {
            throw new RuntimeException('transaction journal already exists');
        }
        return $this->append($transactionId, 'prepared', ['record' => $record]);
    }

    /** @param array<string,mixed> $payload */
    public function append(string $transactionId, string $state, array $payload = []): array
    {
        $events = $this->read($transactionId);
        if (($events['ok'] ?? false) !== true && ($events['state'] ?? null) !== 'missing') {
            throw new RuntimeException('transaction journal is indeterminate');
        }
        $previous = $events['events'] !== [] ? $events['events'][count($events['events']) - 1] : null;
        $event = [
            'sequence' => count($events['events']) + 1,
            'transaction_id' => $transactionId,
            'state' => $state,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'previous_event_sha256' => $previous['event_sha256'] ?? null,
            'payload' => $payload,
        ];
        $event['event_sha256'] = hash('sha256', self::canonical($event));
        $bytes = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($this->path($transactionId), $bytes, FILE_APPEND | LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('transaction journal append failed');
        }
        return $event;
    }

    /** @return array{ok:bool,state:string,events:array<int,array<string,mixed>>,error?:string} */
    public function read(string $transactionId): array
    {
        $path = $this->path($transactionId);
        if (!is_file($path)) {
            return ['ok' => true, 'state' => 'missing', 'events' => []];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines) || $lines === []) {
            return ['ok' => false, 'state' => 'indeterminate', 'events' => [], 'error' => 'journal is empty'];
        }
        $events = [];
        $previous = null;
        foreach ($lines as $line) {
            try {
                $event = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return ['ok' => false, 'state' => 'indeterminate', 'events' => $events, 'error' => 'journal contains invalid JSON'];
            }
            if (!is_array($event) || ($event['transaction_id'] ?? null) !== $transactionId
                || ($event['sequence'] ?? null) !== count($events) + 1
                || ($event['previous_event_sha256'] ?? null) !== ($previous['event_sha256'] ?? null)) {
                return ['ok' => false, 'state' => 'indeterminate', 'events' => $events, 'error' => 'journal chain is contradictory'];
            }
            $claimed = $event['event_sha256'] ?? null;
            unset($event['event_sha256']);
            if (!is_string($claimed) || !hash_equals($claimed, hash('sha256', self::canonical($event)))) {
                return ['ok' => false, 'state' => 'indeterminate', 'events' => $events, 'error' => 'journal event digest mismatch'];
            }
            $event['event_sha256'] = $claimed;
            $events[] = $event;
            $previous = $event;
        }
        return ['ok' => true, 'state' => 'valid', 'events' => $events];
    }

    /** @return array<int,string> */
    public function transactionIds(): array
    {
        $ids = [];
        foreach (glob($this->root . '/transaction-*.jsonl') ?: [] as $path) {
            $name = basename($path);
            if (preg_match('/\Atransaction-([a-f0-9]{32})\.jsonl\z/D', $name, $match) === 1) {
                $ids[] = $match[1];
            }
        }
        sort($ids);
        return $ids;
    }

    private function path(string $transactionId): string
    {
        if (preg_match('/\A[a-f0-9]{32}\z/D', $transactionId) !== 1) {
            throw new RuntimeException('invalid transaction ID');
        }
        return $this->root . '/transaction-' . $transactionId . '.jsonl';
    }

    /** @param array<string,mixed> $value */
    private static function canonical(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
