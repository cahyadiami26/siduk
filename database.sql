-- ============================================================
-- Sistem Administrasi Kependudukan (SIDUK)
-- Skema Database MySQL / MariaDB
-- ============================================================
CREATE DATABASE IF NOT EXISTS siduk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE siduk;

-- ---------------- Users & Roles ----------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  nip VARCHAR(30) DEFAULT NULL,
  jabatan VARCHAR(100) DEFAULT NULL,
  role ENUM('admin','kepala_dinas','kepala_bidang','petugas') NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  aktif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------- Pengaturan Aplikasi ----------------
CREATE TABLE settings (
  key_name VARCHAR(50) PRIMARY KEY,
  value TEXT
) ENGINE=InnoDB;

INSERT INTO settings (key_name, value) VALUES
('nama_dinas', 'DINAS KEPENDUDUKAN DAN CATATAN SIPIL'),
('nama_dinas_short', 'Disdukcapil'),
('alamat', 'Jl. Jenderal Sudirman No. 123, Kota Semarang, Jawa Tengah 50123'),
('telepon', '(024) 1234567'),
('email', 'info@disdukcapil.go.id'),
('website', 'https://disdukcapil.go.id'),
('format_nomor', 'DKC/{y}/{m}/{n}'),
('ttd_kepala_dinas', ''),
('logo', ''),
('quota_global', '100'),
('mail_enabled', '0'),
('mail_host', 'smtp.gmail.com'),
('mail_port', '587'),
('mail_user', ''),
('mail_pass', ''),
('mail_enc', 'tls'),
('mail_from', 'noreply@disdukcapil.go.id'),
('mail_fromname', 'Disdukcapil');

-- ---------------- Jenis Permohonan ----------------
CREATE TABLE jenis_permohonan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  deskripsi TEXT,
  kuota_per_hari INT DEFAULT 20,
  aktif TINYINT(1) DEFAULT 1,
  urutan INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Field isian dinamis per jenis permohonan
CREATE TABLE field_isian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  jenis_id INT NOT NULL,
  nama_field VARCHAR(50) NOT NULL,       -- key, misal: nama_lengkap
  label VARCHAR(150) NOT NULL,
  tipe ENUM('text','number','date','select','textarea') DEFAULT 'text',
  options TEXT DEFAULT NULL,             -- daftar pilihan utk select, dipisah koma
  wajib TINYINT(1) DEFAULT 1,
  urutan INT DEFAULT 0,
  FOREIGN KEY (jenis_id) REFERENCES jenis_permohonan(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Syarat dokumen yang harus diupload per jenis permohonan
CREATE TABLE syarat_dokumen (
  id INT AUTO_INCREMENT PRIMARY KEY,
  jenis_id INT NOT NULL,
  nama_syarat VARCHAR(150) NOT NULL,     -- nama field syarat
  deskripsi VARCHAR(255) DEFAULT NULL,
  wajib TINYINT(1) DEFAULT 1,
  urutan INT DEFAULT 0,
  FOREIGN KEY (jenis_id) REFERENCES jenis_permohonan(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------- Loket ----------------
CREATE TABLE loket (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nomor VARCHAR(10) NOT NULL,
  nama VARCHAR(100) NOT NULL,
  jenis ENUM('penerimaan','verifikasi','penyerahan') NOT NULL,
  aktif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE loket_jenis (
  loket_id INT NOT NULL,
  jenis_id INT NOT NULL,
  PRIMARY KEY (loket_id, jenis_id),
  FOREIGN KEY (loket_id) REFERENCES loket(id) ON DELETE CASCADE,
  FOREIGN KEY (jenis_id) REFERENCES jenis_permohonan(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------- Permohonan ----------------
CREATE TABLE permohonan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nomor_pendaftaran VARCHAR(50) NOT NULL UNIQUE,
  token VARCHAR(64) NOT NULL,
  jenis_id INT NOT NULL,
  nama VARCHAR(100) NOT NULL,
  nik VARCHAR(16) NOT NULL,
  email VARCHAR(100) NOT NULL,
  no_hp VARCHAR(20) NOT NULL,
  data_isian LONGTEXT,                    -- JSON {field: value}
  tgl_daftar DATE NOT NULL,
  jam_daftar TIME NOT NULL,
  tgl_verifikasi DATE NOT NULL,
  nomor_urut INT DEFAULT 0,
  loket_id INT DEFAULT NULL,
  status ENUM('menunggu_verifikasi','diproses','selesai','dibatalkan') DEFAULT 'menunggu_verifikasi',
  alasan_batal TEXT DEFAULT NULL,
  diverifikasi_oleh INT DEFAULT NULL,
  tgl_verifikasi_akhir DATETIME DEFAULT NULL,
  tgl_pengambilan DATE DEFAULT NULL,
  diserahkan_pada DATETIME DEFAULT NULL,
  penyerahan_siapa ENUM('bersendiri','diwakilkan') DEFAULT NULL,
  penjamin_nama VARCHAR(100) DEFAULT NULL,
  penjamin_nik VARCHAR(16) DEFAULT NULL,
  penjamin_telp VARCHAR(20) DEFAULT NULL,
  diserahkan_oleh INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (jenis_id) REFERENCES jenis_permohonan(id),
  FOREIGN KEY (loket_id) REFERENCES loket(id) ON DELETE SET NULL,
  INDEX idx_status (status),
  INDEX idx_tglv (tgl_verifikasi, jenis_id)
) ENGINE=InnoDB;

CREATE TABLE upload_syarat (
  id INT AUTO_INCREMENT PRIMARY KEY,
  permohonan_id INT NOT NULL,
  syarat_id INT DEFAULT NULL,
  nama_syarat VARCHAR(150) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (permohonan_id) REFERENCES permohonan(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------- Log Aktivitas ----------------
CREATE TABLE activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  user_name VARCHAR(100) DEFAULT NULL,
  peran VARCHAR(30) DEFAULT NULL,
  aktivitas VARCHAR(255) NOT NULL,
  keterangan TEXT,
  modul VARCHAR(50) DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ================= DATA CONTOH (seed) =================
INSERT INTO jenis_permohonan (kode, nama, deskripsi, kuota_per_hari, urutan) VALUES
('KK',   'Kartu Keluarga Baru / Perubahan', 'Penerbitan kartu keluarga baru atau perubahan data KK', 30, 1),
('KTP',  'KTP Elektronik (Perubahan/Cetak)', 'Pencetakan KTP-el karena perubahan data, rusak, atau hilang', 40, 2),
('AKTA_L','Akta Kelahiran', 'Pencatatan dan penerbitan akta kelahiran', 25, 3),
('AKTA_M','Akta Kematian', 'Pencatatan dan penerbitan akta kematian', 15, 4),
('SKPW','Surat Pindah Datang (SKPWNI)', 'Pengurusan surat pindah datang antar wilayah', 20, 5);

INSERT INTO field_isian (jenis_id, nama_field, label, tipe, options, wajib, urutan) VALUES
(1,'nama_kk','Nama Kepala Keluarga','text',NULL,1,1),
(1,'alamat','Alamat Lengkap','textarea',NULL,1,2),
(1,'no_keluarga','Nomor Kartu Keluarga Lama','text',NULL,0,3),
(1,'permasalahan','Permasalahan / Keperluan','select','KK Baru,KK Hilang,KK Rusak,Perubahan Data Anggota,Pindah Datang',1,4),
(2,'nik_ktp','NIK pada KTP','text',NULL,1,1),
(2,'alasan','Alasan Permohonan','select','Rusak,Hilang,Perubahan Data,Penggantian Pertama',1,2),
(2,'nama_ibu','Nama Ibu Kandung','text',NULL,1,3),
(3,'nama_ayah','Nama Ayah','text',NULL,1,1),
(3,'nama_ibu','Nama Ibu','text',NULL,1,2),
(3,'tgl_lahir','Tanggal Lahir Anak','date',NULL,1,3),
(3,'tempat_lahir','Tempat Lahir','text',NULL,1,4),
(3,'sex','Jenis Kelamin Anak','select','Laki-laki,Perempuan',1,5),
(3,'anak_ke','Anak Ke-','number',NULL,1,6),
(4,'nama_wafat','Nama Yang Meninggal','text',NULL,1,1),
(4,'nik_wafat','NIK Yang Meninggal','text',NULL,1,2),
(4,'tgl_wafat','Tanggal Meninggal','date',NULL,1,3),
(4,'tempat_wafat','Tempat Meninggal','text',NULL,1,4),
(4,'penyebab','Penyebab Kematian','select','Sakit,Accident,Lainnya',1,5),
(5,'asal_dtg','Asal / Tujuan Pindah','select','Pindah Dari Luar Daerah,Pindah Keluar Daerah',1,1),
(5,'alamat_baru','Alamat Baru','textarea',NULL,1,2),
(5,'tgl_pindah','Tanggal Pindah','date',NULL,1,3),
(5,'alasan_pindah','Alasan Pindah','select','Pekerjaan,Pendidikan,Keluarga,Lainnya',1,4);

INSERT INTO syarat_dokumen (jenis_id, nama_syarat, deskripsi, wajib, urutan) VALUES
(1,'Fotokopi KTP','KTP pemilik dan anggota keluarga',1,1),
(1,'Fotokopi KK Lama','Bagi perubahan data',0,2),
(1,'Surat Pengantar RT/RW','Opsional sesuai kebijakan',0,3),
(2,'Fotokopi KK',NULL,1,1),
(2,'Fotokopi KTP Lama / Surat Kehilangan Polisi','Jika hilang',1,2),
(3,'Fotokopi Buku Nikah Orang Tua',NULL,1,1),
(3,'Surat Keterangan Kelahiran dari Bidan/Dokter/RS',NULL,1,2),
(3,'Fotokopi KTP Kedua Orang Tua',NULL,1,3),
(3,'Fotokopi KK',NULL,1,4),
(4,'Surat Keterangan Kematian dari RS/Desa',NULL,1,1),
(4,'Fotokopi KTP & KK Yang Meninggal',NULL,1,2),
(4,'Fotokopi KTP Pelapor',NULL,1,3),
(5,'Fotokopi KK',NULL,1,1),
(5,'Fotokopi KTP Seluruh Anggota',NULL,1,2),
(5,'Surat Keterangan Pindah (SKPW) Asal',NULL,1,3);

INSERT INTO loket (nomor, nama, jenis) VALUES
('L-01','Loket Penerimaan Berkas 1','penerimaan'),
('L-02','Loket Penerimaan Berkas 2','penerimaan'),
('V-01','Loket Verifikasi 1','verifikasi'),
('V-02','Loket Verifikasi 2','verifikasi'),
('P-01','Loket Penyerahan Dokumen','penyerahan');

INSERT INTO loket_jenis (loket_id, jenis_id) VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),
(2,1),(2,2),(2,3),(2,4),(2,5),
(3,1),(3,2),(3,3),(3,4),(3,5),
(4,1),(4,2),(4,3),(4,4),(4,5),
(5,1),(5,2),(5,3),(5,4),(5,5);

-- Password default semua user: password123
INSERT INTO users (nama, nip, jabatan, role, username, password) VALUES
('Administrator Sistem','1900000000000001','Analis Kepegawaian','admin','admin','$2y$10$amNFdgUzkC.J3NwDINtIG.sTu42OE43W0N0NobdYQLoDFmQlgCuuW'),
('Drs. H. Bambang Sutrisno, M.M.','196805121990031005','Kepala Dinas','kepala_dinas','kadis','$2y$10$amNFdgUzkC.J3NwDINtIG.sTu42OE43W0N0NobdYQLoDFmQlgCuuW'),
('Siti Aminah, S.Sos., M.Pd.','197503202000012004','Kepala Bidang Pendataan','kepala_bidang','kabid','$2y$10$amNFdgUzkC.J3NwDINtIG.sTu42OE43W0N0NobdYQLoDFmQlgCuuW'),
('Rina Kartika, A.Md.','198811052010012003','Petugas Front Office','petugas','petugas','$2y$10$amNFdgUzkC.J3NwDINtIG.sTu42OE43W0N0NobdYQLoDFmQlgCuuW');
