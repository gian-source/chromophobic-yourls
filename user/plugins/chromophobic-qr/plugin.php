<?php

/*
Plugin Name: Chromophobic QR Codes
Plugin URI: https://github.com/gian-source/chromophobic-yourls
Description: Selbst gehostete QR-Codes mit Downloadfunktion für YOURLS.
Version: 1.3.0
Author: gian-source
Author URI: https://github.com/gian-source
*/

use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
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
    $long_url = yourls_get_keyword_longurl($keyword);

    $target = ($_GET['target'] ?? 'short') === 'long'
        ? 'long'
        : 'short';

    $qr_target_url = $target === 'long'
        ? (string) $long_url
        : $short_url;

    if ($qr_target_url === '') {
        throw new RuntimeException(
            'Die Ziel-URL konnte nicht geladen werden.'
        );
    }

    $size_presets = [
        256 => 25.0,
        512 => 50.0,
        1024 => 100.0,
        2048 => 200.0,
    ];

    $requested_size = (int) ($_GET['size'] ?? 1024);
    $size = array_key_exists($requested_size, $size_presets)
        ? $requested_size
        : 1024;

    $vector_artboard_mm = $size_presets[$size];
    $margin = max(10, (int) round($size * 0.04));

    $safe_filename = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '-',
        $keyword
    );

    $download = isset($_GET['download'])
        && $_GET['download'] === '1';

    try {
        $qr_code = new QrCode(
            data: $qr_target_url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: $margin,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255, 127)
        );

        [$contents, $mime_type, $extension] =
            chromophobic_qr_generate_file(
                $qr_code,
                $format,
                $vector_artboard_mm
            );

        $disposition = $download ? 'attachment' : 'inline';

        header('Content-Type: ' . $mime_type);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=86400');
        header(
            'Content-Disposition: ' .
            $disposition .
            '; filename="' .
            $safe_filename .
            '-' .
            $target .
            '-' .
            $size .
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
    string $format,
    float $vector_artboard_mm
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
            return [
                chromophobic_qr_generate_vector_eps(
                    $qr_code,
                    $vector_artboard_mm
                ),
                'application/postscript',
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
            $source_image = imagecreatefromstring(
                $png_result->getString()
            );

            if (!$source_image instanceof GdImage) {
                throw new RuntimeException(
                    'PNG konnte nicht in JPG umgewandelt werden.'
                );
            }

            $width = imagesx($source_image);
            $height = imagesy($source_image);

            $jpg_image = imagecreatetruecolor(
                $width,
                $height
            );

            if (!$jpg_image instanceof GdImage) {
                imagedestroy($source_image);

                throw new RuntimeException(
                    'JPG-Arbeitsfläche konnte nicht erzeugt werden.'
                );
            }

            $white = imagecolorallocate(
                $jpg_image,
                255,
                255,
                255
            );

            imagefill(
                $jpg_image,
                0,
                0,
                $white
            );

            imagealphablending(
                $jpg_image,
                true
            );

            imagecopy(
                $jpg_image,
                $source_image,
                0,
                0,
                0,
                0,
                $width,
                $height
            );

            ob_start();

            $success = imagejpeg(
                $jpg_image,
                null,
                92
            );

            $jpg_contents = ob_get_clean();

            imagedestroy($source_image);
            imagedestroy($jpg_image);

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
 * Erzeugt ein echtes Vektor-EPS direkt aus der QR-Matrix.
 *
 * Es wird bewusst kein Hintergrund-Rechteck gezeichnet.
 */
function chromophobic_qr_generate_vector_eps(
    QrCode $qr_code,
    float $artboard_mm
): string {
    $matrix = (new MatrixFactory())->create($qr_code);

    $foreground = $qr_code->getForegroundColor();

    $red = $foreground->getRed() / 255;
    $green = $foreground->getGreen() / 255;
    $blue = $foreground->getBlue() / 255;

    $outer_size = (float) $matrix->getOuterSize();
    $block_size = (float) $matrix->getBlockSize();
    $block_count = $matrix->getBlockCount();
    $margin = (float) $matrix->getMarginLeft();

    // EPS arbeitet in PostScript-Punkten. Illustrator erhält dadurch
    // eine klar definierte quadratische Zeichenfläche in Millimetern.
    $artboard_points = $artboard_mm * 72 / 25.4;
    $scale = $artboard_points / $outer_size;

    $bounding_box = (int) ceil($artboard_points);
    $hires_box = sprintf('%.4F', $artboard_points);

    $lines = [
        '%!PS-Adobe-3.0 EPSF-3.0',
        '%%Creator: Chromophobic QR Codes',
        '%%BoundingBox: 0 0 ' .
            $bounding_box .
            ' ' .
            $bounding_box,
        '%%HiResBoundingBox: 0 0 ' .
            $hires_box .
            ' ' .
            $hires_box,
        '%%PageBoundingBox: 0 0 ' .
            $bounding_box .
            ' ' .
            $bounding_box,
        '%%LanguageLevel: 2',
        '%%Pages: 1',
        '%%EndComments',
        '%%Page: 1 1',
        '/F { rectfill } bind def',
        sprintf(
            '%.6F %.6F %.6F setrgbcolor',
            $red,
            $green,
            $blue
        ),
    ];

    for ($row = 0; $row < $block_count; ++$row) {
        for ($column = 0; $column < $block_count; ++$column) {
            if (
                1 !== $matrix->getBlockValue(
                    $block_count - 1 - $row,
                    $column
                )
            ) {
                continue;
            }

            $x = ($margin + ($block_size * $column)) * $scale;
            $y = ($margin + ($block_size * $row)) * $scale;
            $scaled_block_size = $block_size * $scale;

            $lines[] = sprintf(
                '%.10F %.10F %.10F %.10F F',
                $x,
                $y,
                $scaled_block_size,
                $scaled_block_size
            );
        }
    }

    $lines[] = 'showpage';
    $lines[] = '%%EOF';

    return implode("\n", $lines) . "\n";
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

        .chromophobic-qr-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin: 0 0 18px;
            text-align: left;
        }

        .chromophobic-qr-options label {
            display: grid;
            gap: 6px;
            margin: 0;
        }

        .chromophobic-qr-options span {
            font-size: 12px;
            font-weight: 700;
            color: #5f6368;
        }

        .chromophobic-qr-options select {
            width: 100%;
            min-height: 40px;
            box-sizing: border-box;
        }

        .chromophobic-qr-hint {
            margin: -8px 0 18px;
            font-size: 12px;
            line-height: 1.45;
            color: #6b7280;
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

        @media (max-width: 520px) {
            .chromophobic-qr-options {
                grid-template-columns: 1fr;
            }
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

        <div class="chromophobic-qr-options">
            <label>
                <span>QR-Ziel</span>
                <select id="chromophobic-qr-target">
                    <option value="short" selected>
                        Kurzlink (empfohlen)
                    </option>
                    <option value="long">
                        Original-URL
                    </option>
                </select>
            </label>

            <label>
                <span>Grösse</span>
                <select id="chromophobic-qr-size">
                    <option value="256">256 px / 25 mm</option>
                    <option value="512">512 px / 50 mm</option>
                    <option value="1024" selected>1024 px / 100 mm</option>
                    <option value="2048">2048 px / 200 mm</option>
                </select>
            </label>
        </div>

        <p class="chromophobic-qr-hint">
            Kurzlink: kompakter QR-Code, YOURLS-Statistik und Ziel später änderbar.
            SVG und EPS bleiben frei skalierbar.
        </p>

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
        let chromophobicQrBaseUrl = '';
        let chromophobicQrKeyword = '';

        function chromophobicQrBuildUrl(format, download) {
            const target = document.getElementById(
                'chromophobic-qr-target'
            ).value;

            const size = document.getElementById(
                'chromophobic-qr-size'
            ).value;

            const url = new URL(
                chromophobicQrBaseUrl + '.' + format
            );

            url.searchParams.set('target', target);
            url.searchParams.set('size', size);

            if (download) {
                url.searchParams.set('download', '1');
            }

            return url;
        }

        function chromophobicQrUpdate() {
            if (!chromophobicQrBaseUrl) {
                return;
            }

            const target = document.getElementById(
                'chromophobic-qr-target'
            ).value;

            const size = document.getElementById(
                'chromophobic-qr-size'
            ).value;

            document.getElementById(
                'chromophobic-qr-preview'
            ).src = chromophobicQrBuildUrl('svg', false).toString();

            ['svg', 'png', 'jpg', 'eps'].forEach(function (format) {
                const link = document.getElementById(
                    'chromophobic-qr-' + format
                );

                link.href = chromophobicQrBuildUrl(
                    format,
                    true
                ).toString();

                link.download =
                    chromophobicQrKeyword +
                    '-' +
                    target +
                    '-' +
                    size +
                    '.' +
                    format;
            });
        }

        function chromophobicQrOpen(qrUrl) {
            const dialog = document.getElementById(
                'chromophobic-qr-dialog'
            );

            chromophobicQrBaseUrl = qrUrl.replace(
                /\.qr(?:\.(?:svg|png|jpe?g|eps))?(?:\?.*)?$/i,
                '.qr'
            );

            const url = new URL(chromophobicQrBaseUrl);
            const filePart = url.pathname
                .split('/')
                .filter(Boolean)
                .pop() || 'qr-code.qr';

            chromophobicQrKeyword = filePart.replace(/\.qr$/i, '');

            document.getElementById(
                'chromophobic-qr-target'
            ).value = 'short';

            document.getElementById(
                'chromophobic-qr-size'
            ).value = '1024';

            chromophobicQrUpdate();
            dialog.showModal();
        }

        document.getElementById(
            'chromophobic-qr-target'
        ).addEventListener('change', chromophobicQrUpdate);

        document.getElementById(
            'chromophobic-qr-size'
        ).addEventListener('change', chromophobicQrUpdate);
    </script>
    <?php
}