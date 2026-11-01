USE sipantau_gizi;
select database();
create table wilayah (
	id_wilayah INT AUTO_INCREMENT PRIMARY KEY,
    nama_wilayah VARCHAR(100) NOT NULL,
    kecamatan VARCHAR(100) NOT NULL,
    kabupaten VARCHAR (100) NOT NULL
    );

CREATE TABLE puskesmas (
	id_puskesmas INT AUTO_INCREMENT PRIMARY KEY,
    id_wilayah INT NOT NULL,
    nama_puskesmas VARCHAR(100) NOT NULL,
    alamat TEXT NOT NULL,
    no_telepon VARCHAR (20) NOT NULL,
    
	CONSTRAINT fk_puskesmas_wilayah
		FOREIGN KEY(id_wilayah)
        REFERENCES wilayah(id_wilayah)
        );
    
CREATE TABLE posyandu (
	id_posyandu INT AUTO_INCREMENT PRIMARY KEY,
    id_puskesmas INT NOT NULL,
    id_wilayah INT NOT NULL,
    nama_posyandu VARCHAR(100) NOT NULL,
    alamat TEXT,
    
    CONSTRAINT fk_posyandu_puskesmas
		FOREIGN KEY (id_puskesmas)
        REFERENCES puskesmas(id_puskesmas),
        
	CONSTRAINT fk_posyandu_wilayah
		FOREIGN KEY (id_wilayah)
        REFERENCES wilayah(id_wilayah)
);

	show tables; describe posyandu;

CREATE TABLE role (
	id_role INT AUTO_INCREMENT PRIMARY KEY,
    nama_role VARCHAR(50) NOT NULL
    );
    
INSERT INTO role (nama_role) VALUES
('Kader'),
('Bidan/Petugas Puskesmas'),
('Koordinator Desa'),
('Admin');

SELECT * FROM role;

CREATE TABLE user (
	id_user INT AUTO_INCREMENT PRIMARY KEY,
	id_role INT NOT NULL,
	id_posyandu INT NULL,
	id_puskesmas INT NULL,
	id_wilayah INT NULL,
	nama VARCHAR(100) NOT NULL,
	username VARCHAR(50) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	email VARCHAR(100),
	no_telepon VARCHAR(20),
	status VARCHAR (20) NOT NULL DEFAULT 'Aktif',
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	update_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	
    CONSTRAINT fk_user_role
		FOREIGN KEY (id_role)
        REFERENCES role (id_role),
        
	CONSTRAINT fk_user_posyandu
		FOREIGN KEY (id_posyandu)
        REFERENCES posyandu (id_posyandu),
	
    CONSTRAINT fk_user_puskesmas
		FOREIGN KEY (id_puskesmas)
        REFERENCES puskesmas (id_puskesmas),
	
    CONSTRAINT fk_user_wilayah
		FOREIGN KEY (id_wilayah)
        REFERENCES wilayah (id_wilayah)
	);

CREATE TABLE orangtua (
	id_orangtua INT AUTO_INCREMENT PRIMARY KEY,
	nama_ayah VARCHAR(100),
    nama_ibu VARCHAR(100),
    no_telepon VARCHAR(20),
    alamat TEXT
);

CREATE TABLE anak (
    id_anak INT AUTO_INCREMENT PRIMARY KEY,
    id_posyandu INT NOT NULL,
    id_orangtua INT NOT NULL,
    nik VARCHAR(20) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    tanggal_lahir DATE NOT NULL,
    jenis_kelamin CHAR(1) NOT NULL,
    alamat TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_anak_posyandu
        FOREIGN KEY (id_posyandu)
        REFERENCES posyandu(id_posyandu),

    CONSTRAINT fk_anak_orangtua
        FOREIGN KEY (id_orangtua)
        REFERENCES orangtua(id_orangtua)
);

CREATE TABLE pengukuran(
	id_pengukuran INT AUTO_INCREMENT PRIMARY KEY,
    id_anak INT NOT NULL,
    id_user INT NOT NULL,
    tanggal_pengukuran DATE NOT NULL,
    berat_badan DECIMAL(5,2),
    tinggi_badan DECIMAL(5,2),
    lingkar_kepala DECIMAL(5,2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_pengukuran_anak
		FOREIGN KEY (id_anak)
        REFERENCES anak (id_anak),
        
	CONSTRAINT fk_pengukuran_user
		FOREIGN KEY (id_user)
        REFERENCES user (id_user)
);

CREATE TABLE status_gizi (
		id_status_gizi INT AUTO_INCREMENT PRIMARY KEY,
        id_pengukuran INT NOT NULL UNIQUE,
        
        zscore_bb_u DECIMAL(5,2),
        zscore_tb_u DECIMAL(5,2),
        zscore_bb_tb DECIMAL(5,2),
        zscore_imt_u DECIMAL(5,2),
        
        status_bb_u VARCHAR(50),
        status_tb_u VARCHAR(50),
        status_bb_tb VARCHAR(50),
        status_imt_u VARCHAR(50),
        
        status_risiko VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        
        CONSTRAINT fk_status_gizi_pengukuran
        FOREIGN KEY (id_pengukuran)
        REFERENCES pengukuran(id_pengukuran)
);

CREATE TABLE pemantauan (
    id_pemantauan INT AUTO_INCREMENT PRIMARY KEY,
    id_anak INT NOT NULL,
    id_pengukuran INT NOT NULL,
    tanggal_mulai DATE NOT NULL,
    status_tindak_lanjut VARCHAR(50),
    tanggal_tindak_lanjut DATE,
    keterangan TEXT,

    CONSTRAINT fk_pemantauan_anak
        FOREIGN KEY (id_anak)
        REFERENCES anak(id_anak),

    CONSTRAINT fk_pemantauan_pengukuran
        FOREIGN KEY (id_pengukuran)
        REFERENCES pengukuran(id_pengukuran)
);

CREATE TABLE catatan_intervensi (
    id_intervensi INT AUTO_INCREMENT PRIMARY KEY,
    id_pemantauan INT NOT NULL,
    id_user INT NOT NULL,
    tanggal DATE NOT NULL,
    jenis_intervensi VARCHAR(100) NOT NULL,
    catatan TEXT,

    CONSTRAINT fk_intervensi_pemantauan
        FOREIGN KEY (id_pemantauan)
        REFERENCES pemantauan(id_pemantauan),

    CONSTRAINT fk_intervensi_user
        FOREIGN KEY (id_user)
        REFERENCES user(id_user)
);

CREATE TABLE jadwal_posyandu (
    id_jadwal INT AUTO_INCREMENT PRIMARY KEY,
    id_posyandu INT NOT NULL,
    tanggal DATE NOT NULL,
    waktu TIME NOT NULL,
    lokasi VARCHAR(200),
    keterangan TEXT,

    CONSTRAINT fk_jadwal_posyandu
        FOREIGN KEY (id_posyandu)
        REFERENCES posyandu(id_posyandu)
);

CREATE TABLE kehadiran (
    id_kehadiran INT AUTO_INCREMENT PRIMARY KEY,
    id_jadwal INT NOT NULL,
    id_anak INT NOT NULL,
    status_kehadiran VARCHAR(30) NOT NULL,
    waktu_kehadiran DATETIME,
    keterangan TEXT,

    CONSTRAINT fk_kehadiran_jadwal
        FOREIGN KEY (id_jadwal)
        REFERENCES jadwal_posyandu(id_jadwal),

    CONSTRAINT fk_kehadiran_anak
        FOREIGN KEY (id_anak)
        REFERENCES anak(id_anak)
);

SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'sipantau_gizi'
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, COLUMN_NAME;