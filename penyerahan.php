<?php
require_once __DIR__ . '/includes/helpers.php';
require_role(['petugas', 'admin', 'kepala_dinas', 'kepala_bidang']);
$u = current_user();

/* ---------- Aksi POST: serahkan dokumen ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = post('aksi');
    $id   = (int)post('id');
    $p    = $id ? row("SELECT * FROM permohonan WHERE id = ?", [$id]) : null;

    if ($aksi === 'serah' && $p) {
        if ($p['status'] !== 'diproses') { flash_set('danger', 'Hanya permohonan berstatus DIPROSES yang dapat diserahkan.'); redirect('penyerahan.php'); }
        $siapa = post('penyerahan_siapa');
        $pn = $pp = $pt = null;
        if ($siapa === 'diwakilkan') {
            $pn = post('penjamin_nama'); $pp = post('penjamin_nik'); $pt = post('penjamin_telp');
            if (!$pn || !valid_nik($pp) || !$pt) {
                flash_set('danger', 'Data yang menerima (nama, NIK 16 digit, no. telepon) wajib diisi lengkap untuk pengambilan diwakilkan.');
                redirect('penyerahan.php?serahkan=' . $id);
            }
            $siapa = 'diwakilkan';
        } else {
            $siapa = 'bersendiri';
        }
        q("UPDATE permohonan SET status='selesai', diserahkan_pada=?, penyerahan_siapa=?,
            penjamin_nama=?, penjamin_nik=?, penjamin_telp=?, diserahkan_oleh=? WHERE id=?",
          [date('Y-m-d H:i:s'), $siapa, $pn, $pp, $pt, $u['id'], $id]);
        log_activity('Menyerahkan dokumen permohonan', 'Penyerahan',
                     "{$p['nomor_pendaftaran']} — {$p['nama']} (" . ($siapa === 'diwakilkan' ? "diwakilkan: $pn" : 'sendiri') . ")");
        flash_set('success', 'Dokumen diserahkan. Status permohonan menjadi SELESAI.');
        redirect('penyerahan.php');
    }

    if ($aksi === 'batal' && $p) {
        if ($p['status'] !== 'diproses') { flash_set('danger', 'Permohonan ini tidak dapat dibatalkan dari sini.'); redirect('penyerahan.php'); }
        $alasan = post('alasan_batal');
        if (strlen($alasan) < 5) { flash_set('danger', 'Alasan pembatalan wajib diisi.'); redirect('penyerahan.php?serahkan=' . $id); }
        q("UPDATE permohonan SET status='dibatalkan', alasan_batal=? WHERE id=?", [$alasan, $id]);
        log_activity('Membatalkan permohonan (tahap penyerahan)', 'Penyerahan', "{$p['nomor_pendaftaran']} — alasan: $alasan");
        flash_set('success', 'Permohonan dibatalkan.');
        redirect('penyerahan.php');
    }
}

/* ---------- Filter ---------- */
$fJenis = (int)get('jenis');
$fTglAmbil = get('ambil') !== '' ? get('ambil') : today();
$fTglDaftar = get('daftar');
$fCari = get('cari');

$where = ["p.status IN ('diproses','selesai')"]; $params = [];
if ($fJenis) { $where[] = "p.jenis_id = ?"; $params[] = $fJenis; }
if ($fTglAmbil !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fTglAmbil)) { $where[] = "p.tgl_pengambilan = ?"; $params[] = $fTglAmbil; }
if ($fTglDaftar && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fTglDaftar)) { $where[] = "p.tgl_daftar = ?"; $params[] = $fTglDaftar; }
if ($fCari !== '') { $where[] = "(p.nomor_pendaftaran LIKE ? OR p.nama LIKE ?)"; $like = "%$fCari%"; array_push($params, $like, $like); }
$wsql = 'WHERE ' . implode(' AND ', $where);

$rows = rows("SELECT p.*, j.nama AS jenis_nama, j.kode AS jenis_kode, u.nama AS penyerah
              FROM permohonan p JOIN jenis_permohonan j ON j.id = p.jenis_id
              LEFT JOIN users u ON u.id = p.diserahkan_oleh
              $wsql ORDER BY p.status='diproses' DESC, p.tgl_pengambilan, p.id DESC LIMIT 300", $params);
$nSelesai = (int)val("SELECT COUNT(*) FROM permohonan WHERE status='selesai' AND DATE(diserahkan_pada)=?", [today()]);
$nTerlambat = (int)val("SELECT COUNT(*) FROM permohonan WHERE status='diproses' AND tgl_pengambilan < CURDATE()");

$semuaJenis = rows("SELECT id, kode, nama FROM jenis_permohonan ORDER BY urutan");

/* data utk modal serahkan */
$sId = (int)get('serahkan');
$s = $sId ? permohonan_detail($sId) : null;
if ($s) $s['labels'] = field_labels_map((int)$s['jenis_id']);

$page_title = 'Penyerahan Dokumen';
include __DIR__ . '/app/partials/header_dash.php';
?>
<div class="stats">
  <div class="stat" style="--dot:#16a34a;--tint:#e7f7ee"><div class="lbl"><i class="dot"></i>Diserahkan Hari Ini</div>
    <div class="val"><?= numfmt($nSelesai) ?></div><div class="sub"><?= tgl_id(today(), true) ?></div></div>
  <div class="stat" style="--dot:#dc2626;--tint:#fdecec"><div class="lbl"><i class="dot"></i>Jadwal Terlambat Ambil</div>
    <div class="val"><?= numfmt($nTerlambat) ?></div><div class="sub">Status diproses, melewati tanggal pengambilan</div></div>
</div>

<div class="card">
  <div class="hd"><h5>Antrian Penyerahan Dokumen</h5>
    <span class="small muted">Tanggal pengambilan default: hari berjalan</span></div>
  <div class="bd">
    <form method="get" class="filterbar" style="margin-bottom:16px">
      <div class="fgroup"><label class="flbl">Jenis Permohonan</label>
        <select class="inp" name="jenis">
          <option value="0">Semua Jenis</option>
          <?php foreach ($semuaJenis as $j): ?><option value="<?= (int)$j['id'] ?>" <?= $fJenis===(int)$j['id']?'selected':'' ?>><?= e($j['nama']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="fgroup"><label class="flbl">Tanggal Pengambilan</label>
        <input class="inp" type="date" name="ambil" value="<?= e($fTglAmbil) ?>"></div>
      <div class="fgroup"><label class="flbl">Tanggal Pendaftaran</label>
        <input class="inp" type="date" name="daftar" value="<?= e($fTglDaftar) ?>"></div>
      <div class="fgroup" style="flex:1;min-width:200px"><label class="flbl">Cari No. Pendaftaran / Nama</label>
        <input class="inp" name="cari" value="<?= e($fCari) ?>" placeholder="mis. DKC/2026/1/12"></div>
      <div class="fgroup"><button class="btn btn-primary">Terapkan</button>
        <a class="btn btn-outline" href="penyerahan.php">Reset</a></div>
    </form>

    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>No. Pendaftaran</th><th>Pemohon</th><th>Jenis Layanan</th><th>Tgl Pengambilan</th><th>Penyerahan</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><b><?= e($r['nomor_pendaftaran']) ?></b><br><span class="small muted">daftar <?= tgl_id($r['tgl_daftar']) ?></span></td>
            <td><?= e($r['nama']) ?><br><span class="small muted"><?= e($r['no_hp']) ?></span></td>
            <td><span class="chip"><?= e($r['jenis_kode']) ?></span> <span class="small"><?= e($r['jenis_nama']) ?></span></td>
            <td><?= tgl_id($r['tgl_pengambilan'], true) ?>
              <?php if ($r['status']==='diproses' && $r['tgl_pengambilan'] < today()): ?><br><span class="badge bg-danger">Terlambat</span><?php endif; ?></td>
            <td class="small"><?= $r['status']==='selesai'
                  ? e(ucfirst($r['penyerahan_siapa'] ?? '-')) . ($r['penyerahan_siapa']==='diwakilkan' ? '<br>' . e($r['penjamin_nama']) : '') . '<br><span class="muted">' . tgl_id($r['diserahkan_pada'], false, true) . ' · ' . e($r['penyerah'] ?: '-') . '</span>'
                  : '—' ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td style="white-space:nowrap">
              <?php if ($r['status'] === 'diproses'): ?>
                <a class="btn btn-success btn-sm" href="penyerahan.php?serahkan=<?= (int)$r['id'] ?>">📦 Diserahkan</a>
              <?php else: ?>
                <a class="btn btn-gold btn-sm" target="_blank" href="pdf/bukti-verifikasi.php?id=<?= (int)$r['id'] ?>">Bukti Verifikasi</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">Tidak ada dokumen pada filter ini.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($s): ?>
<div class="modal-bg open" id="m-serah">
  <div class="modal" style="max-width:620px">
    <div class="hd"><h5>Penyerahan Dokumen — <?= e($s['nomor_pendaftaran']) ?></h5>
      <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('m-serah')">✕</button></div>
    <div class="bd">
      <?php if ($s['status'] !== 'diproses'): ?>
        <div class="alert alert-warning">Permohonan ini berstatus <b><?= e($s['status']) ?></b> dan tidak dapat diserahkan.</div>
      <?php else: ?>
        <table class="tbl" style="width:100%;margin-bottom:12px">
          <tr><td class="small muted" style="width:32%">Pemohon</td><td><b><?= e($s['nama']) ?></b> · NIK <?= e($s['nik']) ?></td></tr>
          <tr><td class="small muted">Jenis Layanan</td><td><span class="chip"><?= e($s['jenis_kode']) ?></span> <?= e($s['jenis_nama']) ?></td></tr>
          <tr><td class="small muted">Jadwal Ambil</td><td><b><?= tgl_id($s['tgl_pengambilan'], true) ?></b></td></tr>
        </table>

        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="serah"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <div class="fgroup"><label class="flbl">Dokumen diterima oleh <span class="req">*</span></label>
            <select class="inp" name="penyerahan_siapa" onchange="togglePenjamin(this)">
              <option value="bersendiri">Yang bersangkutan (pemohon sendiri)</option>
              <option value="diwakilkan">Diwakilkan (keluarga/kuasa dengan KTP asli)</option>
            </select></div>
          <div id="box-penjamin" style="display:none;border:1.5px solid #d7dfec;border-radius:10px;padding:12px;margin-bottom:14px;background:#fafcff">
            <div class="fgroup"><label class="flbl">Nama Lengkap Yang Menerima <span class="req">*</span></label>
              <input class="inp" name="penjamin_nama" placeholder="sesuai KTP"></div>
            <div class="form-row">
              <div class="fgroup"><label class="flbl">NIK Yang Menerima <span class="req">*</span></label>
                <input class="inp" name="penjamin_nik" inputmode="numeric" maxlength="16" pattern="\d{16}" placeholder="16 digit angka"></div>
              <div class="fgroup"><label class="flbl">No. Telepon <span class="req">*</span></label>
                <input class="inp" name="penjamin_telp" placeholder="08xxxxxxxxxx"></div>
            </div>
            <p class="small muted">Petugas wajib mencocokkan KTP asli penerima kuasa sebelum menyerahkan dokumen.</p>
          </div>
          <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
            <button type="button" class="btn btn-outline" onclick="closeModal('m-serah')">Batal / Tutup</button>
            <button class="btn btn-success" onclick="return confirm('Konfirmasi dokumen telah diserahkan dan status berubah menjadi SELESAI?')">✔ Serahkan Dokumen</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/app/partials/footer_dash.php'; ?>
