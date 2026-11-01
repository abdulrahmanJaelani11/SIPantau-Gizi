<?php
require_once __DIR__.'/_common.php'; $current_slug='data-anak'; require_once ROOT_PATH.'/includes/auth-guard.php';$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);$anak=$id?data_anak_get($id):null;if(!$anak){set_flash('warning','Data anak tidak ditemukan.');redirect(data_anak_url());}$page_title='Ubah Data Anak';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){try{data_anak_save((int)$id);}catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'Data gagal diperbarui. Pastikan NIK unik dan data posyandu tersedia.';}}
require ROOT_PATH.'/includes/header.php'; ?>
<div class="mb-4"><a href="<?= data_anak_url('detail.php',['id'=>$id]) ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Kembali ke detail</a><h1 class="h3 mt-2">Ubah Data Anak</h1></div>
<?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post"><?= csrf_field() ?><?php data_anak_form($anak); ?><div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light" href="<?= data_anak_url('detail.php',['id'=>$id]) ?>">Batal</a><button class="btn btn-primary">Simpan Perubahan</button></div></form>
<?php require ROOT_PATH.'/includes/footer.php'; ?>
