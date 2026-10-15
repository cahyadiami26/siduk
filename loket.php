<?php
require_once __DIR__ . '/includes/helpers.php';
require_role(['admin', 'kepala_dinas', 'kepala_bidang']);

$JENIS_LOKET = ['penerimaan' => 'Penerimaan Berkas', 'verifikasi' => 'Verifikasi', 'penyerahan' => 'Penyerahan Dokumen'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = post('aksi');

    if ($aksi === 'simpan') {
        $id     = (int)post('id');
        $nomor  = post('nomor');
        $nama   = post('nama');
        $jenis  = post('jenis');
        $aktif  = post('aktif') === '1' ? 1 : 0;
        $jenisIds = array_map('intval', (array)($_POST['jenis_ids'] ?? []));

        if (!$nomor || !$nama || !isset($JENIS_LOKET[$jenis])) {
            flash_set('danger', 'Nomor, nama, dan jenis loket wajib diisi dengan benar.'); redirect('loket.php');
        }
        if ($id) {
            q("UPDATE loket SET nomor=?, nama=?, jenis=?, aktif=? WHERE id=?", [$nomor, $nama, $jenis, $aktif, $id]);
            log_activity('Mengubah data loket', 'Loket', "$nomor — $nama");
            flash_set('success', 'Loket diperbarui.');
        } else {
            q("INSERT INTO loket (nomor, nama, jenis, aktif) VALUES (?,?,?,?)", [$nomor, $nama, $jenis, $aktif]);
            $id = ins_id();
            log_activity('Menambah loket', 'Loket', "$nomor — $nama");
            flash_set('success', 'Loket ditambahkan.');
        }
        // mapping jenis permohonan
        q("DELETE FROM loket_jenis WHERE loket_id = ?", [$id]);
        foreach ($jenisIds as $jid) {
            if ((int)val("SELECT COUNT(*) FROM jenis_permohonan WHERE id = ?", [$jid]) > 0)
                q("INSERT IGNORE INTO loket_jenis (loket_id, jenis_id) VALUES (?,?)", [$id, $jid]);
        }
        redirect('loket.php');
    }

    if ($aksi === 'hapus') {
        $id = (int)post('id');
        $l = row("SELECT * FROM loket WHERE id = ?", [$id]);
        if ($l) {
            q("DELETE FROM loket WHERE id = ?", [$id]);
            log_activity('Menghapus loket', 'Loket', "{$l['nomor']} — {$l['nama']}");
            flash_set('success', 'Loket dihapus. Permohonan terkait tetap tersimpan (loket dikosongkan).');
        }
        redirect('loket.php');
    }
}

$edit = null;
if (get('edit')) { $edit = row("SELECT * FROM loket WHERE id = ?", [(int)get('edit')]); }

$list = rows("SELECT l.*, GROUP_CONCAT(j.nama ORDER BY j.urutan SEPARATOR ' | ') AS jenis_list
              FROM loket l
              LEFT JOIN loket_jenis lj ON lj.loket_id = l.id
              LEFT JOIN jenis_permohonan j ON j.id = lj.jenis_id
              GROUP BY l.id ORDER BY l.jenis, l.nomor");
$semuaJenis = rows("SELECT id, kode, nama FROM jenis_permohonan ORDER BY urutan");

$page_title = 'Pengaturan Loket';
include __DIR__ . '/app/partials/header_dash.php';
?>
<div class="card">
  <div class="hd"><h5>Data Loket Pelayanan</h5>
    <button class="btn btn-primary btn-sm" onclick="openModal('m-loket')">+ Tambah Loket</button></div>
  <div class="bd">
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Nomor</th><th>Nama Loket</th><th>Jenis Loket</th><th>Jenis Permohonan yang Diproses</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($list as $r): ?>
          <tr>
            <td><span class="chip"><?= e($r['nomor']) ?></span></td>
            <td><b><?= e($r['nama']) ?></b></td>
            <td><span class="badge bg-<?= $r['jenis']==='penerimaan'?'info':($r['jenis']==='verifikasi'?'warning':'success') ?>"><?= e($JENIS_LOKET[$r['jenis']]) ?></span></td>
            <td class="small"><?= e($r['jenis_list'] ?: '— belum diatur —') ?></td>
            <td><?= $r['aktif'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Nonaktif</span>' ?></td>
            <td style="white-space:nowrap">
              <a class="btn btn-outline btn-sm" href="loket.php?edit=<?= (int)$r['id'] ?>">Ubah</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Hapus loket <?= e($r['nomor']) ?>?')">
                <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-danger btn-sm">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="6" class="empty">Belum ada loket.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="small muted mt0">Saat warga mendaftar, sistem menugaskan <b>loket penerimaan</b> aktif yang terdaftar untuk jenis permohonan tersebut secara acak, dan nomor urut mengikuti kuota harian per jenis layanan.</p>
  </div>
</div>

<div class="modal-bg<?= $edit ? ' open' : '' ?>" id="m-loket">
  <div class="modal"><form method="post">
    <div class="hd"><h5><?= $edit ? 'Ubah Loket' : 'Tambah Loket' ?></h5>
      <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('m-loket')">✕</button></div>
    <div class="bd">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
      <div class="form-row">
        <div class="fgroup"><label class="flbl">Nomor Loket <span class="req">*</span></label>
          <input class="inp" name="nomor" required placeholder="mis. L-03" value="<?= e($edit['nomor'] ?? '') ?>"></div>
        <div class="fgroup"><label class="flbl">Jenis Loket <span class="req">*</span></label>
          <select class="inp" name="jenis" required>
            <?php foreach ($JENIS_LOKET as $k => $v): ?>
              <option value="<?= $k ?>" <?= ($edit['jenis'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <div class="fgroup"><label class="flbl">Nama Loket <span class="req">*</span></label>
        <input class="inp" name="nama" required placeholder="mis. Loket Penerimaan Berkas 3" value="<?= e($edit['nama'] ?? '') ?>"></div>
      <div class="fgroup"><label class="flbl">Jenis Permohonan yang Diproses</label>
        <div style="border:1.5px solid #d7dfec;border-radius:9px;padding:10px 12px;max-height:180px;overflow:auto">
          <?php $terpilih = $edit ? array_column(rows("SELECT jenis_id FROM loket_jenis WHERE loket_id = ?", [$edit['id']]), 'jenis_id') : []; ?>
          <?php foreach ($semuaJenis as $j): ?>
            <label style="display:flex;gap:8px;align-items:center;font-size:.88rem;padding:3px 0">
              <input type="checkbox" name="jenis_ids[]" value="<?= (int)$j['id'] ?>" <?= in_array((int)$j['id'], array_map('intval', $terpilih)) ? 'checked' : '' ?>>
              <span class="chip"><?= e($j['kode']) ?></span> <?= e($j['nama']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="fgroup"><label style="display:flex;gap:8px;align-items:center;font-size:.9rem">
        <input type="checkbox" name="aktif" value="1" <?= (!$edit || (int)$edit['aktif'] === 1) ? 'checked' : '' ?>> Loket Aktif</label></div>
      <div style="text-align:right;margin-top:8px">
        <button type="button" class="btn btn-outline" onclick="closeModal('m-loket')">Batal</button>
        <button class="btn btn-primary">Simpan</button></div>
    </div>
  </form></div>
</div>
<?php include __DIR__ . '/app/partials/footer_dash.php'; ?>
