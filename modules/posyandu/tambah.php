<?php
require_once __DIR__.'/_common.php';$current_slug='posyandu';require_once ROOT_PATH.'/includes/auth-guard.php';$page_title='Tambah Posyandu';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){try{posyandu_save();}catch(Throwable $ex){$error=$ex instanceof InvalidArgumentException?$ex->getMessage():'Data gagal disimpan. Periksa kembali pilihan wilayah dan puskesmas.';}}
require ROOT_PATH.'/includes/header.php'; ?>
<div class="mb-4"><a href="<?= posyandu_url() ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Kembali</a><h1 class="h3 mt-2">Tambah Posyandu</h1><p class="text-muted">Masukkan data posyandu dan wilayah layanannya.</p></div><?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post"><?= csrf_field() ?><?php posyandu_form(); ?><div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light" href="<?= posyandu_url() ?>">Batal</a><button class="btn btn-primary">Simpan Posyandu</button></div></form>
<?php require ROOT_PATH.'/includes/footer.php'; ?>
