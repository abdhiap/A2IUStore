<?php
/**
 * faq-pengiriman.php
 * Halaman FAQ Pengiriman — A2IU Store
 */
session_start();
require_once 'db.php';

$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ Pengiriman — A2IU Store</title>
    <link rel="stylesheet" href="a2iu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Roboto+Condensed:wght@700&display=swap" rel="stylesheet">

    <style>
        /* ===========================
           FAQ PAGE STYLES
           =========================== */

        /* Hero mini (sama dengan page-hero-mini di a2iu.css) */
        .faq-hero {
            background: linear-gradient(135deg, var(--dark) 0%, #2a2a2a 100%);
            padding: 100px 0 40px;
            margin-bottom: 0;
        }
        .faq-hero-inner {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .faq-hero-icon {
            width: 52px;
            height: 52px;
            background: var(--primary);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #fff;
            flex-shrink: 0;
        }
        .faq-hero h1 {
            font-size: clamp(22px, 4vw, 32px);
            font-weight: 700;
            color: #fff;
            font-family: 'Roboto Condensed', sans-serif;
            letter-spacing: -0.5px;
        }
        .faq-hero p {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
            margin-top: 4px;
        }

        /* Breadcrumb */
        .faq-breadcrumb {
            background: var(--dark);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            padding: 10px 0;
        }
        .faq-breadcrumb ul {
            display: flex;
            align-items: center;
            gap: 8px;
            list-style: none;
            font-size: 13px;
        }
        .faq-breadcrumb ul li a {
            color: rgba(255,255,255,0.5);
            transition: color 0.2s;
        }
        .faq-breadcrumb ul li a:hover { color: var(--primary); }
        .faq-breadcrumb ul li.sep { color: rgba(255,255,255,0.25); }
        .faq-breadcrumb ul li.current { color: var(--primary); font-weight: 500; }

        /* Layout dua kolom */
        .faq-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 32px;
            padding: 40px 0 60px;
            align-items: start;
        }
        @media (max-width: 768px) {
            .faq-layout { grid-template-columns: 1fr; }
            .faq-sidebar { display: none; }
        }

        /* ─── Sidebar ─── */
        .faq-sidebar {
            position: sticky;
            top: 80px;
        }
        .faq-sidebar-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--gray-light);
            margin-bottom: 12px;
            padding-left: 4px;
        }
        .faq-nav {
            background: var(--card-bg);
            border: 1px solid var(--gray-border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .faq-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: var(--gray);
            border-bottom: 1px solid var(--gray-border);
            transition: var(--transition);
        }
        .faq-nav a:last-child { border-bottom: none; }
        .faq-nav a i {
            width: 16px;
            font-size: 13px;
            color: var(--gray-light);
            transition: var(--transition);
        }
        .faq-nav a:hover,
        .faq-nav a.active {
            background: #fff8f3;
            color: var(--primary);
            font-weight: 600;
        }
        .faq-nav a:hover i,
        .faq-nav a.active i { color: var(--primary); }

        /* Kontak kotak sidebar */
        .faq-sidebar-contact {
            margin-top: 20px;
            background: var(--card-bg);
            border: 1px solid var(--gray-border);
            border-radius: var(--radius);
            padding: 16px;
        }
        .faq-sidebar-contact h5 {
            font-size: 13px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 12px;
        }
        .faq-contact-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 10px;
        }
        .faq-contact-item:last-child { margin-bottom: 0; }
        .faq-contact-item i { color: var(--primary); margin-top: 2px; width: 14px; }
        .faq-contact-item a { color: var(--primary); }

        /* ─── Accordion ─── */
        .faq-section {
            margin-bottom: 36px;
        }
        .faq-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gray-border);
        }
        .faq-section-title i {
            color: var(--primary);
            font-size: 16px;
        }

        .faq-item {
            background: var(--card-bg);
            border: 1px solid var(--gray-border);
            border-radius: var(--radius);
            margin-bottom: 8px;
            overflow: hidden;
            transition: box-shadow 0.25s;
        }
        .faq-item:hover { box-shadow: var(--shadow); }
        .faq-item.open { box-shadow: var(--shadow); border-color: var(--primary); }

        .faq-question {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 20px;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            font-family: var(--font);
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            transition: var(--transition);
        }
        .faq-question:hover { color: var(--primary); }
        .faq-item.open .faq-question { color: var(--primary); }

        .faq-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: var(--gray);
            flex-shrink: 0;
            transition: var(--transition);
        }
        .faq-item.open .faq-icon {
            background: var(--primary);
            color: #fff;
            transform: rotate(45deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s ease;
        }
        .faq-answer-inner {
            padding: 0 20px 18px;
            font-size: 14px;
            line-height: 1.75;
            color: var(--gray);
            border-top: 1px solid var(--gray-border);
            padding-top: 14px;
        }
        .faq-answer-inner p { margin-bottom: 10px; }
        .faq-answer-inner p:last-child { margin-bottom: 0; }
        .faq-answer-inner ul {
            list-style: disc;
            padding-left: 20px;
            margin: 8px 0;
        }
        .faq-answer-inner ul li { margin-bottom: 6px; }
        .faq-answer-inner strong { color: var(--dark); }

        /* Tabel estimasi */
        .delivery-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 4px;
            font-size: 13px;
        }
        .delivery-table th {
            background: var(--bg);
            color: var(--dark);
            font-weight: 700;
            padding: 9px 14px;
            text-align: left;
            border: 1px solid var(--gray-border);
        }
        .delivery-table td {
            padding: 9px 14px;
            border: 1px solid var(--gray-border);
            color: var(--gray);
            vertical-align: top;
        }
        .delivery-table tr:hover td { background: #fafafa; }
        .badge-hari {
            display: inline-block;
            background: #fff3e0;
            color: var(--primary);
            border-radius: 4px;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: 700;
        }

        /* Mitra pengiriman cards */
        .courier-cards {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        .courier-card {
            flex: 1;
            min-width: 140px;
            background: var(--bg);
            border: 1px solid var(--gray-border);
            border-radius: var(--radius);
            padding: 14px 16px;
            text-align: center;
            transition: var(--transition);
        }
        .courier-card:hover {
            border-color: var(--primary);
            background: #fff8f3;
        }
        .courier-card i {
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 8px;
            display: block;
        }
        .courier-card strong {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
        }
        .courier-card a {
            font-size: 11px;
            color: var(--primary);
            text-decoration: underline;
        }

        /* Info banner */
        .faq-info-banner {
            background: linear-gradient(135deg, #fff3e0 0%, #fff8f3 100%);
            border: 1px solid #ffcc80;
            border-radius: var(--radius);
            padding: 16px 20px;
            display: flex;
            gap: 14px;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        .faq-info-banner i {
            color: var(--primary);
            font-size: 18px;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .faq-info-banner p {
            font-size: 13px;
            color: #6d3a00;
            line-height: 1.6;
            margin: 0;
        }

        /* Tombol back to top */
        .back-to-top {
            position: fixed;
            bottom: 28px;
            right: 28px;
            width: 44px;
            height: 44px;
            background: var(--primary);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            box-shadow: 0 4px 16px rgba(255,102,0,0.4);
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            cursor: pointer;
            z-index: 999;
            border: none;
        }
        .back-to-top.visible { opacity: 1; visibility: visible; }
        .back-to-top:hover { background: var(--primary-dark); transform: translateY(-3px); }
    </style>
</head>
<body>

<!-- ===== HEADER (identik dengan a2iu.php) ===== -->
<header class="site-header" id="siteHeader">
    <div class="header-top">
        <div class="container">
            <div class="header-top-inner">
                <a href="a2iu.php" class="logo">
                    <span class="logo-text">A2IU</span>
                    <span class="logo-sub">store</span>
                </a>
                <nav class="main-nav">
                    <ul>
                        <li><a href="a2iu.php" class="nav-link">Beranda</a></li>
                        <li><a href="a2iu.php#products" class="nav-link">Produk</a></li>
                        <li><a href="a2iu.php#about" class="nav-link">Tentang</a></li>
                        <li><a href="faq-pengiriman.php" class="nav-link active">FAQ</a></li>
                    </ul>
                </nav>
                <div class="header-actions">
                    <div class="user-wrap">
                        <button class="icon-btn"><i class="fas fa-user"></i></button>
                        <div class="user-dropdown">
                            <?php if ($user): ?>
                                <div class="user-info-drop"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($user['name']) ?></div>
                                <a href="a2iu.php#orders"><i class="fas fa-box"></i> Pesanan Saya</a>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <a href="a2iu.php#admin"><i class="fas fa-cog"></i> Admin Panel</a>
                                <?php endif; ?>
                                <a href="a2iu.php?action=logout" class="logout-link"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                            <?php else: ?>
                                <a href="a2iu.php#login"><i class="fas fa-sign-in-alt"></i> Masuk</a>
                                <a href="a2iu.php#register"><i class="fas fa-user-plus"></i> Daftar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="a2iu.php#cart" class="icon-btn cart-btn">
                        <i class="fas fa-shopping-cart"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- ===== HERO ===== -->
<section class="faq-hero">
    <div class="container">
        <div class="faq-hero-inner">
            <div class="faq-hero-icon"><i class="fas fa-truck"></i></div>
            <div>
                <h1>FAQ Pengiriman</h1>
                <p>Pertanyaan umum seputar pengiriman dan pengantaran pesanan A2IU Store</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== BREADCRUMB ===== -->
<nav class="faq-breadcrumb">
    <div class="container">
        <ul>
            <li><a href="a2iu.php"><i class="fas fa-home"></i> Beranda</a></li>
            <li class="sep"><i class="fas fa-chevron-right" style="font-size:10px"></i></li>
            <li><a href="#">Dukungan</a></li>
            <li class="sep"><i class="fas fa-chevron-right" style="font-size:10px"></i></li>
            <li class="current">FAQ Pengiriman</li>
        </ul>
    </div>
</nav>

<!-- ===== MAIN CONTENT ===== -->
<div class="container">
    <div class="faq-layout">

        <!-- ─── SIDEBAR ─── -->
        <aside class="faq-sidebar">
            <div class="faq-sidebar-title">Daftar Topik</div>
            <nav class="faq-nav">
                <a href="#area-pengiriman" class="active"><i class="fas fa-map-marker-alt"></i> Area Pengiriman</a>
                <a href="#waktu-pengiriman"><i class="fas fa-clock"></i> Waktu Pengiriman</a>
                <a href="#estimasi-tiba"><i class="fas fa-calendar-alt"></i> Estimasi Tiba</a>
                <a href="#penerimaan-paket"><i class="fas fa-box-open"></i> Penerimaan Paket</a>
                <a href="#ubah-alamat"><i class="fas fa-edit"></i> Ubah Alamat</a>
                <a href="#masalah-paket"><i class="fas fa-exclamation-triangle"></i> Masalah Paket</a>
                <a href="#mitra-kurir"><i class="fas fa-shipping-fast"></i> Mitra Kurir</a>
                <a href="#lacak-pesanan"><i class="fas fa-search-location"></i> Lacak Pesanan</a>
                <a href="#hubungi-kami"><i class="fas fa-headset"></i> Hubungi Kami</a>
            </nav>

            <div class="faq-sidebar-contact">
                <h5><i class="fas fa-headset" style="color:var(--primary);margin-right:6px"></i> Butuh Bantuan?</h5>
                <div class="faq-contact-item">
                    <i class="fas fa-envelope"></i>
                    <span><a href="mailto:cs@a2iu.store">cs@a2iu.store</a></span>
                </div>
                <div class="faq-contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Jl. Serayu Timur No.04, Pandean, Madiun, Jawa Timur 63133</span>
                </div>
                <div class="faq-contact-item">
                    <i class="fas fa-clock"></i>
                    <span>Senin – Jumat, 08:00 – 17:00 WIB</span>
                </div>
            </div>
        </aside>

        <!-- ─── KONTEN FAQ ─── -->
        <div class="faq-content">

            <!-- Info Banner -->
            <div class="faq-info-banner">
                <i class="fas fa-info-circle"></i>
                <p>Semua produk A2IU Store dikirim langsung dari gudang resmi kami di Madiun, Jawa Timur, dan diasuransikan selama proses pengiriman. Pastikan alamat pengiriman Anda sudah benar sebelum melakukan checkout.</p>
            </div>

            <!-- ─── SECTION: Area Pengiriman ─── -->
            <section class="faq-section" id="area-pengiriman">
                <h2 class="faq-section-title"><i class="fas fa-map-marker-alt"></i> Area Pengiriman</h2>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Kemana saja A2IU Store bisa mengirimkan pesanan?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>A2IU Store melayani pengiriman ke seluruh wilayah Indonesia, termasuk:</p>
                            <ul>
                                <li>Seluruh kota/kabupaten di <strong>Pulau Jawa</strong></li>
                                <li>Kota-kota besar di <strong>Sumatera, Kalimantan, Sulawesi, Bali, NTB, dan NTT</strong></li>
                                <li>Wilayah <strong>Papua dan Maluku</strong> (dengan estimasi pengiriman lebih panjang)</li>
                            </ul>
                            <p>Jika area Anda tidak tersedia saat checkout, berarti layanan pengiriman ke wilayah tersebut belum tersedia. Kami terus memperluas jangkauan pengiriman secara bertahap.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Apakah ada biaya pengiriman?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>Biaya pengiriman dihitung berdasarkan berat/dimensi produk dan lokasi tujuan pengiriman. Biaya akan ditampilkan secara transparan pada halaman checkout sebelum Anda menyelesaikan pembayaran.</p>
                            <p>A2IU Store sesekali mengadakan promo <strong>gratis ongkir</strong> untuk pembelian di atas nominal tertentu. Pantau halaman promo kami untuk info terbaru.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ─── SECTION: Waktu Pengiriman ─── -->
            <section class="faq-section" id="waktu-pengiriman">
                <h2 class="faq-section-title"><i class="fas fa-clock"></i> Waktu Pengiriman</h2>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Kapan waktu pengantaran dari A2IU Store?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>Jadwal pengantaran paket dari mitra kurir kami adalah sebagai berikut:</p>
                            <ul>
                                <li><strong>Senin – Jumat:</strong> 08:00 – 18:00 WIB</li>
                                <li><strong>Sabtu:</strong> 08:00 – 14:00 WIB</li>
                                <li><strong>Minggu & Hari Libur Nasional:</strong> Tidak ada pengantaran</li>
                            </ul>
                            <p>Pesanan yang masuk setelah pukul 14:00 WIB pada hari kerja akan diproses pada hari kerja berikutnya.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Berapa lama proses verifikasi sebelum pesanan dikirim?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>Setelah pembayaran dikonfirmasi, tim A2IU Store akan memproses dan mengemas pesanan Anda dalam waktu <strong>1×24 jam kerja</strong>. Pesanan kemudian diserahkan ke kurir untuk dikirimkan ke alamat Anda.</p>
                            <p>Anda akan menerima notifikasi email beserta nomor resi saat pesanan sudah diserahkan ke kurir.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ─── SECTION: Estimasi Tiba ─── -->
            <section class="faq-section" id="estimasi-tiba">
                <h2 class="faq-section-title"><i class="fas fa-calendar-alt"></i> Estimasi Tiba</h2>

                <div class="faq-item open">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Kapan saya akan menerima pesanan saya?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer" style="max-height:600px">
                        <div class="faq-answer-inner">
                            <p>Setelah pesanan dikirim dari gudang kami, estimasi waktu tiba adalah sebagai berikut:</p>

                            <strong>📦 Barang Kecil (Smartphone, Smartwatch, Aksesoris)</strong>
                            <table class="delivery-table">
                                <thead>
                                    <tr>
                                        <th>Wilayah Tujuan</th>
                                        <th>Estimasi Tiba</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Madiun & sekitarnya</td>
                                        <td><span class="badge-hari">1 hari</span></td>
                                    </tr>
                                    <tr>
                                        <td>Jawa Timur (kota besar: Surabaya, Malang, Kediri)</td>
                                        <td><span class="badge-hari">1–2 hari</span></td>
                                    </tr>
                                    <tr>
                                        <td>Jawa Tengah, Jawa Barat, DI Yogyakarta</td>
                                        <td><span class="badge-hari">2–4 hari</span></td>
                                    </tr>
                                    <tr>
                                        <td>DKI Jakarta & Banten</td>
                                        <td><span class="badge-hari">2–5 hari</span></td>
                                    </tr>
                                    <tr>
                                        <td>Sumatera, Bali, Kalimantan, Sulawesi</td>
                                        <td><span class="badge-hari">4–10 hari</span></td>
                                    </tr>
                                    <tr>
                                        <td>Papua, Maluku, NTT</td>
                                        <td><span class="badge-hari">10–20 hari</span></td>
                                    </tr>
                                </tbody>
                            </table>

                            <br>
                            <strong>🖥️ Barang Besar (Laptop, Tablet)</strong>
                            <table class="delivery-table">
                                <thead>
                                    <tr>
                                        <th>Wilayah Tujuan</th>
                                        <th>Estimasi Tiba</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Madiun & sekitarnya</td>
                                        <td><span class="badge-hari">1–2 hari</span></td>
                                    </tr>
                                    <tr>
                                        <td>Jawa Timur (kota besar)</td>
                                        <td><span class="badge-hari">2–4 hari</span></td>
                                    </tr>
                                    <tr>
                                        <td>Pulau Jawa lainnya</td>
                                        <td><span class="badge-hari">3–7 hari</span></td>
                                    </tr>
                                    <tr>
                                        <td>Luar Pulau Jawa</td>
                                        <td><span class="badge-hari">7–20 hari</span></td>
                                    </tr>
                                    <tr>
                                        <td>Papua, Maluku, NTT</td>
                                        <td><span class="badge-hari">15–30 hari</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            <p style="margin-top:10px;font-size:12px;color:#aaa"><i class="fas fa-info-circle" style="color:var(--primary)"></i> Estimasi di atas tidak termasuk hari Minggu dan hari libur nasional. Kondisi cuaca dan kepadatan logistik dapat memengaruhi waktu pengiriman.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ─── SECTION: Penerimaan Paket ─── -->
            <section class="faq-section" id="penerimaan-paket">
                <h2 class="faq-section-title"><i class="fas fa-box-open"></i> Penerimaan Paket</h2>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Apakah saya perlu menunjukkan identitas saat menerima paket?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>Tidak diperlukan KTP atau identitas resmi. Namun, <strong>penerima yang berwenang wajib menandatangani dokumen pengiriman</strong> sebagai bukti bahwa paket telah diterima dalam kondisi baik.</p>
                            <p>Pastikan ada orang yang bisa menerima paket di alamat tujuan pada jam pengantaran. Jika tidak ada, kurir umumnya akan meninggalkan pemberitahuan dan mencoba pengiriman ulang.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Apa yang harus saya lakukan saat menerima paket?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>Ikuti langkah berikut sebelum menandatangani dokumen penerimaan:</p>
                            <ul>
                                <li>Periksa kondisi <strong>luar kemasan/box</strong> — pastikan tidak ada penyok, sobekan, atau tanda-tanda sudah dibuka.</li>
                                <li>Jika kemasan tampak mencurigakan, <strong>tolak pengiriman</strong> dan jangan menandatangani dokumen apapun.</li>
                                <li>Serahkan paket kembali ke kurir — kami akan mengganti paket Anda dalam beberapa hari kerja.</li>
                                <li>Setelah box dibuka dan Anda menemukan kerusakan atau kehilangan produk, segera hubungi <strong>Customer Service A2IU Store</strong>.</li>
                            </ul>
                            <p>Semua produk dalam perjalanan <strong>diasuransikan</strong>. Laporan kerusakan harus disampaikan dalam waktu <strong>2×24 jam</strong> setelah paket diterima.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ─── SECTION: Ubah Alamat ─── -->
            <section class="faq-section" id="ubah-alamat">
                <h2 class="faq-section-title"><i class="fas fa-edit"></i> Ubah Alamat Pengiriman</h2>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Bisakah saya mengubah alamat pengiriman setelah pesanan dibuat?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p><strong>Setelah pesanan ditempatkan, alamat pengiriman tidak dapat diubah langsung.</strong> Anda perlu membatalkan pesanan terlebih dahulu, lalu memesan ulang dengan alamat yang benar.</p>
                            <p>Jika Anda sudah melakukan pembayaran, dana akan dikembalikan sesuai <a href="#" style="color:var(--primary)">Kebijakan Pengembalian Dana</a> A2IU Store.</p>
                            <p>Untuk memastikan alamat sudah benar, selalu cek kembali detail pengiriman sebelum menekan tombol <strong>"Buat Pesanan"</strong>.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Bisakah saya membatalkan pesanan setelah melakukan pembayaran?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>Pembatalan pesanan hanya dapat dilakukan selama status masih <strong>"Pending"</strong> (menunggu proses). Jika pesanan sudah berstatus <strong>"Processing"</strong> atau lebih, pembatalan tidak dapat dilakukan.</p>
                            <p>Segera hubungi Customer Service kami melalui email <a href="mailto:cs@a2iu.store" style="color:var(--primary)">cs@a2iu.store</a> jika Anda ingin membatalkan pesanan.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ─── SECTION: Masalah Paket ─── -->
            <section class="faq-section" id="masalah-paket">
                <h2 class="faq-section-title"><i class="fas fa-exclamation-triangle"></i> Masalah pada Paket</h2>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Bagaimana jika paket dikirim ke orang yang salah?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>Jika paket Anda dikirim ke alamat atau orang yang salah, segera hubungi mitra pengiriman kami atau Customer Service A2IU Store untuk mendapatkan bantuan penyelesaian masalah tersebut.</p>
                            <p>Jangan membuka paket yang bukan milik Anda. Serahkan kepada kurir atau laporkan segera agar dapat dilakukan investigasi dan pengiriman ulang.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Produk yang saya terima rusak. Apa yang harus saya lakukan?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>Segera laporkan dalam <strong>2×24 jam</strong> setelah menerima paket melalui:</p>
                            <ul>
                                <li>Email ke <a href="mailto:cs@a2iu.store" style="color:var(--primary)">cs@a2iu.store</a> beserta foto/video kerusakan</li>
                                <li>Fitur <strong>Klaim Retur</strong> di halaman "Pesanan Saya" (jika sudah login)</li>
                            </ul>
                            <p>Tim kami akan memproses klaim retur dan memberikan solusi dalam waktu 3–5 hari kerja. Pastikan Anda menyimpan kemasan asli sebagai bukti.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Apa yang terjadi jika paket hilang dalam pengiriman?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>Semua barang yang dikirim A2IU Store <strong>diasuransikan</strong>. Jika paket dinyatakan hilang setelah investigasi bersama pihak kurir, kami akan memberikan penggantian produk atau refund penuh kepada Anda.</p>
                            <p>Laporkan kepada kami jika pesanan tidak kunjung tiba setelah melewati estimasi maksimum pengiriman.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ─── SECTION: Mitra Kurir ─── -->
            <section class="faq-section" id="mitra-kurir">
                <h2 class="faq-section-title"><i class="fas fa-shipping-fast"></i> Mitra Kurir</h2>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Perusahaan kurir apa yang digunakan A2IU Store?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>A2IU Store bermitra dengan kurir-kurir terpercaya untuk memastikan produk Anda tiba dengan aman:</p>
                            <div class="courier-cards">
                                <div class="courier-card">
                                    <i class="fas fa-truck"></i>
                                    <strong>J&T Express</strong>
                                    <span style="font-size:12px;color:var(--gray);display:block;margin-bottom:4px">Pengiriman reguler & ekspres</span>
                                    <a href="https://jet.co.id/" target="_blank" rel="noopener">jet.co.id</a>
                                </div>
                                <div class="courier-card">
                                    <i class="fas fa-boxes"></i>
                                    <strong>J&T Cargo</strong>
                                    <span style="font-size:12px;color:var(--gray);display:block;margin-bottom:4px">Untuk barang besar/berat</span>
                                    <a href="https://www.jtcargo.id/" target="_blank" rel="noopener">jtcargo.id</a>
                                </div>
                                <div class="courier-card">
                                    <i class="fas fa-bolt"></i>
                                    <strong>Ninja Xpress</strong>
                                    <span style="font-size:12px;color:var(--gray);display:block;margin-bottom:4px">Pengiriman cepat &amp; terjangkau</span>
                                    <a href="https://www.ninjaxpress.co/id-id" target="_blank" rel="noopener">ninjaxpress.co</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ─── SECTION: Lacak Pesanan ─── -->
            <section class="faq-section" id="lacak-pesanan">
                <h2 class="faq-section-title"><i class="fas fa-search-location"></i> Lacak Pesanan</h2>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Bagaimana cara melacak pesanan saya?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>Anda bisa melacak status pesanan melalui dua cara:</p>
                            <ul>
                                <li><strong>Di A2IU Store:</strong> Masuk ke akun Anda → <em>Pesanan Saya</em> → pilih pesanan → lihat status pengiriman dan nomor resi.</li>
                                <li><strong>Langsung di situs kurir:</strong> Gunakan nomor resi yang kami kirim via email untuk melacak di situs mitra kurir.</li>
                            </ul>
                            <div class="courier-cards" style="margin-top:12px">
                                <div class="courier-card">
                                    <i class="fas fa-external-link-alt"></i>
                                    <strong>J&T Express</strong>
                                    <a href="https://jet.co.id/" target="_blank" rel="noopener">Lacak di jet.co.id</a>
                                </div>
                                <div class="courier-card">
                                    <i class="fas fa-external-link-alt"></i>
                                    <strong>J&T Cargo</strong>
                                    <a href="https://www.jtcargo.id/" target="_blank" rel="noopener">Lacak di jtcargo.id</a>
                                </div>
                                <div class="courier-card">
                                    <i class="fas fa-external-link-alt"></i>
                                    <strong>Ninja Xpress</strong>
                                    <a href="https://www.ninjaxpress.co/id-id" target="_blank" rel="noopener">Lacak di ninjaxpress.co</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Nomor resi saya belum muncul di situs kurir, kenapa?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>Nomor resi biasanya membutuhkan waktu <strong>6–12 jam</strong> setelah pesanan diserahkan ke kurir untuk aktif di sistem pelacakan mereka. Silakan coba lagi beberapa saat kemudian.</p>
                            <p>Jika setelah 24 jam resi masih tidak ditemukan, hubungi kami melalui <a href="mailto:cs@a2iu.store" style="color:var(--primary)">cs@a2iu.store</a>.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ─── SECTION: Hubungi Kami ─── -->
            <section class="faq-section" id="hubungi-kami">
                <h2 class="faq-section-title"><i class="fas fa-headset"></i> Hubungi Kami</h2>

                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        Bagaimana cara menghubungi Customer Service A2IU Store?
                        <span class="faq-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            <p>Tim Customer Service kami siap membantu Anda melalui:</p>
                            <ul>
                                <li><i class="fas fa-envelope" style="color:var(--primary);margin-right:6px"></i> <strong>Email:</strong> <a href="mailto:cs@a2iu.store" style="color:var(--primary)">cs@a2iu.store</a> — respons dalam 1×24 jam kerja</li>
                                <li><i class="fab fa-instagram" style="color:var(--primary);margin-right:6px"></i> <strong>Instagram:</strong> <a href="https://www.instagram.com/ti1c.creative" target="_blank" style="color:var(--primary)">@ti1c.creative</a></li>
                                <li><i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:6px"></i> <strong>Kunjungi langsung:</strong> Jl. Serayu Timur No.04, Pandean, Madiun</li>
                            </ul>
                            <p>Jam operasional: <strong>Senin – Jumat, 08:00 – 17:00 WIB</strong>.</p>
                        </div>
                    </div>
                </div>
            </section>

        </div><!-- /faq-content -->
    </div><!-- /faq-layout -->
</div><!-- /container -->

<!-- FOOTER -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <div class="footer-logo">A2IU<span>store</span></div>
                <p>Toko elektronik resmi dengan produk berkualitas dan layanan terbaik.</p>
                <div class="social-links">
                    <a href="https://www.instagram.com/ti1c.creative?igsh=MXRidm1zOHhocjlxNA==" target="_blank"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Produk</h4>
                <ul>
                    <li><a href="a2iu.php">Smartphone</a></li>
                    <li><a href="a2iu.php">Tablet</a></li>
                    <li><a href="a2iu.php">Laptop</a></li>
                    <li><a href="a2iu.php">Smartwatch</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Layanan</h4>
                <ul>
                    <li><a href="faq-pengiriman.php">FAQ Pengiriman</a></li>
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

<!-- Back to Top -->
<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
    // ─── Header scroll effect ───────────────────────────────
    const header = document.getElementById('siteHeader');
    window.addEventListener('scroll', () => {
        header.classList.toggle('scrolled', window.scrollY > 40);
        document.getElementById('backToTop').classList.toggle('visible', window.scrollY > 300);
    });

    // ─── Accordion toggle ───────────────────────────────────
    function toggleFaq(btn) {
        const item = btn.closest('.faq-item');
        const answer = item.querySelector('.faq-answer');
        const isOpen = item.classList.contains('open');

        // Tutup semua yang lain
        document.querySelectorAll('.faq-item.open').forEach(el => {
            if (el !== item) {
                el.classList.remove('open');
                el.querySelector('.faq-answer').style.maxHeight = '0';
            }
        });

        if (isOpen) {
            item.classList.remove('open');
            answer.style.maxHeight = '0';
        } else {
            item.classList.add('open');
            answer.style.maxHeight = answer.scrollHeight + 'px';
        }
    }

    // ─── Sidebar nav active state pada scroll ───────────────
    const sections = document.querySelectorAll('.faq-section');
    const navLinks = document.querySelectorAll('.faq-nav a');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                navLinks.forEach(link => {
                    link.classList.toggle('active', link.getAttribute('href') === '#' + entry.target.id);
                });
            }
        });
    }, { rootMargin: '-30% 0px -60% 0px' });

    sections.forEach(sec => observer.observe(sec));

    // ─── Sidebar nav smooth scroll ──────────────────────────
    navLinks.forEach(link => {
        link.addEventListener('click', e => {
            const href = link.getAttribute('href');
            if (href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });

    // ─── Expand item yang sudah .open saat load ─────────────
    document.querySelectorAll('.faq-item.open').forEach(item => {
        const answer = item.querySelector('.faq-answer');
        answer.style.maxHeight = answer.scrollHeight + 'px';
    });
</script>

</body>
</html>