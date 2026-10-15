<?php
require_once __DIR__ . '/../../config/app.php';

function data_anak_url(string $page = 'index.php', array $query = []): string
{
    return base_url('modules/data-anak/' . $page . ($query ? '?' . http_build_query($query) : ''));
}

function data_anak_get(int $id): ?array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT a.*, o.nama_ayah, o.nama_ibu, o.no_telepon AS telepon_wali, o.alamat AS alamat_wali, p.nama_posyandu FROM anak a JOIN orangtua o ON o.id_orangtua=a.id_orangtua JOIN posyandu p ON p.id_posyandu=a.id_posyandu WHERE a.id_anak=?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function data_anak_form(array $anak = []): void
{
    global $pdo;
    $posyandu = $pdo->query('SELECT id_posyandu,nama_posyandu FROM posyandu ORDER BY nama_posyandu')->fetchAll();
    $v = fn($key, $default = '') => e((string)($_POST[$key] ?? $anak[$key] ?? $default));
    ?>
    <div class="row g-4">
      <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Identitas Anak</h5></div><div class="card-body row g-3">
        <div class="col-12"><label class="form-label">Nama lengkap *</label><input class="form-control" name="nama" required maxlength="100" value="<?= $v('nama') ?>"></div>
        <div class="col-md-6"><label class="form-label">NIK *</label><input class="form-control" name="nik" required inputmode="numeric" maxlength="20" value="<?= $v('nik') ?>"></div>
        <div class="col-md-6"><label class="form-label">Tanggal lahir *</label><input class="form-control" type="date" name="tanggal_lahir" required max="<?= date('Y-m-d') ?>" value="<?= $v('tanggal_lahir') ?>"></div>
        <div class="col-md-6"><label class="form-label">Jenis kelamin *</label><select class="form-select" name="jenis_kelamin" required><option value="">Pilih...</option><option value="L" <?= $v('jenis_kelamin')==='L'?'selected':'' ?>>Laki-laki</option><option value="P" <?= $v('jenis_kelamin')==='P'?'selected':'' ?>>Perempuan</option></select></div>
        <div class="col-md-6"><label class="form-label">Posyandu *</label><select class="form-select" name="id_posyandu" required><option value="">Pilih posyandu...</option><?php foreach($posyandu as $p): ?><option value="<?= (int)$p['id_posyandu'] ?>" <?= (string)($_POST['id_posyandu'] ?? $anak['id_posyandu'] ?? '')===(string)$p['id_posyandu']?'selected':'' ?>><?= e($p['nama_posyandu']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Alamat anak</label><textarea class="form-control" name="alamat" rows="3"><?= $v('alamat') ?></textarea></div>
      </div></div></div>
      <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Orang Tua / Wali</h5></div><div class="card-body row g-3">
        <div class="col-12"><label class="form-label">Nama ayah</label><input class="form-control" name="nama_ayah" maxlength="100" value="<?= $v('nama_ayah') ?>"></div>
        <div class="col-12"><label class="form-label">Nama ibu</label><input class="form-control" name="nama_ibu" maxlength="100" value="<?= $v('nama_ibu') ?>"></div>
        <div class="col-12"><label class="form-label">No. telepon orang tua/wali</label><input class="form-control" name="telepon_wali" maxlength="20" value="<?= $v('telepon_wali') ?>"></div>
        <div class="col-12"><label class="form-label">Alamat orang tua/wali</label><textarea class="form-control" name="alamat_wali" rows="3"><?= $v('alamat_wali') ?></textarea></div>
      </div></div></div>
    </div>
    <?php
}

function data_anak_save(int $id = 0): void
{
    global $pdo;
    $nik=trim($_POST['nik']??''); $nama=trim($_POST['nama']??''); $lahir=$_POST['tanggal_lahir']??''; $jk=$_POST['jenis_kelamin']??''; $pos=(int)($_POST['id_posyandu']??0);
    if (!verify_csrf_token() || $nama==='' || !preg_match('/^\d{8,20}$/',$nik) || !in_array($jk,['L','P'],true) || !$pos || !$lahir || $lahir>date('Y-m-d')) throw new InvalidArgumentException('Periksa token, NIK (8–20 digit), nama, tanggal lahir, jenis kelamin, dan posyandu.');
    $check=$pdo->prepare('SELECT id_anak FROM anak WHERE nik=? AND id_anak<>?'); $check->execute([$nik,$id]); if($check->fetch()) throw new InvalidArgumentException('NIK tersebut sudah terdaftar.');
    $pdo->beginTransaction();
    try {
        $parent=[trim($_POST['nama_ayah']??'')?:null,trim($_POST['nama_ibu']??'')?:null,trim($_POST['telepon_wali']??'')?:null,trim($_POST['alamat_wali']??'')?:null];
        if($id){ $a=data_anak_get($id); if(!$a) throw new RuntimeException('Data anak tidak ditemukan.'); $s=$pdo->prepare('UPDATE orangtua SET nama_ayah=?,nama_ibu=?,no_telepon=?,alamat=? WHERE id_orangtua=?'); $s->execute([...$parent,$a['id_orangtua']]); $s=$pdo->prepare('UPDATE anak SET id_posyandu=?,nik=?,nama=?,tanggal_lahir=?,jenis_kelamin=?,alamat=? WHERE id_anak=?'); $s->execute([$pos,$nik,$nama,$lahir,$jk,trim($_POST['alamat']??'')?:null,$id]); }
        else { $s=$pdo->prepare('INSERT INTO orangtua (nama_ayah,nama_ibu,no_telepon,alamat) VALUES (?,?,?,?)'); $s->execute($parent); $oid=(int)$pdo->lastInsertId(); $s=$pdo->prepare('INSERT INTO anak (id_posyandu,id_orangtua,nik,nama,tanggal_lahir,jenis_kelamin,alamat) VALUES (?,?,?,?,?,?,?)'); $s->execute([$pos,$oid,$nik,$nama,$lahir,$jk,trim($_POST['alamat']??'')?:null]); $id=(int)$pdo->lastInsertId(); }
        $pdo->commit(); set_flash('success',$id?'Data anak berhasil disimpan.':'Data anak berhasil ditambahkan.'); redirect(data_anak_url('detail.php',['id'=>$id]));
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
