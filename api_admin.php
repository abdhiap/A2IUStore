<?php
/**
 * api_admin.php
 * API endpoint khusus ADMIN
 * Semua action di sini wajib role = 'admin'
 * Dipanggil via: api_admin.php?action=...
 */
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// ─── GUARD: Semua request ke file ini harus admin ─────────
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

// ─── PRODUK: Tambah ───────────────────────────────────────
if ($action === 'admin_add_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $category    = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image       = trim($_POST['image'] ?? '');
    $badge       = trim($_POST['badge'] ?? '');
    $pdo->prepare("INSERT INTO products (name, price, category, description, image, badge) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$name, $price, $category, $description, $image, $badge]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── PRODUK: Update ───────────────────────────────────────
if ($action === 'admin_update_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $category    = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image       = trim($_POST['image'] ?? '');
    $badge       = trim($_POST['badge'] ?? '');
    $pdo->prepare("UPDATE products SET name=?, price=?, category=?, description=?, image=?, badge=? WHERE id=?")
        ->execute([$name, $price, $category, $description, $image, $badge, $id]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── PRODUK: Hapus ────────────────────────────────────────
if ($action === 'admin_delete_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── VARIAN: Tambah ───────────────────────────────────────
if ($action === 'add_variant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid     = (int)($_POST['product_id'] ?? 0);
    $color   = trim($_POST['color'] ?? '');
    $storage = trim($_POST['storage'] ?? '');
    $ram     = trim($_POST['ram'] ?? '');
    $stock   = (int)($_POST['stock'] ?? 0);
    $pdo->prepare("INSERT INTO product_variants (product_id, color, storage, ram, stock) VALUES (?,?,?,?,?)")
        ->execute([$pid, $color, $storage, $ram, $stock]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── VARIAN: Update ───────────────────────────────────────
if ($action === 'update_variant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id'] ?? 0);
    $color   = trim($_POST['color'] ?? '');
    $storage = trim($_POST['storage'] ?? '');
    $ram     = trim($_POST['ram'] ?? '');
    $stock   = (int)($_POST['stock'] ?? 0);
    $pdo->prepare("UPDATE product_variants SET color=?, storage=?, ram=?, stock=? WHERE id=?")
        ->execute([$color, $storage, $ram, $stock, $id]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── VARIAN: Hapus ───────────────────────────────────────
if ($action === 'delete_variant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM product_variants WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── SPECS: Simpan ───────────────────────────────────────
if ($action === 'save_specs' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid   = (int)($_POST['product_id'] ?? 0);
    $specs = $_POST['specs'] ?? '[]';
    json_decode($specs);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Format specs tidak valid']); exit;
    }
    $pdo->prepare("UPDATE products SET specs=? WHERE id=?")->execute([$specs, $pid]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── USERS: List ─────────────────────────────────────────
if ($action === 'admin_get_users') {
    $users = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();
    echo json_encode($users);
    exit;
}

// ─── ORDERS: List ────────────────────────────────────────
if ($action === 'admin_get_orders') {
    $orders = $pdo->query("SELECT o.*, u.name as user_name, u.email FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC")->fetchAll();
    foreach ($orders as &$order) {
        $items = $pdo->prepare("SELECT oi.qty, oi.price, p.name as product_name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $items->execute([$order['id']]);
        $order['items'] = $items->fetchAll();
    }
    echo json_encode($orders);
    exit;
}

// ─── ORDERS: Update status ───────────────────────────────
if ($action === 'admin_update_order_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Status tidak valid']); exit;
    }
    $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status, $id]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── FEATURED: Toggle produk unggulan ────────────────────
if ($action === 'toggle_featured' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $category = trim($_POST['category'] ?? '');

    // Cek apakah produk ini sudah featured
    $stmt = $pdo->prepare("SELECT is_featured, category FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $prod = $stmt->fetch();
    if (!$prod) {
        echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']); exit;
    }

    if ($prod['is_featured']) {
        // Kalau sudah featured → unset
        $pdo->prepare("UPDATE products SET is_featured = 0 WHERE id = ?")
            ->execute([$id]);
        echo json_encode(['success' => true, 'featured' => false]);
    } else {
        // Kalau belum featured → set, dan unset produk lain di kategori yang sama
        $pdo->prepare("UPDATE products SET is_featured = 0 WHERE category = ?")
            ->execute([$prod['category']]);
        $pdo->prepare("UPDATE products SET is_featured = 1 WHERE id = ?")
            ->execute([$id]);
        echo json_encode(['success' => true, 'featured' => true]);
    }
    exit;
}

// ─── DISKON: Set discount_percent produk ─────────────────
if ($action === 'set_discount' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id'] ?? 0);
    $disc    = max(0, min(100, (float)($_POST['discount_percent'] ?? 0)));
    $pdo->prepare("UPDATE products SET discount_percent = ? WHERE id = ?")
        ->execute([$disc, $id]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── RETUR: List semua ───────────────────────────────────
if ($action === 'admin_get_returns') {
    $stmt = $pdo->query("
        SELECT r.*, u.name as user_name, u.email, o.total
        FROM returns r
        JOIN users u ON r.user_id = u.id
        JOIN orders o ON r.order_id = o.id
        ORDER BY r.created_at DESC
    ");
    echo json_encode($stmt->fetchAll());
    exit;
}

// ─── RETUR: Update status ────────────────────────────────
if ($action === 'admin_update_return' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id            = (int)($_POST['id'] ?? 0);
    $status        = trim($_POST['status'] ?? '');
    $catatan_admin = trim($_POST['catatan_admin'] ?? '');
    $allowed = ['pending', 'approved', 'rejected'];
    if (!in_array($status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Status tidak valid']); exit;
    }
    $pdo->prepare("UPDATE returns SET status = ?, catatan_admin = ? WHERE id = ?")
        ->execute([$status, $catatan_admin, $id]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── HOMEPAGE SECTIONS: Get config ───────────────────────────
if ($action === 'admin_get_home_sections') {
    $rows = $pdo->query("SELECT * FROM homepage_sections ORDER BY sort_order ASC")->fetchAll();
    echo json_encode($rows);
    exit;
}

// ─── HOMEPAGE SECTIONS: Save config (update semua sekaligus) ─
if ($action === 'admin_save_home_sections' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sections = json_decode($_POST['sections'] ?? '[]', true);
    if (!is_array($sections)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid']); exit;
    }
    $stmt = $pdo->prepare("UPDATE homepage_sections SET title=?, is_active=?, sort_order=?, max_items=? WHERE section_key=?");
    foreach ($sections as $sec) {
        $key       = trim($sec['section_key'] ?? '');
        $title     = trim($sec['title'] ?? '');
        $is_active = (int)(bool)($sec['is_active'] ?? 0);
        $sort      = (int)($sec['sort_order'] ?? 0);
        $max       = max(1, min(20, (int)($sec['max_items'] ?? 8)));
        if ($key) $stmt->execute([$title, $is_active, $sort, $max, $key]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// ─── FLAG TERLARIS: Toggle ────────────────────────────────────
if ($action === 'toggle_terlaris' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE products SET is_terlaris = IF(is_terlaris=1, 0, 1) WHERE id=?")->execute([$id]);
    $row = $pdo->prepare("SELECT is_terlaris FROM products WHERE id=?");
    $row->execute([$id]);
    $val = $row->fetchColumn();
    echo json_encode(['success' => true, 'is_terlaris' => (int)$val]);
    exit;
}

// ─── FLAG TRENDING: Toggle ───────────────────────────────────
if ($action === 'toggle_trending' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE products SET is_trending = IF(is_trending=1, 0, 1) WHERE id=?")->execute([$id]);
    $row = $pdo->prepare("SELECT is_trending FROM products WHERE id=?");
    $row->execute([$id]);
    $val = $row->fetchColumn();
    echo json_encode(['success' => true, 'is_trending' => (int)$val]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);