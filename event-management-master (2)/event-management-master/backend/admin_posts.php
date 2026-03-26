<?php
session_start();
include "../db/connect.php";

if (empty($_SESSION["portal_csrf"])) {
  $_SESSION["portal_csrf"] = bin2hex(random_bytes(32));
}

// Auto-promote user with admin role to portal_admin session
if (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === 'admin') {
  $_SESSION["portal_admin"] = true;
  if (!isset($_SESSION["admin_username"]) && isset($_SESSION["user_name"])) {
    $_SESSION["admin_username"] = $_SESSION["user_name"];
  }
}

$message = "";

function esc($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function ensure_column($con, $table, $column, $definition) {
  $safeTable = preg_replace("/[^a-zA-Z0-9_]/", "", $table);
  $safeColumn = preg_replace("/[^a-zA-Z0-9_]/", "", $column);
  $res = mysqli_query($con, "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
  if ($res && mysqli_num_rows($res) === 0) {
    mysqli_query($con, "ALTER TABLE `$safeTable` ADD COLUMN $definition");
  }
}

function ensure_admins_table($con) {
  $sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  mysqli_query($con, $sql);
  
  $res = mysqli_query($con, "SELECT COUNT(*) as cnt FROM admins");
  if ($res) {
    $row = mysqli_fetch_assoc($res);
    if ($row["cnt"] == 0) {
      $hashed = password_hash("admin123", PASSWORD_DEFAULT);
      mysqli_query($con, "INSERT INTO admins (username, password) VALUES ('admin', '$hashed')");
    }
  }
}

if (isset($_POST["login"])) {
  ensure_admins_table($con);
  $user = isset($_POST["username"]) ? trim($_POST["username"]) : "";
  $pass = isset($_POST["password"]) ? $_POST["password"] : "";
  
  $stmt = mysqli_prepare($con, "SELECT password FROM admins WHERE username = ?");
  mysqli_stmt_bind_param($stmt, "s", $user);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $admin = mysqli_fetch_assoc($res);
  mysqli_stmt_close($stmt);

  if ($admin && password_verify($pass, $admin["password"])) {
    $_SESSION["portal_admin"] = true;
    $_SESSION["admin_username"] = $user;
    $_SESSION["portal_csrf"] = bin2hex(random_bytes(32));
  } else {
    $message = "Invalid username or password.";
  }
}

if (isset($_POST["logout"])) {
  unset($_SESSION["portal_admin"]);
}

if (isset($_SESSION["portal_admin"]) && $_SESSION["portal_admin"] === true && isset($_POST["moderate_post"])) {
  $csrf = isset($_POST["csrf"]) ? $_POST["csrf"] : "";
  if (!hash_equals($_SESSION["portal_csrf"], $csrf)) {
    $message = "Invalid request token.";
  } else {
    $postId = isset($_POST["post_id"]) ? (int)$_POST["post_id"] : 0;
    $status = isset($_POST["status"]) ? $_POST["status"] : "";
    if ($postId > 0 && in_array($status, ["approved", "rejected"], true)) {
      $stmt = mysqli_prepare($con, "UPDATE community_posts SET status = ? WHERE id = ?");
      if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $status, $postId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $message = "Post updated successfully.";
      }
    }
  }
}

if (isset($_SESSION["portal_admin"]) && $_SESSION["portal_admin"] === true && isset($_POST["remove_post"])) {
  $csrf = isset($_POST["csrf"]) ? $_POST["csrf"] : "";
  if (!hash_equals($_SESSION["portal_csrf"], $csrf)) {
    $message = "Invalid request token.";
  } else {
    $postId = isset($_POST["post_id"]) ? (int)$_POST["post_id"] : 0;
    if ($postId > 0) {
      mysqli_query($con, "DELETE FROM community_posts WHERE id = $postId");
      $message = "Post removed.";
    }
  }
}

if (isset($_SESSION["portal_admin"]) && $_SESSION["portal_admin"] === true && isset($_POST["moderate_event"])) {
  $csrf = isset($_POST["csrf"]) ? $_POST["csrf"] : "";
  if (!hash_equals($_SESSION["portal_csrf"], $csrf)) {
    $message = "Invalid request token.";
  } else {
    $eventId = isset($_POST["event_id"]) ? (int)$_POST["event_id"] : 0;
    $status = isset($_POST["event_status"]) ? $_POST["event_status"] : "";
    if ($eventId > 0 && in_array($status, ["approved", "rejected"], true)) {
      mysqli_query($con, "UPDATE events SET publish_status = '$status' WHERE event_id = $eventId");
      $message = "Event updated.";
    }
  }
}

if (isset($_SESSION["portal_admin"]) && $_SESSION["portal_admin"] === true && isset($_POST["remove_event"])) {
  $csrf = isset($_POST["csrf"]) ? $_POST["csrf"] : "";
  if (!hash_equals($_SESSION["portal_csrf"], $csrf)) {
    $message = "Invalid request token.";
  } else {
    $eventId = isset($_POST["event_id"]) ? (int)$_POST["event_id"] : 0;
    if ($eventId > 0) {
      mysqli_query($con, "DELETE FROM events WHERE event_id = $eventId");
      $message = "Event removed.";
    }
  }
}

$pending = []; $approved = []; $pendingEvents = []; $approvedEvents = [];
if (isset($_SESSION["portal_admin"]) && $_SESSION["portal_admin"] === true) {
  ensure_admins_table($con);
  mysqli_query($con, "CREATE TABLE IF NOT EXISTS community_posts (id INT AUTO_INCREMENT PRIMARY KEY, author_name VARCHAR(120), author_meta VARCHAR(180), content TEXT, likes INT DEFAULT 0, status VARCHAR(20) DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
  ensure_column($con, "events", "publish_status", "VARCHAR(20) DEFAULT 'approved'");
  ensure_column($con, "events", "event_datetime", "DATETIME NULL");
  ensure_column($con, "events", "location", "VARCHAR(255)");
  ensure_column($con, "events", "category", "VARCHAR(30)");
  ensure_column($con, "events", "department", "VARCHAR(50)");

  $p = mysqli_query($con, "SELECT * FROM community_posts WHERE status='pending' ORDER BY created_at DESC");
  while ($row = mysqli_fetch_assoc($p)) { $pending[] = $row; }
  $a = mysqli_query($con, "SELECT * FROM community_posts WHERE status != 'pending' ORDER BY created_at DESC LIMIT 200");
  while ($row = mysqli_fetch_assoc($a)) { $approved[] = $row; }
  $ep = mysqli_query($con, "SELECT * FROM events WHERE publish_status='pending' ORDER BY event_id DESC");
  while ($row = mysqli_fetch_assoc($ep)) { $pendingEvents[] = $row; }
  $ea = mysqli_query($con, "SELECT * FROM events WHERE publish_status='approved' ORDER BY event_id DESC LIMIT 100");
  while ($row = mysqli_fetch_assoc($ea)) { $approvedEvents[] = $row; }
  
  $userCount = 0;
  $uRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM users");
  if ($uRes) {
    $uRow = mysqli_fetch_assoc($uRes);
    $userCount = (int)$uRow["cnt"];
  }

  $pendingApprovalsCount = 0;
  $paRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM registrations WHERE status = 'awaiting_confirmation'");
  if ($paRes) {
    $paRow = mysqli_fetch_assoc($paRes);
    $pendingApprovalsCount = (int)$paRow["cnt"];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Portal — BVRIT Hyderabad</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
  <style>
    :root {
      --bg: #030408;
      --sidebar: #06070d;
      --surface: #0c0e18;
      --surface2: #121421;
      --border: rgba(255,255,255,0.04);
      --border-bright: rgba(255,255,255,0.08);
      --accent: #6366f1;
      --accent-glow: rgba(99,102,241,0.15);
      --accent2: #f43f5e;
      --gold: #fbbf24;
      --green: #10b981;
      --red: #ef4444;
      --text: #f8fafc;
      --muted: #64748b;
      --radius-sm: 8px;
      --radius: 16px;
      --radius-lg: 24px;
      --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      margin: 0;
      overflow-x: hidden;
      letter-spacing: -0.01em;
    }
    .admin-layout {
      display: flex;
      min-height: 100vh;
    }

    /* ─── GLASS EFFECTS ─── */
    .glass {
      background: rgba(12, 14, 24, 0.75);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid var(--border-bright);
    }

    /* ─── SIDEBAR ─── */
    .sidebar {
      width: 280px;
      background: var(--sidebar);
      border-right: 1px solid var(--border);
      height: 100vh;
      position: fixed;
      left: 0; top: 0;
      display: flex;
      flex-direction: column;
      z-index: 1000;
    }
    .sidebar-header {
      padding: 48px 32px 32px;
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .sidebar-logo {
      width: 40px; height: 40px;
      background: linear-gradient(135deg, var(--accent), #818cf8);
      border-radius: 12px;
      display: grid; place-items: center;
      font-family: 'Syne', sans-serif;
      font-weight: 800; font-size: 16px; color: #fff;
      box-shadow: 0 8px 20px rgba(99,102,241,0.25);
    }
    .sidebar-brand {
      font-family: 'Syne', sans-serif;
      font-weight: 800; font-size: 22px; letter-spacing: -0.8px;
      background: linear-gradient(to bottom, #fff, #94a3b8);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .sidebar-nav {
      flex: 1;
      padding: 0 16px;
    }
    .nav-label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      color: var(--muted);
      margin: 32px 20px 12px;
    }
    .nav-item {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 20px;
      border-radius: 14px;
      color: var(--muted);
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 6px;
      transition: var(--transition);
      cursor: pointer;
    }
    .nav-item:hover {
      background: rgba(255,255,255,0.03);
      color: #fff;
    }
    .nav-item.active {
      background: var(--accent-glow);
      color: var(--accent);
      box-shadow: inset 0 0 0 1px rgba(99,102,241,0.1);
    }
    .nav-item svg { width: 20px; height: 20px; opacity: 0.6; transition: var(--transition); }
    .nav-item.active svg { opacity: 1; stroke-width: 2.5px; }

    .sidebar-footer {
      padding: 24px;
      margin: 16px;
      border-radius: 20px;
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
    }
    .user-profile {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 20px;
    }
    .user-avatar {
      width: 44px; height: 44px;
      background: var(--surface2);
      border-radius: 14px;
      display: grid; place-items: center;
      font-size: 16px; color: var(--accent);
      border: 1px solid var(--border-bright);
      font-weight: 800;
      font-family: 'Syne', sans-serif;
    }
    .user-info .name { font-size: 14px; font-weight: 700; display: block; color: var(--text); }
    .user-info .role { font-size: 11px; color: var(--muted); font-weight: 500; }

    /* ─── MAIN CONTENT ─── */
    .main {
      flex: 1;
      margin-left: 280px;
      padding: 48px 60px;
      position: relative;
      min-width: 0;
    }

    /* ─── HEADER ─── */
    .header-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-bottom: 48px;
    }
    .page-title { font-family: 'Syne', sans-serif; font-size: 36px; font-weight: 800; letter-spacing: -1.2px; line-height: 1; }
    .page-subtitle { color: var(--muted); font-size: 15px; margin-top: 10px; font-weight: 500; }

    /* ─── STATS ─── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 20px;
      margin-bottom: 40px;
    }
    .stat-card {
      background: var(--surface);
      border: 1px solid var(--border-bright);
      border-radius: 24px;
      padding: 24px;
      transition: var(--transition);
      display: flex;
      flex-direction: column;
      gap: 16px;
      position: relative;
      overflow: hidden;
    }
    .stat-card::after {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(135deg, transparent, rgba(255,255,255,0.03));
      pointer-events: none;
    }
    .stat-card:hover { 
      transform: translateY(-4px); 
      border-color: var(--border-bright);
      background: var(--surface2);
      box-shadow: 0 20px 40px rgba(0,0,0,0.4);
    }
    .stat-icon {
      width: 48px; height: 48px;
      border-radius: 16px;
      display: grid; place-items: center;
      font-size: 20px;
      background: rgba(255,255,255,0.03);
      border: 1px solid var(--border);
    }
    
    .stat-val { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; letter-spacing: -1.5px; line-height: 1; }
    .stat-label { font-size: 11px; color: var(--muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; }

    /* ─── CHARTS ─── */
    .dashboard-row {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 24px;
      margin-bottom: 40px;
    }
    .chart-container {
      background: var(--surface);
      border: 1px solid var(--border-bright);
      border-radius: 24px;
      padding: 32px;
      height: 340px;
    }

    /* ─── CONTENT BLOCKS ─── */
    .content-section {
      background: var(--surface);
      border: 1px solid var(--border-bright);
      border-radius: 24px;
      padding: 32px;
      animation: slideIn 0.4s cubic-bezier(0, 0, 0.2, 1);
    }
    .tab-pane { display: none; }
    .tab-pane.active { display: block; }
    @keyframes slideIn { from{opacity:0; transform:translateY(20px)} to{opacity:1; transform:translateY(0)} }
    
    .section-header-row {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 32px;
    }
    .section-title {
      font-family: 'Syne', sans-serif;
      font-size: 24px; font-weight: 800; letter-spacing: -0.8px;
    }

    .card-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 20px; }
    .data-card {
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 24px;
      transition: var(--transition);
      display: flex;
      flex-direction: column;
      gap: 20px;
      position: relative;
      overflow: hidden;
      background-size: cover;
      background-position: center;
    }
    .data-card.has-bg::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to bottom, rgba(12, 14, 24, 0.4), rgba(12, 14, 24, 0.95));
      z-index: 1;
    }
    .data-card.has-bg > * {
      position: relative;
      z-index: 2;
    }
    .data-card:hover { 
      border-color: var(--accent); 
      transform: scale(1.02);
      box-shadow: 0 12px 30px rgba(0,0,0,0.3);
    }
    
    .card-header { display: flex; align-items: flex-start; justify-content: space-between; }
    .card-info { display: flex; align-items: center; gap: 14px; }
    .card-avatar {
      width: 48px; height: 48px; border-radius: 14px;
      background: var(--bg); display: grid; place-items: center;
      font-weight: 800; color: var(--accent); font-size: 18px;
      border: 1px solid var(--border-bright);
    }
    .card-title { font-weight: 700; font-size: 16px; color: var(--text); margin-bottom: 4px; }
    .card-subtitle { font-size: 12px; color: var(--muted); font-weight: 500; }
    
    .card-body {
      font-size: 14px; color: #94a3b8; line-height: 1.6;
      background: rgba(0,0,0,0.2); padding: 16px; border-radius: 14px;
      border: 1px solid var(--border);
    }
    .card-footer { display: flex; align-items: center; justify-content: space-between; }
    
    .badge {
      font-size: 10px; font-weight: 800; padding: 6px 14px; border-radius: 10px;
      text-transform: uppercase; letter-spacing: 0.08em;
    }
    .badge-pending { background: rgba(251,191,36,0.1); color: var(--gold); }
    .badge-approved { background: rgba(16,185,129,0.1); color: var(--green); }
    .badge-rejected { background: rgba(239,68,68,0.1); color: var(--red); }

    /* ─── BUTTONS ─── */
    .btn {
      padding: 12px 24px; border-radius: 14px; font-size: 14px; font-weight: 700;
      cursor: pointer; transition: var(--transition); border: 1px solid transparent;
      display: inline-flex; align-items: center; gap: 10px;
      text-decoration: none; font-family: 'DM Sans', sans-serif;
    }
    .btn-primary { 
      background: var(--accent); color: #fff; 
      box-shadow: 0 8px 16px rgba(99,102,241,0.2); 
    }
    .btn-primary:hover { 
      background: #4f46e5; 
      box-shadow: 0 12px 24px rgba(99,102,241,0.3); 
      transform: translateY(-2px);
    }
    .btn-ghost { background: var(--surface2); color: var(--text); border: 1px solid var(--border-bright); }
    .btn-ghost:hover { background: var(--surface); border-color: var(--muted); }
    .btn-icon {
      width: 40px; height: 40px; padding: 0; justify-content: center;
      border-radius: 12px;
    }

    /* ─── TABLE ─── */
    .table-container {
      background: var(--surface2); border-radius: 20px; overflow: hidden;
      border: 1px solid var(--border);
    }
    .reg-table { width: 100%; border-collapse: collapse; }
    .reg-table th { 
      text-align: left; color: var(--muted); font-weight: 700; 
      padding: 20px 24px; background: rgba(0,0,0,0.2);
      font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em;
    }
    .reg-table td { padding: 20px 24px; border-bottom: 1px solid var(--border); font-size: 14px; }
    .reg-table tr:last-child td { border-bottom: none; }
    .reg-table tr:hover td { background: rgba(255,255,255,0.01); }

    /* ─── FORM GROUPS ─── */
    .form-group { margin-bottom: 24px; }
    .form-group label {
      display: block; font-size: 11px; font-weight: 800; color: var(--muted);
      text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 10px;
    }

    /* ─── INPUTS ─── */
    .form-control {
      background: var(--surface2); border: 1px solid var(--border-bright);
      color: #fff; padding: 12px 16px; border-radius: 12px;
      font-family: inherit; font-size: 14px; transition: var(--transition);
      outline: none;
    }
    .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-glow); }

    /* ─── LIVE DOT ─── */
    .live-indicator {
      display: inline-flex; align-items: center; gap: 8px;
      font-size: 11px; font-weight: 700; color: var(--green);
      background: rgba(16,185,129,0.1); padding: 4px 10px; border-radius: 20px;
    }
    .dot { width: 6px; height: 6px; background: var(--green); border-radius: 50%; animation: pulse 2s infinite; }
    @keyframes pulse { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(1.5); } 100% { opacity: 1; transform: scale(1); } }

    /* ─── MODALS ─── */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(3, 4, 8, 0.9);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      z-index: 10000;
      place-items: center;
      padding: 20px;
      overflow-y: auto;
    }
    .modal.active { display: grid; }
    .modal-content {
      background: var(--surface);
      border: 1px solid var(--border-bright);
      border-radius: 24px;
      width: 100%;
      max-width: 500px;
      position: relative;
      box-shadow: 0 40px 100px rgba(0,0,0,0.8);
      animation: modalScale 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      margin: auto;
    }
    #adminEventModal .modal-content { max-width: 700px; }
    @keyframes modalScale { from{opacity:0; transform:scale(0.9)} to{opacity:1; transform:scale(1)} }
    
    .modal-header {
      padding: 24px 32px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .modal-title { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; }
    .close-modal { 
      width: 32px; height: 32px; border-radius: 8px;
      display: grid; place-items: center; background: var(--surface2);
      font-size: 18px; color: var(--muted); cursor: pointer; transition: var(--transition);
      border: 1px solid var(--border);
    }
    .close-modal:hover { background: var(--red); color: #fff; transform: rotate(90deg); }
    .modal-body { padding: 32px; }

    /* ─── TOAST ─── */
    .toast {
      position: fixed; bottom: 32px; right: 32px; z-index: 10000;
      background: var(--surface); border: 1px solid var(--accent);
      padding: 16px 28px; border-radius: 16px; color: var(--text);
      box-shadow: 0 20px 50px rgba(0,0,0,0.6); display: flex; align-items: center; gap: 14px;
      animation: slideUpToast 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
      font-weight: 500;
    }
    @keyframes slideUpToast { from{transform:translateY(40px);opacity:0} to{transform:translateY(0);opacity:1} }
    .toast.fade-out {
      animation: fadeOutToast 0.5s cubic-bezier(0.2, 0, 0.2, 1) forwards;
    }
    @keyframes fadeOutToast { from{transform:translateY(0);opacity:1} to{transform:translateY(10px);opacity:0} }

    /* ─── LOGIN SCREEN ─── */
    .login-screen {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: var(--bg);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      padding: 20px;
    }
    .login-box {
      width: 100%;
      max-width: 420px;
      padding: 48px;
      text-align: center;
      border-radius: 32px;
      position: relative;
    }

    @media (max-width: 1400px) { .dashboard-row { grid-template-columns: 1fr; } }
    @media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 800px) { 
      .sidebar { width: 80px; }
      .sidebar-brand, .nav-text, .user-info, .nav-label, .sidebar-footer .btn span { display: none; }
      .main { margin-left: 80px; padding: 32px; }
      .nav-item { justify-content: center; padding: 14px; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>

<?php if ($message !== ""): ?>
  <div class="toast" id="systemToast">
    <span style="color:var(--accent);font-size:18px;">●</span>
    <?php echo esc($message); ?>
  </div>
<?php endif; ?>

<?php if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true): ?>
  <div class="login-screen">
    <div class="login-box glass">
      <div style="margin-bottom: 40px;">
        <div class="sidebar-logo" style="width: 60px; height: 60px; margin: 0 auto 24px; font-size: 24px;">BV</div>
        <h1 style="font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; letter-spacing: -1.2px;">Admin Access</h1>
        <p style="color: var(--muted); font-size: 15px; margin-top: 10px; font-weight: 500;">Enter credentials to manage BVRITH portal</p>
      </div>
      <form method="post">
        <div class="form-group" style="text-align: left;">
          <label>Username</label>
          <input type="text" name="username" class="form-control" style="width:100%" placeholder="admin" required autofocus>
        </div>
        <div class="form-group" style="text-align: left; margin-bottom: 32px;">
          <label>Password</label>
          <input type="password" name="password" class="form-control" style="width:100%" placeholder="••••••••" required>
        </div>
        <button type="submit" name="login" value="1" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 16px; font-size: 15px;">Enter Dashboard</button>
      </form>
    </div>
  </div>
<?php else: ?>
<div class="admin-layout">
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">BV</div>
      <div class="sidebar-brand">Portal Admin</div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-label">Management</div>
      <div class="nav-item active" onclick="switchTab('posts', this)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
        <span class="nav-text">Community Posts</span>
      </div>
      <div class="nav-item" onclick="switchTab('events', this)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        <span class="nav-text">Events Manager</span>
      </div>
      <div class="nav-item" onclick="switchTab('users', this)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        <span class="nav-text">User Management</span>
      </div>
      <div class="nav-item" onclick="switchTab('approvals', this)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <span class="nav-text">Registration Approvals</span>
      </div>
      
      <div class="nav-label">System</div>
      <a href="../index.php" class="nav-item" target="_blank">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
        <span class="nav-text">View Website</span>
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-profile">
        <div class="user-avatar">A</div>
        <div class="user-info">
          <span class="name">Administrator</span>
          <span class="role">Super Admin</span>
        </div>
      </div>
      <form method="post">
        <button type="submit" name="logout" value="1" class="btn btn-danger" style="width: 100%; justify-content: center;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
          <span>Logout</span>
        </button>
      </form>
    </div>
  </aside>

  <main class="main">
    <div class="header-row">
      <div>
        <h1 class="page-title">Dashboard Overview</h1>
        <p class="page-subtitle">Welcome back, Admin. Here's a live look at campus activity.</p>
      </div>
      <div class="live-indicator">
        <span class="dot"></span>
        WEBSOCKET CONNECTED
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card" style="border-bottom: 3px solid var(--accent);">
        <div class="stat-icon" style="color: var(--accent);">👥</div>
        <div class="stat-content">
          <div class="stat-val"><?php echo $userCount; ?></div>
          <div class="stat-label">Total Users</div>
        </div>
      </div>
      <div class="stat-card" style="border-bottom: 3px solid var(--accent2);">
        <div class="stat-icon" style="color: var(--accent2);">💬</div>
        <div class="stat-content">
          <div class="stat-val"><?php echo count($pending); ?></div>
          <div class="stat-label">Pending Posts</div>
        </div>
      </div>
      <div class="stat-card" style="border-bottom: 3px solid var(--green);">
        <div class="stat-icon" style="color: var(--green);">✅</div>
        <div class="stat-content">
          <div class="stat-val"><?php echo count($approved); ?></div>
          <div class="stat-label">Live Posts</div>
        </div>
      </div>
      <div class="stat-card" style="border-bottom: 3px solid var(--gold);">
        <div class="stat-icon" style="color: var(--gold);">📅</div>
        <div class="stat-content">
          <div class="stat-val"><?php echo count($pendingEvents); ?></div>
          <div class="stat-label">Event Proposals</div>
        </div>
      </div>
      <div class="stat-card" style="border-bottom: 3px solid #a855f7; cursor: pointer;" onclick="switchTab('approvals', document.querySelector('[onclick*=\'approvals\']'))">
        <div class="stat-icon" style="color: #a855f7;">📑</div>
        <div class="stat-content">
          <div class="stat-val"><?php echo $pendingApprovalsCount; ?></div>
          <div class="stat-label">Pending Registrations</div>
        </div>
      </div>
    </div>



    <div id="tab-posts" class="tab-pane active">
      <div class="content-section">
        <div class="section-header-row">
          <h2 class="section-title">Community Moderation</h2>
          <div style="display: flex; gap: 12px;">
            <input type="text" id="postSearch" class="form-control" placeholder="Search posts..." style="width:240px">
            <select id="postStatusFilter" class="form-control">
              <option value="all">All Status</option>
              <option value="pending" selected>Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>
        <div id="adminPostsList" class="card-list"></div>
      </div>
    </div>

    <div id="tab-events" class="tab-pane">
      <div class="content-section">
        <div class="section-header-row">
          <h2 class="section-title">Events Manager</h2>
          <button class="btn btn-primary" onclick="openEventModal()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Create New Event
          </button>
        </div>
        
        <div id="adminEventsList">
          <div style="margin-bottom: 32px;">
            <h3 style="font-size:12px; color:var(--gold); text-transform:uppercase; letter-spacing:0.1em; margin-bottom:16px; font-weight:800;">Pending Proposals</h3>
            <div id="pendingEventsList" class="card-list"></div>
          </div>

          <h3 style="font-size:12px; color:var(--green); text-transform:uppercase; letter-spacing:0.1em; margin-bottom:16px; font-weight:800;">Live Events</h3>
          <div id="approvedEventsList" class="card-list"></div>
        </div>
      </div>
    </div>

    <div id="tab-users" class="tab-pane">
      <div class="content-section">
        <div class="section-header-row">
          <h2 class="section-title">User Management</h2>
          <input type="text" id="userSearch" class="form-control" placeholder="Search students..." style="width:280px">
        </div>
        <div class="table-container">
          <table class="reg-table">
            <thead>
              <tr>
                <th>Student Details</th>
                <th>Performance</th>
                <th>Activity</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="adminUsersList"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div id="tab-approvals" class="tab-pane">
      <div class="content-section">
        <div class="section-header-row">
          <h2 class="section-title">Registration & Payment Approvals</h2>
          <div style="display:flex; gap:12px;">
            <select id="approvalStatusFilter" class="form-control" onchange="loadApprovals()">
              <option value="awaiting_confirmation" selected>Awaiting Confirmation</option>
              <option value="pending_payment">Pending Payment</option>
              <option value="confirmed">Confirmed</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>
        <div class="table-container">
          <table class="reg-table">
            <thead>
              <tr>
                <th>Student</th>
                <th>Event</th>
                <th>Payment Proof</th>
                <th>Applied On</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="adminApprovalsList"></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
<?php endif; ?>

<!-- MODALS -->
<div class="modal" id="registrantsModal">
  <div class="modal-content glass">
    <div class="modal-header">
      <div id="regModalTitle" class="modal-title">Event Registrants</div>
      <button class="close-modal" onclick="closeModal('registrantsModal')">&times;</button>
    </div>
    <div class="modal-body" id="regModalBody"></div>
  </div>
</div>

<div class="modal" id="announcementModal">
  <div class="modal-content glass" style="max-width: 500px;">
    <div class="modal-header">
      <div class="modal-title">Broadcast Announcement</div>
      <button class="close-modal" onclick="closeModal('announcementModal')">&times;</button>
    </div>
    <div class="modal-body">
      <form id="announcementForm">
        <input type="hidden" id="announcementEventId">
        <div class="form-group"><label>Message</label><textarea id="announcementMessage" class="form-control" placeholder="Enter update here..." required></textarea></div>
        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">📢 Send Broadcast</button>
      </form>
    </div>
  </div>
</div>

<div class="modal" id="adminEventModal">
  <div class="modal-content glass">
    <div class="modal-header">
      <div id="eventModalTitle" class="modal-title">Event Configuration</div>
      <button class="close-modal" onclick="closeModal('adminEventModal')">&times;</button>
    </div>
    <div class="modal-body">
      <form id="adminEventForm">
        <input type="hidden" id="adminEventId" value="0">
        <div class="form-group">
          <label>Event Title</label>
          <input type="text" id="adminEventTitle" class="form-control" required placeholder="Grand Hackathon 2026">
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
          <div class="form-group" style="margin-bottom:0;"><label>Date & Time</label><input type="datetime-local" id="adminEventDateTime" class="form-control" required></div>
          <div class="form-group" style="margin-bottom:0;"><label>Location</label><input type="text" id="adminEventLocation" class="form-control" required placeholder="Seminar Hall"></div>
        </div>
        
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-bottom:20px;">
          <div class="form-group" style="margin-bottom:0;"><label>Category</label>
            <select id="adminEventCategory" class="form-control">
              <option value="technical">Technical</option>
              <option value="cultural">Cultural</option>
              <option value="sports">Sports</option>
              <option value="workshop">Workshop</option>
              <option value="seminar">Seminar</option>
              <option value="gaming">Gaming</option>
              <option value="social">Social</option>
              <option value="hackathon">Hackathon</option>
              <option value="competition">Competition</option>
              <option value="concert">Concert</option>
              <option value="festival">Festival</option>
              <option value="webinar">Webinar</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;"><label>Department</label>
            <select id="adminEventDept" class="form-control">
              <option value="cse">CSE</option>
              <option value="csm">CSM</option>
              <option value="csd">CSD</option>
              <option value="csg">CSG</option>
              <option value="aids">AIDS</option>
              <option value="aiml">AIML</option>
              <option value="ece">ECE</option>
              <option value="it">IT</option>
              <option value="eee">EEE</option>
              <option value="mech">MECH</option>
              <option value="civil">CIVIL</option>
              <option value="bsh">BSH</option>
              <option value="mba">MBA</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;"><label>Entry Fee (₹)</label>
            <input type="number" id="adminEventFee" class="form-control" value="0" onchange="toggleAdminPayment(this.value)">
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
          <div class="form-group" style="margin-bottom:0;"><label>Max Team Size</label>
            <input type="number" id="adminEventTeam" class="form-control" value="1">
          </div>
          <div class="form-group" style="margin-bottom:0;"><label>Event Image (filename)</label>
            <input type="text" id="adminEventImg" class="form-control" placeholder="cs01.jpg">
          </div>
        </div>

        <div id="adminPaymentFields" style="display:none; background:rgba(255,255,255,0.03); padding:20px; border-radius:16px; border:1px solid var(--border); margin-bottom:24px;">
          <div style="font-size:10px; font-weight:800; color:var(--gold); margin-bottom:12px; text-transform:uppercase; letter-spacing:0.1em;">Payment Configuration</div>
          <div style="display:grid; grid-template-columns:1fr 2fr; gap:16px;">
            <div class="form-group" style="margin-bottom:0;"><label>Method</label>
              <select id="adminPaymentType" class="form-control">
                <option value="link">Link</option>
                <option value="qr">QR Code</option>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:0;"><label>Payment URL / QR File</label>
              <div id="adminPaymentDestWrap">
                <input type="text" id="adminPaymentDest" class="form-control" placeholder="https://...">
              </div>
              <div id="adminPaymentQRWrap" style="display:none;">
                <input type="file" id="adminPaymentQRFile" class="form-control" accept="image/*">
              </div>
            </div>
          </div>
        </div>

        <div class="form-group"><label>Details</label><textarea id="adminEventDetails" class="form-control" style="height:100px;" placeholder="Event description and rules..."></textarea></div>
        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:10px; padding:16px;">Save Event & Publish</button>
      </form>
    </div>
  </div>
</div>

<script>
  let ALL_POSTS = <?php echo json_encode(array_merge($pending, $approved)); ?>;
  let ALL_EVENTS = <?php echo json_encode(array_merge($pendingEvents, $approvedEvents)); ?>;
  const ADMIN_CSRF = "<?php echo esc($_SESSION["portal_csrf"]); ?>";

  const IS_ADMIN = <?php echo (isset($_SESSION["portal_admin"]) && $_SESSION["portal_admin"] === true) ? 'true' : 'false'; ?>;

  document.addEventListener("DOMContentLoaded", () => {
    if (IS_ADMIN) {
      renderAdminPosts();
      renderAdminEvents();
      initSocket();
      // Start Real-time Polling
      setInterval(loadDashboardData, 10000);
    }
    
    const phpToast = document.getElementById('systemToast');
    if (phpToast) {
      setTimeout(() => {
        phpToast.classList.add('fade-out');
        setTimeout(() => phpToast.remove(), 500);
      }, 3000);
    }
  });

  async function loadDashboardData() {
    try {
      const res = await fetch('portal_api.php?action=admin_dashboard_data');
      const d = await res.json();
      if (d.ok) {
        ALL_POSTS = d.data.posts;
        ALL_EVENTS = d.data.events;
        
        // Update stats
        updateStatCards(d.data.stats);
        
        // Re-render current active tab
        const activeTab = document.querySelector('.tab-pane.active').id;
        if (activeTab === 'tab-posts') renderAdminPosts();
        if (activeTab === 'tab-events') renderAdminEvents();
        if (activeTab === 'tab-approvals') loadApprovals(true); // Silent refresh
      }
    } catch (e) { console.warn("Polling failed", e); }
  }

  function updateStatCards(stats) {
    const cards = document.querySelectorAll('.stat-card');
    if (cards.length >= 5) {
      cards[0].querySelector('.stat-val').textContent = stats.userCount;
      cards[1].querySelector('.stat-val').textContent = stats.pendingPosts;
      cards[2].querySelector('.stat-val').textContent = stats.livePosts;
      cards[3].querySelector('.stat-val').textContent = stats.eventProposals;
      cards[4].querySelector('.stat-val').textContent = stats.pendingRegistrations;
    }
  }

  function initSocket() {
    try {
      const socket = io("http://localhost:3000", {
        reconnectionAttempts: 2,
        timeout: 3000
      });
      socket.on("connect", () => {
        console.log("Admin connected to WebSocket");
        document.querySelector('.live-indicator').style.display = 'inline-flex';
      });

      socket.on("connect_error", () => {
        document.querySelector('.live-indicator').style.display = 'none';
      });
      
      socket.on("global_notification", (data) => {
        showToast(`🔔 ${data.title}: ${data.message}`);
        loadDashboardData(); // Refresh data on new notification
      });
    } catch (e) {
      console.warn("WebSocket failed");
      document.querySelector('.live-indicator').style.display = 'none';
    }
  }

  function closeAllModals() {
    document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
  }

  function closeModal(id) { document.getElementById(id).classList.remove('active'); }

  function switchTab(tab, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    btn.classList.add('active');
    if (tab === 'users') loadUsers();
    if (tab === 'approvals') loadApprovals();
    if (tab === 'events') renderAdminEvents();
    if (tab === 'posts') renderAdminPosts();
  }

  function renderAdminEvents() {
    const pendingList = document.getElementById('pendingEventsList');
    const approvedList = document.getElementById('approvedEventsList');
    if (!pendingList || !approvedList) return;

    const pending = ALL_EVENTS.filter(e => e.publish_status === 'pending');
    const approved = ALL_EVENTS.filter(e => e.publish_status === 'approved');

    const renderCard = (event, isPending) => {
      const bgImg = event.img_link ? `../images/${event.img_link}` : "";
      const cardClass = bgImg ? "data-card has-bg" : "data-card";
      const cardStyle = bgImg ? `style="background-image: url('${bgImg}')"` : "";
      const statusBadge = isPending ? '<span class="badge badge-pending">Proposal</span>' : '<span class="badge badge-approved">Active</span>';
      const icon = isPending ? '📅' : '✓';
      const iconColor = isPending ? 'var(--gold)' : 'var(--green)';

      return `
        <div class="${cardClass}" ${cardStyle}>
          <div class="card-header">
            <div class="card-info">
              <div class="card-avatar" style="color: ${iconColor};">${icon}</div>
              <div>
                <div class="card-title">${event.event_title}</div>
                <div class="card-subtitle">${isPending ? `📍 ${event.location || 'TBD'}` : `📅 ${new Date(event.event_datetime).toLocaleDateString('en-US', {month:'short', day:'numeric'})}`}</div>
              </div>
            </div>
            ${statusBadge}
          </div>
          <div class="card-footer">
            <div style="${isPending ? 'font-size: 16px; font-weight: 800; color: var(--accent);' : 'font-size: 12px; color: var(--muted); font-weight: 500;'}">${isPending ? `₹${parseInt(event.event_price)}` : `📍 ${event.location}`}</div>
            <div class="card-actions">
              ${isPending ? `
                <div style="display:inline">
                  <button onclick="moderateEvent(${event.event_id}, 'approved')" class="btn btn-primary" style="padding: 8px 16px; font-size: 12px;">Approve</button>
                </div>
              ` : `
                <button class="btn btn-ghost btn-icon" title="View Registrants" onclick="viewRegistrants(${event.event_id}, '${event.event_title.replace(/'/g, "\\'")}')">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </button>
                <button class="btn btn-ghost btn-icon" title="Broadcast" onclick="openAnnouncementModal(${event.event_id})">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                </button>
              `}
              <button class="btn btn-ghost btn-icon" title="Edit" onclick="openEventModal(${event.event_id}, '${event.event_title.replace(/'/g, "\\'")}', '${event.event_datetime}', '${event.location}', '${(event.details || '').replace(/'/g, "\\'")}', ${event.event_price}, ${event.participents}, '${event.category}', '${event.department}', '${event.img_link}', '${event.payment_type}', '${event.payment_type === 'qr' ? (event.payment_qr || '') : (event.payment_link || '')}')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
              </button>
              ${!isPending ? `
                <button onclick="removeEvent(${event.event_id})" class="btn btn-danger btn-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg></button>
              ` : ''}
            </div>
          </div>
        </div>
      `;
    };

    pendingList.innerHTML = pending.length ? pending.map(e => renderCard(e, true)).join('') : '<div style="color:var(--muted); font-size:14px; padding:20px; background:rgba(255,255,255,0.02); border-radius:14px; border:1px dashed var(--border);">No new proposals at the moment.</div>';
    approvedList.innerHTML = approved.map(e => renderCard(e, false)).join('');
  }

  async function loadApprovals(silent = false) {
    const list = document.getElementById('adminApprovalsList');
    if (!list) return;
    const status = document.getElementById('approvalStatusFilter').value;
    if (!silent) list.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px; color:var(--muted);">Loading...</td></tr>`;
    try {
      const res = await fetch(`portal_api.php?action=admin_approvals&status=${status}`);
      const d = await res.json();
      if (!d.ok) return;
      list.innerHTML = d.approvals.map(r => `
        <tr>
          <td><div style="font-weight:700;">${r.full_name}</div><div style="font-size:12px; color:var(--muted);">${r.email}</div></td>
          <td style="font-weight:600;">${r.event_title || 'Unknown Event'}</td>
          <td style="font-size:12px; color:var(--muted);">${new Date(r.created_at).toLocaleDateString()}</td>
          <td>
            ${r.payment_proof ? `<a href="../${r.payment_proof}" target="_blank" class="btn btn-primary" style="font-size:10px; padding:6px 12px; background:var(--accent); display: inline-flex; align-items: center; gap: 4px;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              View Proof
            </a>` : '<span style="color:var(--muted); font-size:11px;">No Proof</span>'}
          </td>
          <td>
            ${r.status === 'pending_payment' || r.status === 'awaiting_confirmation' ? `
              <div style="display:flex; gap:8px;">
                <button class="btn btn-primary" onclick="moderateReg(${r.id}, 'confirmed', 0, '')" style="padding:6px 12px; font-size:11px; background:var(--green);">Approve</button>
                <button class="btn btn-ghost" onclick="moderateReg(${r.id}, 'rejected', 0, '')" style="padding:6px 12px; font-size:11px; color:var(--red);">Reject</button>
              </div>
            ` : `<span class="badge badge-${r.status}">${r.status}</span>`}
          </td>
        </tr>
      `).join('');
    } catch (e) { console.error(e); }
  }

  function renderAdminPosts() {
    const list = document.getElementById('adminPostsList');
    const search = document.getElementById('postSearch').value.toLowerCase();
    const status = document.getElementById('postStatusFilter').value;
    
    let filtered = ALL_POSTS.filter(p => {
      const matchesSearch = p.author_name.toLowerCase().includes(search) || 
                            p.author_meta.toLowerCase().includes(search) || 
                            p.content.toLowerCase().includes(search);
      const matchesStatus = status === 'all' || p.status === status;
      return matchesSearch && matchesStatus;
    });

    if (!filtered.length) {
      list.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 60px; color: var(--muted); border: 1px dashed var(--border); border-radius: 20px;">No posts found.</div>`;
      return;
    }

    list.innerHTML = filtered.map(post => {
      const bgImg = post.image ? `../images/${post.image}` : "";
      const cardClass = bgImg ? "data-card has-bg" : "data-card";
      const cardStyle = bgImg ? `style="background-image: url('${bgImg}')"` : "";

      return `
        <div class="${cardClass}" ${cardStyle}>
          <div class="card-header">
            <div class="card-info">
              <div class="card-avatar">${post.author_name.charAt(0).toUpperCase()}</div>
              <div>
                <div class="card-title">${post.author_name}</div>
                <div class="card-subtitle">${post.author_meta}</div>
              </div>
            </div>
            <span class="badge badge-${post.status}">${post.status}</span>
          </div>
          <div class="card-body">${post.content.replace(/\n/g, '<br>')}</div>
          <div class="card-footer">
            <div style="font-size: 12px; color: var(--muted); display: flex; align-items: center; gap: 6px;">♥ ${post.likes} Likes</div>
            <div class="card-actions">
              ${post.status === 'pending' ? `
                <div style="display:inline">
                  <button onclick="moderatePost(${post.id}, 'approved')" class="btn btn-primary" style="padding: 8px 16px; font-size: 11px; background: var(--green);">Approve</button>
                  <button onclick="moderatePost(${post.id}, 'rejected')" class="btn btn-ghost" style="padding: 8px 16px; font-size: 11px; color: var(--red);">Reject</button>
                </div>
              ` : `
                <button onclick="removePost(${post.id})" class="btn btn-danger btn-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg></button>
              `}
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  document.getElementById('postSearch').addEventListener('input', renderAdminPosts);
  document.getElementById('postStatusFilter').addEventListener('change', renderAdminPosts);

  document.getElementById('userSearch').addEventListener('input', e => {
    if (window.userSearchTimeout) clearTimeout(window.userSearchTimeout);
    window.userSearchTimeout = setTimeout(() => {
      loadUsers(e.target.value);
    }, 400);
  });

  document.getElementById('announcementForm').addEventListener('submit', async e => {
    e.preventDefault();
    const id = document.getElementById('announcementEventId').value;
    const msg = document.getElementById('announcementMessage').value;
    const res = await fetch('portal_api.php?action=send_announcement', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event_id: id, message: msg, csrf: ADMIN_CSRF })
    });
    const d = await res.json();
    if (d.ok) {
      showToast("Announcement broadcasted!");
      closeAllModals();
      e.target.reset();
    }
  });

  async function loadUsers(query = "") {
    const list = document.getElementById('adminUsersList');
    if (!list) return;
    list.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:40px; color:var(--muted);">Loading students...</td></tr>`;
    try {
      const res = await fetch(`portal_api.php?action=admin_users&q=${encodeURIComponent(query)}`);
      const d = await res.json();
      if (!d.ok) {
        list.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:40px; color:var(--red);">Failed to load users: ${d.message || 'Unknown error'}</td></tr>`;
        return;
      }
      if (!d.users.length) {
        list.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:40px; color:var(--muted);">No students found.</td></tr>`;
        return;
      }
      list.innerHTML = d.users.map(u => `
        <tr>
          <td><div style="font-weight:700;">${u.fullname}</div><div style="font-size:12px; color:var(--muted);">${u.email}</div></td>
          <td><div style="display:flex; gap:16px;"><div><div style="font-size:10px; color:var(--muted); font-weight:800;">ATTENDANCE</div><div style="font-weight:800; color:var(--accent);">${parseFloat(u.attendance_pct || 0).toFixed(1)}%</div></div><div><div style="font-size:10px; color:var(--muted); font-weight:800;">GPA</div><div style="font-weight:800; color:var(--gold);">${parseFloat(u.gpa || 0).toFixed(2)}</div></div></div></td>
          <td><div style="display:flex; flex-wrap:wrap; gap:6px; max-width:240px;">${(u.registered_events || []).map(e => `<span style="font-size:10px; padding:4px 10px; background:rgba(255,255,255,0.03); border-radius:20px; border:1px solid var(--border);">${e}</span>`).join('')}</div></td>
          <td><button class="btn btn-danger btn-icon" onclick="deleteUser(${u.id})"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg></button></td>
        </tr>
      `).join('');
    } catch (e) { 
      console.error(e);
      list.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:40px; color:var(--red);">Connection error. Check console.</td></tr>`;
    }
  }

  async function deleteUser(id) {
    if (!confirm("Are you sure?")) return;
    const res = await fetch('portal_api.php?action=admin_delete_user', { 
      method: 'POST', 
      headers: { 'Content-Type': 'application/json' }, 
      body: JSON.stringify({ user_id: id, csrf: ADMIN_CSRF }) 
    });
    const d = await res.json();
    if (d.ok) {
      showToast("User deleted successfully");
      loadUsers();
    } else {
      showToast("Error: " + d.message);
    }
  }

  async function moderatePost(postId, status) {
    const res = await fetch('portal_api.php?action=admin_moderate_post', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ post_id: postId, status: status, csrf: ADMIN_CSRF })
    });
    const d = await res.json();
    if (d.ok) {
      showToast(`Post ${status}!`);
      loadDashboardData();
    }
  }

  async function removePost(postId) {
    if (!confirm("Delete permanently?")) return;
    const res = await fetch('portal_api.php?action=admin_delete_post', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ post_id: postId, csrf: ADMIN_CSRF })
    });
    const d = await res.json();
    if (d.ok) {
      showToast("Post removed.");
      loadDashboardData();
    }
  }

  async function moderateEvent(eventId, status) {
    const res = await fetch('portal_api.php?action=admin_moderate_event', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event_id: eventId, status: status, csrf: ADMIN_CSRF })
    });
    const d = await res.json();
    if (d.ok) {
      showToast(`Event ${status}!`);
      loadDashboardData();
    }
  }

  async function removeEvent(eventId) {
    if (!confirm("Delete event?")) return;
    const res = await fetch('portal_api.php?action=admin_delete_event', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event_id: eventId, csrf: ADMIN_CSRF })
    });
    const d = await res.json();
    if (d.ok) {
      showToast("Event removed.");
      loadDashboardData();
    }
  }

  function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = `<span style="color:var(--accent);font-size:18px;">●</span> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => { t.classList.add('fade-out'); setTimeout(() => t.remove(), 500); }, 4000);
  }

  function viewRegistrants(id, title) {
    closeAllModals();
    const b = document.getElementById('regModalBody');
    document.getElementById('regModalTitle').textContent = title;
    b.innerHTML = '<div style="text-align:center; color:var(--muted); padding:40px;">Fetching...</div>';
    document.getElementById('registrantsModal').classList.add('active');
    fetch(`portal_api.php?action=event_registrants&id=${id}`).then(r => r.json()).then(d => {
      if(d.ok && d.registrants.length) {
        let h = '<div class="table-container"><table class="reg-table"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        d.registrants.forEach(r => {
          let action = (r.status === 'pending_payment' || r.status === 'awaiting_confirmation') ? `<button class="btn btn-primary" onclick="moderateReg(${r.id}, 'confirmed', ${id}, '${title}')" style="padding:6px 12px; font-size:10px; background:var(--green);">Approve</button>` : '';
          let statusLabel = r.payment_proof ? `<a href="../${r.payment_proof}" target="_blank" class="btn btn-ghost" style="padding:4px 8px; font-size:10px; color:var(--accent); font-weight:700;">View Proof</a>` : `<span class="badge badge-${r.status}" style="font-size:10px;">${r.status}</span>`;
          h += `<tr><td>${r.full_name}</td><td>${r.email}</td><td>${statusLabel}</td><td>${action}</td></tr>`;
        });
        b.innerHTML = h + '</tbody></table></div>';
      } else b.innerHTML = '<div style="text-align:center; color:var(--muted); padding:40px;">No records.</div>';
    });
  }

  async function moderateReg(regId, status, eventId, eventTitle) {
    const res = await fetch('portal_api.php?action=admin_moderate_registration', { 
      method: 'POST', 
      headers: { 'Content-Type': 'application/json' }, 
      body: JSON.stringify({ reg_id: regId, status: status, csrf: ADMIN_CSRF }) 
    });
    const d = await res.json();
    if (d.ok) {
      showToast(`Registration ${status}!`);
      if (eventId) viewRegistrants(eventId, eventTitle);
      else loadApprovals();
    }
  }

  function openAnnouncementModal(id) { 
    closeAllModals();
    document.getElementById('announcementEventId').value = id; 
    document.getElementById('announcementModal').classList.add('active'); 
  }

  function toggleAdminPayment(val) { document.getElementById('adminPaymentFields').style.display = parseInt(val) > 0 ? 'block' : 'none'; }

  function openEventModal(id=0, title="", date="", loc="", details="", fee=0, team=1, category="technical", dept="cse", img="cs01.jpg", pType="link", pDest="") {
    closeAllModals();
    document.getElementById('adminEventId').value = id;
    document.getElementById('adminEventTitle').value = title;
    document.getElementById('adminEventDateTime').value = date ? date.replace(" ", "T").substring(0, 16) : "";
    document.getElementById('adminEventLocation').value = loc;
    document.getElementById('adminEventDetails').value = details;
    document.getElementById('adminEventFee').value = fee;
    document.getElementById('adminEventTeam').value = team;
    document.getElementById('adminEventCategory').value = category;
    document.getElementById('adminEventDept').value = dept;
    document.getElementById('adminEventImg').value = img;
    document.getElementById('adminPaymentType').value = pType;
    document.getElementById('adminPaymentDest').value = pDest;
    toggleAdminPayment(fee);
    
    // Trigger display toggle for payment destination/QR
    const isQR = pType === 'qr';
    document.getElementById('adminPaymentDestWrap').style.display = isQR ? 'none' : 'block';
    document.getElementById('adminPaymentQRWrap').style.display = isQR ? 'block' : 'none';
    
    document.getElementById('adminEventModal').classList.add('active');
  }

  document.getElementById('adminPaymentType').addEventListener('change', function() {
    const isQR = this.value === 'qr';
    document.getElementById('adminPaymentDestWrap').style.display = isQR ? 'none' : 'block';
    document.getElementById('adminPaymentQRWrap').style.display = isQR ? 'block' : 'none';
  });

  document.getElementById('adminEventForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fee = parseInt(document.getElementById('adminEventFee').value);
    const payType = document.getElementById('adminPaymentType').value;
    
    const formData = new FormData();
    formData.append('id', document.getElementById('adminEventId').value);
    formData.append('title', document.getElementById('adminEventTitle').value);
    formData.append('datetime', document.getElementById('adminEventDateTime').value.replace("T", " "));
    formData.append('location', document.getElementById('adminEventLocation').value);
    formData.append('details', document.getElementById('adminEventDetails').value);
    formData.append('fee', fee);
    formData.append('team_size', document.getElementById('adminEventTeam').value);
    formData.append('category', document.getElementById('adminEventCategory').value);
    formData.append('department', document.getElementById('adminEventDept').value);
    formData.append('thumb', document.getElementById('adminEventImg').value);
    formData.append('payment_type', payType);
    formData.append('status', "approved");
    formData.append('csrf', ADMIN_CSRF);

    if (fee > 0) {
      if (payType === 'link') {
        formData.append('payment_link', document.getElementById('adminPaymentDest').value);
      } else {
        const qrFile = document.getElementById('adminPaymentQRFile').files[0];
        if (qrFile) {
          formData.append('payment_qr_file', qrFile);
        } else {
          // If no new file, maybe keep existing or use the text field as fallback
          formData.append('payment_qr', document.getElementById('adminPaymentDest').value);
        }
      }
    }

    const res = await fetch('portal_api.php?action=admin_save_event', { 
      method: 'POST', 
      body: formData 
    });
    const resData = await res.json();
    if (resData.ok) {
      showToast("Event saved successfully!");
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast("Error saving event: " + resData.message);
    }
  });

  window.onclick = function(e) { if (e.target.classList.contains('modal')) closeAllModals(); }
</script>
</body>
</html>
