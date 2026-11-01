<?php
require_once __DIR__ . '/../../config/app.php';

// Older databases may not yet have a dedicated dusun/RW column.
if ($pdo) {
    $columns = $pdo->query("SHOW COLUMNS FROM posyandu LIKE 'dusun_rw'")->fetchAll();
    if (!$columns) $pdo->exec('ALTER TABLE posyandu ADD COLUMN dusun_rw VARCHAR(100) NULL AFTER nama_posyandu');
}

function posyandu_url($page = 'index.php', $query = array())
{
    return base_url('modules/posyandu/' . $page . ($query ? '?' . http_build_query($query) : ''));
}

function posyandu_get($id)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT p.*, w.nama_wilayah, w.kecamatan, w.kabupaten, pk.nama_puskesmas FROM posyandu p JOIN wilayah w ON w.id_wilayah=p.id_wilayah JOIN puskesmas pk ON pk.id_puskesmas=p.id_puskesmas WHERE p.id_posyandu=?');
    $stmt->execute(array($id));
    return $stmt->fetch() ?: null;
}

function posyandu_form($record = array())
{
    global $pdo;
    $wilayah = $pdo->query('SELECT id_wilayah,nama_wilayah,kecamatan,kabupaten FROM wilayah ORDER BY nama_wilayah')->fetchAll();
    $puskesmas = $pdo->query('SELECT id_puskesmas,nama_puskesmas FROM puskesmas ORDER BY nama_puskesmas')->fetchAll();
    $value = function ($key) use ($record) { return e((string)(isset($_POST[$key]) ? $_POST[$key] : (isset($record[$key]) ? $record[$key] : ''))); };
    ?>
    <div class="card"><div class="card-header"><h5 class="mb-0">Informasi Posyandu</h5></div><div class="card-body row g-3">
      <div class="col-md-6"><label class="form-label">Nama posyandu *</label><input class="form-control" name="nama_posyandu" maxlength="100" required value="<?= $value('nama_posyandu') ?>"></div>
      <div class="col-md-6"><label class="form-label">Dusun / RW *</label><input class="form-control" name="dusun_rw" maxlength="100" required placeholder="Contoh: Dusun Melati / RW 02" value="<?= $value('dusun_rw') ?>"></div>
      <div class="col-md-6"><label class="form-label">Puskesmas induk *</label><select class="form-select" name="id_puskesmas" required><option value="">Pilih puskesmas...</option><?php foreach ($puskesmas as $item): ?><option value="<?= (int)$item['id_puskesmas'] ?>" <?= (string)(isset($_POST['id_puskesmas'])?$_POST['id_puskesmas']:(isset($record['id_puskesmas'])?$record['id_puskesmas']:''))===(string)$item['id_puskesmas']?'selected':'' ?>><?= e($item['nama_puskesmas']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-6"><label class="form-label">Wilayah *</label><select class="form-select" name="id_wilayah" required><option value="">Pilih wilayah...</option><?php foreach ($wilayah as $item): ?><option value="<?= (int)$item['id_wilayah'] ?>" <?= (string)(isset($_POST['id_wilayah'])?$_POST['id_wilayah']:(isset($record['id_wilayah'])?$record['id_wilayah']:''))===(string)$item['id_wilayah']?'selected':'' ?>><?= e($item['nama_wilayah'].' · '.$item['kecamatan'].', '.$item['kabupaten']) ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><label class="form-label">Alamat / lokasi</label><textarea class="form-control" name="alamat" rows="3"><?= $value('alamat') ?></textarea></div>
    </div></div>
    <?php
}

function posyandu_save($id = 0)
{
    global $pdo;
    $is_new = !$id;
    if (!verify_csrf_token()) throw new InvalidArgumentException('Token keamanan tidak valid. Silakan kirim ulang formulir.');
    $name = trim(isset($_POST['nama_posyandu']) ? $_POST['nama_posyandu'] : '');
    $dusun = trim(isset($_POST['dusun_rw']) ? $_POST['dusun_rw'] : '');
    $health = (int)(isset($_POST['id_puskesmas']) ? $_POST['id_puskesmas'] : 0);
    $area = (int)(isset($_POST['id_wilayah']) ? $_POST['id_wilayah'] : 0);
    if ($name === '' || $dusun === '' || !$health || !$area) throw new InvalidArgumentException('Nama posyandu, dusun/RW, puskesmas induk, dan wilayah wajib diisi.');
    $sql = $id ? 'UPDATE posyandu SET nama_posyandu=?,dusun_rw=?,id_puskesmas=?,id_wilayah=?,alamat=? WHERE id_posyandu=?' : 'INSERT INTO posyandu (nama_posyandu,dusun_rw,id_puskesmas,id_wilayah,alamat) VALUES (?,?,?,?,?)';
    $args = array($name,$dusun,$health,$area,trim(isset($_POST['alamat'])?$_POST['alamat']:'') ?: null);
    if ($id) $args[] = $id;
    $stmt = $pdo->prepare($sql); $stmt->execute($args);
    if (!$id) $id = (int)$pdo->lastInsertId();
    set_flash('success', $is_new ? 'Data posyandu berhasil ditambahkan.' : 'Data posyandu berhasil diperbarui.');
    redirect(posyandu_url('index.php'));
}
