<?php
/**
 * Modul Otorisasi & Hak Akses — SiPantau Gizi
 * ============================================
 * Menyediakan fungsionalitas pemeriksaan otorisasi berbasis Role-Based Access Control (RBAC).
 * Menghubungkan tabel `role`, `menu`, dan `role_menu`.
 */

if (!defined('APP_LOADED')) {
    require_once __DIR__ . '/../config/app.php';
}

class Otorisasi
{
    /**
     * Cache hak akses per-request untuk optimasi query
     * Format: [role_id => ['dashboard', 'data-anak', ...]]
     */
    private static array $cache = [];

    /**
     * ID Role Bawaan Sistem yang dilindungi
     */
    public const SYSTEM_ROLE_IDS = [1, 2, 3, 4];
    public const ADMIN_ROLE_ID = 4;

    /**
     * Periksa apakah role tertentu berhak mengakses menu dengan slug tertentu.
     *
     * @param int|string|null $role_id ID Role pengguna
     * @param string|null $slug Slug menu yang ingin diakses
     * @return bool
     */
    public static function check($role_id, ?string $slug): bool
    {
        if (empty($role_id) || empty($slug)) {
            return false;
        }

        $role_id = (int)$role_id;
        $slug = trim($slug);

        // Dashboard selalu dapat diakses oleh semua pengguna yang sudah login
        if ($slug === 'dashboard') {
            return true;
        }

        // Administrator (id_role = 4) memiliki hak akses mutlak (super-admin bypass)
        if ($role_id === self::ADMIN_ROLE_ID) {
            return true;
        }

        // Ambil daftar slug yang diizinkan untuk role ini
        $allowedSlugs = self::getAllowedSlugs($role_id);

        return in_array($slug, $allowedSlugs, true);
    }

    /**
     * Ambil seluruh slug menu yang diizinkan untuk role tertentu.
     *
     * @param int $role_id
     * @return array
     */
    public static function getAllowedSlugs(int $role_id): array
    {
        if (isset(self::$cache[$role_id])) {
            return self::$cache[$role_id];
        }

        global $pdo;
        if (!$pdo) {
            return ['dashboard'];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT m.slug
                FROM role_menu rm
                JOIN menu m ON rm.id_menu = m.id_menu
                WHERE rm.id_role = ?
            ");
            $stmt->execute([$role_id]);
            $slugs = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Pastikan dashboard selalu ada
            if (!in_array('dashboard', $slugs, true)) {
                $slugs[] = 'dashboard';
            }

            self::$cache[$role_id] = $slugs;
            return $slugs;
        } catch (PDOException $e) {
            return ['dashboard'];
        }
    }

    /**
     * Ambil seluruh menu yang ada di database, dikelompokkan berdasarkan kategori.
     *
     * @return array
     */
    public static function getAllMenusGrouped(): array
    {
        global $pdo;
        if (!$pdo) return [];

        try {
            $stmt = $pdo->query("
                SELECT id_menu, nama_menu, slug, icon, url, kategori, urutan
                FROM menu
                ORDER BY 
                    CASE kategori
                        WHEN 'Utama' THEN 1
                        WHEN 'Data & Laporan' THEN 2
                        WHEN 'Master Data' THEN 3
                        WHEN 'Utilitas' THEN 4
                        ELSE 5
                    END,
                    urutan ASC,
                    id_menu ASC
            ");
            $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($menus as $m) {
                $cat = $m['kategori'] ?: 'Lainnya';
                $grouped[$cat][] = $m;
            }

            return $grouped;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Ambil daftar id_menu yang dimiliki oleh suatu role.
     *
     * @param int $role_id
     * @return array
     */
    public static function getMenuIdsForRole(int $role_id): array
    {
        global $pdo;
        if (!$pdo) return [];

        try {
            $stmt = $pdo->prepare("SELECT id_menu FROM role_menu WHERE id_role = ?");
            $stmt->execute([$role_id]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Sinkronisasi hak akses menu untuk sebuah role (Atomic Transaction).
     *
     * @param int $role_id
     * @param array $menu_ids Daftar ID menu baru
     * @return bool
     */
    public static function syncRoleMenus(int $role_id, array $menu_ids): bool
    {
        global $pdo;
        if (!$pdo) return false;

        try {
            $pdo->beginTransaction();

            // Hapus semua hak akses lama untuk role ini
            $stmtDel = $pdo->prepare("DELETE FROM role_menu WHERE id_role = ?");
            $stmtDel->execute([$role_id]);

            // Selalu sertakan Dashboard (id_menu = 1) jika belum ada di list
            $menu_ids = array_unique(array_map('intval', $menu_ids));
            if (!in_array(1, $menu_ids, true)) {
                $menu_ids[] = 1;
            }

            if (!empty($menu_ids)) {
                $stmtIns = $pdo->prepare("INSERT INTO role_menu (id_role, id_menu) VALUES (?, ?)");
                foreach ($menu_ids as $menu_id) {
                    if ($menu_id > 0) {
                        $stmtIns->execute([$role_id, $menu_id]);
                    }
                }
            }

            $pdo->commit();

            // Bersihkan cache lokal
            unset(self::$cache[$role_id]);
            return true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    /**
     * Cek apakah role merupakan role bawaan sistem yang tidak boleh dihapus.
     *
     * @param int $role_id
     * @return bool
     */
    public static function isSystemRole(int $role_id): bool
    {
        return in_array($role_id, self::SYSTEM_ROLE_IDS, true);
    }

    /**
     * Dapatkan jumlah pengguna yang saat ini memakai role tertentu.
     *
     * @param int $role_id
     * @return int
     */
    public static function getUserCount(int $role_id): int
    {
        global $pdo;
        if (!$pdo) return 0;

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE id_role = ?");
            $stmt->execute([$role_id]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Dapatkan class badge bootstrap untuk nama role.
     *
     * @param string|null $role_name
     * @return string
     */
    public static function getBadgeClass(?string $role_name): string
    {
        switch (trim($role_name ?? '')) {
            case 'Admin':
                return 'text-bg-primary';
            case 'Kader':
                return 'text-bg-info';
            case 'Bidan/Petugas Puskesmas':
            case 'Petugas Puskesmas':
                return 'text-bg-success';
            case 'Koordinator Desa':
                return 'text-bg-warning';
            default:
                return 'text-bg-secondary';
        }
    }

    /**
     * Memastikan tabel role, menu, dan role_menu telah terbentuk dan siap pakai
     * (Self-healing database migration).
     */
    public static function ensureTablesExist(?PDO $pdoInstance = null): void
    {
        global $pdo;
        $db = $pdoInstance ?? $pdo;
        if (!$db) return;

        static $migrated = false;
        if ($migrated) return;
        $migrated = true;

        try {
            $db->query("SELECT 1 FROM role_menu LIMIT 1");
        } catch (Exception $e) {
            try {
                // Periksa kolom deskripsi di tabel role
                $cols = $db->query("SHOW COLUMNS FROM role LIKE 'deskripsi'")->fetchAll();
                if (empty($cols)) {
                    $db->exec("ALTER TABLE role ADD COLUMN deskripsi VARCHAR(255) NULL AFTER nama_role");
                }
                $colsCr = $db->query("SHOW COLUMNS FROM role LIKE 'created_at'")->fetchAll();
                if (empty($colsCr)) {
                    $db->exec("ALTER TABLE role ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
                }
                $colsUp = $db->query("SHOW COLUMNS FROM role LIKE 'updated_at'")->fetchAll();
                if (empty($colsUp)) {
                    $db->exec("ALTER TABLE role ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                }

                // Buat tabel menu
                $db->exec("
                    CREATE TABLE IF NOT EXISTS menu (
                      id_menu int(11) NOT NULL AUTO_INCREMENT,
                      nama_menu varchar(100) NOT NULL,
                      slug varchar(100) NOT NULL UNIQUE,
                      icon varchar(50) DEFAULT NULL,
                      url varchar(255) DEFAULT NULL,
                      kategori varchar(50) DEFAULT 'Utama',
                      parent_id int(11) DEFAULT NULL,
                      urutan int(11) DEFAULT 0,
                      PRIMARY KEY (id_menu)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                ");

                // Buat tabel role_menu
                $db->exec("
                    CREATE TABLE IF NOT EXISTS role_menu (
                      id_role_menu int(11) NOT NULL AUTO_INCREMENT,
                      id_role int(11) NOT NULL,
                      id_menu int(11) NOT NULL,
                      PRIMARY KEY (id_role_menu),
                      UNIQUE KEY uk_role_menu (id_role, id_menu),
                      KEY fk_role_menu_role (id_role),
                      KEY fk_role_menu_menu (id_menu)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                ");

                // Seed menu
                $mCount = (int)$db->query("SELECT COUNT(*) FROM menu")->fetchColumn();
                if ($mCount === 0) {
                    $db->exec("
                        INSERT INTO menu (id_menu, nama_menu, slug, icon, url, kategori, urutan) VALUES
                        (1, 'Dashboard', 'dashboard', 'bi-speedometer2', 'modules/dashboard/index.php', 'Utama', 1),
                        (2, 'Data Balita & Anak', 'data-anak', 'bi-person-hearts', '#', 'Data & Laporan', 2),
                        (3, 'Pengukuran Gizi', 'pengukuran', 'bi-rulers', 'modules/pengukuran/index.php', 'Data & Laporan', 3),
                        (9, 'Jadwal & Kunjungan', 'jadwal', 'bi-calendar-check', 'modules/jadwal/index.php', 'Data & Laporan', 4),
                        (4, 'Laporan & Grafik', 'laporan-gizi', 'bi-bar-chart-line', '#', 'Data & Laporan', 5),
                        (5, 'Data Posyandu', 'posyandu', 'bi-hospital', '#', 'Master Data', 6),
                        (6, 'Data Puskesmas', 'puskesmas', 'bi-building', '#', 'Master Data', 7),
                        (7, 'Daftar Pengguna', 'pengguna', 'bi-people', 'modules/utilitas/pengguna/index.php', 'Utilitas', 8),
                        (8, 'Role & Otorisasi', 'role', 'bi-shield-lock', 'modules/utilitas/role/index.php', 'Utilitas', 9)
                        ON DUPLICATE KEY UPDATE nama_menu=VALUES(nama_menu);
                    ");
                }

                // Seed role_menu
                $rmCount = (int)$db->query("SELECT COUNT(*) FROM role_menu")->fetchColumn();
                if ($rmCount === 0) {
                    $db->exec("
                        INSERT IGNORE INTO role_menu (id_role, id_menu) VALUES
                        (4, 1), (4, 2), (4, 3), (4, 9), (4, 4), (4, 5), (4, 6), (4, 7), (4, 8),
                        (1, 1), (1, 2), (1, 3), (1, 9), (1, 5),
                        (2, 1), (2, 2), (2, 3), (2, 9), (2, 4), (2, 5), (2, 6),
                        (3, 1), (3, 9), (3, 4), (3, 5), (3, 6);
                    ");
                }
            } catch (Exception $ign) {
                // Abaikan error self-healing
            }
        }
    }
}

// Inisialisasi otomatis jika koneksi database aktif
if (isset($pdo) && $pdo !== null) {
    Otorisasi::ensureTablesExist($pdo);
}

/**
 * Helper fungsional bolehAkses() agar kompatibel dengan rancangan awal auth-guard.php
 */
function bolehAkses($role_id, ?string $slug): bool
{
    return Otorisasi::check($role_id, $slug);
}
