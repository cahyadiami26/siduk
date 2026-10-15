<?php
require_once __DIR__ . '/includes/helpers.php';

$sess = $_SESSION['pendaftaran_sukses'] ?? null;
if (!$sess) redirect('permohonan.php');
$pid = (int)$sess['id'];
$p = permohonan_detail($pid);
unset($_SESSION['pendaftaran_sukses']);

$page_title = 'Pendaftaran Berhasil';
include __DIR__ . '/app/partials/header_public.php';
?>
<div class="public-wrap">
  <div class="card" style="overflow:hidden">
    <div style="background:linear-gradient(120deg,#15803d,#22c55e);color:#fff;padding:34px;text-align:center">
      <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <h2 style="color:#fff;margin:12px 0 4px;font-size:1.5rem">Permohonan Berhasil Didaftarkan</h2>
      <p style="margin:0;color:#dcfce7">Nomor pendaftaran Anda:</p>
      <div style="font-size:1.5rem;font-weight:800;letter-spacing:1px;margin-top:6px"><?= e($p['nomor_pendaftaran']) ?></div>
    </div>
    <div class="bd">
      <?php if ($sess['email_sent']): ?>
        <div class="alert alert-success">✉ Email konfirmasi telah dikirim ke <b><?= e($p['email']) ?></b>.</div>
      <?php else: ?>
        <div class="alert alert-warning">⚠ Email konfirmasi gagal terkirim (masalah konfigurasi mail server). Simpan/cetak bukti pendaftaran di bawah ini sebagai arsip Anda.</div>
      <?php endif; ?>

      <div class="grid-2">
        <div>
          <table class="tbl">
            <tr><td class="muted">Nama Pemohon</td><td><b><?= e($p['nama']) ?></b></td></tr>
            <tr><td class="muted">NIK</td><td><?= e($p['nik']) ?></td></tr>
            <tr><td class="muted">Jenis Permohonan</td><td><span class="chip"><?= e($p['jenis_nama']) ?></span></td></tr>
            <tr><td class="muted">Tanggal Pendaftaran</td><td><?= tgl_id($p['tgl_daftar'], true, true) ?></td></tr>
            <tr><td class="muted"><b>Tanggal Verifikasi Berkas</b></td><td><b style="color:var(--navy)"><?= tgl_id($p['tgl_verifikasi'], true) ?></b></td></tr>
            <tr><td class="muted">Nomor Urut</td><td><b><?= (int)$p['nomor_urut'] ?></b></td></tr>
            <tr><td class="muted">Loket</td><td><b><?= e($p['loket_nama'] ?: '-') ?> <?= $p['loket_nomor'] ? '(' . e($p['loket_nomor']) . ')' : '' ?></b></td></tr>
          </table>
        </div>
        <div style="text-align:center;background:var(--sky);border-radius:12px;padding:20px">
          <img src="<?= e(qr_url(base_url() . '/cek-permohonan.php?nomor=' . urlencode($p['nomor_pendaftaran']) . '&token=' . $p['token'], 170)) ?>"
               alt="QR Verifikasi" style="width:170px;height:170px;background:#fff;padding:8px;border-radius:10px">
          <p class="small muted mt0">QR Code validasi pendaftaran.<br>Scan untuk melihat status permohonan.</p>
        </div>
      </div>

      <div class="flex" style="margin-top:22px">
        <a class="btn btn-primary" href="pdf/bukti-pendaftaran.php?id=<?= $p['id'] ?>&token=<?= e($p['token']) ?>" target="_blank">
          🖨 Cetak Bukti Pendaftaran (PDF A5)</a>
        <a class="btn btn-outline" href="cek-permohonan.php?nomor=<?= e(urlencode($p['nomor_pendaftaran'])) ?>&token=<?= e($p['token']) ?>">Lihat Status Online</a>
        <a class="btn btn-outline" href="index.php">Kembali ke Beranda</a>
      </div>

      <div class="alert alert-info" style="margin-top:20px">
        <div><b>Langkah selanjutnya:</b> datang ke kantor dinas pada tanggal verifikasi berkas yang tertera,
        dengan membawa <b>berkas asli</b> sesuai syarat, tunjukkan nomor pendaftaran / QR code ini di loket
        <?= e($p['loket_nomor'] ?: '') ?>.</div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/app/partials/footer_public.php'; ?>
