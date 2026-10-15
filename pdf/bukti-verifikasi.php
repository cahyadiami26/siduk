<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pdf.php';

$id = (int)get('id');
$token = get('token');
$p = permohonan_detail($id);
if (!$p) die('Data tidak ditemukan.');
if (!is_logged() && $p['token'] !== $token) die('Akses ditolak: token tidak valid.');

$jenis = row("SELECT * FROM jenis_permohonan WHERE id = ?", [$p['jenis_id']]);
$qrcode = qr_url(base_url() . '/cek-permohonan.php?nomor=' . urlencode($p['nomor_pendaftaran']) . '&token=' . $p['token'], 120);
$petugas = $p['diverifikasi_oleh'] ? row("SELECT * FROM users WHERE id = ?", [$p['diverifikasi_oleh']]) : null;
$tglAmbil = $p['tgl_pengambilan'] ? tgl_id($p['tgl_pengambilan'], true) : '-';

$html = '
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . pdf_css() . '</style></head><body>
' . pdf_kop() . '
<h2 class="title">Bukti Verifikasi Berkas &amp; Jadwal Pengambilan Dokumen</h2>
<p class="sub">Nomor: <span class="nomor-big">' . e($p['nomor_pendaftaran']) . '</span></p>
<table style="width:100%">
<tr>
  <td style="width:64%;vertical-align:top">
    <table class="info">
      <tr><td class="lbl">Nama Pemohon</td><td class="val">' . e($p['nama']) . ' (NIK ' . e($p['nik']) . ')</td></tr>
      <tr><td class="lbl">Jenis Permohonan</td><td class="val">' . e($jenis['nama']) . '</td></tr>
      <tr><td class="lbl">Tanggal Pendaftaran</td><td class="val">' . tgl_id($p['tgl_daftar']) . '</td></tr>
      <tr><td class="lbl">Tanggal Verifikasi Berkas</td><td class="val">' . tgl_id($p['tgl_verifikasi_akhir'] ?: $p['tgl_verifikasi'], true, true) . '</td></tr>
      <tr><td class="lbl"><b>Tanggal Pengambilan Dokumen</b></td><td class="val" style="color:#15803d;font-size:10pt">' . $tglAmbil . '</td></tr>
      <tr><td class="lbl">Status</td><td class="val">' . e(ucwords(str_replace('_',' ', $p['status']))) . '</td></tr>
    </table>
    <div class="box" style="margin-top:6px">
      <span class="small">Serahkan bukti ini pada loket penyerahan dokumen sesuai tanggal pengambilan.
      Pengambilan dapat diwakilkan dengan menunjukkan KTP asli penjamin dan surat kuasa bila diperlukan.</span>
    </div>
  </td>
  <td style="width:36%;vertical-align:top;padding-left:10px;text-align:center">
    <img src="' . $qrcode . '" alt="QR Code Verifikasi" style="width:118px;height:118px;border:1px solid #c8cfdd;border-radius:8px;background:#fff;padding:5px"><br>
    <span class="small">QR Validasi Bukti Verifikasi</span>
    <div style="font-size:8pt;margin-top:10px;text-align:right;padding-right:6px">
      ' . e(setting('nama_dinas_short')) . ', ' . tgl_id(date('Y-m-d')) . '<br>
      Petugas Verifikasi Loket,<br><br><br>
      <b>' . e($petugas['nama'] ?? '-') . '</b><br>
      <span class="small">NIP. ' . e($petugas['nip'] ?? '-') . '</span>
    </div>
  </td>
</tr>
</table>
</body></html>';

pdf_output($html, 'bukti-verifikasi-' . preg_replace('/[^\w\-]/', '', $p['nomor_pendaftaran']) . '.pdf', 'A5', 'landscape');
