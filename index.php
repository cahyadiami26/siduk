<?php
require_once __DIR__ . '/includes/helpers.php';
$page_title = 'Beranda';
$jenis = rows("SELECT * FROM jenis_permohonan WHERE aktif = 1 ORDER BY urutan");
$logoRel = setting('logo');
include __DIR__ . '/app/partials/header_public.php';
?>
<div class="hero">
  <div style="font-size:.8rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);font-weight:700;margin-bottom:10px">
    <?= e(setting('nama_dinas')) ?>
  </div>
  <h1>Layanan Administrasi Kependudukan<br>Lebih Cepat, Mudah &amp; Transparan</h1>
  <p>Ajukan permohonan dokumen kependudukan secara online, unduh bukti pendaftaran, pantau status permohonan, dan datang sesuai jadwal verifikasi berkas.</p>
  <a href="permohonan.php" class="btn btn-gold" style="padding:13px 26px;font-size:1rem">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
    Ajukan Permohonan Baru
  </a>
  <a href="cek-permohonan.php" class="btn btn-outline" style="padding:13px 26px;font-size:1rem;background:rgba(255,255,255,.1);color:#fff;border-color:rgba(255,255,255,.4)">Cek Status Permohonan</a>

  <div class="timeline">
    <span class="tl"><span class="node">1. Isi Form + Upload Syarat</span><span class="arr">→</span></span>
    <span class="tl"><span class="node">2. Terima Bukti Pendaftaran</span><span class="arr">→</span></span>
    <span class="tl"><span class="node">3. Datang Verifikasi Berkas</span><span class="arr">→</span></span>
    <span class="tl"><span class="node">4. Terima Bukti + Jadwal Ambil</span><span class="arr">→</span></span>
    <span class="tl"><span class="node">5. Ambil Dokumen → Selesai</span></span>
  </div>
</div>

<div class="public-wrap" style="padding-top:0">
  <div class="feature">
    <div class="f"><div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
      <b style="color:var(--navy)">Hemat Waktu</b><p class="small muted mt0">Pilih jadwal verifikasi berkas sesuai kuota harian yang tersedia — tanpa antre lama.</p></div>
    <div class="f"><div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
      <b style="color:var(--navy)">Terpercaya</b><p class="small muted mt0">Bukti pendaftaran &amp; verifikasi dilengkapi QR code validasi resmi dinas.</p></div>
    <div class="f"><div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
      <b style="color:var(--navy)">Notifikasi Email</b><p class="small muted mt0">Konfirmasi pendaftaran dikirim otomatis ke email Anda beserta rincian permohonan.</p></div>
    <div class="f"><div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17h5l-1.4-1.4A2 2 0 1118 14.2L15 17zM9 7H4m1.4 1.4A2 2 0 116 9.8L4 7m0 10h16"/></svg></div>
      <b style="color:var(--navy)">Gratis</b><p class="small muted mt0">Seluruh layanan administrasi kependudukan tidak dipungut biaya.</p></div>
  </div>

  <h3 style="margin-top:40px">Jenis Layanan Permohonan</h3>
  <p class="muted small mt0">Klik salah satu layanan untuk memulai pengajuan permohonan.</p>
  <div class="jenis-grid">
    <?php foreach ($jenis as $j): ?>
      <a class="jenis-card" href="permohonan.php?jenis=<?= (int)$j['id'] ?>">
        <div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6M7 4h10l4 4v12a1 1 0 01-1 1H4a1 1 0 01-1-1V8l4-4z"/></svg></div>
        <b><?= e($j['nama']) ?></b>
        <p><?= e($j['deskripsi']) ?></p>
        <span class="chip" style="margin-top:10px;display:inline-block">Kuota/hari: <?= (int)$j['kuota_per_hari'] ?></span>
      </a>
    <?php endforeach; ?>
    <?php if (!$jenis): ?><div class="empty">Belum ada jenis permohonan yang diaktifkan.</div><?php endif; ?>
  </div>

  <div class="card" style="margin-top:36px">
    <div class="bd flex spread">
      <div>
        <h4 class="mt0 mb0">Sudah pernah mendaftar?</h4>
        <p class="muted small mt0 mb0">Lacak status permohonan Anda menggunakan nomor pendaftaran dan NIK.</p>
      </div>
      <a class="btn btn-primary" href="cek-permohonan.php">Cek Status Permohonan →</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/app/partials/footer_public.php'; ?>
