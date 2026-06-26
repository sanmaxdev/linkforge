<?php

namespace App\Services\Qr;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

class QrService
{
    /**
     * Render a QR code. SVG is the default (dependency-free, crisp at any size);
     * PNG is used when requested and the GD extension is available.
     *
     * @param  array<string, mixed>  $design
     * @return array{data:string, mime:string, format:string}
     */
    public function render(string $data, array $design = []): array
    {
        $format = $design['format'] ?? 'svg';
        if ($format === 'png' && ! extension_loaded('gd')) {
            $format = 'svg';
        }
        if (! in_array($format, ['svg', 'png'], true)) {
            $format = 'svg';
        }

        $size = max(96, min(1024, (int) ($design['size'] ?? 320)));
        $margin = max(0, min(40, (int) ($design['margin'] ?? 12)));
        [$fr, $fg, $fb] = $this->hexToRgb((string) ($design['fg'] ?? '#0f172a'));
        [$br, $bg, $bb] = $this->hexToRgb((string) ($design['bg'] ?? '#ffffff'));

        $result = (new Builder)->build(
            writer: $format === 'svg' ? new SvgWriter : new PngWriter,
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: $margin,
            foregroundColor: new Color($fr, $fg, $fb),
            backgroundColor: new Color($br, $bg, $bb),
        );

        return ['data' => $result->getString(), 'mime' => $result->getMimeType(), 'format' => $format];
    }

    /** @return array{0:int, 1:int, 2:int} */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '0f172a';
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
