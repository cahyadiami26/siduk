<?php
require_once __DIR__ . '/functions.php';

/**
 * Kirim email konfirmasi pendaftaran via PHPMailer (manual library di /lib/phpmailer).
 * Konfigurasi SMTP disimpan pada tabel settings (diatur dari dashboard).
 * Jika mail_enabled = 0, fallback ke mail() PHP bawaan.
 */
function send_mail(string $to, string $subject, string $htmlBody): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    require_once BASE_PATH . '/lib/phpmailer/src/Exception.php';
    require_once BASE_PATH . '/lib/phpmailer/src/PHPMailer.php';
    require_once BASE_PATH . '/lib/phpmailer/src/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        if (setting('mail_enabled', '0') == '1') {
            $mail->isSMTP();
            $mail->Host       = setting('mail_host');
            $mail->SMTPAuth   = true;
            $mail->Username   = setting('mail_user');
            $mail->Password   = setting('mail_pass');
            $port = (int)setting('mail_port', 587);
            $mail->Port       = $port;
            if (setting('mail_enc') === 'ssl') { $mail->SMTPSecure = 'ssl'; }
            elseif ($port === 465)             { $mail->SMTPSecure = 'ssl'; }
            else                               { $mail->SMTPSecure = 'tls'; }
        } else {
            $mail->isMail(); // php mail() bawaan
        }
        $mail->setFrom(setting('mail_from', 'noreply@example.com'), setting('mail_fromname', setting('nama_dinas_short')));
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace('<br', "\n", $htmlBody));
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('MAIL ERROR: ' . $e->getMessage());
        return false;
    }
}

/** Template email konfirmasi pendaftaran */
function mail_body_pendaftaran(array $p, array $jenis, string $link_cek): string
{
    $dinas = e(setting('nama_dinas'));
    $alamat = e(setting('alamat'));
    $rowsData = '';
    foreach ($p['data'] as $label => $value) {
        $rowsData .= '<tr><td style="padding:6px 10px;border:1px solid #ddd">' . e($label) .
                     '</td><td style="padding:6px 10px;border:1px solid #ddd">' . e($value ?: '-') . '</td></tr>';
    }
    $syaratLi = '<ul>';
    foreach ($p['syarat'] as $s) $syaratLi .= '<li>' . e($s['nama_syarat']) . '</li>';
    $syaratLi .= '</ul>';
    return '
    <div style="font-family:Segoe UI,Arial,sans-serif;max-width:640px;margin:auto;border:1px solid #e3e6f0;border-radius:10px;overflow:hidden">
      <div style="background:linear-gradient(135deg,#1e3a8a,#2563eb);color:#fff;padding:22px 26px">
        <h2 style="margin:0;font-size:19px">' . $dinas . '</h2>
        <p style="margin:6px 0 0;font-size:12.5px;opacity:.85">' . $alamat . '</p>
      </div>
      <div style="padding:24px;color:#333;font-size:14px;line-height:1.6">
        <p>Yth. <b>' . e($p['nama']) . '</b>,</p>
        <p>Permohonan Anda telah berhasil terdaftar pada sistem kami dengan rincian:</p>
        <table style="width:100%;border-collapse:collapse;margin:14px 0">
          <tr><td style="padding:6px 10px;border:1px solid #ddd;background:#f8f9fc"><b>Nomor Pendaftaran</b></td>
              <td style="padding:6px 10px;border:1px solid #ddd"><b style="color:#2563eb">' . e($p['nomor']) . '</b></td></tr>
          <tr><td style="padding:6px 10px;border:1px solid #ddd;background:#f8f9fc">Jenis Permohonan</td>
              <td style="padding:6px 10px;border:1px solid #ddd">' . e($jenis['nama']) . '</td></tr>
          <tr><td style="padding:6px 10px;border:1px solid #ddd;background:#f8f9fc">NIK</td>
              <td style="padding:6px 10px;border:1px solid #ddd">' . e($p['nik']) . '</td></tr>
          <tr><td style="padding:6px 10px;border:1px solid #ddd;background:#f8f9fc">Tanggal Daftar</td>
              <td style="padding:6px 10px;border:1px solid #ddd">' . tgl_id($p['tgl_daftar'], true) . '</td></tr>
          <tr><td style="padding:6px 10px;border:1px solid #ddd;background:#f8f9fc"><b>Tanggal Verifikasi Berkas</b></td>
              <td style="padding:6px 10px;border:1px solid #ddd"><b>' . tgl_id($p['tgl_verifikasi'], true) . '</b></td></tr>
          <tr><td style="padding:6px 10px;border:1px solid #ddd;background:#f8f9fc">Nomor Urut / Loket</td>
              <td style="padding:6px 10px;border:1px solid #ddd">' . (int)$p['nomor_urut'] . ' &nbsp;/&nbsp; ' . e($p['loket']) . '</td></tr>
        </table>
        <p><b>Rincian isian permohonan:</b></p>
        <table style="width:100%;border-collapse:collapse;font-size:13px">' . $rowsData . '</table>
        <p style="margin-top:16px"><b>Syarat dokumen yang diunggah:</b></p>' . $syaratLi . '
        <div style="background:#eff6ff;border-left:4px solid #2563eb;padding:12px 16px;margin:18px 0;font-size:13px">
          Harap datang sesuai <b>tanggal verifikasi berkas</b> di atas dengan membawa <b>berkas aslinya</b>.
          Simpan nomor pendaftaran ini dan gunakan untuk melacak status permohonan Anda.
        </div>
        <p style="font-size:13px">Lacak status permohonan: <a href="' . e($link_cek) . '">' . e($link_cek) . '</a></p>
        <p style="margin-top:18px;color:#666">Hormat kami,<br><b>' . $dinas . '</b></p>
      </div>
      <div style="background:#f8f9fc;color:#889;padding:12px 24px;font-size:11.5px;text-align:center">
        Email ini dikirim otomatis oleh sistem. Mohon tidak membalas.
      </div>
    </div>';
}
