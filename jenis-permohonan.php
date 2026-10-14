<?php
require_once __DIR__ . '/includes/helpers.php';
require_role(['admin', 'kepala_dinas', 'kepala_bidang']);

/* ---------- Aksi POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = post('aksi');

    if ($aksi === 'simpan_jenis') {
        $id    = (int)post('id');
        $kode  = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', post('kode')));
        $nama  = post('nama');
        $desk  = post('deskripsi');
        $kuota = max(1, (int)post('kuota_per_hari'));
        $aktif = post('aktif') === '1' ? 1 : 0;
        if (!$kode || !$nama) { flash_set('danger', 'Kode dan nama jenis wajib diisi.'); redirect('jenis-permohonan.php'); }
        $exist = row("SELECT id FROM jenis_permohonan WHERE kode = ? AND id != ?", [$kode, $id]);
        if ($exist) { flash_set('danger', 'Kode sudah digunakan.'); redirect('jenis-permohonan.php'); }
        if ($id) {
            q("UPDATE jenis_permohonan SET kode=?, nama=?, deskripsi=?, kuota_per_hari=?, aktif=? WHERE id=?",
              [$kode, $nama, $desk, $kuota, $aktif, $id]);
            log_activity('Mengubah jenis permohonan', 'Jenis Permohonan', "$nama ($kode)");
            flash_set('success', 'Jenis permohonan diperbarui.');
        } else {
            $urutan = (int)val("SELECT COALESCE(MAX(urutan),0)+1 FROM jenis_permohonan");
            q("INSERT INTO jenis_permohonan (kode, nama, deskripsi, kuota_per_hari, aktif, urutan) VALUES (?,?,?,?,?,?)",
              [$kode, $nama, $desk, $kuota, $aktif, $urutan]);
            log_activity('Menambah jenis permohonan', 'Jenis Permohonan', "$nama ($kode)");
            flash_set('success', 'Jenis permohonan ditambahkan. Silakan atur field isian & syaratnya.');
        }
        redirect('jenis-permohonan.php');
    }

    if ($aksi === 'hapus_jenis') {
        $id = (int)post('id');
        $j = row("SELECT * FROM jenis_permohonan WHERE id = ?", [$id]);
        if ($j) {
            $pakai = (int)val("SELECT COUNT(*) FROM permohonan WHERE jenis_id = ?", [$id]);
            if ($pakai > 0) { flash_set('danger', "Jenis tidak dapat dihapus, sudah dipakai $pakai permohonan. Nonaktifkan saja."); redirect('jenis-permohonan.php'); }
            q("DELETE FROM jenis_permohonan WHERE id = ?", [$id]);
            log_activity('Menghapus jenis permohonan', 'Jenis Permohonan', $j['nama']);
            flash_set('success', 'Jenis permohonan dihapus.');
        }
        redirect('jenis-permohonan.php');
    }

    if ($aksi === 'simpan_field') {
        $id       = (int)post('id');
        $jenis_id = (int)post('jenis_id');
        $label    = post('label');
        $tipe     = post('tipe');
        $options  = post('options');
        $wajib    = post('wajib') === '1' ? 1 : 0;
        $nama_f   = post('nama_field');
        if (!$jenis_id || !$label) { flash_set('danger', 'Label wajib diisi.'); redirect('jenis-permohonan.php?detail=' . $jenis_id); }
        if (!in_array($tipe, ['text','number','date','select','textarea'], true)) $tipe = 'text';
        if (!$nama_f) $nama_f = 'fld_' . substr(md5($label . microtime()), 0, 8);
        $nama_f = preg_replace('/[^a-z0-9_]/i', '_', strtolower($nama_f));
        if ($id) {
            q("UPDATE field_isian SET nama_field=?, label=?, tipe=?, options=?, wajib=? WHERE id=? AND jenis_id=?",
              [$nama_f, $label, $tipe, $tipe === 'select' ? $options : null, $wajib, $id, $jenis_id]);
            log_activity('Mengubah field isian', 'Jenis Permohonan', "$label (jenis #$jenis_id)");
        } else {
            $urutan = (int)val("SELECT COALESCE(MAX(urutan),0)+1 FROM field_isian WHERE jenis_id = ?", [$jenis_id]);
            q("INSERT INTO field_isian (jenis_id, nama_field, label, tipe, options, wajib, urutan) VALUES (?,?,?,?,?,?,?)",
              [$jenis_id, $nama_f, $label, $tipe, $tipe === 'select' ? $options : null, $wajib, $urutan]);
            log_activity('Menambah field isian', 'Jenis Permohonan', "$label (jenis #$jenis_id)");
        }
        flash_set('success', 'Field isian disimpan.');
        redirect('jenis-permohonan.php?detail=' . $jenis_id);
    }

    if ($aksi === 'hapus_field') {
        $id = (int)post('id'); $jenis_id = (int)post('jenis_id');
        q("DELETE FROM field_isian WHERE id = ? AND jenis_id = ?", [$id, $jenis_id]);
        log_activity('Menghapus field isian', 'Jenis Permohonan', "field #$id");
        flash_set('success', 'Field isian dihapus.');
        redirect('jenis-permohonan.php?detail=' . $jenis_id);
    }

    if ($aksi === 'simpan_syarat') {
        $id       = (int)post('id');
        $jenis_id = (int)post('jenis_id');
        $nama_s   = post('nama_syarat');
        $desk     = post('deskripsi');
        $wajib    = post('wajib') === '1' ? 1 : 0;
        if (!$jenis_id || !$nama_s) { flash_set('danger', 'Nama syarat wajib diisi.'); redirect('jenis-permohonan.php?detail=' . $jenis_id); }
        if ($id) {
            q("UPDATE syarat_dokumen SET nama_syarat=?, deskripsi=?, wajib=? WHERE id=? AND jenis_id=?",
              [$nama_s, $desk, $wajib, $id, $jenis_id]);
            log_activity('Mengubah syarat dokumen', 'Jenis Permohonan', "$nama_s (jenis #$jenis_id)");
        } else {
            $urutan = (int)val("SELECT COALESCE(MAX(urutan),0)+1 FROM syarat_dokumen WHERE jenis_id = ?", [$jenis_id]);
            q("INSERT INTO syarat_dokumen (jenis_id, nama_syarat, deskripsi, wajib, urutan) VALUES (?,?,?,?,?)",
              [$jenis_id, $nama_s, $desk, $wajib, $urutan]);
            log_activity('Menambah syarat dokumen', 'Jenis Permohonan', "$nama_s (jenis #$jenis_id)");
        }
        flash_set('success', 'Syarat dokumen disimpan.');
        redirect('jenis-permohonan.php?detail=' . $jenis_id);
    }

    if ($aksi === 'hapus_syarat') {
        $id = (int)post('id'); $jenis_id = (int)post('jenis_id');
        q("DELETE FROM syarat_dokumen WHERE id = ? AND jenis_id = ?", [$id, $jenis_id]);
        log_activity('Menghapus syarat dokumen', 'Jenis Permohonan', "syarat #$id");
        flash_set('success', 'Syarat dokumen dihapus.');
        redirect('jenis-permohonan.php?detail=' . $jenis_id);
    }
}

$detail = null;
if (get('detail')) {
    $detail = row("SELECT * FROM jenis_permohonan WHERE id = ?", [(int)get('detail')]);
}
$edit = null;
if (get('edit')) { $edit = row("SELECT * FROM jenis_permohonan WHERE id = ?", [(int)get('edit')]); }

$list = rows("SELECT j.*, 
              (SELECT COUNT(*) FROM field_isian f WHERE f.jenis_id = j.id) AS n_field,
              (SELECT COUNT(*) FROM syarat_dokumen s WHERE s.jenis_id = j.id) AS n_syarat,
              (SELECT COUNT(*) FROM permohonan p WHERE p.jenis_id = j.id) AS n_perm
              FROM jenis_permohonan j ORDER BY j.urutan, j.nama");

$page_title = 'Jenis Permohonan';
include __DIR__ . '/app/partials/header_dash.php';

$TIPE_LABEL = ['text'=>'Teks', 'number'=>'Angka', 'date'=>'Tanggal', 'select'=>'Pilihan (dropdown)', 'textarea'=>'Uraian (textarea)'];
?>

<?php if (!$detail): ?>
<div class="card">
  <div class="hd"><h5>Daftar Jenis Permohonan</h5>
    <button class="btn btn-primary btn-sm" onclick="openModal('m-jenis')">+ Tambah Jenis</button></div>
  <div class="bd">
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Kode</th><th>Nama Layanan</th><th>Field Isian</th><th>Syarat</th><th>Kuota / Hari</th><th>Permohonan</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($list as $r): ?>
          <tr>
            <td><span class="chip"><?= e($r['kode']) ?></span></td>
            <td><b><?= e($r['nama']) ?></b><br><span class="small muted"><?= e(mb_strimwidth($r['deskripsi'] ?: '', 0, 70, '…')) ?></span></td>
            <td><?= (int)$r['n_field'] ?> field</td>
            <td><?= (int)$r['n_syarat'] ?> syarat</td>
            <td><b><?= (int)$r['kuota_per_hari'] ?></b></td>
            <td><?= numfmt($r['n_perm']) ?></td>
            <td><?= $r['aktif'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Nonaktif</span>' ?></td>
            <td style="white-space:nowrap">
              <a class="btn btn-info btn-sm" href="jenis-permohonan.php?detail=<?= (int)$r['id'] ?>">Isian &amp; Syarat</a>
              <a class="btn btn-outline btn-sm" href="jenis-permohonan.php?edit=<?= (int)$r['id'] ?>">Ubah</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Hapus jenis <?= e($r['nama']) ?> beserta field &amp; syaratnya?')">
                <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus_jenis"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-danger btn-sm">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal-bg<?= $edit ? ' open' : '' ?>" id="m-jenis">
  <div class="modal">
    <form method="post">
      <div class="hd"><h5><?= $edit ? 'Ubah Jenis Permohonan' : 'Tambah Jenis Permohonan' ?></h5>
        <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('m-jenis')">✕</button></div>
      <div class="bd">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan_jenis">
        <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
        <div class="form-row">
          <div class="fgroup"><label class="flbl">Kode <span class="req">*</span></label>
            <input class="inp" name="kode" required placeholder="mis. KK, KTP_EL" value="<?= e($edit['kode'] ?? '') ?>"></div>
          <div class="fgroup"><label class="flbl">Kuota Pemohon / Hari <span class="req">*</span></label>
            <input class="inp" type="number" min="1" name="kuota_per_hari" required value="<?= e($edit['kuota_per_hari'] ?? 20) ?>"></div>
        </div>
        <div class="fgroup"><label class="flbl">Nama Layanan <span class="req">*</span></label>
          <input class="inp" name="nama" required value="<?= e($edit['nama'] ?? '') ?>"></div>
        <div class="fgroup"><label class="flbl">Deskripsi</label>
          <textarea class="inp" name="deskripsi" rows="2"><?= e($edit['deskripsi'] ?? '') ?></textarea></div>
        <div class="fgroup"><label style="display:flex;gap:8px;align-items:center;font-size:.9rem">
          <input type="checkbox" name="aktif" value="1" <?= (!$edit || (int)$edit['aktif'] === 1) ? 'checked' : '' ?>> Aktif pada form permohonan warga</label></div>
        <div style="text-align:right;margin-top:8px">
          <button type="button" class="btn btn-outline" onclick="closeModal('m-jenis')">Batal</button>
          <button class="btn btn-primary">Simpan</button></div>
      </div>
    </form>
  </div>
</div>

<?php else:
  $fields  = rows("SELECT * FROM field_isian WHERE jenis_id = ? ORDER BY urutan", [$detail['id']]);
  $syarats = rows("SELECT * FROM syarat_dokumen WHERE jenis_id = ? ORDER BY urutan", [$detail['id']]);
  $ef = get('edit_field') ? row("SELECT * FROM field_isian WHERE id=? AND jenis_id=?", [(int)get('edit_field'), $detail['id']]) : null;
  $es = get('edit_syarat') ? row("SELECT * FROM syarat_dokumen WHERE id=? AND jenis_id=?", [(int)get('edit_syarat'), $detail['id']]) : null;
?>
<div class="card" style="margin-bottom:16px">
  <div class="hd">
    <h5><span class="chip"><?= e($detail['kode']) ?></span> &nbsp;<?= e($detail['nama']) ?>
      &nbsp;<span class="small muted">Kuota: <b><?= (int)$detail['kuota_per_hari'] ?></b> pemohon/hari · <?= $detail['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></h5>
    <a class="btn btn-outline btn-sm" href="jenis-permohonan.php">← Kembali ke Daftar</a>
  </div>
</div>

<div class="grid-2">
  <!-- FIELD ISIAN -->
  <div class="card">
    <div class="hd"><h5>Field Isian Dinamis (<?= count($fields) ?>)</h5>
      <button class="btn btn-primary btn-sm" onclick="openModal('m-field')">+ Tambah Field</button></div>
    <div class="bd">
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Label</th><th>Tipe</th><th>Key</th><th>Wajib</th><th>Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($fields as $f): ?>
            <tr>
              <td><b><?= e($f['label']) ?></b><?php if ($f['tipe']==='select' && $f['options']): ?><br><span class="small muted"><?= e($f['options']) ?></span><?php endif; ?></td>
              <td><span class="badge bg-secondary"><?= e($TIPE_LABEL[$f['tipe']] ?? $f['tipe']) ?></span></td>
              <td><code class="small"><?= e($f['nama_field']) ?></code></td>
              <td><?= $f['wajib'] ? '<span class="badge bg-warning">Wajib</span>' : 'Opsional' ?></td>
              <td style="white-space:nowrap">
                <a class="btn btn-outline btn-sm" href="jenis-permohonan.php?detail=<?= (int)$detail['id'] ?>&edit_field=<?= (int)$f['id'] ?>">Ubah</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus field ini?')">
                  <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus_field"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="jenis_id" value="<?= (int)$detail['id'] ?>">
                  <button class="btn btn-danger btn-sm">✕</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$fields): ?><tr><td colspan="5" class="empty">Belum ada field isian untuk jenis ini.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- SYARAT DOKUMEN -->
  <div class="card">
    <div class="hd"><h5>Syarat Dokumen Upload (<?= count($syarats) ?>)</h5>
      <button class="btn btn-primary btn-sm" onclick="openModal('m-syarat')">+ Tambah Syarat</button></div>
    <div class="bd">
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Nama Syarat</th><th>Deskripsi</th><th>Wajib</th><th>Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($syarats as $s): ?>
            <tr>
              <td><b><?= e($s['nama_syarat']) ?></b></td>
              <td class="small muted"><?= e($s['deskripsi'] ?: '-') ?></td>
              <td><?= $s['wajib'] ? '<span class="badge bg-warning">Wajib</span>' : 'Opsional' ?></td>
              <td style="white-space:nowrap">
                <a class="btn btn-outline btn-sm" href="jenis-permohonan.php?detail=<?= (int)$detail['id'] ?>&edit_syarat=<?= (int)$s['id'] ?>">Ubah</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus syarat ini?')">
                  <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus_syarat"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="jenis_id" value="<?= (int)$detail['id'] ?>">
                  <button class="btn btn-danger btn-sm">✕</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$syarats): ?><tr><td colspan="4" class="empty">Belum ada syarat dokumen.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- MODAL FIELD -->
<div class="modal-bg<?= $ef ? ' open' : '' ?>" id="m-field">
  <div class="modal"><form method="post">
    <div class="hd"><h5><?= $ef ? 'Ubah Field Isian' : 'Tambah Field Isian' ?></h5>
      <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('m-field')">✕</button></div>
    <div class="bd">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan_field">
      <input type="hidden" name="jenis_id" value="<?= (int)$detail['id'] ?>">
      <input type="hidden" name="id" value="<?= $ef ? (int)$ef['id'] : 0 ?>">
      <div class="fgroup"><label class="flbl">Label Pertanyaan <span class="req">*</span></label>
        <input class="inp" name="label" required value="<?= e($ef['label'] ?? '') ?>" placeholder="mis. Nama Kepala Keluarga"></div>
      <div class="form-row">
        <div class="fgroup"><label class="flbl">Tipe Input</label>
          <select class="inp" name="tipe" onchange="document.getElementById('opt-box').style.display=this.value==='select'?'':'none'">
            <?php foreach ($TIPE_LABEL as $k => $v): ?>
              <option value="<?= $k ?>" <?= ($ef['tipe'] ?? 'text') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="fgroup"><label class="flbl">Key Field (opsional)</label>
          <input class="inp" name="nama_field" value="<?= e($ef['nama_field'] ?? '') ?>" placeholder="otomatis jika kosong"></div>
      </div>
      <div class="fgroup" id="opt-box" style="<?= ($ef['tipe'] ?? '') === 'select' ? '' : 'display:none' ?>">
        <label class="flbl">Pilihan (pisahkan dengan koma)</label>
        <input class="inp" name="options" value="<?= e($ef['options'] ?? '') ?>" placeholder="mis. Laki-laki,Perempuan"></div>
      <div class="fgroup"><label style="display:flex;gap:8px;align-items:center;font-size:.9rem">
        <input type="checkbox" name="wajib" value="1" <?= (!$ef || (int)$ef['wajib'] === 1) ? 'checked' : '' ?>> Wajib diisi warga</label></div>
      <div style="text-align:right;margin-top:8px">
        <button type="button" class="btn btn-outline" onclick="closeModal('m-field')">Batal</button>
        <button class="btn btn-primary">Simpan</button></div>
    </div>
  </form></div>
</div>

<!-- MODAL SYARAT -->
<div class="modal-bg<?= $es ? ' open' : '' ?>" id="m-syarat">
  <div class="modal"><form method="post">
    <div class="hd"><h5><?= $es ? 'Ubah Syarat Dokumen' : 'Tambah Syarat Dokumen' ?></h5>
      <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('m-syarat')">✕</button></div>
    <div class="bd">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan_syarat">
      <input type="hidden" name="jenis_id" value="<?= (int)$detail['id'] ?>">
      <input type="hidden" name="id" value="<?= $es ? (int)$es['id'] : 0 ?>">
      <div class="fgroup"><label class="flbl">Nama Field Syarat <span class="req">*</span></label>
        <input class="inp" name="nama_syarat" required value="<?= e($es['nama_syarat'] ?? '') ?>" placeholder="mis. Fotokopi KTP"></div>
      <div class="fgroup"><label class="flbl">Deskripsi / Petunjuk</label>
        <input class="inp" name="deskripsi" value="<?= e($es['deskripsi'] ?? '') ?>" placeholder="mis. KTP pemilik dan anggota keluarga"></div>
      <div class="fgroup"><label style="display:flex;gap:8px;align-items:center;font-size:.9rem">
        <input type="checkbox" name="wajib" value="1" <?= (!$es || (int)$es['wajib'] === 1) ? 'checked' : '' ?>> Wajib diupload</label></div>
      <p class="small muted">Format file diterima: PDF, JPG, PNG — maksimal 4 MB per berkas.</p>
      <div style="text-align:right;margin-top:8px">
        <button type="button" class="btn btn-outline" onclick="closeModal('m-syarat')">Batal</button>
        <button class="btn btn-primary">Simpan</button></div>
    </div>
  </form></div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/app/partials/footer_dash.php'; ?>
