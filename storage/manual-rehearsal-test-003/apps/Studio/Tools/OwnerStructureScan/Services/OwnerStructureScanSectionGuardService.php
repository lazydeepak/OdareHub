<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

final class OwnerStructureScanSectionGuardService
{
    /**
     * @return array{status:string,data:array<string,mixed>,error_code:string,message:string}
     */
    public static function safeSection(string $sectionKey, callable $callback, string $errorCode, string $message): array
    {
        try {
            $data = $callback();
            if (!is_array($data)) {
                $data = [];
            }

            return [
                'status' => $data === [] ? 'empty' : 'ready',
                'data' => $data,
                'error_code' => '',
                'message' => '',
            ];
        } catch (\Throwable $e) {
            error_log(
                'OwnerStructureScan section failed [' . $sectionKey . ']: '
                . get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . (string)$e->getLine()
                . "\n" . $e->getTraceAsString()
            );

            return [
                'status' => 'failed',
                'data' => [],
                'error_code' => $errorCode,
                'message' => $message,
            ];
        }
    }

    /**
     * @return array{status:string,data:array<string,mixed>,error_code:string,message:string}
     */
    public static function emptySection(string $errorCode = '', string $message = ''): array
    {
        return [
            'status' => 'empty',
            'data' => [],
            'error_code' => $errorCode,
            'message' => $message,
        ];
    }
}
