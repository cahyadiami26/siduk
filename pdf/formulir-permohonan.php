<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pdf.php';

$id = (int)get('id');
if (!is_logged()) redirect('../login.php');

$p = permohonan_detail($id);
if (!$p) die('Data tidak ditemukan.');
$jenis = row("SELECT * FROM jenis_permohonan WHERE id = ?", [$p['jenis_id']]);
$labels = field_labels_map((int)$p['jenis_id']);

$rowsIsian = '';
foreach ($labels as $k => $lbl) {
    $v = $p['data_isian'][$k] ?? '';
    if ($v && preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) $v = tgl_id($v);
    $rowsIsian .= '<tr><td style="width:35%;background:#f6f8fc">' . e($lbl) . '</td><td>' . e($v ?: '') . '</td></tr>';
}

// Lampiran: gambar utk jpg/png, teks utk pdf
$lampiran = '';
foreach ($p['syarat'] as $s) {
    $abs = UPLOAD_DIR . '/' . $s['file_path'];
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $lampiran .= '<div style="page-break-before:always"></div>
      <div style="font-size:10pt;font-weight:bold;color:#1e3a8a">LAMPIRAN: ' . e($s['nama_syarat']) . '
      <span style="font-weight:normal;font-size:8pt;color:#666"> (' . e($s['original_name']) . ')</span></div>';
    if (is_file($abs) && in_array($ext, ['jpg','jpeg','png'])) {
        $lampiran .= '<div style="text-align:center;margin-top:8px"><img src="' . img_data_uri($s['file_path']) . '" style="max-width:170mm;max-height:160mm"></div>';
    } else {
        $lampiran .= '<div class="box small" style="margin-top:8px">Dokumen berformat PDF — dapat dibuka melalui sistem pada menu Pemrosesan Permohonan (tautan berkas terlampir pada detail permohonan).</div>';
    }
}

$ttd = img_data_uri(setting('ttd_kepala_dinas'));
$kadis = setting('ttd_kepala_dinas_nama') ?: setting('nama_dinas');

$html = '
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . pdf_css('@page{margin:14mm 16mm}') . '</style></head><body>
' . pdf_kop() . '
<h2 class="title">Formulir Permohonan Dokumen Kependudukan</h2>
<p class="sub">Nomor Pendaftaran: <b>' . e($p['nomor_pendaftaran']) . '</b> &nbsp;·&nbsp; Jenis: <b>' . e($jenis['nama']) . '</b></p>
<table class="grid">
  <tr><th colspan="2">A. Data Pemohon</th></tr>
  <tr><td style="width:35%;background:#f6f8fc">Nama Lengkap</td><td>' . e($p['nama']) . '</td></tr>
  <tr><td style="background:#f6f8fc">NIK</td><td>' . e($p['nik']) . '</td></tr>
  <tr><td style="background:#f6f8fc">Email</td><td>' . e($p['email']) . '</td></tr>
  <tr><td style="background:#f6f8fc">Nomor HP</td><td>' . e($p['no_hp']) . '</td></tr>
  <tr><td style="background:#f6f8fc">Tanggal Daftar</td><td>' . tgl_id($p['tgl_daftar'] . ' ' . $p['jam_daftar'], true, true) . '</td></tr>
  <tr><th colspan="2">B. Data Permohonan</th></tr>
  ' . $rowsIsian . '
  <tr><th colspan="2">C. Syarat Dokumen</th></tr>';
$i = 1;
foreach ($p['syarat'] as $s) {
    $html .= '<tr><td style="width:35%;background:#f6f8fc">' . $i++ . '. ' . e($s['nama_syarat']) . '</td><td>✔ Terunggah (' . e($s['original_name']) . ')</td></tr>';
}
$html .= '</table>
<div class="box small" style="margin-top:10px">Yang bertanda tangan di bawah ini menyatakan bahwa data dan dokumen yang disampaikan adalah benar dan dapat dipertanggungjawabkan sesuai ketentuan perundang-undangan.</div>
<table style="width:100%;margin-top:16px"><tr>
  <td style="width:50%;font-size:9pt;text-align:center">Pemohon,<br><br><br><br>
    <b>(' . e($p['nama']) . ')</b></td>
  <td style="width:50%;font-size:9pt;text-align:center">Mengetahui,<br>' . strtoupper(e(setting('nama_dinas'))) . '<br>' .
   ($ttd ? '<img src="' . $ttd . '" style="height:52px;margin:2px 0">' : '<br><br><br>') . '
    <b>' . e($kadis) . '</b><br><span class="small">NIP. ' . e(setting('ttd_kepala_dinas_nip')) . '</span></td>
</tr></table>' . $lampiran . '
</body></html>';

pdf_output($html, 'formulir-' . preg_replace('/[^\w\-]/', '', $p['nomor_pendaftaran']) . '.pdf', 'A4', 'portrait');
