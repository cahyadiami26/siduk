<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pdf.php';

$id = (int)get('id');
$token = get('token');
$p = permohonan_detail($id);
if (!$p) die('Data tidak ditemukan.');

// akses publik harus pakai token; petugas login boleh tanpa token
if (!is_logged() && $p['token'] !== $token) die('Akses ditolak: token tidak valid.');

$jenis = row("SELECT * FROM jenis_permohonan WHERE id = ?", [$p['jenis_id']]);
$labels = field_labels_map((int)$p['jenis_id']);
$loket = $p['loket_nomor'] ? trim($p['loket_nama'] . ' (' . $p['loket_nomor'] . ')') : '-';
$qrcode = qr_url(base_url() . '/cek-permohonan.php?nomor=' . urlencode($p['nomor_pendaftaran']) . '&token=' . $p['token'], 120);

$rowsIsian = '';
foreach ($labels as $k => $lbl) {
    $v = $p['data_isian'][$k] ?? '';
    if ($v && !is_numeric($v) && strtotime($v) !== false && preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) $v = tgl_id($v);
    $rowsIsian .= '<tr><td style="width:38%;color:#555">' . e($lbl) . '</td><td><b>' . e($v ?: '-') . '</b></td></tr>';
}
$rowsSyarat = '';
foreach ($p['syarat'] as $s) {
    $rowsSyarat .= '<tr><td style="padding:2px 6px">✔</td><td style="padding:2px 6px">' . e($s['nama_syarat']) . '</td></tr>';
}
if (!$rowsSyarat) $rowsSyarat = '<tr><td colspan="2" class="small">Tidak ada syarat diunggah</td></tr>';

$html = '
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . pdf_css() . '</style></head><body>
' . pdf_kop() . '
<h2 class="title">Bukti Pendaftaran Permohonan Dokumen Kependudukan</h2>
<p class="sub">Nomor: <span class="nomor-big">' . e($p['nomor_pendaftaran']) . '</span></p>
<table style="width:100%">
<tr>
  <td style="width:62%;vertical-align:top">
    <table class="info">
      <tr><td class="lbl">Nama Pemohon</td><td class="val">' . e($p['nama']) . '</td></tr>
      <tr><td class="lbl">NIK</td><td class="val">' . e($p['nik']) . '</td></tr>
      <tr><td class="lbl">No. HP / Email</td><td class="val">' . e($p['no_hp']) . ' · ' . e($p['email']) . '</td></tr>
      <tr><td class="lbl">Jenis Layanan</td><td class="val">' . e($jenis['nama']) . '</td></tr>
      <tr><td class="lbl">Tanggal Pendaftaran</td><td class="val">' . tgl_id($p['tgl_daftar'] . ' ' . $p['jam_daftar'], true, true) . '</td></tr>
      <tr><td class="lbl">Tgl. Verifikasi Berkas</td><td class="val" style="color:#b91c1c">' . tgl_id($p['tgl_verifikasi'], true) . '</td></tr>
      <tr><td class="lbl">Nomor Urut / Loket</td><td class="val">' . (int)$p['nomor_urut'] . ' / ' . e($loket) . '</td></tr>
    </table>
    <div class="box" style="margin-top:6px">
      <b style="font-size:8.5pt;color:#1e3a8a">Rincian Isian Permohonan</b>
      <table class="info" style="font-size:8pt;margin-top:2px">' . $rowsIsian . '</table>
    </div>
  </td>
  <td style="width:38%;vertical-align:top;padding-left:10px">
    <div style="text-align:center;border:1px solid #c8cfdd;border-radius:8px;padding:8px;background:#fbfcfe">
      <img src=' . $qrcode . '" alt="QR Code Verifikasi" style="width:118px;height:118px"><br>
      <span class="small">QR Verifikasi Pendaftaran</span>
    </div>
    <div class="box" style="margin-top:8px">
      <b style="font-size:8.5pt;color:#1e3a8a">Syarat Diunggah</b>
      <table style="width:100%;font-size:8pt;border-collapse:collapse">' . $rowsSyarat . '</table>
    </div>
  </td>
</tr>
</table>
<table style="width:100%;margin-top:6px"><tr>
  <td style="width:55%"><span class="small">Harap datang sesuai tanggal verifikasi berkas dengan membawa <b>berkas asli</b>.
  Bukti ini tidak berlaku jika data diubah. Pantau status via QR code.</span></td>
  <td style="text-align:right;font-size:8pt">' . e(setting('nama_dinas_short')) . ', ' . tgl_id($p['tgl_daftar']) . '<br>Petugas Penerima,<br><br><br>
  (<b>' . e($p['verifikator_nama'] ?? current_user()['nama'] ?? 'Sistem Online') . '</b>)</td>
</tr></table>
</body></html>';

pdf_output($html, 'bukti-pendaftaran-' . preg_replace('/[^\w\-]/', '', $p['nomor_pendaftaran']) . '.pdf', 'A5', 'landscape');
