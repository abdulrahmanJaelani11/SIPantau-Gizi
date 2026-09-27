<?php
/**
 * Footer Layout — SiPantau Gizi
 * ================================
 * Di-include di akhir setiap halaman modul (setelah konten halaman).
 *
 * Variabel opsional:
 *   $extra_scripts — string, tag <script> tambahan khusus halaman ini
 */

if (!defined('APP_LOADED')) {
    die('Akses langsung tidak diizinkan.');
}
?>

        </div><!-- /.container-fluid -->
      </main><!-- /.dashboard-content -->

      <!-- Footer -->
      <footer class="admin-footer">
        <div class="container-fluid px-3 px-lg-4">
          <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Capstone Project STSI4440.</span>
          <span>Sistem Informasi Pemantauan Gizi.</span>
        </div>
      </footer>

    </div><!-- /.admin-main -->
  </div><!-- /.admin-shell -->

  <!-- Script Utama -->
  <script src="<?= asset('js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= asset('js/main.js') ?>"></script>
  <?php if (!empty($extra_scripts)) echo $extra_scripts; ?>
</body>
</html>
