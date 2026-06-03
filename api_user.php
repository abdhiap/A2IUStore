<?php
/**
 * api_user.php
 * API endpoint khusus USER (publik & user login)
 * Dipanggil via: api_user.php?action=...
 */
session_start();
require_once 'db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

// ─── LOGIN ───────────────────────────────────────────────
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
        echo json_encode(['success' => true, 'name' => $user['name'], 'role' => $user['role']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Email atau password salah']);
    }
    exit;
}

// ─── REGISTER ────────────────────────────────────────────
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$name || !$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi']); exit;
    }
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar']); exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')")
        ->execute([$name, $email, $hash]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── LOGOUT ──────────────────────────────────────────────
if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// ─── GET SESSION ─────────────────────────────────────────
if ($action === 'get_session') {
    echo json_encode([
        'user'       => $_SESSION['user'] ?? null,
        'cart_count' => array_sum($_SESSION['cart'] ?? []),
    ]);
    exit;
}

// ─── GET PRODUCTS ────────────────────────────────────────
if ($action === 'get_products') {
    $category = $_GET['category'] ?? '';
    $search   = $_GET['search'] ?? '';
    $sql      = "SELECT * FROM products WHERE 1=1";
    $params   = [];
    if ($category && $category !== 'all') {
        $sql      .= " AND category = ?";
        $params[] = $category;
    }
    if ($search) {
        $sql      .= " AND (name LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['discount_percent'] = isset($row['discount_percent']) ? (float)$row['discount_percent'] : 0;
    }
    echo json_encode($rows);
    exit;
}

// ─── GET SINGLE PRODUCT ──────────────────────────────────
if ($action === 'get_product') {
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if ($product) {
        $vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id");
        $vStmt->execute([$id]);
        $product['variants']    = $vStmt->fetchAll();
        $product['specs_parsed'] = $product['specs'] ? json_decode($product['specs'], true) : [];
    }
    echo json_encode($product);
    exit;
}

// ─── ADD TO CART ─────────────────────────────────────────
if ($action === 'add_to_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);
    if (isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id] += $qty;
    } else {
        $_SESSION['cart'][$id] = $qty;
    }
    echo json_encode(['success' => true, 'count' => array_sum($_SESSION['cart'])]);
    exit;
}

// ─── GET CART ────────────────────────────────────────────
if ($action === 'get_cart') {
    $items = [];
    foreach ($_SESSION['cart'] as $pid => $qty) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $product = $stmt->fetch();
        if ($product) {
            $product['discount_percent'] = isset($product['discount_percent']) ? (float)$product['discount_percent'] : 0;
            $items[] = ['product' => $product, 'qty' => $qty];
        }
    }
    echo json_encode(['items' => $items, 'count' => array_sum($_SESSION['cart'])]);
    exit;
}

// ─── UPDATE CART ─────────────────────────────────────────
if ($action === 'update_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);
    if ($qty <= 0) {
        unset($_SESSION['cart'][$id]);
    } else {
        $_SESSION['cart'][$id] = $qty;
    }
    echo json_encode(['success' => true, 'count' => array_sum($_SESSION['cart'])]);
    exit;
}

// ─── REMOVE FROM CART ────────────────────────────────────
if ($action === 'remove_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    unset($_SESSION['cart'][$id]);
    echo json_encode(['success' => true, 'count' => array_sum($_SESSION['cart'])]);
    exit;
}

// ─── CHECKOUT ────────────────────────────────────────────
// ─── CHECKOUT ────────────────────────────────────────────
if ($action === 'checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']); exit;
    }
    if (empty($_SESSION['cart'])) {
        echo json_encode(['success' => false, 'message' => 'Keranjang kosong']); exit;
    }
    $userId        = $_SESSION['user']['id'];
    $nama_penerima  = trim($_POST['nama_penerima'] ?? '');
    $no_hp          = trim($_POST['no_hp'] ?? '');
    $alamat         = trim($_POST['alamat'] ?? '');
    $kota           = trim($_POST['kota'] ?? '');
    $kode_pos       = trim($_POST['kode_pos'] ?? '');
    $catatan        = trim($_POST['catatan'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $allowed_payment = ['bca','bri','bni','mandiri','gopay','ovo','dana','shopeepay'];
    if (!$nama_penerima || !$no_hp || !$alamat || !$kota) {
        echo json_encode(['success' => false, 'message' => 'Data pengiriman wajib diisi lengkap']); exit;
    }
    if (!in_array($payment_method, $allowed_payment)) {
        echo json_encode(['success' => false, 'message' => 'Pilih metode pembayaran yang valid']); exit;
    }

    // ─── Upload bukti transfer (opsional saat checkout, bisa upload terpisah) ───
    $bukti_path = null;
    if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $ftype = mime_content_type($_FILES['bukti_transfer']['tmp_name']);
        if (in_array($ftype, $allowed_types) && $_FILES['bukti_transfer']['size'] <= 5 * 1024 * 1024) {
            $ext = pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION);
            $filename = 'bukti_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $upload_dir = __DIR__ . '/uploads/bukti/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $upload_dir . $filename);
            $bukti_path = 'uploads/bukti/' . $filename;
        }
    }

    $total = 0;
    foreach ($_SESSION['cart'] as $pid => $qty) {
        $stmt = $pdo->prepare("SELECT price, discount_percent FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $p = $stmt->fetch();
        if ($p) {
            $disc = isset($p['discount_percent']) ? (float)$p['discount_percent'] : 0;
            $unitPrice = $disc > 0 ? $p['price'] * (1 - $disc / 100) : $p['price'];
            $total += $unitPrice * $qty;
        }
    }

    // ─── UBAH: tambah bukti_transfer di INSERT ───────────
    $pdo->prepare("INSERT INTO orders (user_id, total, status, nama_penerima, no_hp, alamat, kota, kode_pos, catatan, payment_method, bukti_transfer) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$userId, $total, $nama_penerima, $no_hp, $alamat, $kota, $kode_pos, $catatan, $payment_method, $bukti_path]);

    $orderId = $pdo->lastInsertId();
    foreach ($_SESSION['cart'] as $pid => $qty) {
        $stmt = $pdo->prepare("SELECT price, discount_percent FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $p = $stmt->fetch();
        if ($p) {
            $disc = isset($p['discount_percent']) ? (float)$p['discount_percent'] : 0;
            $unitPrice = $disc > 0 ? $p['price'] * (1 - $disc / 100) : $p['price'];
            $pdo->prepare("INSERT INTO order_items (order_id, product_id, qty, price) VALUES (?, ?, ?, ?)")
                ->execute([$orderId, $pid, $qty, $unitPrice]);
        }
    }
    $_SESSION['cart'] = [];
    echo json_encode(['success' => true, 'order_id' => $orderId, 'total' => $total]);
    exit;
}

// ─── GET VARIANTS (publik, untuk halaman produk) ──────────
if ($action === 'get_variants') {
    $pid  = (int)($_GET['product_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id");
    $stmt->execute([$pid]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ─── GET FEATURED (1 produk unggulan per kategori) ────────
if ($action === 'get_featured') {
    $categories = ['smartphone', 'tablet', 'laptop', 'smartwatch'];
    $result = [];
    foreach ($categories as $cat) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE category = ? AND is_featured = 1 LIMIT 1");
        $stmt->execute([$cat]);
        $prod = $stmt->fetch();
        if (!$prod) {
            $stmt2 = $pdo->prepare("SELECT * FROM products WHERE category = ? ORDER BY created_at DESC LIMIT 1");
            $stmt2->execute([$cat]);
            $prod = $stmt2->fetch();
        }
        if ($prod) {
            $prod['discount_percent'] = isset($prod['discount_percent']) ? (float)$prod['discount_percent'] : 0;
        }
        $result[$cat] = $prod ?: null;
    }
    echo json_encode($result);
    exit;
}

// ─── GET BANNERS ─────────────────────────────────────────
if ($action === 'get_banners') {
    $stmt = $pdo->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order ASC");
    echo json_encode($stmt->fetchAll());
    exit;
}

// ─── GET MY ORDERS (user login) ──────────────────────────
if ($action === 'get_my_orders') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Login diperlukan']); exit;
    }
    $uid = $_SESSION['user']['id'];
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $orders = $stmt->fetchAll();
    foreach ($orders as &$order) {
        $iStmt = $pdo->prepare(
            "SELECT oi.qty, oi.price, p.name AS product_name, p.image
             FROM order_items oi
             JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id = ?"
        );
        $iStmt->execute([$order['id']]);
        $order['items'] = $iStmt->fetchAll();
    }
    echo json_encode(['success' => true, 'orders' => $orders]);
    exit;
}

// ─── RETUR: Ajukan klaim ─────────────────────────────────
if ($action === 'submit_return' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Login diperlukan']); exit;
    }
    $user_id  = $_SESSION['user']['id'];
    $order_id = (int)($_POST['order_id'] ?? 0);
    $alasan   = trim($_POST['alasan'] ?? '');
    if (!$order_id || !$alasan) {
        echo json_encode(['success' => false, 'message' => 'Order dan alasan wajib diisi']); exit;
    }
    // Pastikan order milik user ini
    $chk = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $chk->execute([$order_id, $user_id]);
    if (!$chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan']); exit;
    }
    // Cek sudah pernah ajukan retur untuk order ini
    $dup = $pdo->prepare("SELECT id FROM returns WHERE order_id = ? AND user_id = ?");
    $dup->execute([$order_id, $user_id]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Klaim retur untuk pesanan ini sudah diajukan']); exit;
    }
    // Upload foto bukti (opsional)
    $foto_path = null;
    if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $ftype = mime_content_type($_FILES['foto_bukti']['tmp_name']);
        if (!in_array($ftype, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Format foto harus JPG/PNG/WEBP']); exit;
        }
        if ($_FILES['foto_bukti']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Ukuran foto maksimal 5MB']); exit;
        }
        $ext = pathinfo($_FILES['foto_bukti']['name'], PATHINFO_EXTENSION);
        $filename = 'retur_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        $upload_dir = __DIR__ . '/uploads/retur/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        move_uploaded_file($_FILES['foto_bukti']['tmp_name'], $upload_dir . $filename);
        $foto_path = 'uploads/retur/' . $filename;
    }
    $pdo->prepare("INSERT INTO returns (order_id, user_id, alasan, foto_bukti) VALUES (?, ?, ?, ?)")
        ->execute([$order_id, $user_id, $alasan, $foto_path]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── RETUR: Get milik user ───────────────────────────────
if ($action === 'get_my_returns') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Login diperlukan']); exit;
    }
    $uid = $_SESSION['user']['id'];
    $stmt = $pdo->prepare("
        SELECT r.*, o.total, p.name as product_name
        FROM returns r
        JOIN orders o ON r.order_id = o.id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$uid]);
    echo json_encode(['success' => true, 'returns' => $stmt->fetchAll()]);
    exit;
}

// ─── UPLOAD BUKTI TRANSFER (terpisah setelah checkout) ───
if ($action === 'upload_bukti' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    if (!$order_id) { echo json_encode(['success' => false, 'message' => 'Order tidak valid']); exit; }

    // Pastikan order milik user ini
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $userId]);
    if (!$stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan']); exit; }

    if (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File bukti tidak ditemukan']); exit;
    }
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $ftype = mime_content_type($_FILES['bukti_transfer']['tmp_name']);
    if (!in_array($ftype, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Format harus JPG/PNG/WEBP']); exit;
    }
    if ($_FILES['bukti_transfer']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 5MB']); exit;
    }
    $ext = pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION);
    $filename = 'bukti_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $upload_dir = __DIR__ . '/uploads/bukti/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $upload_dir . $filename);
    $bukti_path = 'uploads/bukti/' . $filename;

    $pdo->prepare("UPDATE orders SET bukti_transfer = ? WHERE id = ?")->execute([$bukti_path, $order_id]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── HOME SECTIONS: Ambil data tiap section untuk beranda ─────
if ($action === 'get_home_sections') {
    // Ambil config section yang aktif, urut by sort_order
    $sections = $pdo->query("SELECT * FROM homepage_sections WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
    $result = [];
    foreach ($sections as $sec) {
        $key   = $sec['section_key'];
        $limit = (int)$sec['max_items'];
        $products = [];
        if ($key === 'promo') {
            // Produk dengan discount_percent > 0, urutkan diskon terbesar
            $stmt = $pdo->prepare("SELECT * FROM products WHERE discount_percent > 0 ORDER BY discount_percent DESC LIMIT ?");
            $stmt->execute([$limit]);
            $products = $stmt->fetchAll();
        } elseif ($key === 'terlaris') {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE is_terlaris = 1 ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            $products = $stmt->fetchAll();
        } elseif ($key === 'trending') {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE is_trending = 1 ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            $products = $stmt->fetchAll();
        } elseif ($key === 'terbaru') {
            $stmt = $pdo->prepare("SELECT * FROM products ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            $products = $stmt->fetchAll();
        }
        // Normalize discount_percent
        foreach ($products as &$p) {
            $p['discount_percent'] = isset($p['discount_percent']) ? (float)$p['discount_percent'] : 0;
        }
        $result[] = [
            'key'      => $key,
            'title'    => $sec['title'],
            'products' => $products,
        ];
    }
    echo json_encode($result);
    exit;
}

echo json_encode(['error' => 'Unknown action']);