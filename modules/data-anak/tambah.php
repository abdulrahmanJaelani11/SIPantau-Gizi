<?php
require_once __DIR__.'/_common.php'; $current_slug='data-anak'; require_once ROOT_PATH.'/includes/auth-guard.php'; $page_title='Tambah Anak';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){try{data_anak_save();}catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'Data gagal disimpan. Pastikan NIK unik dan data posyandu tersedia.';}}
require ROOT_PATH.'/includes/header.php'; ?>
<div class="mb-4"><a href="<?= data_anak_url() ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Kembali</a><h1 class="h3 mt-2">Tambah Data Anak</h1><p class="text-muted">Isi identitas anak dan data orang tua atau wali.</p></div>
<?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post"><?= csrf_field() ?><?php data_anak_form(); ?><div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light" href="<?= data_anak_url() ?>">Batal</a><button class="btn btn-primary">Simpan Data</button></div></form>
<?php require ROOT_PATH.'/includes/footer.php'; ?>
