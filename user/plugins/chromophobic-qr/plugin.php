<?php

/*
Plugin Name: Chromophobic QR Codes
Plugin URI: https://github.com/gian-source/chromophobic-yourls
Description: Erzeugt selbst gehostete SVG-QR-Codes für YOURLS-Kurzlinks.
Version: 1.0.0
Author: gian-source
Author URI: https://github.com/gian-source
*/

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

$chromophobic_qr_autoloader = __DIR__ . '/vendor/autoload.php';

if (is_readable($chromophobic_qr_autoloader)) {
    require_once $chromophobic_qr_autoloader;

    yourls_add_action(
        'redirect_keyword_not_found',
        'chromophobic_qr_render',
        1
    );
}

/**
 * Erzeugt für einen bestehenden Kurzlink einen SVG-QR-Code.
 *
 * Beispiel:
 * https://go.chromophobic.ch/test.qr
 */
function chromophobic_qr_render($request): void
{
    $requested_path = $request[0] ?? '';

    $allowed_characters = yourls_make_regexp_pattern(
        yourls_get_shorturl_charset()
    );

    $matches_qr_url = preg_match(
        "@^([$allowed_characters]+)\.qr/?$@",
        $requested_path,
        $matches
    );

    if ($matches_qr_url !== 1) {
        return;
    }

    $keyword = yourls_sanitize_keyword($matches[1]);

    if (!yourls_is_shorturl($keyword)) {
        return;
    }

    $short_url = yourls_link($keyword);

    try {
        $qr_code = new QrCode(
            data: $short_url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 400,
            margin: 20,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );

        $writer = new SvgWriter();
        $result = $writer->write($qr_code);

        header('Content-Type: ' . $result->getMimeType());
        header(
            'Content-Disposition: inline; filename="' .
            $keyword .
            '.svg"'
        );
        header('Cache-Control: public, max-age=86400');

        echo $result->getString();
        exit;
    } catch (Throwable $exception) {
        error_log(
            'Chromophobic QR error: ' . $exception->getMessage()
        );

        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');

        echo 'Der QR-Code konnte nicht erzeugt werden.';
        exit;
    }
}
