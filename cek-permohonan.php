<?php
require_once __DIR__ . '/includes/helpers.php';

$page_title = 'Cek Status Permohonan';
$p = null; $found = false;

$nomor = post('nomor') ?: get('nomor');
$token = get('token');
$nik   = post('nik');

if ($nomor && $token) {
    // akses via link QR/email
    $p = row("SELECT p.*, j.nama AS jenis_nama, l.nomor AS loket_nomor, l.nama AS loket_nama
              FROM permohonan p JOIN jenis_permohonan j ON j.id=p.jenis_id
              LEFT JOIN loket l ON l.id=p.loket_id
              WHERE p.nomor_pendaftaran = ? AND p.token = ?", [$nomor, $token]);
} elseif ($nomor && $nik && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $p = row("SELECT p.*, j.nama AS jenis_nama, l.nomor AS loket_nomor, l.nama AS loket_nama
              FROM permohonan p JOIN jenis_permohonan j ON j.id=p.jenis_id
              LEFT JOIN loket l ON l.id=p.loket_id
              WHERE p.nomor_pendaftaran = ? AND p.nik = ?", [$nomor, $nik]);
    $found = (bool)$p;
}
if ($p) {
    $p['data_isian'] = json_decode($p['data_isian'] ?? '{}', true) ?: [];
    $p['syarat'] = rows("SELECT * FROM upload_syarat WHERE permohonan_id = ?", [$p['id']]);
    $labels = field_labels_map((int)$p['jenis_id']);
}

$status_steps = ['menunggu_verifikasi','diproses','selesai'];
include __DIR__ . '/app/partials/header_public.php';
?>
<div class="public-wrap" style="max-width:860px">
  <h2 class="mt0">Cek Status Permohonan</h2>
  <div class="card">
    <div class="bd">
      <?php if (!$p): ?>
      <form method="post" action="cek-permohonan.php">
        <?= csrf_field() ?>
        <div class="form-row">
          <div class="fgroup"><label class="flbl req">Nomor Pendaftaran</label>
            <input class="inp" name="nomor" required placeholder="mis. DKC/26/10/0001" value="<?= e($nomor) ?>"></div>
          <div class="fgroup"><label class="flbl req">NIK</label>
            <input class="inp" name="nik" required pattern="\d{16}" maxlength="16" inputmode="numeric" placeholder="16 digit NIK"></div>
        </div>
        <button class="btn btn-primary">🔍 Cari Permohonan</button>
        <?php if ($_SERVER['REQUEST_METHOD']==='POST' && !$found): ?>
          <div class="alert alert-danger" style="margin-top:14px">Nomor pendaftaran / NIK tidak ditemukan. Periksa kembali.</div>
        <?php endif; ?>
      </form>
      <?php else: ?>
      <div class="flex spread">
        <div>
          <div class="small muted">Nomor Pendaftaran</div>
          <div style="font-size:1.3rem;font-weight:800;color:var(--navy)"><?= e($p['nomor_pendaftaran']) ?></div>
          <div class="small muted mt0"><?= e($p['nama']) ?> — <?= e($p['jenis_nama']) ?></div>
        </div>
        <div><?= status_badge($p['status']) ?></div>
      </div>

      <?php if ($p['status'] !== 'dibatalkan'):
        $idx = array_search($p['status'], $status_steps); ?>
      <div class="steps" style="justify-content:flex-start;margin:20px 0 8px">
        <div class="step <?= $idx>=0?'done':'on' ?>">✓ Diajukan</div>
        <div class="step <?= $idx>=1?'done':($p['status']==='menunggu_verifikasi'?'on':'') ?>">
          <?= $idx>=1?'✓':'○' ?> Verifikasi Berkas<?= $p['tgl_verifikasi']?' ('.tgl_id($p['tgl_verifikasi']).')':'' ?></div>
        <div class="step <?= $idx>=2?'done':'' ?>">Diproses<?= $p['tgl_pengambilan']?' (ambil '.tgl_id($p['tgl_pengambilan']).')':'' ?></div>
        <div class="step <?= $idx>=2?'done on':'' ?>">✓ Selesai</div>
      </div>
      <?php endif; ?>

      <table class="tbl" style="margin-top:12px">
        <tr><td class="muted" style="width:38%">Tanggal Pendaftaran</td><td><?= tgl_id($p['tgl_daftar'], true, true) ?></td></tr>
        <tr><td class="muted">Jadwal Verifikasi Berkas</td><td><b><?= tgl_id($p['tgl_verifikasi'], true) ?></b> — Loket <?= e($p['loket_nomor'] ?: '-') ?> (No. Urut <?= (int)$p['nomor_urut'] ?>)</td></tr>
        <?php if ($p['tgl_pengambilan']): ?>
        <tr><td class="muted">Jadwal Pengambilan Dokumen</td><td><b style="color:var(--green)"><?= tgl_id($p['tgl_pengambilan'], true) ?></b></td></tr>
        <?php endif; ?>
        <?php if ($p['diserahkan_pada']): ?>
        <tr><td class="muted">Diserahkan Pada</td><td><?= tgl_id($p['diserahkan_pada'], true, true) ?> (<?= $p['penyerahan_siapa']==='diwakilkan' ? 'diwakili ' . e($p['penjamin_nama']) : 'yang bersangkutan' ?>)</td></tr>
        <?php endif; ?>
        <?php if ($p['status']==='dibatalkan'): ?>
        <tr><td class="muted">Alasan Pembatalan</td><td class="small"><?= nl2br(e($p['alasan_batal'])) ?></td></tr>
        <?php endif; ?>
      </table>

      <?php if ($p['syarat']): ?>
      <h5 style="margin-top:18px">Syarat yang Diunggah</h5>
      <ul class="list-latest">
        <?php foreach ($p['syarat'] as $s): ?>
          <li><span>📄 <?= e($s['nama_syarat']) ?></span><span class="small muted"><?= e($s['original_name']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <div class="flex" style="margin-top:18px">
        <a class="btn btn-outline btn-sm" href="pdf/bukti-pendaftaran.php?id=<?= (int)$p['id'] ?>&token=<?= e($p['token']) ?>" target="_blank">🖨 Bukti Pendaftaran (A5)</a>
        <?php if (in_array($p['status'], ['diproses','selesai'])): ?>
        <a class="btn btn-outline btn-sm" href="pdf/bukti-verifikasi.php?id=<?= (int)$p['id'] ?>&token=<?= e($p['token']) ?>" target="_blank">🖨 Bukti Verifikasi + Jadwal Ambil (A5)</a>
        <?php endif; ?>
        <a class="btn btn-outline btn-sm" href="cek-permohonan.php">Cari Lain</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/app/partials/footer_public.php'; ?>
