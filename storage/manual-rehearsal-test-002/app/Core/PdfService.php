<?php
declare(strict_types=1);

namespace App\Core;

final class PdfService
{
    private bool $dompdfReady;
    private array $baseOptions;

    public function __construct(array $options = [])
    {
        $this->dompdfReady = class_exists('Dompdf\\Dompdf');
        $this->baseOptions = array_merge([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => false,
        ], $options);
    }

    public function isReady(): bool
    {
        return $this->dompdfReady;
    }

    public function outputFromHtml(string $html, array $config = []): string
    {
        if (!$this->dompdfReady) {
            throw new \RuntimeException('Dompdf is not available in this runtime. Run composer install (or composer require dompdf/dompdf).');
        }

        $dompdfClass = 'Dompdf\\Dompdf';
        $optionsClass = 'Dompdf\\Options';

        $options = new $optionsClass();
        foreach ($this->baseOptions as $key => $value) {
            $setter = 'set' . ucfirst($key);
            if (method_exists($options, $setter)) {
                $options->{$setter}($value);
            }
        }

        /** @var object $dompdf */
        $dompdf = new $dompdfClass($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper((string)($config['paper'] ?? 'A4'), (string)($config['orientation'] ?? 'portrait'));
        $dompdf->render();

        return (string)$dompdf->output();
    }

    public function stream(string $pdfBinary, string $filename, bool $download = false): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdfBinary;
    }
}
