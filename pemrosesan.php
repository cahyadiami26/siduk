<?php
require_once __DIR__ . '/includes/helpers.php';
require_role(['petugas', 'admin', 'kepala_dinas', 'kepala_bidang']);
$u = current_user();

/* ---------- Aksi POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = post('aksi');
    $id   = (int)post('id');
    $p    = $id ? row("SELECT * FROM permohonan WHERE id = ?", [$id]) : null;
    if (!$p) { flash_set('danger', 'Permohonan tidak ditemukan.'); redirect('pemrosesan.php'); }

    if ($aksi === 'verifikasi') {
        if ($p['status'] !== 'menunggu_verifikasi') { flash_set('warning', 'Permohonan ini sudah diproses.'); redirect('pemrosesan.php'); }
        $tglAmbil = post('tgl_pengambilan');
        $ts = strtotime($tglAmbil);
        if (!$ts || date('Y-m-d', $ts) <= today()) {
            flash_set('danger', 'Tanggal pengambilan harus setelah hari ini.'); redirect('pemrosesan.php?detail=' . $id);
        }
        $loketV = pick_loket((int)$p['jenis_id'], 'verifikasi');
        q("UPDATE permohonan SET status='diproses', tgl_pengambilan=?, diverifikasi_oleh=?, tgl_verifikasi_akhir=?, loket_id=? WHERE id=?",
          [date('Y-m-d', $ts), $u['id'], date('Y-m-d H:i:s'), $loketV['id'] ?? $p['loket_id'], $id]);
        log_activity('Memverifikasi berkas permohonan', 'Pemrosesan',
                     "{$p['nomor_pendaftaran']} — {$p['nama']}, jadwal ambil " . date('Y-m-d', $ts));
        flash_set('success', 'Berkas terverifikasi. Status diubah menjadi DIPROSES. Cetak bukti verifikasi untuk pemohon.');
        redirect('pemrosesan.php?cetak=' . $id);
    }

    if ($aksi === 'batal') {
        if ($p['status'] === 'selesai') { flash_set('danger', 'Permohonan selesai tidak dapat dibatalkan.'); redirect('pemrosesan.php'); }
        $alasan = post('alasan_batal');
        if (strlen($alasan) < 5) { flash_set('danger', 'Alasan pembatalan wajib diisi (min. 5 karakter).'); redirect('pemrosesan.php?detail=' . $id); }
        q("UPDATE permohonan SET status='dibatalkan', alasan_batal=?, diverifikasi_oleh=? WHERE id=?",
          [$alasan, $u['id'], $id]);
        log_activity('Membatalkan permohonan', 'Pemrosesan', "{$p['nomor_pendaftaran']} — alasan: $alasan");
        flash_set('success', 'Permohonan dibatalkan.');
        redirect('pemrosesan.php');
    }
}

/* ---------- Filter list ---------- */
$fJenis = (int)get('jenis');
$fTgl   = get('tgl') !== '' ? get('tgl') : today();
$fCari  = get('cari');
$fStat  = get('status');

$where = []; $params = [];
if ($fJenis) { $where[] = "p.jenis_id = ?"; $params[] = $fJenis; }
if ($fTgl !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fTgl)) { $where[] = "p.tgl_verifikasi = ?"; $params[] = $fTgl; }
if ($fCari !== '') { $where[] = "(p.nomor_pendaftaran LIKE ? OR p.nama LIKE ? OR p.nik LIKE ?)"; $like = "%$fCari%"; array_push($params, $like, $like, $like); }
if (in_array($fStat, ['menunggu_verifikasi','diproses','selesai','dibatalkan'])) { $where[] = "p.status = ?"; $params[] = $fStat; }
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rows = rows("SELECT p.*, j.nama AS jenis_nama, j.kode AS jenis_kode, l.nomor AS loket_nomor, l.nama AS loket_nama,
              u.nama as verifikator
              FROM permohonan p
              JOIN jenis_permohonan j ON j.id = p.jenis_id
              LEFT JOIN loket l ON l.id = p.loket_id
              LEFT JOIN users u ON u.id = p.diverifikasi_oleh
              $wsql ORDER BY p.status='menunggu_verifikasi' DESC, p.tgl_verifikasi, p.nomor_urut, p.id DESC LIMIT 300", $params);
$totalFiltered = count($rows);
$semuaJenis = rows("SELECT id, kode, nama FROM jenis_permohonan ORDER BY urutan");

/* ---------- Detail modal data ---------- */
$detail = null;
if (get('detail')) {
    $detail = permohonan_detail((int)get('detail'));
    if ($detail) $detail['labels'] = field_labels_map((int)$detail['jenis_id']);
}
$cetakId = (int)get('cetak');

$page_title = 'Pemrosesan Permohonan';
include __DIR__ . '/app/partials/header_dash.php';
?>

<?php if ($cetakId):
    $cp = row("SELECT * FROM permohonan WHERE id = ?", [$cetakId]);
    if ($cp && $cp['status'] === 'diproses'): ?>
<div class="alert alert-success" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
  <span><b>Verifikasi berhasil.</b> Silakan cetak dokumen untuk <?= e($cp['nomor_pendaftaran']) ?> (<?= e($cp['nama']) ?>).</span>
  <span style="white-space:nowrap">
    <a class="btn btn-gold btn-sm" target="_blank" href="pdf/bukti-verifikasi.php?id=<?= $cetakId ?>">🖨 Cetak Bukti Verifikasi (A5)</a>
    <a class="btn btn-primary btn-sm" target="_blank" href="pdf/formulir-permohonan.php?id=<?= $cetakId ?>">📄 Cetak Formulir + Syarat (A4)</a>
  </span>
</div>
<?php endif; endif; ?>

<div class="card">
  <div class="hd"><h5>Daftar Permohonan Masuk</h5>
    <span class="small muted"><?= numfmt($totalFiltered) ?> data ditampilkan</span></div>
  <div class="bd">
    <form method="get" class="filterbar" style="margin-bottom:16px">
      <div class="fgroup"><label class="flbl">Jenis Permohonan</label>
        <select class="inp" name="jenis">
          <option value="0">Semua Jenis</option>
          <?php foreach ($semuaJenis as $j): ?>
            <option value="<?= (int)$j['id'] ?>" <?= $fJenis === (int)$j['id'] ? 'selected' : '' ?>><?= e($j['nama']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="fgroup"><label class="flbl">Tanggal Verifikasi</label>
        <input class="inp" type="date" name="tgl" value="<?= e($fTgl) ?>"></div>
      <div class="fgroup"><label class="flbl">Status</label>
        <select class="inp" name="status">
          <option value="">Semua Status</option>
          <?php foreach (['menunggu_verifikasi'=>'Menunggu Verifikasi','diproses'=>'Diproses','selesai'=>'Selesai','dibatalkan'=>'Dibatalkan'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $fStat === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="fgroup" style="flex:1;min-width:220px"><label class="flbl">Cari No. Pendaftaran / Nama / NIK</label>
        <input class="inp" name="cari" value="<?= e($fCari) ?>" placeholder="mis. DKC/2026/1/12"></div>
      <div class="fgroup"><button class="btn btn-primary">Terapkan</button>
        <a class="btn btn-outline" href="pemrosesan.php">Reset</a></div>
    </form>

    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>No. Pendaftaran</th><th>Pemohon</th><th>Jenis Layanan</th><th>Tgl Verifikasi</th><th>Urut · Loket</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><b><?= e($r['nomor_pendaftaran']) ?></b><br><span class="small muted"><?= tgl_id($r['tgl_daftar'], false, true) ?></span></td>
            <td><?= e($r['nama']) ?><br><span class="small muted">NIK <?= e($r['nik']) ?></span></td>
            <td><span class="chip"><?= e($r['jenis_kode']) ?></span> <span class="small"><?= e($r['jenis_nama']) ?></span></td>
            <td><?= tgl_id($r['tgl_verifikasi'], true) ?></td>
            <td><b>#<?= (int)$r['nomor_urut'] ?></b> · <?= e($r['loket_nomor'] ?: '-') ?></td>
            <td><?= status_badge($r['status']) ?><?= $r['status']==='dibatalkan' && $r['alasan_batal'] ? '<br><span class="small muted">'.e(mb_strimwidth($r['alasan_batal'],0,40,'…')).'</span>' : '' ?></td>
            <td style="white-space:nowrap">
              <a class="btn btn-outline btn-sm" href="pemrosesan.php?detail=<?= (int)$r['id'] ?>&jenis=<?= $fJenis ?: '' ?>&tgl=<?= e($fTgl) ?>&cari=<?= urlencode($fCari) ?>">View</a>
              <?php if ($r['status'] === 'menunggu_verifikasi'): ?>
                <a class="btn btn-primary btn-sm" href="pemrosesan.php?proses=<?= (int)$r['id'] ?>">Proses</a>
                <a class="btn btn-danger btn-sm" href="pemrosesan.php?batalkan=<?= (int)$r['id'] ?>&detail=<?= (int)$r['id'] ?>">Batal</a>
              <?php elseif ($r['status'] === 'diproses'): ?>
                <a class="btn btn-gold btn-sm" target="_blank" href="pdf/bukti-verifikasi.php?id=<?= (int)$r['id'] ?>">Bukti ✓</a>
              <?php else: ?>
                <a class="btn btn-info btn-sm" target="_blank" href="pdf/formulir-permohonan.php?id=<?= (int)$r['id'] ?>">Formulir</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">Tidak ada permohonan pada filter ini.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php /* ===== MODAL DETAIL / PROSES / BATALKAN ===== */
$openDetail = $detail !== null;
$prosesId = (int)get('proses');
$batalkanId = (int)get('batalkan');
$pr = $prosesId ? permohonan_detail($prosesId) : null;
if ($pr) $pr['labels'] = field_labels_map((int)$pr['jenis_id']);
$bk = $batalkanId ? row("SELECT * FROM permohonan WHERE id = ?", [$batalkanId]) : null;
$modalOpen = ($openDetail || $pr || $bk) ? ' open' : '';
?>
<div class="modal-bg<?= $modalOpen ?>" id="m-detail">
  <div class="modal" style="max-width:680px">
    <div class="hd"><h5>Detail Permohonan</h5>
      <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('m-detail')">✕</button></div>
    <div class="bd">
      <?php $d = $detail ?: $pr ?: ($bk ? permohonan_detail((int)$bk['id']) : null);
      if ($d): if (empty($d['labels'])) $d['labels'] = field_labels_map((int)$d['jenis_id']); ?>
        <p style="margin:0"><span class="chip"><?= e($d['jenis_kode']) ?></span> <b><?= e($d['jenis_nama']) ?></b>
           &nbsp; <?= status_badge($d['status']) ?></p>
        <h6 style="margin:12px 0 4px;font-size:.95rem;color:var(--navy)"><?= e($d['nomor_pendaftaran']) ?></h6>
        <table class="tbl defn" style="width:100%">
          <tr><td class="small muted" style="width:35%">Nama / NIK</td><td><?= e($d['nama']) ?> · <?= e($d['nik']) ?></td></tr>
          <tr><td class="small muted">Email / No. HP</td><td><?= e($d['email']) ?> · <?= e($d['no_hp']) ?></td></tr>
          <tr><td class="small muted">Tgl Daftar</td><td><?= tgl_id($d['tgl_daftar'], false, true) ?> · Urut #<?= (int)$d['nomor_urut'] ?> Loket <?= e($d['loket_nomor'] ?: '-') ?></td></tr>
          <tr><td class="small muted">Tgl Verifikasi</td><td><?= tgl_id($d['tgl_verifikasi'], true) ?><?= $d['tgl_verifikasi_akhir'] ? ' · <span class="small">diverifikasi ' . tgl_id($d['tgl_verifikasi_akhir'], false, true) . '</span>' : '' ?></td></tr>
          <?php if ($d['tgl_pengambilan']): ?>
          <tr><td class="small muted">Tgl Pengambilan</td><td><b><?= tgl_id($d['tgl_pengambilan'], true) ?></b></td></tr><?php endif; ?>
          <?php if ($d['status'] === 'dibatalkan'): ?>
          <tr><td class="small muted">Alasan Pembatalan</td><td style="color:#b91c1c"><?= e($d['alasan_batal']) ?></td></tr><?php endif; ?>
          <?php foreach (($d['data_isian'] ?? []) as $k => $v): ?>
            <?php if ($v === '' || $v === null) continue; ?>
          <tr><td class="small muted"><?= e($d['labels'][$k] ?? $k) ?></td><td><?= e(is_array($v) ? json_encode($v) : $v) ?></td></tr>
          <?php endforeach; ?>
        </table>

        <h6 style="margin:14px 0 6px;font-size:.9rem;color:var(--navy)">Syarat Terupload (<?= count($d['syarat']) ?>)</h6>
        <?php foreach ($d['syarat'] as $s): ?>
          <div class="upload-item ok" style="margin-bottom:6px;display:flex;justify-content:space-between;align-items:center;gap:8px">
            <span>✓ <?= e($s['nama_syarat']) ?> <span class="small muted">— <?= e($s['original_name']) ?></span></span>
            <a class="btn btn-outline btn-sm" target="_blank" href="uploads/<?= e($s['file_path']) ?>">Lihat</a>
          </div>
        <?php endforeach; ?>
        <?php if (!$d['syarat']): ?><p class="small muted">Tidak ada berkas syarat yang diupload.</p><?php endif; ?>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
          <a class="btn btn-info btn-sm" target="_blank" href="pdf/formulir-permohonan.php?id=<?= (int)$d['id'] ?>">📄 Formulir + Syarat (A4)</a>
          <a class="btn btn-outline btn-sm" target="_blank" href="pdf/bukti-pendaftaran.php?id=<?= (int)$d['id'] ?>">🎫 Bukti Pendaftaran (A5)</a>
          <?php if ($d['status'] === 'diproses'): ?>
            <a class="btn btn-gold btn-sm" target="_blank" href="pdf/bukti-verifikasi.php?id=<?= (int)$d['id'] ?>">✅ Bukti Verifikasi (A5)</a>
          <?php endif; ?>
        </div>

        <?php if ($pr && $pr['status'] === 'menunggu_verifikasi'):
          // saran tanggal pengambilan: +3..+14 hari kerja sederhana
        ?>
        <form method="post" style="margin-top:16px;border-top:2px dashed var(--line);padding-top:14px">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="verifikasi"><input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
          <div class="fgroup"><label class="flbl"><b>Tanggal Pengambilan Dokumen</b> (setelah verifikasi)</label>
            <input class="inp" type="date" name="tgl_pengambilan" required min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>"
                   value="<?= e(date('Y-m-d', strtotime('+3 days'))) ?>"></div>
          <p class="small muted">Dengan menekan tombol ini, status permohonan berubah menjadi <b>DIPROSES</b> dan tercatat petugas <b><?= e($u['nama']) ?></b>.</p>
          <button class="btn btn-success">✔ Verifikasi Berkas &amp; Simpan Jadwal</button>
        </form>
        <?php endif; ?>

        <?php if ($bk && in_array($bk['status'], ['menunggu_verifikasi','diproses'])): ?>
        <form method="post" style="margin-top:16px;border-top:2px dashed var(--line);padding-top:14px">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="batal"><input type="hidden" name="id" value="<?= (int)$bk['id'] ?>">
          <div class="fgroup"><label class="flbl"><b>Alasan Pembatalan Permohonan</b></label>
            <textarea class="inp" name="alasan_batal" rows="2" required placeholder="mis. Berkas syarat tidak lengkap / data tidak sesuai database kependudukan"></textarea></div>
          <button class="btn btn-danger">✖ Batalkan Permohonan</button>
        </form>
        <?php endif; ?>
      <?php else: ?><p class="empty">Data tidak ditemukan.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/app/partials/footer_dash.php'; ?>
