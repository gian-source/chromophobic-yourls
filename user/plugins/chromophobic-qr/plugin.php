<?php

/*
Plugin Name: Chromophobic QR Codes
Plugin URI: https://github.com/gian-source/chromophobic-yourls
Description: Selbst gehostete QR-Codes mit Downloadfunktion für YOURLS.
Version: 1.1.0
Author: gian-source
Author URI: https://github.com/gian-source
*/

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\EpsWriter;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

if (!defined('YOURLS_ABSPATH')) {
    die();
}

$chromophobic_qr_autoloader = __DIR__ . '/vendor/autoload.php';

if (!is_readable($chromophobic_qr_autoloader)) {
    error_log('Chromophobic QR: Composer-Autoloader fehlt.');
    return;
}

require_once $chromophobic_qr_autoloader;

yourls_add_action(
    'redirect_keyword_not_found',
    'chromophobic_qr_render',
    1
);

yourls_add_filter(
    'table_add_row_action_array',
    'chromophobic_qr_add_admin_action',
    10,
    2
);

yourls_add_action(
    'html_head',
    'chromophobic_qr_admin_styles'
);

yourls_add_action(
    'html_footer',
    'chromophobic_qr_admin_dialog'
);

/**
 * Erzeugt QR-Codes für bestehende Kurzlinks.
 *
 * Unterstützte URLs:
 * /test.qr
 * /test.qr.svg
 * /test.qr.png
 * /test.qr.jpg
 * /test.qr.eps
 */
function chromophobic_qr_render($request): void
{
    $requested_path = is_array($request)
        ? (string) ($request[0] ?? '')
        : (string) $request;

    $allowed_characters = yourls_make_regexp_pattern(
        yourls_get_shorturl_charset()
    );

    $matches_qr_url = preg_match(
        "@^([$allowed_characters]+)\.qr(?:\.(svg|png|jpe?g|eps))?/?$@i",
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

    $format = strtolower($matches[2] ?? 'svg');

    if ($format === 'jpeg') {
        $format = 'jpg';
    }

    $short_url = yourls_link($keyword);

    $safe_filename = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '-',
        $keyword
    );

    $download = isset($_GET['download'])
        && $_GET['download'] === '1';

    try {
        $qr_code = new QrCode(
            data: $short_url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 800,
            margin: 30,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );

        [$contents, $mime_type, $extension] =
            chromophobic_qr_generate_file($qr_code, $format);

        $disposition = $download ? 'attachment' : 'inline';

        header('Content-Type: ' . $mime_type);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=86400');
        header(
            'Content-Disposition: ' .
            $disposition .
            '; filename="' .
            $safe_filename .
            '.' .
            $extension .
            '"'
        );

        echo $contents;
        exit;
    } catch (Throwable $exception) {
        error_log(
            'Chromophobic QR error: ' .
            $exception->getMessage()
        );

        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');

        echo 'Der QR-Code konnte nicht erzeugt werden.';
        exit;
    }
}

/**
 * Erzeugt den Dateiinhalt für das gewünschte Format.
 *
 * @return array{0:string, 1:string, 2:string}
 */
function chromophobic_qr_generate_file(
    QrCode $qr_code,
    string $format
): array {
    switch ($format) {
        case 'svg':
            $result = (new SvgWriter())->write($qr_code);

            return [
                $result->getString(),
                $result->getMimeType(),
                'svg',
            ];

        case 'eps':
            $result = (new EpsWriter())->write($qr_code);

            return [
                $result->getString(),
                $result->getMimeType(),
                'eps',
            ];

        case 'png':
            chromophobic_qr_require_gd();

            $result = (new PngWriter())->write($qr_code);

            return [
                $result->getString(),
                $result->getMimeType(),
                'png',
            ];

        case 'jpg':
            chromophobic_qr_require_gd();

            $png_result = (new PngWriter())->write($qr_code);
            $image = imagecreatefromstring(
                $png_result->getString()
            );

            if (!$image instanceof GdImage) {
                throw new RuntimeException(
                    'PNG konnte nicht in JPG umgewandelt werden.'
                );
            }

            ob_start();

            $success = imagejpeg(
                $image,
                null,
                92
            );

            $jpg_contents = ob_get_clean();

            imagedestroy($image);

            if (!$success || !is_string($jpg_contents)) {
                throw new RuntimeException(
                    'JPG konnte nicht erzeugt werden.'
                );
            }

            return [
                $jpg_contents,
                'image/jpeg',
                'jpg',
            ];

        default:
            throw new InvalidArgumentException(
                'Nicht unterstütztes QR-Code-Format.'
            );
    }
}

/**
 * Prüft die für PNG und JPG erforderliche GD-Erweiterung.
 */
function chromophobic_qr_require_gd(): void
{
    if (
        !extension_loaded('gd')
        || !function_exists('imagepng')
    ) {
        throw new RuntimeException(
            'Die PHP-GD-Erweiterung ist nicht verfügbar.'
        );
    }
}

/**
 * Fügt den QR-Button zu jeder Adminzeile hinzu.
 */
function chromophobic_qr_add_admin_action(
    array $actions,
    string $keyword
): array {
    $keyword = yourls_sanitize_keyword($keyword);

    $actions['qr'] = [
        'href' => yourls_esc_url(
            yourls_link($keyword) . '.qr'
        ),
        'id' => 'qr-button-' . preg_replace(
            '/[^A-Za-z0-9_-]/',
            '-',
            $keyword
        ),
        'title' => 'QR-Code herunterladen',
        'anchor' => 'QR',
        'onclick' =>
            'chromophobicQrOpen(this.href); return false;',
    ];

    return $actions;
}

/**
 * QR-Icon und Dialog-Design im Adminbereich.
 */
function chromophobic_qr_admin_styles(
    $hook_arguments
): void {
    $context = is_array($hook_arguments)
        ? (string) ($hook_arguments[0] ?? '')
        : (string) $hook_arguments;

    if ($context !== 'index') {
        return;
    }
    ?>
    <style>
        td.actions .button_qr {
            background-color: transparent;
            background-repeat: no-repeat;
            background-position: center;
            background-size: 17px 17px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cg fill='%232a85b3'%3E%3Cpath d='M2 2h8v8H2zm2 2v4h4V4zM14 2h8v8h-8zm2 2v4h4V4zM2 14h8v8H2zm2 2v4h4v-4z'/%3E%3Cpath d='M13 13h3v3h-3zm4 0h2v2h-2zm3 0h2v5h-2zm-7 4h2v2h-2zm3 0h3v3h-3zm-3 4h5v2h-5zm7-2h2v4h-2z'/%3E%3C/g%3E%3C/svg%3E");
        }

        #chromophobic-qr-dialog {
            width: min(430px, calc(100vw - 40px));
            border: 3px solid #2a85b3;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            box-sizing: border-box;
        }

        #chromophobic-qr-dialog::backdrop {
            background: rgba(0, 0, 0, 0.55);
        }

        #chromophobic-qr-dialog h2 {
            margin: 0 0 16px;
            color: #2a85b3;
        }

        #chromophobic-qr-preview {
            display: block;
            width: min(280px, 100%);
            height: auto;
            margin: 0 auto 20px;
            border: 1px solid #c7e7ff;
            border-radius: 8px;
            background: #fff;
        }

        .chromophobic-qr-downloads {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .chromophobic-qr-downloads a {
            display: block;
            padding: 10px 14px;
            color: #2a85b3;
            background: #f3fafd;
            border: 1px solid #88c0eb;
            border-radius: 6px;
            font-weight: bold;
            text-decoration: none;
        }

        .chromophobic-qr-downloads a:hover {
            color: #fff;
            background: #2a85b3;
            text-decoration: none;
        }

        .chromophobic-qr-close {
            margin-top: 18px;
            padding: 8px 18px;
            cursor: pointer;
        }
    </style>
    <?php
}

/**
 * Downloadfenster und JavaScript im Adminbereich.
 */
function chromophobic_qr_admin_dialog(
    $hook_arguments
): void {
    $context = is_array($hook_arguments)
        ? (string) ($hook_arguments[0] ?? '')
        : (string) $hook_arguments;

    if ($context !== 'index') {
        return;
    }
    ?>
    <dialog id="chromophobic-qr-dialog">
        <h2>QR-Code herunterladen</h2>

        <img
            id="chromophobic-qr-preview"
            src=""
            alt="Vorschau des QR-Codes"
        >

        <div class="chromophobic-qr-downloads">
            <a id="chromophobic-qr-svg" href="" download>
                SVG herunterladen
            </a>

            <a id="chromophobic-qr-png" href="" download>
                PNG herunterladen
            </a>

            <a id="chromophobic-qr-jpg" href="" download>
                JPG herunterladen
            </a>

            <a id="chromophobic-qr-eps" href="" download>
                EPS herunterladen
            </a>
        </div>

        <form method="dialog">
            <button
                class="chromophobic-qr-close"
                type="submit"
            >
                Schliessen
            </button>
        </form>
    </dialog>

    <script>
        function chromophobicQrOpen(qrUrl) {
            const dialog = document.getElementById(
                'chromophobic-qr-dialog'
            );

            const baseUrl = qrUrl.replace(
                /\.qr(?:\.(?:svg|png|jpe?g|eps))?(?:\?.*)?$/i,
                '.qr'
            );

            const url = new URL(baseUrl);
            const filePart = url.pathname
                .split('/')
                .filter(Boolean)
                .pop() || 'qr-code.qr';

            const keyword = filePart.replace(/\.qr$/i, '');

            const formats = ['svg', 'png', 'jpg', 'eps'];

            document.getElementById(
                'chromophobic-qr-preview'
            ).src = baseUrl + '.svg';

            formats.forEach(function (format) {
                const link = document.getElementById(
                    'chromophobic-qr-' + format
                );

                link.href =
                    baseUrl +
                    '.' +
                    format +
                    '?download=1';

                link.download =
                    keyword +
                    '.' +
                    format;
            });

            dialog.showModal();
        }
    </script>
    <?php
}