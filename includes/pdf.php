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

/* ============================================================
 * QR CODE — generate via API online (dengan beberapa endpoint
 * cadangan) → cache permanen di /uploads/qr → kembalikan data
 * URI base64 sehingga selalu tampil di halaman web maupun pada
 * PDF domPDF (domPDF tidak dapat memuat URL remote).
 * ============================================================ */

/** Daftar endpoint layanan QR code online (dicoba berurutan). */
function qr_endpoints(string $text, int $size): array
{
    $enc = rawurlencode($text);
    return [
        'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&margin=0&data=' . $enc,
        'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&margin=0&qzone=2&data=' . $enc,
        'https://quickchart.io/qr?size=' . $size . '&margin=0&format=png&text=' . $enc,
        'https://chart.googleapis.com/chart?chs=' . $size . 'x' . $size . '&cht=qr&choe=UTF-8&chl=' . $enc,
    ];
}

/** Unduh file via cURL (handal walau allow_url_fopen dinonaktifkan). */
function qr_curl_get(string $url): ?string
{
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT      => 'SIDUK/1.0',
        CURLOPT_HTTPHEADER     => ['Accept: image/png,image/*'],
    ]);
    $data = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($data === false || $code !== 200) return null;
    return $data;
}

/** Unduh file via file_get_contents (butuh allow_url_fopen aktif). */
function qr_fopen_get(string $url): ?string
{
    if (!ini_get('allow_url_fopen')) return null;
    $ctx = stream_context_create([
        'http' => ['timeout' => 12, 'follow_location' => 1, 'user_agent' => 'SIDUK/1.0'],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    return $data === false ? null : $data;
}

/** Validasi bahwa hasil unduhan benar-benar gambar PNG/JPEG. */
function qr_is_image(?string $data): bool
{
    if ($data === null || strlen($data) < 100) return false;
    $sig = substr($data, 0, 12);
    return strpos($sig, 'PNG') !== false || strpos($sig, 'JFIF') !== false
        || strpos($sig, 'Exif') !== false || strncmp($data, "\xFF\xD8", 2) === 0;
}

/** Ambil citra QR dari API online (berbagai metode & endpoint). */
function qr_generate(string $text, int $size): ?string
{
    foreach (qr_endpoints($text, $size) as $url) {
        $data = qr_curl_get($url) ?? qr_fopen_get($url);
        if (qr_is_image($data)) return $data;
    }
    return null;
}

/** URL QR untuk atribut src <img> — mengembalikan data URI base64 (pasti tampil). */
function qr_url(string $text, int $size = 130): string
{
    $cacheDir = defined('UPLOAD_DIR') ? UPLOAD_DIR . '/qr' : __DIR__ . '/../uploads/qr';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
    $file = $cacheDir . '/qr_' . md5($text . '|' . $size) . '.png';

    // pakai cache bila ada
    if (is_file($file) && filesize($file) > 100) {
        $data = @file_get_contents($file);
        if (qr_is_image($data)) return 'data:image/' . (strpos(substr($data, 0, 8), 'PNG') !== false ? 'png' : 'jpeg') . ';base64,' . base64_encode($data);
    }

    $data = qr_generate($text, $size);
    if ($data) {
        @file_put_contents($file, $data);
        $mime = strpos(substr($data, 0, 8), 'PNG') !== false ? 'png' : 'jpeg';
        return 'data:image/' . $mime . ';base64,' . base64_encode($data);
    }

    // Semua API online gagal → placeholder SVG agar area QR tidak blank total.
    $ph = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '">'
        . '<rect width="100%" height="100%" fill="#f1f5f9" stroke="#cbd5e1"/>'
        . '<text x="50%" y="46%" font-size="' . max(8, (int)($size / 12)) . '" fill="#64748b" text-anchor="middle">QR Code</text>'
        . '<text x="50%" y="58%" font-size="' . max(7, (int)($size / 14)) . '" fill="#94a3b8" text-anchor="middle">tidak tersedia</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($ph);
}

/** Render tag <img> QR yang dijamin tampil: jika data URI gagal dimuat browser,
 * JS akan menggantinya dengan memuat langsung dari URL API online (src fallback). */
function qr_img(string $text, int $size = 130, array $attrs = []): string
{
    $uri = qr_url($text, $size);
    $fallback = htmlspecialchars(qr_endpoints($text, $size)[0], ENT_QUOTES);
    $extra = '';
    foreach ($attrs as $k => $v) $extra .= ' ' . $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES) . '"';
    $style = trim(($attrs['style'] ?? '') . ';width:' . $size . 'px;height:' . $size . 'px');
    return '<img src="' . htmlspecialchars($uri, ENT_QUOTES) . '"' .
        ' data-fallback="' . $fallback . '"' .
        ' onerror="this.onerror=null;if(this.dataset.loaded!=1){this.dataset.loaded=1;this.src=this.dataset.fallback}"' .
        ' style="' . htmlspecialchars($style, ENT_QUOTES) . '"' . $extra . '>';
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
