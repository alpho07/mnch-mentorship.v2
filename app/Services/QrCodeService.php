<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeService
{
    /**
     * A base64 PNG data URI for the given data, safe to embed directly in
     * DomPDF-rendered HTML (no remote fetch required).
     */
    public function dataUri(string $data, int $size = 220): string
    {
        $result = Builder::create()
            ->writer(new PngWriter)
            ->data($data)
            ->size($size)
            ->margin(6)
            ->foregroundColor(new Color(17, 24, 39))
            ->backgroundColor(new Color(255, 255, 255))
            ->build();

        return $result->getDataUri();
    }
}
