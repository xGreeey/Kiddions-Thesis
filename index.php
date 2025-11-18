<?php
require_once __DIR__ . '/security/session_config.php';
// Prevent cache/back-forward showing old dashboard state
if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}
// If user already has an authenticated session, redirect them to their dashboard
// so opening mmtvtc.com in another tab lands on the app (like Facebook)
// If a recent logout flag is present, do not auto-redirect; clear the flag and log
if (!empty($_COOKIE['MMTVTC_LOGOUT_FLAG'])) {
  error_log('INDEX: logout flag detected, skip auto-redirect');
  // Do not clear the flag here; allow it to naturally expire so remember-me does not revive immediately
}

if (session_status() === PHP_SESSION_ACTIVE) {
  $isAuthenticated = (
    isset($_SESSION['user_verified']) && $_SESSION['user_verified'] === true ||
    isset($_SESSION['id']) || isset($_SESSION['user_id']) ||
    isset($_SESSION['student_number']) || isset($_SESSION['email'])
  );
  if ($isAuthenticated) {
    error_log('INDEX: authenticated visit | role=' . ($_SESSION['is_role'] ?? $_SESSION['user_role'] ?? 'none') . ' | sid=' . session_id());
    $role = $_SESSION['is_role'] ?? $_SESSION['user_role'] ?? null; // 2 = admin (based on handlers)
    if ($role === 2 || $role === '2' || $role === 'admin') {
      header('Location: dashboard/admin_dashboard.php');
      exit();
    }
    // If there is an instructor role, try to route there
    if ($role === 1 || $role === '1' || $role === 'instructor') {
      header('Location: dashboard/instructors_dashboard.php');
      exit();
    }
    // Default to student dashboard when authenticated but no explicit role
    header('Location: dashboard/student_dashboard.php');
    exit();
  }
}
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once __DIR__ . '/security/db_connect.php';

// Fetch announcements from database (reuse logic from login)
$announcements = [];
try {
  $stmt = $pdo->prepare(
    "SELECT title, content, date_created, type FROM announcements WHERE is_active = 1 ORDER BY date_created DESC LIMIT 10"
  );
  $stmt->execute();
  $dbAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($dbAnnouncements as $announcement) {
    $announcements[] = [
      'title' => $announcement['title'],
      'content' => $announcement['content'],
      'date' => date('Y-m-d', strtotime($announcement['date_created'])),
      'type' => strtolower($announcement['type'])
    ];
  }
  if (empty($announcements)) {
    $announcements[] = [
      'title' => 'Welcome to MMTVTC',
      'content' => 'Check back here for important announcements and updates.',
      'date' => date('Y-m-d'),
      'type' => 'info'
    ];
  }
} catch (Exception $e) {
  $announcements = [
    [
      'title' => 'System Notice',
      'content' => 'Please check back later for announcements.',
      'date' => date('Y-m-d'),
      'type' => 'info'
    ]
  ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" href="images/logo.png" type="image/png">
  <title>Mandaluyong Manpower and Technical Vocational Training Center</title>
  <!-- Using system fonts to avoid external font hosts for stricter CSP -->
  <style nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    :root {
      --glass-bg: rgba(255, 255, 255, 0.06);
      --glass-border: rgba(255, 255, 255, 0.18);
      --brand: #ffd633;
      --text: #e8ecf3;
      --muted: #a5afc0;
      --btn: #ffd633;
      --btn-text: #1b1f2a;
      --shadow: 0 10px 40px rgba(0,0,0,.35);
      --accent-blue: #5aa2ff;
      --success: #4ade80;
      --warning: #fbbf24;
      --danger: #f87171;
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    html, body { height: 100%; }
    body {
      margin: 0;
      font-family: 'Poppins', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
      color: var(--text);
      background:
        linear-gradient(120deg, rgba(8, 12, 22, .92), rgba(10, 15, 28, .92)),
        url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=2000&q=60') center/cover no-repeat fixed;
      overflow-x: hidden;
    }

    /* Enhanced scroll indicator */
    .scroll-progress {
      position: fixed;
      top: 0;
      left: 0;
      width: 0%;
      height: 3px;
      background: linear-gradient(90deg, var(--brand), var(--accent-blue));
      z-index: 9999;
      transition: width 0.1s ease;
    }

    /* Glassmorphic Header - Enhanced */
    header.navbar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 28px;
      margin: 14px auto;
      max-width: 1200px;
      background: var(--glass-bg);
      border: 1px solid var(--glass-border);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border-radius: 16px;
      box-shadow: var(--shadow);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .navbar.scrolled {
      background: rgba(15, 20, 32, 0.85);
      border-color: rgba(255, 255, 255, 0.25);
      box-shadow: 0 20px 60px rgba(0,0,0,.5);
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 700;
      letter-spacing: .2px;
    }
    .brand img {
      width: 34px;
      height: 34px;
      object-fit: cover;
      border-radius: 8px;
      transition: transform 0.3s ease;
    }
    .brand:hover img {
      transform: scale(1.1) rotate(5deg);
    }
    .brand span { 
      color: var(--brand);
      transition: color 0.3s ease;
    }

    nav {
      display: flex;
      align-items: center;
    }
    nav a {
      color: var(--text);
      text-decoration: none;
      margin-left: 22px;
      font-weight: 500;
      opacity: .9;
      position: relative;
      transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    nav a:hover { 
      opacity: 1; 
      transform: translateY(-2px); 
      color: var(--brand);
    }
    nav a::after {
      content: '';
      position: absolute;
      bottom: -8px;
      left: 50%;
      width: 0;
      height: 2px;
      background: var(--brand);
      transform: translateX(-50%);
      transition: width 0.3s ease;
    }
    nav a:hover::after {
      width: 100%;
    }

    /* Mobile menu toggle */
    .mobile-toggle {
      display: none;
      flex-direction: column;
      cursor: pointer;
      padding: 8px;
      border-radius: 8px;
      transition: background 0.3s ease;
    }
    .mobile-toggle:hover {
      background: rgba(255,255,255,0.1);
    }
    .mobile-toggle span {
      width: 24px;
      height: 2px;
      background: var(--text);
      margin: 3px 0;
      transition: all 0.3s ease;
    }
    .mobile-toggle.active span:nth-child(1) {
      transform: rotate(45deg) translate(7px, 7px);
    }
    .mobile-toggle.active span:nth-child(2) {
      opacity: 0;
    }
    .mobile-toggle.active span:nth-child(3) {
      transform: rotate(-45deg) translate(6px, -6px);
    }

    /* Enhanced announcements dropdown */
    .announce-wrap { position: relative; margin-left: 16px; }
    .announce-btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 14px; border-radius: 12px; border: 0; cursor: pointer;
      background: rgba(255,255,255,.06); color: var(--text);
      box-shadow: inset 0 0 0 1px rgba(255,255,255,.12);
      transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }
    .announce-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
      transition: left 0.5s ease;
    }
    .announce-btn:hover::before {
      left: 100%;
    }
    .announce-btn:hover { 
      background: rgba(255,255,255,.15); 
      transform: translateY(-2px); 
      box-shadow: inset 0 0 0 1px rgba(255,255,255,.2), 0 8px 25px rgba(0,0,0,0.2); 
    }
    .announce-btn svg { 
      width: 20px; height: 20px; color: var(--brand);
      transition: transform 0.3s ease;
    }
    .announce-btn:hover svg {
      transform: scale(1.1) rotate(10deg);
    }
    .announce-btn .badge {
      min-width: 20px; height: 20px; border-radius: 10px; padding: 0 6px;
      background: linear-gradient(135deg, var(--brand), #ffed4e); 
      color: #1b1f2a; font-weight: 800; font-size: 11px;
      display: inline-grid; place-items: center;
      box-shadow: 0 2px 8px rgba(255, 214, 51, 0.4);
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.1); }
    }

    .announce-panel {
      position: absolute; top: calc(100% + 12px); right: 0; z-index: 2001;
      width: min(480px, 92vw); max-height: 68vh; overflow: hidden;
      background: linear-gradient(180deg, rgba(20,24,34,.95), rgba(14,18,30,.95));
      border: 1px solid rgba(255,255,255,.14); border-radius: 16px; 
      box-shadow: 0 20px 60px rgba(0,0,0,.45);
      opacity: 0; transform: translateY(-10px) scale(0.95); pointer-events: none;
      transition: all .3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .announce-panel.open { 
      opacity: 1; 
      transform: translateY(0) scale(1); 
      pointer-events: auto; 
    }

    .announce-panel-header {
      padding: 16px 20px 10px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .announce-panel-title {
      font-weight: 700;
      color: var(--brand);
      font-size: 16px;
      margin: 0;
    }
    .announce-panel-close {
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      padding: 4px;
      border-radius: 6px;
      transition: all 0.2s ease;
    }
    .announce-panel-close:hover {
      background: rgba(255,255,255,0.1);
      color: var(--text);
    }

    .announce-content {
      max-height: 400px;
      overflow-y: auto;
      padding: 10px;
    }
    .announce-content::-webkit-scrollbar {
      width: 6px;
    }
    .announce-content::-webkit-scrollbar-track {
      background: rgba(255,255,255,0.05);
      border-radius: 3px;
    }
    .announce-content::-webkit-scrollbar-thumb {
      background: rgba(255,255,255,0.2);
      border-radius: 3px;
    }

    .announce-item { 
      background: rgba(255,255,255,.06); 
      border: 1px solid rgba(255,255,255,.12); 
      border-radius: 12px; 
      padding: 14px; 
      margin: 8px 0;
      transition: all 0.3s ease;
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }
    .announce-item::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent);
      transition: left 0.5s ease;
    }
    .announce-item:hover::before {
      left: 100%;
    }
    .announce-item:hover {
      transform: translateY(-2px);
      background: rgba(255,255,255,.1);
      border-color: rgba(255,255,255,.2);
      box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    }
    .announce-item.important { border-left: 4px solid #ff6b6b; }
    .announce-item.info { border-left: 4px solid #4ecdc4; }
    .announce-item.success { border-left: 4px solid #95e1d3; }
    .announce-item-title { 
      margin: 0 0 8px; 
      font-weight: 700; 
      color: var(--brand); 
      font-size: 15px;
      transition: color 0.3s ease;
    }
    .announce-item:hover .announce-item-title {
      color: #ffed4e;
    }
    .announce-item-content { 
      margin: 0 0 10px; 
      color: #c9d2e3; 
      font-size: 14px; 
      line-height: 1.6; 
      display: -webkit-box; 
      -webkit-line-clamp: 3; 
      -webkit-box-orient: vertical; 
      overflow: hidden; 
    }
    .announce-item-date { 
      color: rgba(255,255,255,.6); 
      font-size: 12px; 
      text-align: right; 
      font-weight: 500;
    }

    /* Enhanced Hero */
    .hero {
      position: relative;
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 0 24px;
      overflow: hidden;
    }
    .hero-inner {
      width: 100%;
      max-width: 1100px;
      text-align: center;
      padding-top: 90px;
      animation: fadeInUp 1s ease-out;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(50px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    h1 {
      margin: 24px 0 16px;
      font-size: clamp(32px, 6vw, 56px);
      font-weight: 800;
      line-height: 1.1;
      background: linear-gradient(135deg, var(--text), var(--brand));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .highlight { 
      background: linear-gradient(135deg, var(--brand), #ffed4e);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .sub {
      max-width: 760px;
      margin: 20px auto 40px;
      color: var(--muted);
      font-size: clamp(14px, 2.2vw, 18px);
      line-height: 1.7;
    }

    /* Enhanced CTA Button */
    .cta {
      display: inline-block;
      padding: 16px 32px;
      border-radius: 14px;
      background: linear-gradient(135deg, var(--btn), #ffed4e);
      color: var(--btn-text);
      font-weight: 700;
      text-decoration: none;
      box-shadow: 0 12px 35px rgba(255,214,51,.4);
      transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
      border: 2px solid transparent;
    }
    .cta::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      transition: left 0.6s ease;
    }
    .cta:hover::before {
      left: 100%;
    }
    .cta:hover { 
      transform: translateY(-3px) scale(1.05); 
      box-shadow: 0 20px 50px rgba(255,214,51,.5);
      border-color: rgba(255,255,255,0.2);
    }

    /* Enhanced particles */
    .particles {
      position: absolute; inset: 0; pointer-events: none; overflow: hidden;
    }
    .particles span {
      position: absolute; width: 6px; height: 6px; border-radius: 50%;
      background: rgba(255,255,255,.35); filter: blur(1px);
      animation: float 10s linear infinite;
    }
    .particles span:nth-child(1) { left: 20%; top: 40%; animation-duration: 8s; }
    .particles span:nth-child(2) { left: 10%; top: 30%; animation-duration: 12s; }
    .particles span:nth-child(3) { left: 75%; top: 20%; animation-duration: 11s; }
    .particles span:nth-child(4) { left: 60%; top: 70%; animation-duration: 13s; }
    .particles span:nth-child(5) { left: 25%; top: 65%; animation-duration: 9s; }
    .particles span:nth-child(6) { left: 85%; top: 50%; animation-duration: 14s; }
    .particles span:nth-child(7) { left: 40%; top: 15%; animation-duration: 7s; }
    
    @keyframes float { 
      0% { transform: translateY(0) rotate(0deg); opacity: .4; } 
      50% { transform: translateY(-30px) rotate(180deg); opacity: .8; } 
      100% { transform: translateY(0) rotate(360deg); opacity: .4; } 
    }

    /* Background Mosaic - Enhanced */
    .mosaic {
      position: absolute; inset: 0; z-index: -1; opacity: .18; filter: saturate(85%) contrast(95%);
      display: grid; grid-template-columns: repeat(6, 1fr); grid-auto-rows: 18vh; gap: 6px; 
      padding: 120px 6px 6px;
      animation: mosaicFloat 20s ease-in-out infinite;
    }
    @keyframes mosaicFloat {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    .mosaic div { 
      border-radius: 12px; 
      background-position: center; 
      background-size: cover;
      transition: all 0.5s ease;
      position: relative;
      overflow: hidden;
    }
    .mosaic div::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(45deg, transparent, rgba(255,214,51,0.1), transparent);
      opacity: 0;
      transition: opacity 0.5s ease;
    }
    .mosaic div:hover::before {
      opacity: 1;
    }

    /* Enhanced Features Section */
    .features-section {
      position: relative;
      max-width: 1200px;
      margin: 0 auto;
      padding: 60px 24px 100px;
    }
    .features-header {
      text-align: center;
      margin-bottom: 50px;
    }
    .features-title {
      color: var(--accent-blue);
      font-weight: 800;
      font-size: clamp(26px, 4vw, 40px);
      margin: 0 0 16px;
      position: relative;
      display: inline-block;
    }
    .features-title::after {
      content: '';
      position: absolute;
      bottom: -8px;
      left: 50%;
      width: 60px;
      height: 4px;
      background: linear-gradient(90deg, var(--brand), var(--accent-blue));
      transform: translateX(-50%);
      border-radius: 2px;
    }
    .features-sub {
      color: #c6d0e4;
      max-width: 820px;
      margin: 0 auto;
      line-height: 1.8;
      font-size: 16px;
    }
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 30px;
      margin-top: 40px;
    }
    .feature-card {
      position: relative;
      background: linear-gradient(145deg, rgba(255,255,255,.08), rgba(255,255,255,.02));
      border: 1px solid rgba(255,255,255,.12);
      border-top: 4px solid var(--brand);
      border-radius: 20px;
      padding: 32px;
      box-shadow: 0 20px 60px rgba(0,0,0,.25);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      transition: all .4s cubic-bezier(0.4, 0, 0.2, 1);
      overflow: hidden;
    }
    .feature-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,214,51,0.08), transparent);
      transition: left 0.8s ease;
    }
    .feature-card:hover::before {
      left: 100%;
    }
    .feature-card:hover { 
      transform: translateY(-8px) scale(1.02); 
      box-shadow: 0 30px 80px rgba(0,0,0,.35);
      border-color: rgba(255,255,255,.25);
      background: linear-gradient(145deg, rgba(255,255,255,.12), rgba(255,255,255,.05));
    }
    .feature-icon { 
      color: var(--brand); 
      margin-bottom: 20px;
      transition: all 0.4s ease;
    }
    .feature-card:hover .feature-icon {
      transform: scale(1.1) rotate(5deg);
      filter: drop-shadow(0 8px 16px rgba(255,214,51,0.3));
    }
    .feature-title { 
      font-size: 24px; 
      font-weight: 700; 
      margin: 0 0 16px;
      transition: color 0.3s ease;
    }
    .feature-card:hover .feature-title {
      color: var(--brand);
    }
    .feature-desc { 
      color: #c9d2e3; 
      margin: 0; 
      line-height: 1.8;
      font-size: 15px;
    }

    .feature-icon svg { 
      display: block; 
      width: 48px; 
      height: 48px;
      filter: drop-shadow(0 4px 8px rgba(255,214,51,0.2));
    }

    /* Hide scrollbars */
    html {
      scrollbar-width: none; /* Firefox */
      -ms-overflow-style: none; /* Internet Explorer 10+ */
    }
    html::-webkit-scrollbar {
      width: 0;
      height: 0;
      display: none; /* Chrome, Safari, Opera */
    }
    body {
      scrollbar-width: none;
      -ms-overflow-style: none;
    }
    body::-webkit-scrollbar {
      display: none;
    }

    /* About MMTVTC Section */
    .about-section {
      max-width: 1200px;
      margin: 0 auto;
      padding: 80px 24px;
      text-align: center;
    }
    .about-title {
      font-size: clamp(28px, 4vw, 42px);
      font-weight: 800;
      margin: 0 0 24px;
      color: var(--text);
    }
    .about-title span {
      color: var(--brand);
    }
    .about-description {
      font-size: 16px;
      color: var(--muted);
      line-height: 1.8;
      max-width: 800px;
      margin: 0 auto 60px;
    }
    .mission-vision-values {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 30px;
      margin-bottom: 80px;
    }
    .mvv-card {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 20px;
      padding: 40px 30px;
      text-align: center;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }
    .mvv-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,214,51,0.05), transparent);
      transition: left 0.8s ease;
    }
    .mvv-card:hover::before {
      left: 100%;
    }
    .mvv-card:hover {
      transform: translateY(-8px);
      background: rgba(255,255,255,0.08);
      border-color: rgba(255,255,255,0.2);
      box-shadow: 0 25px 60px rgba(0,0,0,0.3);
    }
    .mvv-icon {
      width: 60px;
      height: 60px;
      margin: 0 auto 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.4s ease;
    }
    .mvv-card:hover .mvv-icon {
      transform: scale(1.1) rotate(5deg);
    }
    .mvv-icon.mission {
      background: linear-gradient(135deg, #ffd633, #ffed4e);
      color: #1b1f2a;
    }
    .mvv-icon.vision {
      background: linear-gradient(135deg, #5aa2ff, #74b9ff);
      color: #fff;
    }
    .mvv-icon.values {
      background: linear-gradient(135deg, #4ecdc4, #95e1d3);
      color: #1b1f2a;
    }
    .mvv-icon svg {
      width: 32px;
      height: 32px;
    }
    .mvv-title {
      font-size: 24px;
      font-weight: 700;
      margin: 0 0 16px;
      color: var(--text);
    }
    .mvv-description {
      font-size: 15px;
      color: var(--muted);
      line-height: 1.7;
      margin: 0;
    }

    .values-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
      align-items: flex-start; /* left-align rows so columns line up */
      width: max-content; /* shrink to content width */
      max-width: 100%;
      margin: 0 auto; /* center the whole block */
    }

    .value-item {
      display: grid;
      grid-template-columns: 28px 12px auto; /* letter, dash, word */
      align-items: center;
      column-gap: 8px;
      width: max-content;
      margin: 0; /* inherit left alignment from parent */
      padding: 4px 0;
    }

    .value-letter {
      font-weight: 800;
      font-size: 18px;
      color: var(--brand); /* This uses your existing yellow color */
      width: 28px;
      min-width: 28px;
      text-align: center;
      margin-right: 0;
    }

    .value-dash {
      color: var(--muted);
      font-weight: 500;
      width: 12px;
      min-width: 12px;
      text-align: center;
      margin-right: 0;
    }

    .value-word {
      color: var(--text);
      font-weight: 500;
      font-size: 15px;
    }

    /* Impact Section */
    .impact-section {
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 24px;
      padding: 50px 40px;
      margin-top: 40px;
    }
    .impact-title {
      font-size: 32px;
      font-weight: 700;
      color: var(--text);
      text-align: center;
      margin: 0 0 40px;
    }
    .impact-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 40px;
      text-align: center;
    }
    .stat-item {
      transition: transform 0.3s ease;
    }
    .stat-item:hover {
      transform: translateY(-4px);
    }
    .stat-number {
      font-size: clamp(32px, 5vw, 48px);
      font-weight: 800;
      color: var(--brand);
      margin: 0 0 8px;
      display: block;
    }
    .stat-label {
      font-size: 16px;
      color: var(--muted);
      font-weight: 500;
    }

    /* Contact Section */
    .contact-section {
      max-width: 1200px;
      margin: 0 auto;
      padding: 80px 24px;
    }
    .contact-header {
      text-align: center;
      margin-bottom: 60px;
    }
    .contact-title {
      font-size: clamp(28px, 4vw, 42px);
      font-weight: 800;
      margin: 0 0 16px;
      color: var(--text);
    }
    .contact-title span {
      color: var(--brand);
    }
    .contact-subtitle {
      font-size: 16px;
      color: var(--muted);
      line-height: 1.8;
      max-width: 600px;
      margin: 0 auto;
    }
    .contact-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 24px;
      margin-bottom: 40px;
    }
    .contact-card {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 16px;
      padding: 32px 24px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    .contact-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,214,51,0.05), transparent);
      transition: left 0.6s ease;
    }
    .contact-card:hover::before {
      left: 100%;
    }
    .contact-card:hover {
      transform: translateY(-4px);
      background: rgba(255,255,255,0.08);
      border-color: rgba(255,255,255,0.2);
      box-shadow: 0 20px 50px rgba(0,0,0,0.2);
    }
    .contact-icon {
      width: 24px;
      height: 24px;
      color: var(--brand);
      margin-bottom: 16px;
    }
    .contact-card-title {
      font-size: 18px;
      font-weight: 700;
      color: var(--text);
      margin: 0 0 8px;
    }
    .contact-card-content {
      font-size: 14px;
      color: var(--muted);
      line-height: 1.6;
      margin: 0;
    }
    .contact-card-content a {
      color: var(--brand);
      text-decoration: none;
      transition: color 0.3s ease;
    }
    .contact-card-content a:hover {
      color: #ffed4e;
    }

    /* Quick Links removed */

    /* Enhanced footer */
    footer { 
      text-align: center; 
      color: #9aa7bd; 
      font-size: 14px; 
      padding: 40px 16px;
      background: rgba(0,0,0,0.2);
      border-top: 1px solid rgba(255,255,255,0.1);
    }

    /* Scroll to top button */
    .scroll-top {
      position: fixed;
      bottom: 30px;
      right: 30px;
      width: 50px;
      height: 50px;
      background: linear-gradient(135deg, var(--brand), #ffed4e);
      border: none;
      border-radius: 25px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 25px rgba(255,214,51,0.4);
      transform: translateY(100px);
      opacity: 0;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 1000;
    }
    .scroll-top.visible {
      transform: translateY(0);
      opacity: 1;
    }
    .scroll-top:hover {
      transform: translateY(-4px) scale(1.1);
      box-shadow: 0 12px 35px rgba(255,214,51,0.6);
    }
    .scroll-top svg {
      width: 24px;
      height: 24px;
      color: var(--btn-text);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      header.navbar { 
        margin: 10px; 
        padding: 12px 16px; 
      }
      .navbar nav {
        display: none;
        position: fixed;
        top: 70px;
        left: 10px;
        right: 10px;
        background: rgba(15, 20, 32, 0.95);
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 16px;
        padding: 20px;
        flex-direction: column;
        gap: 16px;
        backdrop-filter: blur(20px);
      }
      .navbar nav.active {
        display: flex;
      }
      .mobile-toggle {
        display: flex;
      }
      .mosaic { 
        grid-auto-rows: 16vh; 
        grid-template-columns: repeat(4, 1fr); 
      }
      .features-grid { 
        grid-template-columns: 1fr; 
      }
      .particles span {
        display: none;
      }
      .scroll-top {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
      }
    }

    @media (max-width: 900px) { 
      .announce-panel { 
        width: min(94vw, 520px); 
      } 
    }

    /* Loading animation */
    .loading-overlay {
      position: fixed;
      inset: 0;
      background: linear-gradient(120deg, rgba(8, 12, 22, .98), rgba(10, 15, 28, .98));
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      opacity: 1;
      transition: opacity 0.5s ease;
    }
    .loading-overlay.fade-out {
      opacity: 0;
      pointer-events: none;
    }
    .loader {
      width: 60px;
      height: 60px;
      border: 4px solid rgba(255,214,51,0.2);
      border-top: 4px solid var(--brand);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <!-- Loading overlay -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="loader"></div>
  </div>

  <!-- Scroll progress indicator -->
  <div class="scroll-progress" id="scrollProgress"></div>

  <header class="navbar" id="navbar">
    <div class="brand">
      <img src="images/manpower logo.jpg" alt="MMTVTC logo" />
      <div>
        <div class="brand-subtitle"></div>
        <span>MMTVTC</span>
      </div>
    </div>
    <nav id="navMenu">
      <a href="#home">Home</a>
      <a href="#why">Why Choose Us</a>
      <a href="#about">About</a>
      <a href="#contact">Contact</a>
      <span class="announce-wrap">
        <button id="announceBtn" class="announce-btn" type="button" aria-expanded="false" aria-controls="announcePanel">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          <span>Announcements</span>
          <span class="badge"><?php echo count($announcements); ?></span>
        </button>
        <div id="announcePanel" class="announce-panel" role="region" aria-label="Latest announcements">
          <div class="announce-panel-header">
            <h3 class="announce-panel-title">Latest Announcements</h3>
            <button class="announce-panel-close" id="announcePanelClose" aria-label="Close announcements">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>
          <div class="announce-content">
            <?php if (!empty($announcements)): ?>
              <?php foreach ($announcements as $a): ?>
                <div class="announce-item <?php echo htmlspecialchars($a['type'], ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="announce-item-title"><?php echo htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="announce-item-content"><?php echo htmlspecialchars($a['content'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="announce-item-date"><?php echo htmlspecialchars(date('M d, Y', strtotime($a['date'])), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="announce-item info">
                <div class="announce-item-title">No announcements</div>
                <div class="announce-item-content">Please check back later for updates.</div>
                <div class="announce-item-date"><?php echo htmlspecialchars(date('M d, Y'), ENT_QUOTES, 'UTF-8'); ?></div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </span>
    </nav>
    <div class="mobile-toggle" id="mobileToggle">
      <span></span>
      <span></span>
      <span></span>
    </div>
  </header>

  <main class="hero" id="home">
    <div class="mosaic" aria-hidden="true">
      <div class="mosaic-bg-1"></div>
      <div class="mosaic-bg-2"></div>
      <div class="mosaic-bg-3"></div>
      <div class="mosaic-bg-4"></div>
      <div class="mosaic-bg-5"></div>
      <div class="mosaic-bg-6"></div>
      <div class="mosaic-bg-7"></div>
      <div class="mosaic-bg-8"></div>
      <div class="mosaic-bg-9"></div>
    </div>
    <div class="particles" aria-hidden="true">
      <span></span><span></span><span></span><span></span><span></span><span></span><span></span>
    </div>
    <div class="hero-inner">
      <div class="eyebrow">Bridging Trainees and Industry Partners</div>
      <h1>
        Launch Your Career with
        <span class="highlight">Mandaluyong Manpower and Technical Vocational Training Center</span>
      </h1>
      <p class="sub">Discover internships, jobs, and valuable connections tailored for MMTVTC trainees and trusted by industry leaders.</p>
      <a class="cta" href="EKtJkWrAVAsyyA4fbj1KOrcYulJ2Wu">Login to Get Started</a>
    </div>
  </main>

  <!-- Why Choose Section -->
  <section class="features-section" id="why">
    <div class="features-header">
      <h2 class="features-title">Why Choose MMTVTC?</h2>
      <p class="features-sub">We provide comprehensive training and career services designed to bridge the gap between technical learning and professional success in Mandaluyong.</p>
    </div>
    <div class="features-grid">
      <article class="feature-card">
        <div class="feature-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14.7 6.3a3 3 0 1 0-4.4 4.4L3 18v3h3l7.3-7.3a3 3 0 0 0 4.4-4.4l-3-3z"></path>
          </svg>
        </div>
        <h3 class="feature-title">Skills Training & Certification</h3>
        <p class="feature-desc">Hands-on training aligned with TESDA standards and support for NC assessments to validate your technical skills and boost your career prospects.</p>
      </article>

      <article class="feature-card">
        <div class="feature-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"></path>
            <path d="M2 13h20"></path>
          </svg>
        </div>
        <h3 class="feature-title">OJT and Job Placement</h3>
        <p class="feature-desc">Placement assistance and on-the-job training opportunities with partner companies across Mandaluyong and nearby cities for seamless career transition.</p>
      </article>

      <article class="feature-card">
        <div class="feature-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
        </div>
        <h3 class="feature-title">Industry Partnerships & Networking</h3>
        <p class="feature-desc">Collaborate with trusted employers, attend career events, and grow your professional network for long-term success in your chosen field.</p>
      </article>
    </div>
  </section>

  <section id="about" class="about-section">
    <h2 class="about-title">About <span>MMTVTC</span></h2>
    <p class="about-description">
      MMTVTC connects trainees with real opportunities across multiple industries. Our platform centralizes announcements, training programs, and employer partnerships to help you move from classroom to career.
    </p>
    
    <!-- Mission, Vision, Values Cards -->
    <div class="mission-vision-values">
      <div class="mvv-card">
        <div class="mvv-icon mission">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <circle cx="12" cy="12" r="3"/>
            <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
            <circle cx="12" cy="12" r="12" fill="none" stroke="currentColor" stroke-width="1" opacity="0.3"/>
          </svg>
        </div>
        <h3 class="mvv-title">Our Mission</h3>
        <p class="mvv-description">
          We, The MMTVTC Family, working as a community, commit ourselves to promote lifelong Technical - Vocational Training Experience to Develop Practical Life Skills for Great Advancement.
        </p>
      </div>
      
      <div class="mvv-card">
        <div class="mvv-icon vision">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </div>
        <h3 class="mvv-title">Our Vision</h3>
        <p class="mvv-description">
          To be the center of whole Learning Experience for Great Advancement
        </p>
      </div>
      
      <div class="mvv-card">
        <div class="mvv-icon values">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
        </div>
        <h3 class="mvv-title">Our Core Values</h3>
        <div class="mvv-description values-list">
          <div class="value-item">
            <span class="value-letter">M</span>
            <span class="value-dash">-</span>
            <span class="value-word">Mastery</span>
          </div>
          <div class="value-item">
            <span class="value-letter">M</span>
            <span class="value-dash">-</span>
            <span class="value-word">Manifest</span>
          </div>
          <div class="value-item">
            <span class="value-letter">T</span>
            <span class="value-dash">-</span>
            <span class="value-word">Transforming</span>
          </div>
          <div class="value-item">
            <span class="value-letter">V</span>
            <span class="value-dash">-</span>
            <span class="value-word">Versatile</span>
          </div>
          <div class="value-item">
            <span class="value-letter">T</span>
            <span class="value-dash">-</span>
            <span class="value-word">Testimonial</span>
          </div>
          <div class="value-item">
            <span class="value-letter">C</span>
            <span class="value-dash">-</span>
            <span class="value-word">Competent</span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Impact Section - SEPARATE and OUTSIDE the cards above -->
    <div class="impact-section">
      <h3 class="impact-title">Our Impact</h3>
      <div class="impact-stats">
        <div class="stat-item">
          <span class="stat-number">4,000+</span>
          <span class="stat-label">Graduates Trained</span>
        </div>
        <div class="stat-item">
          <span class="stat-number">90%</span>
          <span class="stat-label">Job Placement Rate</span>
        </div>
        <div class="stat-item">
          <span class="stat-number">10+</span>
          <span class="stat-label">Industry Partners</span>
        </div>
      </div>
    </div>
  </section>

  <section id="contact" class="contact-section">
    <div class="contact-header">
      <h2 class="contact-title">Get in <span>Touch</span></h2>
      <p class="contact-subtitle">
        Ready to start your journey? Contact us for more information about our programs and services.
      </p>
    </div>
    
    <div class="contact-grid">
      <div class="contact-card">
        <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
          <polyline points="22,6 12,13 2,6"/>
        </svg>
        <h3 class="contact-box-title">Email</h3>
        <p class="contact-box-content">
          <a href="mailto:mandaluyongmanpower@mmtvtc.com">mandaluyongmanpower@mmtvtc.com</a>
        </p>
      </div>
      
      <div class="contact-card">
        <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
        <h3 class="contact-box-title">Phone</h3>
        <p class="contact-box-content">Contact us during office hours</p>
      </div>
      
      <div class="contact-card">
        <svg class="contact-icon" viewBox="0 0 24 24" fill="currentColor">
          <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
        </svg>
        <h3 class="contact-box-title">Facebook</h3>
        <p class="contact-box-content">
          <a href="https://www.facebook.com/manpowermanda.tesda/" target="_blank" rel="noopener noreferrer">Follow us on Facebook</a>
        </p>
      </div>
      
      <div class="contact-box">
        <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12,6 12,12 16,14"/>
        </svg>
        <h3 class="contact-box-title">Office Hours</h3>
        <div class="contact-card-content">
          <div>Monday - Friday: 8:00 AM - 5:00 PM</div>
          <div>Saturday: 8:00 AM - 12:00 PM</div>
          <div>Sunday: Closed</div>
        </div>
      </div>
      
      <div class="contact-box">
        <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
          <circle cx="12" cy="10" r="3"/>
        </svg>
        <h3 class="contact-box-title">Location</h3>
        <p class="contact-box-content">Mandaluyong City, Metro Manila</p>
      </div>
      
      
    </div>
  </section>

  <footer>
    © <span id="currentYear"></span> Mandaluyong Manpower and Technical Vocational Training Center
  </footer>

  <!-- Scroll to top button -->
  <button class="scroll-top" id="scrollTop" aria-label="Scroll to top">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="m18 15-6-6-6 6"/>
    </svg>
  </button>

  <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    (function () {
      // Initialize page
      document.addEventListener('DOMContentLoaded', function() {
        // Set current year
        document.getElementById('currentYear').textContent = new Date().getFullYear();
        
        // Hide loading overlay
        setTimeout(() => {
          document.getElementById('loadingOverlay').classList.add('fade-out');
        }, 1000);
      });

      // Navbar scroll effect
      const navbar = document.getElementById('navbar');
      const scrollProgress = document.getElementById('scrollProgress');
      const scrollTop = document.getElementById('scrollTop');

      window.addEventListener('scroll', function() {
        const scrolled = window.pageYOffset;
        const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
        const scrollPercent = (scrolled / maxScroll) * 100;
        
        // Update scroll progress
        scrollProgress.style.width = scrollPercent + '%';
        
        // Navbar effect
        if (scrolled > 100) {
          navbar.classList.add('scrolled');
          scrollTop.classList.add('visible');
        } else {
          navbar.classList.remove('scrolled');
          scrollTop.classList.remove('visible');
        }
      });

      // Scroll to top functionality
      scrollTop.addEventListener('click', function() {
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      });

      // Mobile menu toggle
      const mobileToggle = document.getElementById('mobileToggle');
      const navMenu = document.getElementById('navMenu');
      
      mobileToggle.addEventListener('click', function() {
        mobileToggle.classList.toggle('active');
        navMenu.classList.toggle('active');
      });

      // Close mobile menu when clicking on links
      navMenu.addEventListener('click', function(e) {
        if (e.target.tagName === 'A') {
          mobileToggle.classList.remove('active');
          navMenu.classList.remove('active');
        }
      });

      // Announcements dropdown logic
      const announceBtn = document.getElementById('announceBtn');
      const announcePanel = document.getElementById('announcePanel');
      const announcePanelClose = document.getElementById('announcePanelClose');
      
      if (announceBtn && announcePanel) {
        function togglePanel(e) {
          if (e) e.stopPropagation();
          const open = announcePanel.classList.toggle('open');
          announceBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function closePanel() {
          announcePanel.classList.remove('open');
          announceBtn.setAttribute('aria-expanded', 'false');
        }

        announceBtn.addEventListener('click', togglePanel);
        announcePanelClose.addEventListener('click', closePanel);
        
        // Close when clicking outside
        document.addEventListener('click', function (e) {
          if (!announcePanel.classList.contains('open')) return;
          if (!announcePanel.contains(e.target) && 
              e.target !== announceBtn && 
              !announceBtn.contains(e.target)) {
            closePanel();
          }
        });
        
        // Close on Escape
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && announcePanel.classList.contains('open')) {
            closePanel();
          }
        });

        // Add click effects to announcement items
        const announceItems = document.querySelectorAll('.announce-item');
        announceItems.forEach(item => {
          item.addEventListener('click', function() {
            // Add click feedback
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
              this.style.transform = '';
            }, 150);
          });
        });
      }

      // Smooth scrolling for navigation links
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
          e.preventDefault();
          const target = document.querySelector(this.getAttribute('href'));
          if (target) {
            const offsetTop = target.offsetTop - 100; // Account for fixed header
            window.scrollTo({
              top: offsetTop,
              behavior: 'smooth'
            });
          }
        });
      });

      // Add intersection observer for animations
      const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
      };

      const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
          }
        });
      }, observerOptions);

      // Observe feature cards and content sections
      document.querySelectorAll('.feature-card, .content-section').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
      });

      // Add parallax effect to particles
      window.addEventListener('scroll', function() {
        const scrolled = window.pageYOffset;
        const parallax = document.querySelector('.particles');
        if (parallax) {
          const speed = scrolled * 0.5;
          parallax.style.transform = `translateY(${speed}px)`;
        }
      });

      // Add hover sound effects (optional - can be enabled)
      function addHoverSound() {
        const buttons = document.querySelectorAll('.cta, .feature-card, .announce-btn');
        buttons.forEach(button => {
          button.addEventListener('mouseenter', function() {
            // You can add audio context here if needed
            // const audioContext = new AudioContext();
            // Play subtle hover sound
          });
        });
      }

      // Initialize features
      addHoverSound();

      // Add loading states for dynamic content
      const cta = document.querySelector('.cta');
      cta.addEventListener('click', function(e) {
        const originalText = this.textContent;
        this.textContent = 'Loading...';
        this.style.pointerEvents = 'none';
        
        // Reset after navigation (this would be handled by the actual page change)
        setTimeout(() => {
          this.textContent = originalText;
          this.style.pointerEvents = 'auto';
        }, 2000);
      });

    })();
  </script>
</body>
</html>