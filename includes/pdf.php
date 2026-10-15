<?php
require_once __DIR__ . '/functions.php';

/**
 * Helper PDF: memuat domPDF (instalasi manual di /lib/dompdf), membuat header kop
 * dinas, dan merender HTML ke A5 landscape / A4.
 */
function pdf(): \Dompdf\Dompdf
{
    require_once BASE_PATH . '/lib/dompdf/autoload.inc.php';
    $dom = new \Dompdf\Dompdf();
    $paperSize = new \Dompdf\Options();
    return $dom;
}

/** Render & output PDF inline (preview di browser) */
function pdf_output(string $html, string $filename, string $paper = 'A5', string $orientation = 'landscape'): void
{
    require_once BASE_PATH . '/lib/dompdf/autoload.inc.php';
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper($paper, $orientation);
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => false]);
}

/** Gambar dari path file upload → data URI utk domPDF */
function img_data_uri(?string $relPath, int $maxW = 200): string
{
    if (!$relPath) return '';
    $abs = UPLOAD_DIR . '/' . ltrim($relPath, '/');
    if (!is_file($abs)) return '';
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $mime = in_array($ext, ['jpg','jpeg']) ? 'image/jpeg' : ($ext === 'png' ? 'image/png' : 'image/gif');
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($abs));
}

/** URL QR Code online (api.qrserver.com) berisi nomor pendaftaran + token */
function qr_url(string $text, int $size = 130): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size .
           '&margin=0&data=' . urlencode($text);
}

/** Header kop surat standar dinas */
function pdf_kop(array $opt = []): string
{
    $logo = img_data_uri(setting('logo'));
    $dinas = strtoupper(e(setting('nama_dinas')));
    $alamat = e(setting('alamat'));
    $telp = e(setting('telepon'));
    $email = e(setting('email'));
    $web = e(setting('website'));
    $logoHtml = $logo ? '<td style="width:78px;padding-right:12px"><img src="' . $logo . '" style="width:70px"></td>' : '';
    return '
    <table style="width:100%;border-collapse:collapse">
      <tr>' . $logoHtml . '
        <td style="text-align:center;line-height:1.25">
          <div style="font-size:14pt;font-weight:bold;text-transform:uppercase">' . $dinas . '</div>
          <div style="font-size:8.5pt;margin-top:2px">' . $alamat . '</div>
          <div style="font-size:8pt">Telp. ' . $telp . ' &nbsp;|&nbsp; Email: ' . $email . ' &nbsp;|&nbsp; ' . $web . '</div>
        </td>
        <td style="width:78px"></td>
      </tr>
    </table>
    <div style="border-top:3px solid #1e3a8a;border-bottom:1px solid #1e3a8a;height:4px;margin:4px 0 10px"></div>';
}

/** CSS dasar dokumen PDF */
function pdf_css(string $paperNote = ''): string
{
    return '
    @page { margin: 10mm 12mm; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 9pt; color: #222; margin:0; }
    h2.title { text-align:center; font-size: 11.5pt; margin: 2px 0 2px; text-transform: uppercase; letter-spacing:.5px }
    p.sub { text-align:center; font-size: 8.5pt; color:#555; margin:0 0 8px }
    table.info { width:100%; border-collapse: collapse; margin: 4px 0 }
    table.info td { padding: 3.5px 6px; vertical-align: top; border-bottom:1px solid #edf0f5; font-size:8.7pt }
    table.info td.lbl { width:32%; color:#555 }
    table.info td.val { font-weight:600 }
    table.grid { width:100%; border-collapse: collapse; margin:4px 0 }
    table.grid th, table.grid td { border:1px solid #c8cfdd; padding:4px 6px; font-size:8.5pt }
    table.grid th { background:#eef2fa; text-align:left }
    .box { border:1px solid #c8cfdd; border-radius:6px; padding:8px 10px; background:#fbfcfe }
    .sig { margin-top:10px }
    .small { font-size:7.5pt; color:#666 }
    .nomor-big { font-size:13pt; font-weight:bold; color:#1e3a8a; letter-spacing:1px }
    ' . $paperNote;
}
