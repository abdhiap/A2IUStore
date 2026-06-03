<?php
session_start();
require_once 'db.php';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    // LOGIN
    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
            echo json_encode(['success' => true, 'name' => $user['name'], 'role' => $user['role']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Email atau password salah']);
        }
        exit;
    }

    // REGISTER
    if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$name || !$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi']);
            exit;
        }
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar']);
            exit;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
        $stmt->execute([$name, $email, $hash]);
        echo json_encode(['success' => true]);
        exit;
    }

    // LOGOUT
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }

    // GET PRODUCTS
    if ($action === 'get_products') {
        $category = $_GET['category'] ?? '';
        $search = $_GET['search'] ?? '';
        $sql = "SELECT * FROM products WHERE 1=1";
        $params = [];
        if ($category && $category !== 'all') {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        if ($search) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        // Pastikan discount_percent selalu ada meski kolom belum di-ALTER
        foreach ($rows as &$row) {
            $row['discount_percent'] = isset($row['discount_percent']) ? (float)$row['discount_percent'] : 0;
        }
        echo json_encode($rows);
        exit;
    }

    // GET SINGLE PRODUCT
    if ($action === 'get_product') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if ($product) {
            // Attach variants
            $vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id");
            $vStmt->execute([$id]);
            $product['variants'] = $vStmt->fetchAll();
            // Parse specs JSON
            $product['specs_parsed'] = $product['specs'] ? json_decode($product['specs'], true) : [];
        }
        echo json_encode($product);
        exit;
    }

    // ADD TO CART
    if ($action === 'add_to_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $qty = (int)($_POST['qty'] ?? 1);
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id] += $qty;
        } else {
            $_SESSION['cart'][$id] = $qty;
        }
        echo json_encode(['success' => true, 'count' => array_sum($_SESSION['cart'])]);
        exit;
    }

    // GET CART
    if ($action === 'get_cart') {
        $items = [];
        foreach ($_SESSION['cart'] as $pid => $qty) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $product = $stmt->fetch();
            if ($product) {
                $items[] = ['product' => $product, 'qty' => $qty];
            }
        }
        echo json_encode(['items' => $items, 'count' => array_sum($_SESSION['cart'])]);
        exit;
    }

    // UPDATE CART
    if ($action === 'update_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $qty = (int)($_POST['qty'] ?? 0);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
        } else {
            $_SESSION['cart'][$id] = $qty;
        }
        echo json_encode(['success' => true, 'count' => array_sum($_SESSION['cart'])]);
        exit;
    }

    // REMOVE FROM CART
    if ($action === 'remove_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        unset($_SESSION['cart'][$id]);
        echo json_encode(['success' => true, 'count' => array_sum($_SESSION['cart'])]);
        exit;
    }

    // CHECKOUT
    if ($action === 'checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
            exit;
        }
        if (empty($_SESSION['cart'])) {
            echo json_encode(['success' => false, 'message' => 'Keranjang kosong']);
            exit;
        }
        $userId        = $_SESSION['user']['id'];
        $nama_penerima = trim($_POST['nama_penerima'] ?? '');
        $no_hp         = trim($_POST['no_hp'] ?? '');
        $alamat        = trim($_POST['alamat'] ?? '');
        $kota          = trim($_POST['kota'] ?? '');
        $kode_pos      = trim($_POST['kode_pos'] ?? '');
        $catatan       = trim($_POST['catatan'] ?? '');
        if (!$nama_penerima || !$no_hp || !$alamat || !$kota) {
            echo json_encode(['success' => false, 'message' => 'Data pengiriman wajib diisi lengkap']);
            exit;
        }
        $total = 0;
        foreach ($_SESSION['cart'] as $pid => $qty) {
            $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $p = $stmt->fetch();
            if ($p) $total += $p['price'] * $qty;
        }
        $pdo->prepare("INSERT INTO orders (user_id, total, status, nama_penerima, no_hp, alamat, kota, kode_pos, catatan) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?)")
            ->execute([$userId, $total, $nama_penerima, $no_hp, $alamat, $kota, $kode_pos, $catatan]);
        $orderId = $pdo->lastInsertId();
        foreach ($_SESSION['cart'] as $pid => $qty) {
            $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $p = $stmt->fetch();
            if ($p) {
                $pdo->prepare("INSERT INTO order_items (order_id, product_id, qty, price) VALUES (?, ?, ?, ?)")->execute([$orderId, $pid, $qty, $p['price']]);
            }
        }
        $_SESSION['cart'] = [];
        echo json_encode(['success' => true, 'order_id' => $orderId]);
        exit;
    }

    // ADMIN: CRUD PRODUCTS
    if ($action === 'admin_add_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
        }
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $badge = trim($_POST['badge'] ?? '');
        $pdo->prepare("INSERT INTO products (name, price, category, description, image, badge) VALUES (?, ?, ?, ?, ?, ?)")->execute([$name, $price, $category, $description, $image, $badge]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'admin_update_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $badge = trim($_POST['badge'] ?? '');
        $pdo->prepare("UPDATE products SET name=?, price=?, category=?, description=?, image=?, badge=? WHERE id=?")->execute([$name, $price, $category, $description, $image, $badge, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'admin_delete_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // VARIANTS CRUD
    if ($action === 'get_variants') {
        $pid = (int)($_GET['product_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id");
        $stmt->execute([$pid]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'add_variant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            echo json_encode(['success' => false]); exit;
        }
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

    if ($action === 'update_variant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            echo json_encode(['success' => false]); exit;
        }
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

    if ($action === 'delete_variant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            echo json_encode(['success' => false]); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM product_variants WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // SPECS CRUD
    if ($action === 'save_specs' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            echo json_encode(['success' => false]); exit;
        }
        $pid   = (int)($_POST['product_id'] ?? 0);
        $specs = $_POST['specs'] ?? '[]';
        // Validate JSON
        json_decode($specs);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => 'Format specs tidak valid']); exit;
        }
        $pdo->prepare("UPDATE products SET specs=? WHERE id=?")->execute([$specs, $pid]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'admin_get_users') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
        }
        $users = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();
        echo json_encode($users);
        exit;
    }

    if ($action === 'admin_get_orders') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
        }
        $orders = $pdo->query("SELECT o.*, u.name as user_name, u.email FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC")->fetchAll();
        foreach ($orders as &$order) {
            $items = $pdo->prepare("SELECT oi.qty, oi.price, p.name as product_name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
            $items->execute([$order['id']]);
            $order['items'] = $items->fetchAll();
        }
        echo json_encode($orders);
        exit;
    }

    if ($action === 'get_session') {
        echo json_encode(['user' => $_SESSION['user'] ?? null, 'cart_count' => array_sum($_SESSION['cart'] ?? [])]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A2IU Store — Official Store</title>
    <link rel="stylesheet" href="a2iu.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Roboto+Condensed:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- HEADER / NAVBAR -->
<header class="site-header" id="siteHeader">
    <div class="header-top">
        <div class="container">
            <div class="header-top-inner">
                <div class="logo-wrap">
                    <a href="#" class="logo" onclick="navigateTo('home')">
                        <span class="logo-text">A2IU</span>
                        <span class="logo-sub">store</span>
                    </a>
                </div>
                <nav class="main-nav" id="mainNav">
                    <ul>
                        <li><a href="#" onclick="navigateTo('home')" class="nav-link active" data-page="home">Beranda</a></li>
                        <li><a href="#" onclick="filterCategory('smartphone')" class="nav-link" data-page="products">Smartphone</a></li>
                        <li><a href="#" onclick="filterCategory('tablet')" class="nav-link" data-page="products">Tablet</a></li>
                        <li><a href="#" onclick="filterCategory('laptop')" class="nav-link" data-page="products">Laptop</a></li>
                        <li><a href="#" onclick="filterCategory('smartwatch')" class="nav-link" data-page="products">Smartwatch</a></li>
                        <li><a href="#" onclick="navigateTo('about')" class="nav-link" data-page="about">Tentang</a></li>
                    </ul>
                </nav>
                <div class="header-actions">
                    <div class="search-wrap">
                        <button class="icon-btn" id="searchToggle"><i class="fas fa-search"></i></button>
                    </div>
                    <div class="user-wrap" id="userWrap">
                        <button class="icon-btn" id="userBtn"><i class="fas fa-user"></i></button>
                        <div class="user-dropdown" id="userDropdown">
                            <div id="guestMenu">
                                <a href="#" onclick="openModal('loginModal')"><i class="fas fa-sign-in-alt"></i> Masuk</a>
                                <a href="#" onclick="openModal('registerModal')"><i class="fas fa-user-plus"></i> Daftar</a>
                            </div>
                            <div id="loggedMenu" style="display:none">
                                <div class="user-info-drop"><i class="fas fa-user-circle"></i> <span id="loggedName">-</span></div>
                                <a href="#" onclick="navigateTo('orders')"><i class="fas fa-box"></i> Pesanan Saya</a>
                                <a href="#" id="adminLink" style="display:none" onclick="navigateTo('admin')"><i class="fas fa-cog"></i> Admin Panel</a>
                                <a href="#" onclick="doLogout()" class="logout-link"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                            </div>
                        </div>
                    </div>
                    <button class="icon-btn cart-btn" onclick="openCart()">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count" id="cartCount">0</span>
                    </button>
                    <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                </div>
            </div>
        </div>
    </div>
    <!-- Search Bar Slide -->
    <div class="search-bar-slide" id="searchBar">
        <div class="search-inner">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Cari produk...">
            </div>
            <button class="btn-search" onclick="doSearch()">Cari</button>
            <button class="close-search" onclick="closeSearch()"><i class="fas fa-times"></i></button>
        </div>
    </div>
</header>

<!-- PAGE WRAPPER -->
<main id="pageMain">

    <!-- ===== HOME PAGE ===== -->
    <div id="page-home" class="page active">
        <!-- HERO SLIDER — Dinamis dari database -->
        <section class="hero-section">
            <div class="hero-slider" id="heroSlider">
                <!-- Diisi oleh loadHeroBanners() -->
                <div class="hero-slide active hero-skeleton">
                    <div class="hero-skeleton-inner"></div>
                </div>
            </div>
            <div class="hero-dots" id="heroDots"></div>
            <button class="hero-arrow left" onclick="prevSlide()"><i class="fas fa-chevron-left"></i></button>
            <button class="hero-arrow right" onclick="nextSlide()"><i class="fas fa-chevron-right"></i></button>
        </section>

        <!-- CATEGORY ICONS -->
        <section class="category-icons">
            <div class="container">
                <div class="cat-grid">
                    <div class="cat-item" onclick="filterCategory('smartphone')">
                        <div class="cat-icon"><i class="fas fa-mobile-alt"></i></div>
                        <span>Smartphone</span>
                    </div>
                    <div class="cat-item" onclick="filterCategory('tablet')">
                        <div class="cat-icon"><i class="fas fa-tablet-alt"></i></div>
                        <span>Tablet</span>
                    </div>
                    <div class="cat-item" onclick="filterCategory('laptop')">
                        <div class="cat-icon"><i class="fas fa-laptop"></i></div>
                        <span>Laptop</span>
                    </div>
                    <div class="cat-item" onclick="filterCategory('smartwatch')">
                        <div class="cat-icon"><i class="fas fa-clock"></i></div>
                        <span>Smartwatch</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- PROMO BANNER STRIP -->
 <!-- PROMO BANNER STRIP -->
<section class="promo-strip">
    <div class="container">
        <div class="promo-grid">

            <div class="promo-card big" style="background: linear-gradient(135deg, #0d1b3e 0%, #1a1a3e 50%, #0f2d5a 100%);" onclick="filterCategory('smartphone')">
                <div class="promo-card-decor">
                    <span class="promo-card-ring"></span>
                    <span class="promo-card-ring r2"></span>
                </div>
                <div class="promo-card-content">
                    <span class="promo-label">Smartphone Series</span>
                    <h3>Mulai dari<br><strong>Rp 1.999.000</strong></h3>
                    <button onclick="event.stopPropagation();filterCategory('smartphone')">Lihat →</button>
                </div>
                <div class="promo-card-img-wrap">
                    <i class="fas fa-mobile-alt promo-card-icon"></i>
                </div>
            </div>

            <div class="promo-card" style="background: linear-gradient(135deg, #1a0a00 0%, #3d1a00 50%, #5c2800 100%);" onclick="filterCategory('laptop')">
                <div class="promo-card-decor">
                    <span class="promo-card-ring"></span>
                    <span class="promo-card-ring r2"></span>
                </div>
                <div class="promo-card-content">
                    <span class="promo-label">Laptop Gaming</span>
                    <h3>Diskon<br><strong>Hingga 30%</strong></h3>
                    <button onclick="event.stopPropagation();filterCategory('laptop')">Lihat →</button>
                </div>
                <div class="promo-card-img-wrap">
                    <i class="fas fa-laptop promo-card-icon"></i>
                </div>
            </div>

        </div>
    </div>
</section>

        <!-- FEATURED PRODUCTS — 4 banner 16:9 per kategori -->
        <section class="featured-banner-section">
            <div class="container">
                <div class="section-header">
                    <h2>Produk Unggulan</h2>
                    <a href="#" onclick="navigateTo('products')" class="see-all">Lihat Semua <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="featured-banner-grid" id="featuredBanners">
                    <div class="feat-banner-skeleton"></div>
                    <div class="feat-banner-skeleton"></div>
                    <div class="feat-banner-skeleton"></div>
                    <div class="feat-banner-skeleton"></div>
                </div>
            </div>
        </section>

        <!-- HOME PRODUCT SECTIONS (Promo / Terlaris / Trending / Terbaru) -->
        <div id="homeSectionsWrap"></div>

        <!-- BENEFITS -->
        <section class="benefits-section">
            <div class="container">
                <div class="benefits-grid">
                    <div class="benefit-item">
                        <i class="fas fa-truck"></i>
                        <h4>Gratis Ongkir</h4>
                        <p>Pengiriman gratis untuk pembelian di atas Rp 500.000</p>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-shield-alt"></i>
                        <h4>Garansi Resmi</h4>
                        <p>Garansi resmi 1 tahun untuk semua produk</p>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-undo-alt"></i>
                        <h4>Retur 30 Hari</h4>
                        <p>Kembalikan produk dalam 30 hari jika tidak puas</p>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-headset"></i>
                        <h4>Layanan 24/7</h4>
                        <p>Tim kami siap membantu Anda kapan saja</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- ===== PRODUCTS PAGE ===== -->
    <div id="page-products" class="page">
        <div class="page-hero-mini">
            <div class="container">
                <h1>Semua Produk</h1>
                <p>Temukan produk terbaik untuk kebutuhan Anda</p>
            </div>
        </div>
        <div class="container">
            <div class="products-page-layout">
                <aside class="filter-sidebar">
                    <div class="filter-block">
                        <h3>Kategori</h3>
                        <ul class="filter-list" id="categoryFilter">
                            <li class="active" onclick="setFilter('all', this)">Semua</li>
                            <li onclick="setFilter('smartphone', this)">Smartphone</li>
                            <li onclick="setFilter('tablet', this)">Tablet</li>
                            <li onclick="setFilter('laptop', this)">Laptop</li>
                            <li onclick="setFilter('smartwatch', this)">Smartwatch</li>
                        </ul>
                    </div>
                </aside>
                <div class="products-main">
                    <div class="products-toolbar">
                        <span id="productCount">Memuat produk...</span>
                    </div>
                    <div class="products-grid" id="allProducts"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== ABOUT PAGE ===== -->
    <div id="page-about" class="page">
        <div class="page-hero-mini">
            <div class="container">
                <h1>Tentang A2IU Store</h1>
            </div>
        </div>
        <div class="container">
            <div class="about-content" style="display:flex;gap:48px;align-items:flex-start;padding:48px 0;flex-wrap:wrap">
                <!-- KIRI: Kredit -->
                <div class="about-text" style="flex:1;min-width:280px">
                    <h2 style="font-size:26px;font-weight:800;margin-bottom:8px">A2IU Store</h2>
                    <p style="color:#666;margin-bottom:28px;font-size:14px">Toko elektronik resmi dengan produk berkualitas tinggi dan layanan terbaik.</p>

                    <div style="margin-bottom:24px">
                        <div style="font-size:11px;font-weight:700;letter-spacing:1.5px;color:#aaa;text-transform:uppercase;margin-bottom:12px">Dibuat Oleh</div>
                        <div style="display:flex;flex-direction:column;gap:10px">
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:34px;height:34px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0">AA</div>
                                <div>
                                    <div style="font-size:14px;font-weight:600;color:#1a1a1a">Abdhi Anantha Pratama</div>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:34px;height:34px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0">AF</div>
                                <div>
                                    <div style="font-size:14px;font-weight:600;color:#1a1a1a">Adzril Febrianto</div>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:34px;height:34px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0">IY</div>
                                <div>
                                    <div style="font-size:14px;font-weight:600;color:#1a1a1a">Indra Yudhatama</div>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:34px;height:34px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0">UA</div>
                                <div>
                                    <div style="font-size:14px;font-weight:600;color:#1a1a1a">Umar Abdul Aziz Aroofi'i</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="border-top:1px solid #eee;padding-top:20px;margin-bottom:28px">
                        <div style="font-size:11px;font-weight:700;letter-spacing:1.5px;color:#aaa;text-transform:uppercase;margin-bottom:12px">Dosen Pembimbing</div>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:34px;height:34px;border-radius:50%;background:#1a1a1a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0">AZ</div>
                            <div style="font-size:14px;font-weight:600;color:#1a1a1a">Abdul Aziz</div>
                        </div>
                    </div>

                    <div style="display:flex;gap:20px;flex-wrap:wrap;border-top:1px solid #eee;padding-top:20px">
                        <div style="text-align:center">
                            <strong style="font-size:22px;color:var(--primary);display:block">10K+</strong>
                            <span style="font-size:12px;color:#888">Pelanggan</span>
                        </div>
                        <div style="text-align:center">
                            <strong style="font-size:22px;color:var(--primary);display:block">500+</strong>
                            <span style="font-size:12px;color:#888">Produk</span>
                        </div>
                        <div style="text-align:center">
                            <strong style="font-size:22px;color:var(--primary);display:block">50+</strong>
                            <span style="font-size:12px;color:#888">Brand</span>
                        </div>
                        <div style="text-align:center">
                            <strong style="font-size:22px;color:var(--primary);display:block">4.9★</strong>
                            <span style="font-size:12px;color:#888">Rating</span>
                        </div>
                    </div>
                </div>

                <!-- KANAN: Foto Tim -->
                <div style="flex:1;min-width:280px;display:flex;justify-content:center;align-items:flex-start">
                    <div style="position:relative">
                        <img src="data:image/jpeg;base64,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" alt="Tim A2IU Store" style="width:100%;max-width:380px;border-radius:16px;object-fit:cover;box-shadow:0 8px 32px rgba(0,0,0,0.12)">
                        <div style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,0.65);color:#fff;font-size:12px;font-weight:600;padding:6px 16px;border-radius:20px;white-space:nowrap;backdrop-filter:blur(4px)">Tim A2IU Store 💪</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== ORDERS PAGE ===== -->
    <div id="page-orders" class="page">
        <div class="page-hero-mini">
            <div class="container"><h1>Pesanan Saya</h1></div>
        </div>
        <div class="container">
            <div id="ordersContent"><p class="empty-msg">Silakan login untuk melihat pesanan.</p></div>
        </div>
    </div>

    <!-- ===== ADMIN PAGE ===== -->
    <div id="page-admin" class="page page-admin-clean">
        <div class="admin-topbar">
            <div class="container">
                <h1><i class="fas fa-cog"></i> Admin Panel — A2IU Store</h1>
            </div>
        </div>
        <div class="container">
            <div class="admin-tabs">
                <button class="admin-tab" onclick="adminTab('products')" class="active">Produk</button>
                <button class="admin-tab" onclick="adminTab('users')">Pengguna</button>
                <button class="admin-tab" onclick="adminTab('orders')">Pesanan</button>
                <button class="admin-tab" onclick="adminTab('returns')">Retur</button>
                <button class="admin-tab" onclick="adminTab('homepage')">Setting Beranda</button>
            </div>

            <!-- Admin: Products -->
            <div class="admin-panel active" id="admin-products">
                <div class="admin-toolbar">
                    <h3>Manajemen Produk</h3>
                    <button class="btn-primary" onclick="openAddProduct()"><i class="fas fa-plus"></i> Tambah Produk</button>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table" id="adminProductTable">
                        <thead><tr><th>ID</th><th>Gambar</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Diskon</th><th>Aksi</th></tr></thead>
                        <tbody id="adminProductBody"></tbody>
                    </table>
                </div>

                <!-- Sub-panel: Varian & Specs (muncul saat pilih produk) -->
                <div id="variantPanel" style="display:none;margin-top:32px">
                    <div class="admin-toolbar">
                        <h3>Varian Produk: <span id="variantProductName" style="color:var(--primary)"></span></h3>
                        <button class="btn-primary" onclick="openAddVariant()"><i class="fas fa-plus"></i> Tambah Varian</button>
                    </div>
                    <p style="font-size:13px;color:#999;margin-bottom:12px">Setiap varian = kombinasi Warna + Storage + RAM + Stok</p>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>ID</th><th>Warna</th><th>Storage</th><th>RAM</th><th>Stok</th><th>Aksi</th></tr></thead>
                            <tbody id="variantBody"></tbody>
                        </table>
                    </div>

                    <!-- Specs editor -->
                    <div style="margin-top:28px">
                        <h3 style="font-size:18px;font-weight:700;margin-bottom:12px">Spesifikasi Produk</h3>
                        <p style="font-size:13px;color:#999;margin-bottom:12px">Tambah baris spesifikasi (label & nilai). Ini yang muncul di halaman produk.</p>
                        <div id="specsEditor"></div>
                        <button class="btn-edit" style="margin-top:8px" onclick="addSpecRow()"><i class="fas fa-plus"></i> Tambah Baris Spek</button>
                        <button class="btn-primary" style="margin-top:8px;margin-left:8px" onclick="saveSpecs()"><i class="fas fa-save"></i> Simpan Spesifikasi</button>
                    </div>
                </div>
            </div>

            <!-- Admin: Users -->
            <div class="admin-panel" id="admin-users">
                <h3>Daftar Pengguna</h3>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>Role</th><th>Bergabung</th></tr></thead>
                        <tbody id="adminUserBody"></tbody>
                    </table>
                </div>
            </div>

           <!-- Admin: Orders -->
            <div class="admin-panel" id="admin-orders">
                <h3>Semua Pesanan</h3>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>ID</th><th>Pelanggan</th><th>Barang Dipesan</th><th>Alamat Pengiriman</th><th>Total</th><th>Status</th><th>Tanggal</th></tr></thead>
                        <tbody id="adminOrderBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Admin: Returns -->
            <div class="admin-panel" id="admin-returns">
                <h3>Klaim Retur</h3>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>ID</th><th>Pesanan</th><th>Pelanggan</th><th>Alasan</th><th>Foto</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                        <tbody id="adminReturnBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Admin: Homepage Settings -->
            <div class="admin-panel" id="admin-homepage">
                <div class="admin-toolbar">
                    <h3>Pengaturan Section Beranda</h3>
                    <button class="btn-primary" onclick="saveHomeSections()"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </div>
                <p style="font-size:13px;color:#888;margin-bottom:20px">Atur section yang tampil di beranda: aktif/nonaktif, judul, urutan, dan jumlah produk. Flag terlaris &amp; trending diatur di tab Produk.</p>

                <!-- Section config cards -->
                <div id="homeSectionCards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:32px"></div>

                <hr style="border:none;border-top:1px solid #eee;margin:28px 0">

                <!-- Flag terlaris & trending pada produk -->
                <h4 style="font-size:16px;font-weight:700;margin-bottom:8px">🏷️ Flag Produk (Terlaris &amp; Trending)</h4>
                <p style="font-size:13px;color:#888;margin-bottom:16px">Centang produk yang ingin ditampilkan di section Terlaris atau Trending.</p>
                <div class="admin-table-wrap">
                    <table class="admin-table" id="flagProductTable">
                        <thead><tr><th>Gambar</th><th>Nama</th><th>Kategori</th><th>Terlaris</th><th>Trending</th></tr></thead>
                        <tbody id="flagProductBody"></tbody>
                    </table>
                </div>
            </div>

        </div> <!-- penutup container -->
    </div> <!-- penutup page-admin -->

<!-- FOOTER -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <div class="footer-logo">A2IU<span>store</span></div>
                <p>Toko elektronik resmi dengan produk berkualitas dan layanan terbaik.</p>
                <div class="social-links">
                    <a href="https://www.instagram.com/ti1c.creative?igsh=MXRidm1zOHhocjlxNA=="><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Produk</h4>
                <ul>
                    <li><a href="#" onclick="filterCategory('smartphone')">Smartphone</a></li>
                    <li><a href="#" onclick="filterCategory('tablet')">Tablet</a></li>
                    <li><a href="#" onclick="filterCategory('laptop')">Laptop</a></li>
                    <li><a href="#" onclick="filterCategory('smartwatch')">Smartwatch</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Layanan</h4>
                <ul>
                    <li><a href="faq-pengiriman.php">FAQ</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Hubungi Kami</h4>
                <ul>
                    <li><i class="fas fa-map-marker-alt"></i> Jl. Serayu Timur No.04, Pandean, Kec.Taman, Kota Madiun, Jawa Timur 63133</li>
                    <li><i class="fas fa-envelope"></i> cs@a2iu.store</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 A2IU Store. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- ===== MODALS ===== -->

<!-- LOGIN MODAL -->
<div class="modal-overlay" id="loginModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Masuk ke A2IU Store</h3>
            <button onclick="closeModal('loginModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="loginError" class="form-error" style="display:none"></div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="loginEmail" placeholder="email@contoh.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="input-pass">
                    <input type="password" id="loginPassword" placeholder="Password Anda">
                    <button type="button" onclick="togglePass('loginPassword')"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <button class="btn-primary full" onclick="doLogin()">Masuk</button>
            <p class="modal-switch">Belum punya akun? <a href="#" onclick="switchModal('loginModal','registerModal')">Daftar sekarang</a></p>
        </div>
    </div>
</div>

<!-- REGISTER MODAL -->
<div class="modal-overlay" id="registerModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Buat Akun Baru</h3>
            <button onclick="closeModal('registerModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="registerError" class="form-error" style="display:none"></div>
            <div id="registerSuccess" class="form-success" style="display:none"></div>
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" id="regName" placeholder="Nama lengkap Anda">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="regEmail" placeholder="email@contoh.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="input-pass">
                    <input type="password" id="regPassword" placeholder="Minimal 6 karakter">
                    <button type="button" onclick="togglePass('regPassword')"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <button class="btn-primary full" onclick="doRegister()">Daftar</button>
            <p class="modal-switch">Sudah punya akun? <a href="#" onclick="switchModal('registerModal','loginModal')">Masuk</a></p>
        </div>
    </div>
</div>

<!-- CART DRAWER -->
<div class="cart-overlay" id="cartOverlay" onclick="closeCart()"></div>
<div class="cart-drawer" id="cartDrawer">
    <div class="cart-header">
        <h3><i class="fas fa-shopping-cart"></i> Keranjang</h3>
        <button onclick="closeCart()"><i class="fas fa-times"></i></button>
    </div>
    <div class="cart-body" id="cartBody">
        <div class="empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <p>Keranjang Anda kosong</p>
            <button class="btn-primary" onclick="closeCart(); navigateTo('products')">Mulai Belanja</button>
        </div>
    </div>
    <div class="cart-footer" id="cartFooter" style="display:none">
        <div class="cart-total"><span>Total:</span><strong id="cartTotal">Rp 0</strong></div>
        <button class="btn-primary full" onclick="openCheckoutModal()">Checkout</button>
    </div>
</div>

<!-- CHECKOUT / ALAMAT MODAL -->
<div class="modal-overlay" id="checkoutModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-map-marker-alt"></i> Alamat Pengiriman</h3>
            <button onclick="closeModal('checkoutModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="checkoutError" class="form-error" style="display:none"></div>
            <div class="form-group">
                <label>Nama Penerima <span style="color:red">*</span></label>
                <input type="text" id="ckNama" placeholder="Nama lengkap penerima">
            </div>
            <div class="form-group">
                <label>Nomor HP <span style="color:red">*</span></label>
                <input type="text" id="ckHp" placeholder="08xxxxxxxxxx">
            </div>
            <div class="form-group">
                <label>Alamat Lengkap <span style="color:red">*</span></label>
                <textarea id="ckAlamat" rows="3" placeholder="Jalan, nomor rumah, RT/RW, kelurahan, kecamatan..."></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Kota / Kabupaten <span style="color:red">*</span></label>
                    <input type="text" id="ckKota" placeholder="Kota">
                </div>
                <div class="form-group">
                    <label>Kode Pos</label>
                    <input type="text" id="ckKodePos" placeholder="63xxx" maxlength="6">
                </div>
            </div>
            <div class="form-group">
                <label>Catatan untuk Kurir</label>
                <input type="text" id="ckCatatan" placeholder="Opsional — misal: taruh di depan pintu">
            </div>

            <!-- METODE PEMBAYARAN -->
            <div class="form-group">
                <label>Metode Pembayaran <span style="color:red">*</span></label>
                <div class="payment-methods">
                    <!-- Transfer Bank -->
                    <div class="payment-group-label"><i class="fas fa-university"></i> Transfer Bank</div>
                    <div class="payment-options">
                        <label class="payment-card">
                            <input type="radio" name="paymentMethod" value="bca">
                            <div class="payment-card-inner">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/5/5c/Bank_Central_Asia.svg" alt="BCA" onerror="this.style.display='none'">
                                <span>BCA</span>
                            </div>
                        </label>
                        <label class="payment-card">
                            <input type="radio" name="paymentMethod" value="bri">
                            <div class="payment-card-inner">
                                <img src="https://upload.wikimedia.org/wikipedia/id/b/be/BRI_2020.svg" alt="BRI" onerror="this.style.display='none'">
                                <span>BRI</span>
                            </div>
                        </label>
                        <label class="payment-card">
                            <input type="radio" name="paymentMethod" value="bni">
                            <div class="payment-card-inner">
                                <img src="https://upload.wikimedia.org/wikipedia/id/5/55/BNI_logo.svg" alt="BNI" onerror="this.style.display='none'">
                                <span>BNI</span>
                            </div>
                        </label>
                        <label class="payment-card">
                            <input type="radio" name="paymentMethod" value="mandiri">
                            <div class="payment-card-inner">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/a/ad/Bank_Mandiri_logo_2016.svg" alt="Mandiri" onerror="this.style.display='none'">
                                <span>Mandiri</span>
                            </div>
                        </label>
                    </div>
                    <!-- E-Wallet -->
                    <div class="payment-group-label" style="margin-top:14px"><i class="fas fa-wallet"></i> E-Wallet (Dompet Digital)</div>
                    <div class="payment-options">
                        <label class="payment-card">
                            <input type="radio" name="paymentMethod" value="gopay">
                            <div class="payment-card-inner">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/8/86/Gopay_logo.svg" alt="GoPay" onerror="this.style.display='none'">
                                <span>GoPay</span>
                            </div>
                        </label>
                        <label class="payment-card">
                            <input type="radio" name="paymentMethod" value="ovo">
                            <div class="payment-card-inner">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/e/eb/Logo_ovo_purple.svg" alt="OVO" onerror="this.style.display='none'">
                                <span>OVO</span>
                            </div>
                        </label>
                        <label class="payment-card">
                            <input type="radio" name="paymentMethod" value="dana">
                            <div class="payment-card-inner">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/7/72/Logo_dana_blue.svg" alt="DANA" onerror="this.style.display='none'">
                                <span>DANA</span>
                            </div>
                        </label>
                        <label class="payment-card">
                            <input type="radio" name="paymentMethod" value="shopeepay">
                            <div class="payment-card-inner">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/f/f5/ShopeePay_Logo.svg" alt="ShopeePay" onerror="this.style.display='none'">
                                <span>ShopeePay</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            
            <button class="btn-primary full" onclick="doCheckout()"><i class="fas fa-check-circle"></i> Konfirmasi Pesanan</button>
        </div>
    </div>
</div>

<!-- PRODUCT DETAIL PAGE (overlay fullscreen seperti Xiaomi) -->
<div class="product-page-overlay" id="productPageOverlay">
    <div class="product-page">
        <div class="product-page-header">
            <button class="product-page-back" onclick="closeProductPage()"><i class="fas fa-arrow-left"></i> Kembali</button>
            <button class="icon-btn product-page-cart-btn" onclick="closeProductPage(); openCart()">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count" id="cartCount2">0</span>
            </button>
        </div>
        <div class="product-page-body">
            <!-- Kiri: Gambar -->
            <div class="pp-left">
                <div class="pp-img-main">
                    <img id="ppImg" src="" alt="">
                </div>
                <div class="pp-img-thumbs" id="ppThumbs"></div>
            </div>
            <!-- Kanan: Info -->
            <div class="pp-right">
                <span class="pp-badge" id="ppBadge"></span>
                <h1 class="pp-name" id="ppName"></h1>
                <div class="pp-price" id="ppPrice"></div>
                <div class="pp-rating">
                    <span class="stars">★★★★★</span>
                    <span class="rating-count">(128 ulasan)</span>
                </div>

                <!-- Pilihan Warna -->
                <div class="pp-option-group" id="ppColorGroup">
                    <div class="pp-option-label">Warna: <strong id="ppColorSelected">-</strong></div>
                    <div class="pp-colors" id="ppColors"></div>
                </div>

                <!-- Pilihan Storage -->
                <div class="pp-option-group" id="ppStorageGroup">
                    <div class="pp-option-label">Storage: <strong id="ppStorageSelected">-</strong></div>
                    <div class="pp-storages" id="ppStorages"></div>
                </div>

                <!-- Pilihan RAM -->
                <div class="pp-option-group" id="ppRamGroup" style="display:none">
                    <div class="pp-option-label">RAM: <strong id="ppRamSelected">-</strong></div>
                    <div class="pp-storages" id="ppRams"></div>
                </div>

                <!-- Info Stok -->
                <div id="ppStockInfo" style="font-size:13px;font-weight:600;margin-bottom:16px"></div>

                <!-- Qty -->
                <div class="pp-qty-wrap">
                    <span>Jumlah:</span>
                    <button onclick="changePPQty(-1)">−</button>
                    <input type="number" id="ppQty" value="1" min="1" max="99">
                    <button onclick="changePPQty(1)">+</button>
                </div>

                <div class="pp-actions">
                    <button class="btn-primary pp-btn-cart" onclick="addFromPage()"><i class="fas fa-shopping-cart"></i> Tambah ke Keranjang</button>
                    <button class="btn-buy-now" onclick="addFromPage(true)"><i class="fas fa-bolt"></i> Beli Sekarang</button>
                </div>

                <!-- Keunggulan -->
                <div class="pp-perks">
                    <div class="pp-perk"><i class="fas fa-truck"></i> Gratis ongkir</div>
                    <div class="pp-perk"><i class="fas fa-undo"></i> Retur 30 hari</div>
                </div>
            </div>
        </div>

        <!-- Tab: Spesifikasi & Ulasan -->
        <div class="pp-tabs-section">
            <div class="container">
                <div class="pp-tabs">
                    <button class="pp-tab active" onclick="ppTab('spec', this)">Spesifikasi</button>
                    <button class="pp-tab" onclick="ppTab('review', this)">Ulasan</button>
                </div>
                <div class="pp-tab-content active" id="pp-spec">
                    <div class="pp-spec-grid" id="ppSpecGrid"></div>
                </div>
                <div class="pp-tab-content" id="pp-review">
                    <div class="pp-reviews" id="ppReviews">
                        <div class="review-item">
                            <div class="review-head"><strong>Budi S.</strong><span class="stars-sm">★★★★★</span></div>
                            <p>"Produknya bagus banget, pengiriman cepat dan packagingnya aman. Sangat puas!"</p>
                        </div>
                        <div class="review-item">
                            <div class="review-head"><strong>Sari W.</strong><span class="stars-sm">★★★★☆</span></div>
                            <p>"Kualitas sesuai harga, layar cerah dan performa lancar untuk kebutuhan sehari-hari."</p>
                        </div>
                        <div class="review-item">
                            <div class="review-head"><strong>Andi P.</strong><span class="stars-sm">★★★★★</span></div>
                            <p>"Recommended banget! Barang original, garansi resmi, dan CS sangat responsif."</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ADD / EDIT PRODUCT MODAL (Admin) -->
<div class="modal-overlay" id="addProductModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="addProductTitle">Tambah Produk</h3>
            <button onclick="closeModal('addProductModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editProductId">
            <div class="form-group">
                <label>Nama Produk</label>
                <input type="text" id="pName" placeholder="Nama produk">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Harga (Rp)</label>
                    <input type="number" id="pPrice" placeholder="Contoh: 3999000">
                </div>
                <div class="form-group">
                    <label>Kategori</label>
                    <select id="pCategory">
                        <option value="smartphone">Smartphone</option>
                        <option value="tablet">Tablet</option>
                        <option value="laptop">Laptop</option>
                        <option value="smartwatch">Smartwatch</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>URL Gambar</label>
                <input type="text" id="pImage" placeholder="https://...">
            </div>
            <div class="form-group">
                <label>Badge <small style="color:#999">(opsional: NEW / HOT / SALE)</small></label>
                <input type="text" id="pBadge" placeholder="NEW">
            </div>
            <div class="form-group">
                <label>Deskripsi / Spesifikasi</label>
                <textarea id="pDesc" rows="4" placeholder="Tulis spesifikasi atau deskripsi produk..."></textarea>
            </div>
            <button class="btn-primary full" onclick="saveProduct()"><i class="fas fa-save"></i> Simpan Produk</button>
        </div>
    </div>
</div>
<!-- ADD / EDIT VARIANT MODAL -->
<div class="modal-overlay" id="variantModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="variantModalTitle">Tambah Varian</h3>
            <button onclick="closeModal('variantModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editVariantId">
            <div class="form-row">
                <div class="form-group">
                    <label>Warna</label>
                    <input type="text" id="vColor" placeholder="Hitam, Biru, dll">
                </div>
                <div class="form-group">
                    <label>Storage</label>
                    <input type="text" id="vStorage" placeholder="128GB, 256GB, dll">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>RAM <small style="color:#999">(opsional)</small></label>
                    <input type="text" id="vRam" placeholder="8GB, 12GB, dll">
                </div>
                <div class="form-group">
                    <label>Stok</label>
                    <input type="number" id="vStock" placeholder="0" min="0">
                </div>
            </div>
            <button class="btn-primary full" onclick="saveVariant()"><i class="fas fa-save"></i> Simpan Varian</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<!-- PAYMENT INFO MODAL -->
<div class="modal-overlay" id="paymentInfoModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-money-bill-wave"></i> Informasi Pembayaran</h3>
            <button onclick="closeModal('paymentInfoModal');navigateTo('orders')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="pay-success-badge"><i class="fas fa-check-circle"></i> Pesanan berhasil dibuat!</div>
            <p class="pay-order-id" id="payInfoOrderId"></p>
            <div class="pay-info-box" id="payInfoBox"></div>

            <div style="border-top:1px solid #eee;margin:16px 0 14px;display:flex;align-items:center;gap:10px">
                <span style="font-size:12px;color:#999;white-space:nowrap">Setelah transfer, upload bukti di bawah</span>
                <div style="flex:1;height:1px;background:#eee"></div>
            </div>

            <div id="buktiUploadWrap">
                <div style="border:2px dashed #ddd;border-radius:10px;padding:14px;background:#fafafa;cursor:pointer" onclick="document.getElementById('buktiFoto').click()">
                    <div id="buktiPlaceholder" style="text-align:center">
                        <i class="fas fa-cloud-upload-alt" style="font-size:28px;color:#bbb;margin-bottom:6px;display:block"></i>
                        <div style="font-size:13px;color:#666"><strong>Pilih foto bukti transfer</strong></div>
                        <div style="font-size:11px;color:#aaa;margin-top:4px">JPG / PNG / WEBP · maks 5MB</div>
                    </div>
                    <div id="buktiPreview" style="display:none;text-align:center">
                        <img id="buktiImg" style="max-width:100%;max-height:160px;border-radius:8px;border:1px solid #ddd">
                        <div id="buktiName" style="font-size:12px;color:#999;margin-top:6px"></div>
                    </div>
                    <input type="file" id="buktiFoto" accept="image/*" style="display:none" onchange="handleBuktiPreview(this)">
                </div>
                <div id="buktiError" style="display:none;background:#ffebee;color:#c62828;padding:8px 12px;border-radius:8px;margin-top:10px;font-size:13px"></div>
                <button class="btn-primary full" style="margin-top:12px" onclick="doUploadBukti()">
                    <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
                </button>
            </div>

            <p class="pay-note" style="margin-top:12px"><i class="fas fa-info-circle"></i> Pesanan diproses setelah bukti dikonfirmasi admin (maks 1x24 jam).</p>
            <button class="btn-secondary full" onclick="closeModal('paymentInfoModal');navigateTo('orders')">
                <i class="fas fa-list-alt"></i> Lihat Pesanan Saya
            </button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<!-- IMAGE LIGHTBOX -->
<div id="imgLightbox" onclick="if(event.target===this)closeImgLightbox()">
  <button class="lb-close" onclick="closeImgLightbox()">
    <i class="fas fa-times"></i>
  </button>
  <img id="imgLightboxImg" src="" alt="Foto Bukti">
</div>

<script src="a2iu.js"></script>
<!-- MODAL RETUR -->
<div class="modal-overlay" id="returModal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3><i class="fas fa-undo-alt"></i> Ajukan Retur</h3>
      <button onclick="closeModal('returModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="returOrderId">
      <div id="returError" style="display:none;background:#ffebee;color:#c62828;padding:10px;border-radius:8px;margin-bottom:12px;font-size:13px"></div>
      <div class="form-group">
        <label>Alasan Retur <span style="color:red">*</span></label>
        <textarea id="returAlasan" rows="4" placeholder="Jelaskan alasan retur (misal: barang rusak, tidak sesuai deskripsi...)"
          style="width:100%;padding:10px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;resize:vertical"></textarea>
      </div>

      <div class="form-group">
        <label>Foto Bukti <span style="color:#999;font-size:12px">(opsional)</span></label>
        <div style="border:2px dashed #ddd;border-radius:10px;padding:12px;background:#fafafa">
          <input type="file" id="returFoto" accept="image/*" style="width:100%;font-size:13px;cursor:pointer">
          <small style="color:#999;display:block;margin-top:6px"><i class="fas fa-info-circle"></i> Format JPG/PNG/WEBP, maks 5MB</small>
          <div id="returFotoPreview" style="display:none;margin-top:10px;text-align:center">
            <img id="returFotoImg" style="max-width:100%;max-height:180px;border-radius:8px;border:1px solid #ddd">
          </div>
        </div>
      </div>
      <button class="btn-primary full" onclick="submitRetur()"><i class="fas fa-paper-plane"></i> Kirim Klaim Retur</button>
    </div>
  </div>
</div>
</body>
</html>