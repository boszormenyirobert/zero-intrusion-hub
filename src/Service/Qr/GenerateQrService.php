<?php

namespace App\Service\Qr;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class GenerateQrService
{
    /**
     * Generate a base64-encoded PNG QR code from input data.
     *
     * @param array $data Associative array to encode into the QR code
     * @return string Base64-encoded PNG QR image
     */
    public function getQrCode(array $data): string
    {
        // JSON encode with unescaped slashes for cleaner URLs
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Instantiate the QR code with JSON content
        $qrCode = new QrCode($jsonData);

        // Write the QR code to a PNG format
        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        // Return as base64-encoded image string
        return base64_encode($result->getString());
    }
}
