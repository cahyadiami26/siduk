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

/** QR Code: unduh dari API online (api.qrserver.com) → cache permanen di /uploads/qr
 * → kembalikan data URI base64 sehingga selalu tampil baik di halaman web maupun PDF domPDF
 * (domPDF tidak memuat URL remote). Jika API gagal, dicoba endpoint alternatif quickchart.io,
 * lalu fallback ke URL API langsung (browser masih bisa menampilkannya di halaman HTML). */
function qr_api_url(string $text, int $size): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size .
           '&margin=0&data=' . urlencode($text);
}

function qr_fetch(string $url): ?string
{
    $ctx = stream_context_create([
        'http' => ['timeout' => 10, 'follow_location' => 1, 'user_agent' => 'SIDUK/1.0'],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) return null;
    // pastikan hasil unduhan benar-benar gambar (PNG/JPEG), bukan halaman error
    if (strlen($data) < 200) return null;
    $sig = substr($data, 0, 8);
    if (strpos($sig, 'PNG') !== false || strpos($sig, 'JFIF') !== false || strpos($sig, 'Exif') !== false) {
        return $data;
    }
    return null;
}

function qr_url(string $text, int $size = 130): string
{
    $cacheDir = defined('UPLOAD_DIR') ? UPLOAD_DIR . '/qr' : __DIR__ . '/../uploads/qr';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
    $file = $cacheDir . '/qr_' . md5($text . '|' . $size) . '.png';

    $data = (is_file($file) && filesize($file) > 0) ? @file_get_contents($file) : null;

    if (!$data) {
        $data = qr_fetch(qr_api_url($text, $size));
        if (!$data) {   // endpoint cadangan (tetap layanan QR API online)
            $data = qr_fetch('https://quickchart.io/qr?size=' . $size . '&margin=0&format=png&text=' . urlencode($text));
        }
        if ($data) @file_put_contents($file, $data);
    }

    if ($data) return 'data:image/png;base64,' . base64_encode($data);
    // fallback terakhir: URL API langsung (browser bisa memuatnya pada halaman HTML).
    // Jika itu pun gagal dimuat, tampilkan placeholder terkode-data agar area QR tidak blank.
    $ph = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '">'
        . '<rect width="100%" height="100%" fill="#f1f5f9" stroke="#cbd5e1"/>'
        . '<text x="50%" y="46%" font-size="' . max(8, (int)($size / 12)) . '" fill="#64748b" text-anchor="middle">QR Code</text>'
        . '<text x="50%" y="58%" font-size="' . max(7, (int)($size / 14)) . '" fill="#94a3b8" text-anchor="middle">tidak tersedia</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($ph);
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
