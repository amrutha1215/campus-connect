<?php
session_start();
error_reporting(0); // Suppress errors/notices to prevent JSON corruption
header("Content-Type: application/json; charset=UTF-8");

include "../db/connect.php";

function json_response($data, $status = 200) {
  if (ob_get_length()) ob_clean(); // Clear any previous output (warnings/notices)
  http_response_code($status);
  echo json_encode($data);
  exit();
}

function notify_socket($data) {
  $url = "http://localhost:3000/notify";
  $options = [
    'http' => [
      'header'  => "Content-type: application/json\r\n",
      'method'  => 'POST',
      'content' => json_encode($data),
      'timeout' => 1
    ]
  ];
  $context  = stream_context_create($options);
  @file_get_contents($url, false, $context);
}

function get_json_input() {
  static $input = null;
  if ($input === null) {
    $raw = file_get_contents("php://input");
    $input = json_decode($raw, true);
    if (!is_array($input)) $input = [];
  }
  return $input;
}

function require_csrf() {
  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    return;
  }

  $input = get_json_input();
  $token = "";
  if (isset($input["csrf"])) {
    $token = $input["csrf"];
  } elseif (isset($_POST["csrf"])) {
    $token = $_POST["csrf"];
  }

  if (empty($_SESSION["portal_csrf"]) || !hash_equals($_SESSION["portal_csrf"], $token)) {
    json_response(["ok" => false, "message" => "Invalid CSRF token"], 403);
  }
}

function event_columns($con) {
  // Ensure the table exists
  mysqli_query($con, "CREATE TABLE IF NOT EXISTS event_type (
    type_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    type_title VARCHAR(100) NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  // Seed if empty
  $res = mysqli_query($con, "SELECT COUNT(*) as cnt FROM event_type");
  if ($res) {
    $row = mysqli_fetch_assoc($res);
    if ($row["cnt"] == 0) {
      mysqli_query($con, "INSERT INTO event_type (type_id, type_title) VALUES 
        (1, 'Technical'),
        (2, 'Cultural'),
        (3, 'Sports'),
        (4, 'Workshop'),
        (5, 'Seminar'),
        (6, 'Gaming'),
        (7, 'Social'),
        (8, 'Hackathon'),
        (9, 'Competition'),
        (10, 'Concert'),
        (11, 'Festival'),
        (12, 'Webinar')");
    }
  }

  mysqli_query($con, "CREATE TABLE IF NOT EXISTS events (
    event_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_title VARCHAR(255) NOT NULL,
    event_price INT NOT NULL DEFAULT 0,
    participents INT NOT NULL DEFAULT 1,
    img_link VARCHAR(255) NULL,
    type_id INT NOT NULL DEFAULT 1
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $result = mysqli_query($con, "SHOW COLUMNS FROM events");
  $cols = [];
  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $cols[$row["Field"]] = true;
    }
  }

  if (!isset($cols["event_datetime"])) {
    mysqli_query($con, "ALTER TABLE events ADD COLUMN event_datetime DATETIME NULL");
    $cols["event_datetime"] = true;
  }
  if (!isset($cols["location"])) {
    mysqli_query($con, "ALTER TABLE events ADD COLUMN location VARCHAR(255) NULL");
    $cols["location"] = true;
  }
  if (!isset($cols["department"])) {
    mysqli_query($con, "ALTER TABLE events ADD COLUMN department VARCHAR(50) NULL");
    $cols["department"] = true;
  }
  if (!isset($cols["category"])) {
    mysqli_query($con, "ALTER TABLE events ADD COLUMN category VARCHAR(30) NULL");
    $cols["category"] = true;
  }
  if (!isset($cols["details"])) {
    mysqli_query($con, "ALTER TABLE events ADD COLUMN details TEXT NULL");
    $cols["details"] = true;
  }
  if (!isset($cols["publish_status"])) {
    mysqli_query($con, "ALTER TABLE events ADD COLUMN publish_status VARCHAR(20) NOT NULL DEFAULT 'approved'");
    $cols["publish_status"] = true;
  }
  if (!isset($cols["payment_type"])) {
    mysqli_query($con, "ALTER TABLE events ADD COLUMN payment_type VARCHAR(20) NOT NULL DEFAULT 'free'");
    $cols["payment_type"] = true;
  }
  if (!isset($cols["payment_qr"])) {
    mysqli_query($con, "ALTER TABLE events ADD COLUMN payment_qr VARCHAR(255) NULL");
    $cols["payment_qr"] = true;
  }
  if (!isset($cols["payment_link"])) {
    mysqli_query($con, "ALTER TABLE events ADD COLUMN payment_link TEXT NULL");
    $cols["payment_link"] = true;
  }
  if (!isset($cols["custom_photo"])) {
    mysqli_query($con, "ALTER TABLE events ADD COLUMN custom_photo VARCHAR(255) NULL");
    $cols["custom_photo"] = true;
  }
  if (!isset($cols["created_at"])) {
    mysqli_query($con, "ALTER TABLE events ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $cols["created_at"] = true;
  }

  return $cols;
}

function normalize_event($row, $columns) {
  $title = $row["event_title"];
  $typeTitle = isset($row["type_title"]) ? $row["type_title"] : "General";
  $img = !empty($row["custom_photo"]) ? "images/" . $row["custom_photo"] : (!empty($row["img_link"]) ? "images/" . $row["img_link"] : "");

  $payQR = $row["payment_qr"] ?? null;
  if ($payQR && !preg_match("~^(?:f|ht)tps?://~i", $payQR) && strpos($payQR, 'images/') !== 0) {
    $payQR = "images/" . $payQR;
  }

  $category = "academic";
  if (isset($columns["category"]) && !empty($row["category"])) {
    $category = strtolower($row["category"]);
  }
  $categorySource = strtolower($typeTitle . " " . $title);
  if (strpos($categorySource, "game") !== false || strpos($categorySource, "sport") !== false || strpos($categorySource, "athletic") !== false) {
    $category = "sports";
  } elseif (strpos($categorySource, "stage") !== false || strpos($categorySource, "cultural") !== false) {
    $category = "cultural";
  }

  $department = "cse";
  if (isset($columns["department"]) && !empty($row["department"])) {
    $department = strtolower($row["department"]);
  } else {
    $categorySource = strtolower($typeTitle . " " . $title);
    if (strpos($categorySource, "ece") !== false) $department = "ece";
    elseif (strpos($categorySource, "mech") !== false) $department = "mech";
    elseif (strpos($categorySource, "it") !== false) $department = "it";
    elseif (strpos($categorySource, "eee") !== false) $department = "eee";
    elseif (strpos($categorySource, "civil") !== false) $department = "civil";
    elseif (strpos($categorySource, "mba") !== false) $department = "mba";
    elseif (strpos($categorySource, "bsh") !== false) $department = "bsh";
    elseif (strpos($categorySource, "csm") !== false) $department = "csm";
    elseif (strpos($categorySource, "csd") !== false) $department = "csd";
    elseif (strpos($categorySource, "csg") !== false) $department = "csg";
    elseif (strpos($categorySource, "aids") !== false) $department = "aids";
    elseif (strpos($categorySource, "aiml") !== false) $department = "aiml";
  }

  $datetime = null;
  if (isset($columns["event_datetime"]) && !empty($row["event_datetime"])) {
    $datetime = $row["event_datetime"];
  } elseif (isset($columns["event_date"]) && !empty($row["event_date"])) {
    $time = (isset($columns["event_time"]) && !empty($row["event_time"])) ? $row["event_time"] : "10:00:00";
    $datetime = $row["event_date"] . " " . $time;
  }

  $location = "Campus Venue";
  if (isset($columns["location"]) && !empty($row["location"])) {
    $location = $row["location"];
  }

  $status = "upcoming";
  if (!empty($datetime) && strtotime($datetime) < time()) {
    $status = "past";
  }

  $details = "Category: " . $typeTitle . ". Entry fee: Rs " . (int)$row["event_price"] . ". Team size: " . (int)$row["participents"];
  if (isset($columns["details"]) && !empty($row["details"])) {
    $details = $row["details"];
  }

  return [
    "id" => (int)$row["event_id"],
    "title" => $title,
    "datetime" => $datetime,
    "location" => $location,
    "category" => $category,
    "department" => $department,
    "status" => $status,
    "thumb" => $img,
    "details" => $details,
    "fee" => (int)$row["event_price"],
    "teamSize" => (int)$row["participents"],
    "paymentType" => $row["payment_type"] ?? "free",
    "paymentQR" => $payQR,
    "paymentLink" => $row["payment_link"] ?? null
  ];
}

function ensure_posts_table($con) {
  $sql = "CREATE TABLE IF NOT EXISTS community_posts (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    author_name VARCHAR(120) NOT NULL,
    author_meta VARCHAR(180) NOT NULL,
    content TEXT NOT NULL,
    likes INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

  mysqli_query($con, $sql);

  $columnsRes = mysqli_query($con, "SHOW COLUMNS FROM community_posts");
  if ($columnsRes) {
    $columns = [];
    while ($col = mysqli_fetch_assoc($columnsRes)) {
      $columns[$col["Field"]] = true;
    }
    if (!isset($columns["status"])) {
      mysqli_query($con, "ALTER TABLE community_posts ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'approved'");
      // Ensure all existing posts are approved
      mysqli_query($con, "UPDATE community_posts SET status = 'approved'");
    }
  }
}

function ensure_users_table($con) {
  $sql = "CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'student',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  mysqli_query($con, $sql);
}

function ensure_registrations_table($con) {
  $sql = "CREATE TABLE IF NOT EXISTS registrations (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    college VARCHAR(255) NOT NULL,
    branch VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  mysqli_query($con, $sql);
  
  // Check for missing columns in case table already existed
  $colsRes = mysqli_query($con, "SHOW COLUMNS FROM registrations");
  $cols = [];
  while($c = mysqli_fetch_assoc($colsRes)) $cols[$c['Field']] = true;
  if (!isset($cols['payment_proof'])) mysqli_query($con, "ALTER TABLE registrations ADD COLUMN payment_proof VARCHAR(255) NULL AFTER status");
  if (!isset($cols['ticket_qr'])) mysqli_query($con, "ALTER TABLE registrations ADD COLUMN ticket_qr VARCHAR(255) NULL AFTER payment_proof");
}

function ensure_announcements_table($con) {
  $sql = "CREATE TABLE IF NOT EXISTS announcements (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  mysqli_query($con, $sql);
}

function ensure_admins_table($con) {
  $sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  mysqli_query($con, $sql);
  
  // Seed default admin if none exists
  $res = mysqli_query($con, "SELECT COUNT(*) as cnt FROM admins");
  $row = mysqli_fetch_assoc($res);
  if ($row["cnt"] == 0) {
    $hashed = password_hash("admin123", PASSWORD_DEFAULT);
    mysqli_query($con, "INSERT INTO admins (username, password) VALUES ('admin', '$hashed')");
  }
}

function ensure_feedback_table($con) {
  $sql = "CREATE TABLE IF NOT EXISTS event_feedback (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL DEFAULT 5,
    comment TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  mysqli_query($con, $sql);
}

function ensure_notifications_table($con) {
  $sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(20) DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  mysqli_query($con, $sql);
}

function ensure_student_metrics_table($con) {
  $sql = "CREATE TABLE IF NOT EXISTS student_metrics (
    user_id INT NOT NULL PRIMARY KEY,
    attendance_pct DECIMAL(5,2) DEFAULT 0.00,
    gpa DECIMAL(3,2) DEFAULT 0.00,
    credits_earned INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  mysqli_query($con, $sql);
}

function ensure_deadlines_table($con) {
  $sql = "CREATE TABLE IF NOT EXISTS deadlines (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    due_date DATETIME NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  mysqli_query($con, $sql);
}

function ensure_resources_table($con) {
  $sql = "CREATE TABLE IF NOT EXISTS resources (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    type VARCHAR(50) DEFAULT 'pdf',
    category VARCHAR(50) DEFAULT 'general',
    link TEXT NOT NULL,
    user_id INT NULL,
    author_name VARCHAR(120) DEFAULT 'System',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  mysqli_query($con, $sql);

  // Check for missing columns in case table already existed
  $colsRes = mysqli_query($con, "SHOW COLUMNS FROM resources");
  $cols = [];
  while($c = mysqli_fetch_assoc($colsRes)) $cols[$c['Field']] = true;
  if (!isset($cols['user_id'])) mysqli_query($con, "ALTER TABLE resources ADD COLUMN user_id INT NULL AFTER link");
  if (!isset($cols['author_name'])) mysqli_query($con, "ALTER TABLE resources ADD COLUMN author_name VARCHAR(120) DEFAULT 'System' AFTER user_id");
}

function ensure_chat_tables($con) {
  // 1. Conversations
  mysqli_query($con, "CREATE TABLE IF NOT EXISTS chat_conversations (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    type ENUM('direct', 'group') NOT NULL DEFAULT 'direct',
    title VARCHAR(100) NULL,
    creator_id INT NOT NULL,
    invite_code VARCHAR(12) UNIQUE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // 2. Conversation Members
  mysqli_query($con, "CREATE TABLE IF NOT EXISTS chat_members (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member', 'admin') NOT NULL DEFAULT 'member',
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_read_id INT NULL,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // 3. Messages
  mysqli_query($con, "CREATE TABLE IF NOT EXISTS chat_messages (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    content TEXT NOT NULL,
    type ENUM('text', 'image', 'pdf') NOT NULL DEFAULT 'text',
    file_url VARCHAR(255) NULL,
    reply_to_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to_id) REFERENCES chat_messages(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // 4. Hidden Messages (Delete for me)
  mysqli_query($con, "CREATE TABLE IF NOT EXISTS chat_hidden_messages (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message_id INT NOT NULL,
    hidden_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, message_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function add_notification($con, $userId, $title, $message, $type = 'info') {
  ensure_notifications_table($con);
  $stmt = mysqli_prepare($con, "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
  mysqli_stmt_bind_param($stmt, "isss", $userId, $title, $message, $type);
  $ok = mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);
  
  if ($ok) {
    notify_socket([
      "user_id" => $userId,
      "title" => $title,
      "message" => $message,
      "type" => $type
    ]);
  }
}

$action = isset($_GET["action"]) ? $_GET["action"] : "";

if ($action === "admin_login" && $_SERVER["REQUEST_METHOD"] === "POST") {
  ensure_admins_table($con);
  $input = get_json_input();
  $user = isset($input["username"]) ? trim($input["username"]) : "";
  $pass = isset($input["password"]) ? $input["password"] : "";

  $stmt = mysqli_prepare($con, "SELECT password FROM admins WHERE username = ?");
  mysqli_stmt_bind_param($stmt, "s", $user);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $admin = mysqli_fetch_assoc($res);
  mysqli_stmt_close($stmt);

  if ($admin && password_verify($pass, $admin["password"])) {
    $_SESSION["portal_admin"] = true;
    $_SESSION["admin_username"] = $user;
    $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));
    json_response(["ok" => true]);
  }
  json_response(["ok" => false, "message" => "Invalid credentials"], 401);
}

if ($action === "my_portal" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["user_id"])) {
    json_response(["ok" => false, "message" => "Not logged in"], 401);
  }
  
  $userId = (int)$_SESSION["user_id"];
  ensure_registrations_table($con);
  ensure_posts_table($con);
  ensure_announcements_table($con);
  
  // Get Registrations with status
  $regSql = "SELECT e.*, et.type_title, r.id as reg_id, r.status as reg_status, r.payment_proof, r.ticket_qr FROM registrations r JOIN events e ON r.event_id = e.event_id LEFT JOIN event_type et ON et.type_id = e.type_id WHERE r.user_id = $userId ORDER BY r.created_at DESC";
  $regRes = mysqli_query($con, $regSql);
  $columns = event_columns($con);
  $registrations = [];
  if ($regRes) {
    while ($row = mysqli_fetch_assoc($regRes)) {
      $normalized = normalize_event($row, $columns);
      $normalized["regId"] = (int)$row["reg_id"];
      $normalized["regStatus"] = $row["reg_status"];
      $normalized["paymentProof"] = $row["payment_proof"];
      $normalized["ticketQR"] = $row["ticket_qr"];
      
      // Also need event specific payment info
      $normalized["paymentQR"] = !empty($row["payment_qr"]) ? $row["payment_qr"] : null;
      $normalized["paymentLink"] = !empty($row["payment_link"]) ? $row["payment_link"] : null;
      
      // Get announcements for this event
      $annRes = mysqli_query($con, "SELECT message, created_at FROM announcements WHERE event_id = " . $normalized["id"] . " ORDER BY created_at DESC");
      $announcements = [];
      while ($annRow = mysqli_fetch_assoc($annRes)) {
        $announcements[] = $annRow;
      }
      $normalized["announcements"] = $announcements;
      
      $registrations[] = $normalized;
    }
  }
  
  // Get My Posts
  $postsSql = "SELECT * FROM community_posts WHERE author_name = ? ORDER BY created_at DESC";
  $stmt = mysqli_prepare($con, $postsSql);
  mysqli_stmt_bind_param($stmt, "s", $_SESSION["user_name"]);
  mysqli_stmt_execute($stmt);
  $postRes = mysqli_stmt_get_result($stmt);
  $myPosts = [];
  if ($postRes) {
    while ($row = mysqli_fetch_assoc($postRes)) {
      $myPosts[] = [
        "id" => (int)$row["id"],
        "content" => $row["content"],
        "status" => $row["status"],
        "likes" => (int)$row["likes"],
        "time" => $row["created_at"]
      ];
    }
  }
  mysqli_stmt_close($stmt);
  
  json_response([
    "ok" => true,
    "registrations" => $registrations,
    "posts" => $myPosts
  ]);
}

if ($action === "upload_image" && $_SERVER["REQUEST_METHOD"] === "POST") {
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false, "message" => "Admin access required"], 403);
  }

  if (!isset($_FILES["image"])) {
    json_response(["ok" => false, "message" => "No image uploaded"], 400);
  }

  $file = $_FILES["image"];
  $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
  if (!in_array($ext, ["jpg", "jpeg", "png", "webp"])) {
    json_response(["ok" => false, "message" => "Invalid file type"], 400);
  }

  $newName = "event_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
  $dest = "../images/" . $newName;

  if (move_uploaded_file($file["tmp_name"], $dest)) {
    json_response(["ok" => true, "filename" => $newName]);
  }
  json_response(["ok" => false, "message" => "Upload failed"], 500);
}

if ($action === "admin_save_event" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false, "message" => "Admin access required"], 403);
  }
  
  // Handle both JSON and regular POST (FormData)
  $input = get_json_input();
  $data = is_array($input) ? $input : $_POST;

  $id = isset($data["id"]) ? (int)$data["id"] : 0;
  $title = isset($data["title"]) ? trim($data["title"]) : "";
  $datetime = isset($data["datetime"]) ? trim($data["datetime"]) : "";
  $location = isset($data["location"]) ? trim($data["location"]) : "";
  $category = isset($data["category"]) ? strtolower(trim($data["category"])) : "";
  $department = isset($data["department"]) ? strtolower(trim($data["department"])) : "";
  $details = isset($data["details"]) ? trim($data["details"]) : "";
  $fee = isset($data["fee"]) ? (int)$data["fee"] : 0;
  $teamSize = isset($data["team_size"]) ? (int)$data["team_size"] : 1;
  $status = isset($data["status"]) ? $data["status"] : "approved";
  $img = isset($data["thumb"]) ? trim($data["thumb"]) : "cs01.jpg";
  $payType = $data["payment_type"] ?? "free";
  $payQR = $data["payment_qr"] ?? null;
  $payLink = $data["payment_link"] ?? null;

  // Handle QR File Upload
  if (isset($_FILES["payment_qr_file"])) {
    $file = $_FILES["payment_qr_file"];
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if (in_array($ext, ["jpg", "jpeg", "png", "svg"])) {
      $newName = "payqr_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
      if (move_uploaded_file($file["tmp_name"], "../images/" . $newName)) {
        $payQR = $newName; // Store just filename
      }
    }
  }

  if ($title === "") json_response(["ok" => false, "message" => "Title is required"], 422);

  if ($id > 0) {
    $stmt = mysqli_prepare($con, "UPDATE events SET event_title=?, event_price=?, participents=?, event_datetime=?, location=?, department=?, category=?, details=?, publish_status=?, img_link=?, payment_type=?, payment_qr=?, payment_link=? WHERE event_id=?");
    mysqli_stmt_bind_param($stmt, "siissssssssssi", $title, $fee, $teamSize, $datetime, $location, $department, $category, $details, $status, $img, $payType, $payQR, $payLink, $id);
  } else {
    $stmt = mysqli_prepare($con, "INSERT INTO events (event_title, event_price, participents, img_link, type_id, event_datetime, location, department, category, details, publish_status, payment_type, payment_qr, payment_link) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "siisssssssssss", $title, $fee, $teamSize, $img, $datetime, $location, $department, $category, $details, $status, $payType, $payQR, $payLink);
  }
  
  $ok = mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);
  
  json_response(["ok" => $ok, "message" => $ok ? "Event saved" : "Failed to save event"]);
}

if ($action === "export_calendar" && $_SERVER["REQUEST_METHOD"] === "GET") {
  $eventId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
  if ($eventId <= 0) die("Invalid event");
  
  $sql = "SELECT event_title, event_datetime, location, details FROM events WHERE event_id = $eventId";
  $res = mysqli_query($con, $sql);
  $e = mysqli_fetch_assoc($res);
  if (!$e) die("Event not found");

  $start = date("Ymd\THis", strtotime($e["event_datetime"]));
  $end = date("Ymd\THis", strtotime($e["event_datetime"] . " +2 hours")); // Default 2h duration
  $title = $e["event_title"];
  $loc = $e["location"];
  $desc = str_replace(["\r", "\n"], " ", strip_tags($e["details"]));

  header('Content-Type: text/calendar; charset=utf-8');
  header('Content-Disposition: attachment; filename=event_'.$eventId.'.ics');

  echo "BEGIN:VCALENDAR\r\n";
  echo "VERSION:2.0\r\n";
  echo "PRODID:-//BVRIT Hyderabad//Campus Portal//EN\r\n";
  echo "BEGIN:VEVENT\r\n";
  echo "UID:" . uniqid() . "@bvrithyderabad.edu.in\r\n";
  echo "DTSTAMP:" . date("Ymd\THis\Z") . "\r\n";
  echo "DTSTART:$start\r\n";
  echo "DTEND:$end\r\n";
  echo "SUMMARY:$title\r\n";
  echo "LOCATION:$loc\r\n";
  echo "DESCRIPTION:$desc\r\n";
  echo "END:VEVENT\r\n";
  echo "END:VCALENDAR\r\n";
  exit();
}

if ($action === "export_registrants" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    die("Access denied");
  }
  
  $eventId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
  if ($eventId <= 0) die("Invalid event");
  
  $res = mysqli_query($con, "SELECT full_name, email, mobile, college, branch, created_at FROM registrations WHERE event_id = $eventId ORDER BY created_at ASC");
  
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=registrants_event_'.$eventId.'.csv');
  
  $output = fopen('php://output', 'w');
  fputcsv($output, ['Full Name', 'Email', 'Mobile', 'College', 'Branch', 'Registration Date']);
  
  if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
      fputcsv($output, $row);
    }
  }
  fclose($output);
  exit();
}

if ($action === "event_details" && $_SERVER["REQUEST_METHOD"] === "GET") {
  $eventId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
  if ($eventId <= 0) {
    json_response(["ok" => false, "message" => "Invalid event id"], 422);
  }
  
  $columns = event_columns($con);
  $sql = "SELECT e.event_id, e.event_title, e.event_price, e.participents, e.img_link, e.event_datetime, e.location, e.department, e.category, e.details, e.publish_status, et.type_title FROM events e LEFT JOIN event_type et ON et.type_id = e.type_id WHERE e.event_id = " . $eventId;
  $res = mysqli_query($con, $sql);
  if (!$res || mysqli_num_rows($res) === 0) {
    json_response(["ok" => false, "message" => "Event not found"], 404);
  }
  
  $row = mysqli_fetch_assoc($res);
  $event = normalize_event($row, $columns);
  
  // Add current registration count
  $regRes = mysqli_query($con, "SELECT COUNT(*) as count FROM registrations WHERE event_id = $eventId AND status = 'confirmed'");
  $regData = mysqli_fetch_assoc($regRes);
  $event["currentRegistrations"] = (int)$regData["count"];
  
  json_response(["ok" => true, "event" => $event]);
}

if ($action === "register" && $_SERVER["REQUEST_METHOD"] === "POST") {
  ensure_registrations_table($con);
  $input = get_json_input();
  
  $eventId = isset($input["event_id"]) ? (int)$input["event_id"] : 0;
  $fullName = isset($input["full_name"]) ? trim($input["full_name"]) : "";
  $email = isset($input["email"]) ? strtolower(trim($input["email"])) : "";
  $mobile = isset($input["mobile"]) ? trim($input["mobile"]) : "";
  $college = isset($input["college"]) ? trim($input["college"]) : "";
  $branch = isset($input["branch"]) ? trim($input["branch"]) : "";
  $userId = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : null;
  
  // Robust email fetching
  $finalEmail = "";
  if ($userId) {
    if (isset($_SESSION["user_email"])) {
      $finalEmail = $_SESSION["user_email"];
    } else {
      // Fetch from DB if missing in session
      $stmt = mysqli_prepare($con, "SELECT email FROM users WHERE id = ?");
      mysqli_stmt_bind_param($stmt, "i", $userId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      if ($u = mysqli_fetch_assoc($res)) {
        $finalEmail = $u["email"];
        $_SESSION["user_email"] = $finalEmail; // Update session
      }
      mysqli_stmt_close($stmt);
    }
  } else {
    $finalEmail = $email;
  }

  if ($eventId <= 0) {
    json_response(["ok" => false, "message" => "Invalid event selected."], 422);
  }
  if ($fullName === "" || $mobile === "" || $college === "" || $branch === "") {
    json_response(["ok" => false, "message" => "All form fields are required."], 422);
  }
  if (!preg_match('/^[0-9]{10}$/', $mobile)) {
    json_response(["ok" => false, "message" => "Please enter a valid 10-digit mobile number."], 422);
  }
  if ($finalEmail === "") {
    json_response(["ok" => false, "message" => "Session expired or email not found. Please log in again."], 401);
  }

  // PREVENT DUPLICATE REGISTRATION
  $checkStmt = mysqli_prepare($con, "SELECT id FROM registrations WHERE event_id = ? AND (user_id = ? OR email = ?)");
  mysqli_stmt_bind_param($checkStmt, "iis", $eventId, $userId, $finalEmail);
  mysqli_stmt_execute($checkStmt);
  mysqli_stmt_store_result($checkStmt);
  if (mysqli_stmt_num_rows($checkStmt) > 0) {
    mysqli_stmt_close($checkStmt);
    json_response(["ok" => false, "message" => "You are already registered for this event."], 409);
  }
  mysqli_stmt_close($checkStmt);

  // CHECK CAPACITY
  $eventRes = mysqli_query($con, "SELECT event_id, participents, event_price, payment_type, payment_qr, payment_link, details, event_datetime, location, department, category, img_link, custom_photo FROM events WHERE event_id = $eventId");
  $eventData = mysqli_fetch_assoc($eventRes);
  $maxCapacity = (int)($eventData["participents"] ?? 100);
  $isPaid = (int)($eventData["event_price"] ?? 0) > 0;

  $countRes = mysqli_query($con, "SELECT COUNT(*) as count FROM registrations WHERE event_id = $eventId AND status = 'confirmed'");
  $countData = mysqli_fetch_assoc($countRes);
  $currentCount = (int)$countData["count"];

  $status = ($currentCount < $maxCapacity) ? ($isPaid ? 'pending_payment' : 'confirmed') : 'waitlisted';

  $stmt = mysqli_prepare($con, "INSERT INTO registrations (event_id, user_id, full_name, email, mobile, college, branch, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
  if (!$stmt) {
    json_response(["ok" => false, "message" => "Registration failed"], 500);
  }
  mysqli_stmt_bind_param($stmt, "iissssss", $eventId, $userId, $fullName, $finalEmail, $mobile, $college, $branch, $status);
  
  $ok = mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);

  if (!$ok) {
    json_response(["ok" => false, "message" => "Registration failed"], 500);
  }

  $regId = mysqli_insert_id($con);

  $columns = event_columns($con);
  $normalized = normalize_event($eventData, $columns);

  if ($userId) {
    $title = "Registration Update";
    $msg = "You've successfully submitted your details.";
    if ($status === 'confirmed') {
      $title = "Registration Confirmed!";
      $msg = "You are successfully registered for the event.";
    } elseif ($status === 'pending_payment') {
      $title = "Payment Required";
      $msg = "Please complete the payment to confirm your registration.";
    } elseif ($status === 'waitlisted') {
      $title = "Waitlisted";
      $msg = "The event is full. You've been added to the waitlist.";
    }
    add_notification($con, $userId, $title, $msg, ($status === 'confirmed' ? 'success' : 'info'));
  }

  json_response([
    "ok" => true, 
    "message" => ($status === 'pending_payment') ? "Please complete payment." : (($status === 'confirmed') ? "Successfully registered!" : "Waitlisted."),
    "status" => $status,
    "reg_id" => $regId,
    "payment_qr" => $normalized["paymentQR"],
    "payment_link" => $normalized["paymentLink"]
  ]);
}

if ($action === "upload_payment_proof" && $_SERVER["REQUEST_METHOD"] === "POST") {
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  $userId = (int)$_SESSION["user_id"];
  $regId = (int)($_POST["reg_id"] ?? 0);
  
  if ($regId <= 0 || !isset($_FILES["proof"])) {
    json_response(["ok" => false, "message" => "Missing data"], 422);
  }

  $file = $_FILES["proof"];
  $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
  if (!in_array($ext, ["jpg", "jpeg", "png"])) {
    json_response(["ok" => false, "message" => "Only images allowed"], 400);
  }

  $newName = "proof_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
  if (move_uploaded_file($file["tmp_name"], "../images/" . $newName)) {
    $path = "images/" . $newName;
    mysqli_query($con, "UPDATE registrations SET payment_proof = '$path', status = 'awaiting_confirmation' WHERE id = $regId AND user_id = $userId");
    json_response(["ok" => true]);
  }
  json_response(["ok" => false], 500);
}

if ($action === "send_announcement" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false, "message" => "Admin access required"], 403);
  }
  
  ensure_announcements_table($con);
  $input = json_decode(file_get_contents("php://input"), true);
  $eventId = isset($input["event_id"]) ? (int)$input["event_id"] : 0;
  $message = isset($input["message"]) ? trim($input["message"]) : "";

  if ($eventId <= 0 || $message === "") {
    json_response(["ok" => false, "message" => "Event ID and message are required"], 422);
  }

  $stmt = mysqli_prepare($con, "INSERT INTO announcements (event_id, message) VALUES (?, ?)");
  mysqli_stmt_bind_param($stmt, "is", $eventId, $message);
  $ok = mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);

  json_response(["ok" => $ok, "message" => $ok ? "Announcement sent" : "Failed to send announcement"]);
}

if ($action === "event_registrants" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false, "message" => "Admin access required"], 403);
  }
  
  $eventId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
  if ($eventId <= 0) {
    json_response(["ok" => false, "message" => "Invalid event id"], 422);
  }
  
  ensure_registrations_table($con);
  $stmt = mysqli_prepare($con, "SELECT id, full_name, email, mobile, college, branch, status, payment_proof, created_at FROM registrations WHERE event_id = ? ORDER BY status ASC, created_at DESC");
  mysqli_stmt_bind_param($stmt, "i", $eventId);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $registrants = [];
  while ($row = mysqli_fetch_assoc($res)) {
    $registrants[] = $row;
  }
  mysqli_stmt_close($stmt);
  
  json_response(["ok" => true, "registrants" => $registrants]);
}

if ($action === "signup" && $_SERVER["REQUEST_METHOD"] === "POST") {
  ensure_users_table($con);
  $input = json_decode(file_get_contents("php://input"), true);
  
  $fullname = isset($input["fullname"]) ? trim($input["fullname"]) : "";
  $email = isset($input["email"]) ? strtolower(trim($input["email"])) : "";
  $password = isset($input["password"]) ? $input["password"] : "";

  if ($fullname === "" || $email === "" || $password === "") {
    json_response(["ok" => false, "message" => "All fields are required"], 422);
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(["ok" => false, "message" => "Invalid email format"], 422);
  }
  if (strlen($password) < 6) {
    json_response(["ok" => false, "message" => "Password must be at least 6 characters"], 422);
  }

  // Double check table exists
  ensure_users_table($con);

  $hashed = password_hash($password, PASSWORD_DEFAULT);
  $stmt = mysqli_prepare($con, "INSERT INTO users (fullname, email, password) VALUES (?, ?, ?)");
  if (!$stmt) {
    json_response(["ok" => false, "message" => "Registration failed: " . mysqli_error($con)], 500);
  }
  mysqli_stmt_bind_param($stmt, "sss", $fullname, $email, $hashed);
  $ok = mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);

  if (!$ok) {
    $errCode = mysqli_errno($con);
    if ($errCode === 1062) {
      json_response(["ok" => false, "message" => "Email already registered"], 409);
    }
    json_response(["ok" => false, "message" => "Registration failed: " . mysqli_error($con)], 500);
  }

  // Auto-login after successful signup
  $userId = mysqli_insert_id($con);
  
  // POPULATE RANDOM STUDENT DASHBOARD FOR NEW USER
  ensure_student_metrics_table($con);
  $randAttendance = rand(750, 980) / 10.0; // 75.0 to 98.0
  $randGPA = rand(300, 400) / 100.0; // 3.00 to 4.00
  $randCredits = rand(15, 65);
  mysqli_query($con, "INSERT INTO student_metrics (user_id, attendance_pct, gpa, credits_earned) VALUES ($userId, $randAttendance, $randGPA, $randCredits)");

  ensure_deadlines_table($con);
  $sampleDeadlines = [
    ["Project Submission", rand(5, 15)],
    ["Lab Record Review", rand(2, 7)],
    ["Mid-term Quiz", rand(10, 20)],
    ["Research Paper Draft", rand(15, 30)]
  ];
  shuffle($sampleDeadlines);
  $selectedDeadlines = array_slice($sampleDeadlines, 0, 3);
  foreach ($selectedDeadlines as $sd) {
    $title = $sd[0];
    $dueDate = date('Y-m-d H:i:s', strtotime("+" . $sd[1] . " days"));
    mysqli_query($con, "INSERT INTO deadlines (user_id, title, due_date, status) VALUES ($userId, '$title', '$dueDate', 'pending')");
  }

  // Send welcome notification
  add_notification($con, $userId, "Welcome to Campus Portal!", "We're glad to have you. Explore events and stay connected.", "success");
  
  $_SESSION["user_id"] = $userId;
  $_SESSION["user_name"] = $fullname;
  $_SESSION["user_email"] = $email;
  $_SESSION["user_role"] = "student";

  json_response([
    "ok" => true, 
    "message" => "Registration successful",
    "user" => [
      "id" => $userId,
      "name" => $fullname,
      "role" => "student"
    ]
  ]);
}

if ($action === "login" && $_SERVER["REQUEST_METHOD"] === "POST") {
  ensure_users_table($con);
  $input = json_decode(file_get_contents("php://input"), true);
  
  $email = isset($input["email"]) ? strtolower(trim($input["email"])) : "";
  $password = isset($input["password"]) ? $input["password"] : "";

  $stmt = mysqli_prepare($con, "SELECT id, fullname, password, role FROM users WHERE email = ?");
  mysqli_stmt_bind_param($stmt, "s", $email);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $user = mysqli_fetch_assoc($res);
  mysqli_stmt_close($stmt);

  if (!$user || !password_verify($password, $user["password"])) {
    json_response(["ok" => false, "message" => "Invalid email or password"], 401);
  }

  $_SESSION["user_id"] = $user["id"];
  $_SESSION["user_name"] = $user["fullname"];
  $_SESSION["user_email"] = $email;
  $_SESSION["user_role"] = $user["role"];

  json_response([
    "ok" => true, 
    "user" => [
      "id" => $user["id"],
      "name" => $user["fullname"],
      "role" => $user["role"]
    ]
  ]);
}

if ($action === "logout") {
  session_destroy();
  json_response(["ok" => true]);
}

if ($action === "me") {
  if (isset($_SESSION["user_id"])) {
    // If email is missing from session (legacy session), fetch it
    if (!isset($_SESSION["user_email"])) {
      $stmt = mysqli_prepare($con, "SELECT email FROM users WHERE id = ?");
      mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      if ($u = mysqli_fetch_assoc($res)) {
        $_SESSION["user_email"] = $u["email"];
      }
      mysqli_stmt_close($stmt);
    }

    json_response([
      "ok" => true,
      "user" => [
        "id" => $_SESSION["user_id"],
        "name" => $_SESSION["user_name"],
        "email" => isset($_SESSION["user_email"]) ? $_SESSION["user_email"] : "",
        "role" => $_SESSION["user_role"]
      ]
    ]);
  } else {
    json_response(["ok" => false]);
  }
}

if ($action === "events" && $_SERVER["REQUEST_METHOD"] === "GET") {
  $columns = event_columns($con);
  $sql = "SELECT e.*, et.type_title FROM events e LEFT JOIN event_type et ON et.type_id = e.type_id WHERE e.publish_status = 'approved'";
  $res = mysqli_query($con, $sql);
  if (!$res) {
    json_response(["ok" => false, "message" => "Database error: " . mysqli_error($con)], 500);
  }
  
  $events = [];
  $registeredData = [];
  if (isset($_SESSION["user_id"])) {
    ensure_registrations_table($con);
    $uRes = mysqli_query($con, "SELECT id, event_id, status, payment_proof, ticket_qr FROM registrations WHERE user_id = " . (int)$_SESSION["user_id"]);
    if ($uRes) {
      while ($uRow = mysqli_fetch_assoc($uRes)) {
        $registeredData[(int)$uRow["event_id"]] = $uRow;
      }
    }
  }

  ensure_announcements_table($con);
  if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
      $normalized = normalize_event($row, $columns);
      $regInfo = isset($registeredData[$normalized["id"]]) ? $registeredData[$normalized["id"]] : null;
      
      $normalized["isRegistered"] = ($regInfo !== null);
      $normalized["registrationStatus"] = $regInfo ? $regInfo["status"] : null;
      $normalized["registrationId"] = $regInfo ? (int)$regInfo["id"] : null;
      $normalized["paymentProof"] = $regInfo ? $regInfo["payment_proof"] : null;
      $normalized["ticketQR"] = $regInfo ? $regInfo["ticket_qr"] : null;
      
      // Get the latest announcement for this event
      $annSql = "SELECT message FROM announcements WHERE event_id = " . (int)$normalized["id"] . " ORDER BY created_at DESC LIMIT 1";
      $annRes = mysqli_query($con, $annSql);
      if ($annRes && mysqli_num_rows($annRes) > 0) {
        $annRow = mysqli_fetch_assoc($annRes);
        $normalized["announcement"] = $annRow["message"];
      } else {
        $normalized["announcement"] = null;
      }
      
      $events[] = $normalized;
    }
  }
  
  json_response(["ok" => true, "events" => $events]);
}

if ($action === "events" && $_SERVER["REQUEST_METHOD"] === "POST") {
  if (!isset($_SESSION["user_id"])) {
    json_response(["ok" => false, "message" => "Please login to suggest events"], 401);
  }
  $columns = event_columns($con);
  $input = json_decode(file_get_contents("php://input"), true);
  
  $title = isset($input["title"]) ? trim($input["title"]) : "";
  $datetime = isset($input["datetime"]) ? trim($input["datetime"]) : "";
  $location = isset($input["location"]) ? trim($input["location"]) : "";
  $category = isset($input["category"]) ? trim($input["category"]) : "";
  $department = isset($input["department"]) ? trim($input["department"]) : "";
  $details = isset($input["details"]) ? trim($input["details"]) : "";
  $fee = isset($input["fee"]) ? (int)$input["fee"] : 0;
  $teamSize = isset($input["team_size"]) ? (int)$input["team_size"] : 1;
  $paymentType = isset($input["payment_type"]) ? trim($input["payment_type"]) : "free";
  $paymentLink = isset($input["payment_link"]) ? trim($input["payment_link"]) : null;
  $customPhoto = null;

  if (isset($input["custom_photo_base64"])) {
    $base64 = $input["custom_photo_base64"];
    if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
      $data = substr($base64, strpos($base64, ',') + 1);
      $type = strtolower($type[1]);
      if (in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
        $data = base64_decode($data);
        $newName = "custom_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $type;
        if (file_put_contents("../images/" . $newName, $data)) {
          $customPhoto = $newName;
        }
      }
    }
  }
  $paymentQR = null;

  if ($fee > 0 && isset($input["payment_qr_base64"])) {
    $base64 = $input["payment_qr_base64"];
    if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
      $data = substr($base64, strpos($base64, ',') + 1);
      $type = strtolower($type[1]);
      if (in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
        $data = base64_decode($data);
        $newName = "payqr_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $type;
        if (file_put_contents("../images/" . $newName, $data)) {
          $paymentQR = "images/" . $newName;
        }
      }
    }
  }

  if ($title === "" || $datetime === "" || $location === "" || $category === "" || $department === "" || $details === "") {
    json_response(["ok" => false, "message" => "All fields are required"], 422);
  }

  $imgLink = $customPhoto ?: "cs01.jpg";
  $stmt = mysqli_prepare($con, "INSERT INTO events (event_title, event_price, participents, img_link, type_id, event_datetime, location, department, category, details, publish_status, payment_type, payment_qr, payment_link, custom_photo) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)");
  if (!$stmt) {
    json_response(["ok" => false, "message" => "SQL preparation failed: " . mysqli_error($con)], 500);
  }
  mysqli_stmt_bind_param($stmt, "siissssssssss", $title, $fee, $teamSize, $imgLink, $datetime, $location, $department, $category, $details, $paymentType, $paymentQR, $paymentLink, $customPhoto);
  $ok = mysqli_stmt_execute($stmt);
  if (!$ok) {
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    json_response(["ok" => false, "message" => "Execution failed: " . $err], 500);
  }
  mysqli_stmt_close($stmt);

  if ($ok) {
    notify_socket([
      "title" => "New Event Proposed",
      "message" => "A new event '$title' has been suggested!",
      "type" => "info"
    ]);
  }

  json_response(["ok" => $ok, "message" => $ok ? "Event suggested and pending approval" : "Submission failed"]);
}

if ($action === "posts" && $_SERVER["REQUEST_METHOD"] === "GET") {
  ensure_posts_table($con);
  $res = mysqli_query($con, "SELECT id, author_name, author_meta, content, likes, status, created_at FROM community_posts WHERE status = 'approved' ORDER BY created_at DESC LIMIT 50");
  $posts = [];
  if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
      $posts[] = [
        "id" => (int)$row["id"],
        "author" => $row["author_name"],
        "meta" => $row["author_meta"],
        "content" => $row["content"],
        "likes" => (int)$row["likes"],
        "time" => date("M j, g:ia", strtotime($row["created_at"]))
      ];
    }
  }
  json_response(["ok" => true, "posts" => $posts]);
}

if ($action === "posts" && $_SERVER["REQUEST_METHOD"] === "POST") {
  if (!isset($_SESSION["user_id"])) {
    json_response(["ok" => false, "message" => "Please login to post"], 401);
  }
  ensure_posts_table($con);
  $input = json_decode(file_get_contents("php://input"), true);
  
  $author = isset($input["author"]) ? trim($input["author"]) : "";
  $meta = isset($input["meta"]) ? trim($input["meta"]) : "";
  $content = isset($input["content"]) ? trim($input["content"]) : "";

  if ($author === "" || $meta === "" || $content === "") {
    json_response(["ok" => false, "message" => "All fields are required"], 422);
  }
  if (strlen($author) > 120 || strlen($meta) > 180 || strlen($content) > 2000) {
    json_response(["ok" => false, "message" => "Input exceeds allowed length"], 422);
  }

  // Basic profanity filter placeholder
  $profanity = ["badword1", "badword2"]; // Add actual words if needed
  foreach ($profanity as $word) {
    if (stripos($content, $word) !== false) {
      json_response(["ok" => false, "message" => "Content contains restricted language"], 422);
    }
  }

  $stmt = mysqli_prepare($con, "INSERT INTO community_posts (author_name, author_meta, content, status) VALUES (?, ?, ?, 'pending')");
  mysqli_stmt_bind_param($stmt, "sss", $author, $meta, $content);
  $ok = mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);

  if ($ok) {
    notify_socket([
      "title" => "New Community Post",
      "message" => "A new post from $author is waiting for approval.",
      "type" => "info"
    ]);
  }

  json_response(["ok" => $ok, "message" => $ok ? "Post submitted for approval" : "Submission failed"]);
}

if ($action === "like_post" && $_SERVER["REQUEST_METHOD"] === "POST") {
  if (!isset($_SESSION["user_id"])) {
    json_response(["ok" => false, "message" => "Please login to like posts"], 401);
  }
  ensure_posts_table($con);
  $input = json_decode(file_get_contents("php://input"), true);
  $postId = isset($input["post_id"]) ? (int)$input["post_id"] : 0;

  if ($postId > 0) {
    mysqli_query($con, "UPDATE community_posts SET likes = likes + 1 WHERE id = $postId");
    $res = mysqli_query($con, "SELECT likes FROM community_posts WHERE id = $postId");
    $row = mysqli_fetch_assoc($res);
    json_response(["ok" => true, "likes" => (int)$row["likes"]]);
  }
  json_response(["ok" => false], 422);
}

// --- NEW FEATURES ACTIONS ---

if ($action === "feedback" && $_SERVER["REQUEST_METHOD"] === "POST") {
  if (!isset($_SESSION["user_id"])) {
    json_response(["ok" => false, "message" => "Please login to give feedback"], 401);
  }
  ensure_feedback_table($con);
  $input = json_decode(file_get_contents("php://input"), true);
  $eventId = (int)($input["event_id"] ?? 0);
  $rating = (int)($input["rating"] ?? 5);
  $comment = trim($input["comment"] ?? "");

  if ($eventId <= 0) json_response(["ok" => false, "message" => "Invalid event"], 422);

  $stmt = mysqli_prepare($con, "INSERT INTO event_feedback (event_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
  mysqli_stmt_bind_param($stmt, "iiis", $eventId, $_SESSION["user_id"], $rating, $comment);
  $ok = mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);

  json_response(["ok" => $ok, "message" => $ok ? "Feedback submitted" : "Failed to submit feedback"]);
}

if ($action === "notifications" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["user_id"])) {
    json_response(["ok" => false, "message" => "Not logged in"], 401);
  }
  ensure_notifications_table($con);
  $userId = (int)$_SESSION["user_id"];
  $res = mysqli_query($con, "SELECT * FROM notifications WHERE user_id = $userId ORDER BY created_at DESC LIMIT 20");
  $notifs = [];
  if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
      $notifs[] = [
        "id" => (int)$row["id"],
        "title" => $row["title"],
        "message" => $row["message"],
        "type" => $row["type"],
        "isRead" => (bool)$row["is_read"],
        "time" => date("M j, g:ia", strtotime($row["created_at"]))
      ];
    }
  }
  json_response(["ok" => true, "notifications" => $notifs]);
}

if ($action === "mark_notif_read" && $_SERVER["REQUEST_METHOD"] === "POST") {
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  $input = json_decode(file_get_contents("php://input"), true);
  $id = (int)($input["id"] ?? 0);
  if ($id > 0) {
    mysqli_query($con, "UPDATE notifications SET is_read = TRUE WHERE id = $id AND user_id = " . (int)$_SESSION["user_id"]);
  }
  json_response(["ok" => true]);
}

if ($action === "student_dashboard" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  $userId = (int)$_SESSION["user_id"];
  
  ensure_student_metrics_table($con);
  ensure_deadlines_table($con);
  ensure_resources_table($con);

  // Metrics
  $metricsRes = mysqli_query($con, "SELECT * FROM student_metrics WHERE user_id = $userId");
  $metrics = mysqli_fetch_assoc($metricsRes);
  if (!$metrics) {
    // For legacy users, generate once and persist to DB for consistency
    $att = (float)(rand(750, 950) / 10.0);
    $gpa = (float)(rand(320, 395) / 100.0);
    $creds = (int)rand(30, 60);
    mysqli_query($con, "INSERT IGNORE INTO student_metrics (user_id, attendance_pct, gpa, credits_earned) VALUES ($userId, $att, $gpa, $creds)");
    
    $metrics = [
      "attendance_pct" => $att,
      "gpa" => $gpa,
      "credits_earned" => $creds
    ];
  }

  // Deadlines
  $deadlinesRes = mysqli_query($con, "SELECT * FROM deadlines WHERE user_id = $userId AND status = 'pending' ORDER BY due_date ASC");
  $deadlines = [];
  while ($row = mysqli_fetch_assoc($deadlinesRes)) {
    $deadlines[] = [
      "title" => $row["title"],
      "due" => date("M j", strtotime($row["due_date"])),
      "daysLeft" => (int)ceil((strtotime($row["due_date"]) - time()) / 86400)
    ];
  }
  if (empty($deadlines)) {
    $deadlines = [["title" => "Upcoming Mid-terms", "due" => "Next week", "daysLeft" => 7]];
  }

  // Resources Search
  $q = isset($_GET["q"]) ? trim($_GET["q"]) : "";
  $resSql = "SELECT * FROM resources";
  if ($q !== "") {
    $resSql .= " WHERE title LIKE '%$q%' OR type LIKE '%$q%' OR author_name LIKE '%$q%' OR category LIKE '%$q%'";
  }
  $resSql .= " ORDER BY created_at DESC LIMIT 20";
  
  $resRes = mysqli_query($con, $resSql);
  $resources = [];
  while ($row = mysqli_fetch_assoc($resRes)) {
    $resources[] = [
      "id" => (int)$row["id"],
      "title" => $row["title"],
      "type" => $row["type"],
      "category" => $row["category"],
      "author" => $row["author_name"],
      "link" => $row["link"],
      "date" => date("M j, Y", strtotime($row["created_at"]))
    ];
  }

  json_response([
    "ok" => true,
    "metrics" => $metrics,
    "deadlines" => $deadlines,
    "resources" => $resources
  ]);
}

if ($action === "upload_resource" && $_SERVER["REQUEST_METHOD"] === "POST") {
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  ensure_resources_table($con);

  $title = trim($_POST["title"] ?? "");
  $category = trim($_POST["category"] ?? "general");
  $userId = (int)$_SESSION["user_id"];
  $author = $_SESSION["user_name"];

  if ($title === "" || !isset($_FILES["file"])) {
    json_response(["ok" => false, "message" => "Title and file are required"], 422);
  }

  $file = $_FILES["file"];
  $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
  // Allow a wide range of academic and media file types
  $allowed = ["pdf", "doc", "docx", "ppt", "pptx", "zip", "jpg", "jpeg", "png", "gif", "mp4", "mkv", "mp3", "txt", "xls", "xlsx"];
  
  if (!in_array($ext, $allowed)) {
    json_response(["ok" => false, "message" => "File type '$ext' is not allowed. Please upload documents, photos, or videos."], 400);
  }

  // Increase limit for videos if needed (PHP settings might still limit this, but we'll allow it here)
  if ($file["size"] > 50 * 1024 * 1024) { // 50MB limit
    json_response(["ok" => false, "message" => "File is too large. Max size is 50MB."], 400);
  }

  $newName = "res_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
  $dest = "../images/" . $newName; // Using images folder for convenience

  if (move_uploaded_file($file["tmp_name"], $dest)) {
    $link = "images/" . $newName;
    $stmt = mysqli_prepare($con, "INSERT INTO resources (title, type, category, link, user_id, author_name) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssssis", $title, $ext, $category, $link, $userId, $author);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    json_response(["ok" => $ok]);
  }
  json_response(["ok" => false, "message" => "Upload failed"], 500);
}

if ($action === "admin_dashboard" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false], 403);
  }
  
  // Active users (last 30 days)
  $usersRes = mysqli_query($con, "SELECT id, fullname, email, created_at FROM users ORDER BY created_at DESC LIMIT 50");
  $users = [];
  while ($row = mysqli_fetch_assoc($usersRes)) {
    $users[] = $row;
  }

  // Event participation summary
  $eventsRes = mysqli_query($con, "SELECT e.event_title, COUNT(r.id) as registrants FROM events e LEFT JOIN registrations r ON e.event_id = r.event_id GROUP BY e.event_id");
  $stats = [];
  while ($row = mysqli_fetch_assoc($eventsRes)) {
    $stats[] = $row;
  }

  json_response(["ok" => true, "users" => $users, "stats" => $stats]);
}

if ($action === "admin_users" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false], 403);
  }

  $search = isset($_GET["q"]) ? trim($_GET["q"]) : "";
  $sql = "SELECT u.id, u.fullname, u.email, u.created_at, 
          m.attendance_pct, m.gpa, m.credits_earned
          FROM users u
          LEFT JOIN student_metrics m ON u.id = m.user_id";
  
  if ($search !== "") {
    $search = mysqli_real_escape_string($con, $search);
    $sql .= " WHERE u.fullname LIKE '%$search%' OR u.email LIKE '%$search%'";
  }
  
  $sql .= " ORDER BY u.created_at DESC LIMIT 100";
  $res = mysqli_query($con, $sql);
  
  $users = [];
  while ($row = mysqli_fetch_assoc($res)) {
    $uId = $row["id"];
    
    // Apply dashboard-style random fallbacks if metrics are missing AND persist them
    if ($row["attendance_pct"] === null) {
      $att = (float)(rand(750, 950) / 10.0);
      $gpa = (float)(rand(320, 395) / 100.0);
      $creds = (int)rand(30, 60);
      mysqli_query($con, "INSERT IGNORE INTO student_metrics (user_id, attendance_pct, gpa, credits_earned) VALUES ($uId, $att, $gpa, $creds)");
      
      $row["attendance_pct"] = $att;
      $row["gpa"] = $gpa;
      $row["credits_earned"] = $creds;
    }

    // Get registered events
    $regRes = mysqli_query($con, "SELECT e.event_title FROM registrations r JOIN events e ON r.event_id = e.event_id WHERE r.user_id = $uId");
    $events = [];
    while ($regRow = mysqli_fetch_assoc($regRes)) {
      $events[] = $regRow["event_title"];
    }
    $row["registered_events"] = $events;
    $users[] = $row;
  }

  json_response(["ok" => true, "users" => $users]);
}

if ($action === "admin_delete_user" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false], 403);
  }
  
  $input = json_decode(file_get_contents("php://input"), true);
  $userId = (int)($input["user_id"] ?? 0);
  
  if ($userId <= 0) json_response(["ok" => false, "message" => "Invalid user ID"], 422);

  // Cascading deletes are handled by FOREIGN KEY ... ON DELETE CASCADE in the schema
  $ok = mysqli_query($con, "DELETE FROM users WHERE id = $userId");
  
  json_response(["ok" => $ok]);
}

if ($action === "admin_dashboard_stats" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false, "message" => "Admin access required"], 403);
  }

  $stats = [];
  
  // User count
  $uRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM users");
  $stats['userCount'] = (int)mysqli_fetch_assoc($uRes)['cnt'];
  
  // Community posts
  $pRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM community_posts WHERE status = 'pending'");
  $stats['pendingPosts'] = (int)mysqli_fetch_assoc($pRes)['cnt'];
  
  $aRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM community_posts WHERE status = 'approved'");
  $stats['livePosts'] = (int)mysqli_fetch_assoc($aRes)['cnt'];
  
  // Events
  $epRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM events WHERE publish_status = 'pending'");
  $stats['eventProposals'] = (int)mysqli_fetch_assoc($epRes)['cnt'];
  
  $aeRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM events WHERE publish_status = 'approved'");
  $stats['activeEvents'] = (int)mysqli_fetch_assoc($aeRes)['cnt'];
  
  // Registrations awaiting confirmation
  $paRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM registrations WHERE status = 'awaiting_confirmation'");
  $stats['pendingRegistrations'] = (int)mysqli_fetch_assoc($paRes)['cnt'];

  json_response(["ok" => true, "stats" => $stats]);
}

if ($action === "admin_dashboard_data" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false, "message" => "Admin access required"], 403);
  }

  $data = ["stats" => [], "posts" => [], "events" => []];
  
  // Stats
  $uRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM users");
  $data['stats']['userCount'] = (int)mysqli_fetch_assoc($uRes)['cnt'];
  $pRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM community_posts WHERE status = 'pending'");
  $data['stats']['pendingPosts'] = (int)mysqli_fetch_assoc($pRes)['cnt'];
  $aRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM community_posts WHERE status = 'approved'");
  $data['stats']['livePosts'] = (int)mysqli_fetch_assoc($aRes)['cnt'];
  $epRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM events WHERE publish_status = 'pending'");
  $data['stats']['eventProposals'] = (int)mysqli_fetch_assoc($epRes)['cnt'];
  $aeRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM events WHERE publish_status = 'approved'");
  $data['stats']['activeEvents'] = (int)mysqli_fetch_assoc($aeRes)['cnt'];
  $paRes = mysqli_query($con, "SELECT COUNT(*) as cnt FROM registrations WHERE status = 'awaiting_confirmation'");
  $data['stats']['pendingRegistrations'] = (int)mysqli_fetch_assoc($paRes)['cnt'];

  // All Posts
  $postsRes = mysqli_query($con, "SELECT * FROM community_posts ORDER BY created_at DESC");
  while ($row = mysqli_fetch_assoc($postsRes)) {
    $data['posts'][] = $row;
  }

  // All Events
  $eventsRes = mysqli_query($con, "SELECT * FROM events ORDER BY event_id DESC");
  while ($row = mysqli_fetch_assoc($eventsRes)) {
    $data['events'][] = $row;
  }

  json_response(["ok" => true, "data" => $data]);
}

if ($action === "admin_moderate_post" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false], 403);
  }
  $input = json_decode(file_get_contents("php://input"), true);
  $postId = (int)($input["post_id"] ?? 0);
  $status = trim($input["status"] ?? "");
  if ($postId > 0 && in_array($status, ["approved", "rejected"])) {
    $ok = mysqli_query($con, "UPDATE community_posts SET status = '$status' WHERE id = $postId");
    json_response(["ok" => $ok]);
  }
  json_response(["ok" => false], 422);
}

if ($action === "admin_delete_post" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false], 403);
  }
  $input = json_decode(file_get_contents("php://input"), true);
  $postId = (int)($input["post_id"] ?? 0);
  if ($postId > 0) {
    $ok = mysqli_query($con, "DELETE FROM community_posts WHERE id = $postId");
    json_response(["ok" => $ok]);
  }
  json_response(["ok" => false], 422);
}

if ($action === "admin_moderate_event" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false], 403);
  }
  $input = json_decode(file_get_contents("php://input"), true);
  $eventId = (int)($input["event_id"] ?? 0);
  $status = trim($input["status"] ?? "");
  if ($eventId > 0 && in_array($status, ["approved", "rejected"])) {
    $ok = mysqli_query($con, "UPDATE events SET publish_status = '$status' WHERE event_id = $eventId");
    json_response(["ok" => $ok]);
  }
  json_response(["ok" => false], 422);
}

if ($action === "admin_delete_event" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false], 403);
  }
  $input = json_decode(file_get_contents("php://input"), true);
  $eventId = (int)($input["event_id"] ?? 0);
  if ($eventId > 0) {
    $ok = mysqli_query($con, "DELETE FROM events WHERE event_id = $eventId");
    json_response(["ok" => $ok]);
  }
  json_response(["ok" => false], 422);
}

if ($action === "admin_approvals" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false], 403);
  }
  
  $status = isset($_GET["status"]) ? mysqli_real_escape_string($con, $_GET["status"]) : "awaiting_confirmation";
  $where = ($status === 'all') ? "1" : "r.status = '$status'";
  $sql = "SELECT r.*, e.event_title FROM registrations r LEFT JOIN events e ON r.event_id = e.event_id WHERE $where ORDER BY r.created_at DESC";
  $res = mysqli_query($con, $sql);
  $approvals = [];
  while ($row = mysqli_fetch_assoc($res)) {
    $approvals[] = $row;
  }
  json_response(["ok" => true, "approvals" => $approvals]);
}

if ($action === "admin_moderate_registration" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["portal_admin"]) || $_SESSION["portal_admin"] !== true) {
    json_response(["ok" => false], 403);
  }

  $input = json_decode(file_get_contents("php://input"), true);
  $regId = (int)($input["reg_id"] ?? 0);
  $status = trim($input["status"] ?? "");

  if ($regId <= 0 || !in_array($status, ['confirmed', 'rejected'])) {
    json_response(["ok" => false, "message" => "Invalid input"], 422);
  }

  // If confirmed, generate a random QR ticket
  $ticketQR = null;
  if ($status === 'confirmed') {
    $ticketQR = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=TICKET_" . bin2hex(random_bytes(8));
  }

  $stmt = mysqli_prepare($con, "UPDATE registrations SET status = ?, ticket_qr = ? WHERE id = ?");
  mysqli_stmt_bind_param($stmt, "ssi", $status, $ticketQR, $regId);
  $ok = mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);

  if ($ok) {
    // Notify user
    $res = mysqli_query($con, "SELECT user_id, event_id FROM registrations WHERE id = $regId");
    if ($row = mysqli_fetch_assoc($res)) {
      $userId = $row["user_id"];
      $eventId = $row["event_id"];
      $eRes = mysqli_query($con, "SELECT event_title FROM events WHERE event_id = $eventId");
      $eRow = mysqli_fetch_assoc($eRes);
      $eTitle = $eRow["event_title"] ?? "Event";

      if ($userId) {
        $title = $status === 'confirmed' ? "Registration Confirmed!" : "Registration Rejected";
        $msg = $status === 'confirmed' ? "Your registration for '$eTitle' has been approved. You can now download your ticket." : "Your registration for '$eTitle' was not approved.";
        add_notification($con, $userId, $title, $msg, $status === 'confirmed' ? 'success' : 'error');
        
        // Notify via WebSocket
        notify_socket([
          "user_id" => $userId,
          "title" => $title,
          "message" => $msg,
          "type" => $status === 'confirmed' ? 'success' : 'error'
        ]);
      }
    }
  }

  json_response(["ok" => $ok]);
}

// --- CHAT SYSTEM ACTIONS ---

if ($action === "chat_list" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  $userId = (int)$_SESSION["user_id"];
  ensure_chat_tables($con);

  $sql = "SELECT c.*, 
          (SELECT m.content FROM chat_messages m 
           LEFT JOIN chat_hidden_messages hm ON m.id = hm.message_id AND hm.user_id = $userId 
           WHERE m.conversation_id = c.id AND hm.id IS NULL 
           ORDER BY m.created_at DESC LIMIT 1) as last_msg,
          (SELECT m.created_at FROM chat_messages m 
           LEFT JOIN chat_hidden_messages hm ON m.id = hm.message_id AND hm.user_id = $userId 
           WHERE m.conversation_id = c.id AND hm.id IS NULL 
           ORDER BY m.created_at DESC LIMIT 1) as last_msg_time,
          (SELECT COUNT(*) FROM chat_messages m 
           LEFT JOIN chat_hidden_messages hm ON m.id = hm.message_id AND hm.user_id = $userId 
           WHERE m.conversation_id = c.id AND hm.id IS NULL AND m.id > IFNULL(cm.last_read_id, 0)) as unread_count
          FROM chat_conversations c
          JOIN chat_members cm ON c.id = cm.conversation_id
          WHERE cm.user_id = $userId
          ORDER BY last_msg_time DESC";
  
  $res = mysqli_query($con, $sql);
  $chats = [];
  while ($row = mysqli_fetch_assoc($res)) {
    if ($row["type"] === 'direct') {
      // Find the other user's name
      $cId = $row["id"];
      $uRes = mysqli_query($con, "SELECT fullname FROM users JOIN chat_members cm ON users.id = cm.user_id WHERE cm.conversation_id = $cId AND cm.user_id != $userId LIMIT 1");
      $uRow = mysqli_fetch_assoc($uRes);
      $row["title"] = $uRow["fullname"] ?? "Deleted User";
    } else {
      $row["title"] = $row["title"] ?: "Unnamed Group";
    }
    $chats[] = $row;
  }
  json_response(["ok" => true, "chats" => $chats]);
}

if ($action === "chat_history" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  ensure_chat_tables($con);
  $userId = (int)$_SESSION["user_id"];
  $convId = (int)($_GET["id"] ?? 0);
  if ($convId <= 0) json_response(["ok" => false, "message" => "Invalid chat"], 422);

  // Check membership
  $check = mysqli_query($con, "SELECT id FROM chat_members WHERE conversation_id = $convId AND user_id = $userId");
  if (mysqli_num_rows($check) === 0) json_response(["ok" => false, "message" => "Access denied"], 403);

  $sql = "SELECT m.*, u.fullname as sender_name, 
          rm.content as reply_content, rm.type as reply_type
          FROM chat_messages m 
          JOIN users u ON m.sender_id = u.id 
          LEFT JOIN chat_messages rm ON m.reply_to_id = rm.id
          LEFT JOIN chat_hidden_messages hm ON m.id = hm.message_id AND hm.user_id = $userId
          WHERE m.conversation_id = $convId AND hm.id IS NULL
          ORDER BY m.created_at ASC";
  $res = mysqli_query($con, $sql);
  $messages = [];
  while ($row = mysqli_fetch_assoc($res)) {
    $messages[] = $row;
  }

  // Update last read
  if (!empty($messages)) {
    $lastId = end($messages)["id"];
    mysqli_query($con, "UPDATE chat_members SET last_read_id = $lastId WHERE conversation_id = $convId AND user_id = $userId");
  }

  json_response(["ok" => true, "messages" => $messages]);
}

if ($action === "chat_send" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  ensure_chat_tables($con);
  $userId = (int)$_SESSION["user_id"];
  
  $convId = (int)($_POST["conversation_id"] ?? 0);
  $content = trim($_POST["content"] ?? "");
  $type = $_POST["type"] ?? "text";
  $replyTo = isset($_POST["reply_to_id"]) ? (int)$_POST["reply_to_id"] : null;

  if ($convId <= 0 || ($content === "" && !isset($_FILES["file"]))) {
    json_response(["ok" => false, "message" => "Empty message"], 422);
  }

  // Handle file upload
  $fileUrl = null;
  if (isset($_FILES["file"])) {
    $file = $_FILES["file"];
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed = ["jpg", "jpeg", "png", "webp", "pdf"];
    if (!in_array($ext, $allowed)) json_response(["ok" => false, "message" => "File type not allowed"], 400);

    $newName = "chat_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    if (move_uploaded_file($file["tmp_name"], "../images/" . $newName)) {
      $fileUrl = "images/" . $newName;
      $type = ($ext === 'pdf') ? 'pdf' : 'image';
    }
  }

  $stmt = mysqli_prepare($con, "INSERT INTO chat_messages (conversation_id, sender_id, content, type, file_url, reply_to_id) VALUES (?, ?, ?, ?, ?, ?)");
  mysqli_stmt_bind_param($stmt, "iisssi", $convId, $userId, $content, $type, $fileUrl, $replyTo);
  $ok = mysqli_stmt_execute($stmt);
  $msgId = mysqli_insert_id($con);
  mysqli_stmt_close($stmt);

  if ($ok) {
    // Get fresh message with sender info and reply context
    $res = mysqli_query($con, "SELECT m.*, u.fullname as sender_name, 
                               rm.content as reply_content, rm.type as reply_type
                               FROM chat_messages m 
                               JOIN users u ON m.sender_id = u.id 
                               LEFT JOIN chat_messages rm ON m.reply_to_id = rm.id
                               WHERE m.id = $msgId");
    json_response(["ok" => true, "message" => mysqli_fetch_assoc($res)]);
  }
  json_response(["ok" => false, "message" => mysqli_error($con)], 500);
}

if ($action === "chat_create_group" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  ensure_chat_tables($con);
  $userId = (int)$_SESSION["user_id"];
  $input = get_json_input();
  $name = trim($input["name"] ?? "New Group");
  $inviteCode = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 8);

  $stmt = mysqli_prepare($con, "INSERT INTO chat_conversations (type, title, creator_id, invite_code) VALUES ('group', ?, ?, ?)");
  mysqli_stmt_bind_param($stmt, "sis", $name, $userId, $inviteCode);
  if (mysqli_stmt_execute($stmt)) {
    $convId = mysqli_insert_id($con);
    mysqli_query($con, "INSERT INTO chat_members (conversation_id, user_id, role) VALUES ($convId, $userId, 'admin')");
    json_response(["ok" => true, "conversation_id" => $convId, "invite_code" => $inviteCode]);
  }
  json_response(["ok" => false, "message" => mysqli_error($con)], 500);
}

if ($action === "chat_join_group" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  ensure_chat_tables($con);
  $userId = (int)$_SESSION["user_id"];
  $input = get_json_input();
  $code = trim($input["invite_code"] ?? "");

  $res = mysqli_query($con, "SELECT id FROM chat_conversations WHERE invite_code = '$code'");
  if ($row = mysqli_fetch_assoc($res)) {
    $convId = $row["id"];
    // Check if already a member
    $check = mysqli_query($con, "SELECT id FROM chat_members WHERE conversation_id = $convId AND user_id = $userId");
    if (mysqli_num_rows($check) === 0) {
      mysqli_query($con, "INSERT INTO chat_members (conversation_id, user_id) VALUES ($convId, $userId)");
    }
    json_response(["ok" => true, "conversation_id" => $convId]);
  }
  json_response(["ok" => false, "message" => "Invalid code"], 404);
}

if ($action === "chat_start_direct" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  ensure_chat_tables($con);
  $userId = (int)$_SESSION["user_id"];
  $input = get_json_input();
  $targetId = (int)($input["user_id"] ?? 0);

  if ($targetId <= 0 || $targetId === $userId) json_response(["ok" => false], 422);

  // Check if conversation already exists
  $sql = "SELECT c.id FROM chat_conversations c 
          JOIN chat_members m1 ON c.id = m1.conversation_id 
          JOIN chat_members m2 ON c.id = m2.conversation_id 
          WHERE c.type = 'direct' AND m1.user_id = $userId AND m2.user_id = $targetId";
  $res = mysqli_query($con, $sql);
  if ($row = mysqli_fetch_assoc($res)) {
    json_response(["ok" => true, "conversation_id" => (int)$row["id"]]);
  }

  // Create new direct chat
  mysqli_query($con, "INSERT INTO chat_conversations (type, creator_id) VALUES ('direct', $userId)");
  $convId = mysqli_insert_id($con);
  mysqli_query($con, "INSERT INTO chat_members (conversation_id, user_id) VALUES ($convId, $userId), ($convId, $targetId)");
  
  json_response(["ok" => true, "conversation_id" => $convId]);
}

if ($action === "event_participants" && $_SERVER["REQUEST_METHOD"] === "GET") {
  $eventId = (int)($_GET["id"] ?? 0);
  if ($eventId <= 0) json_response(["ok" => false], 422);

  $sql = "SELECT u.id, u.fullname, r.status FROM users u JOIN registrations r ON u.id = r.user_id WHERE r.event_id = $eventId AND r.status = 'confirmed'";
  $res = mysqli_query($con, $sql);
  $participants = [];
  while ($row = mysqli_fetch_assoc($res)) {
    $participants[] = $row;
  }
  json_response(["ok" => true, "participants" => $participants]);
}

if ($action === "chat_delete_msg" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  ensure_chat_tables($con);
  $userId = (int)$_SESSION["user_id"];
  $input = get_json_input();
  $msgId = (int)($input["message_id"] ?? 0);

  if ($msgId <= 0) json_response(["ok" => false], 422);

  // Instead of deleting, we hide it for this user (Delete for me)
  $stmt = mysqli_prepare($con, "INSERT IGNORE INTO chat_hidden_messages (user_id, message_id) VALUES (?, ?)");
  mysqli_stmt_bind_param($stmt, "ii", $userId, $msgId);
  $ok = mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);

  json_response(["ok" => $ok]);
}

if ($action === "chat_edit_msg" && $_SERVER["REQUEST_METHOD"] === "POST") {
  require_csrf();
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  ensure_chat_tables($con);
  $userId = (int)$_SESSION["user_id"];
  $input = get_json_input();
  $msgId = (int)($input["message_id"] ?? 0);
  $content = trim($input["content"] ?? "");

  if ($msgId <= 0 || $content === "") json_response(["ok" => false], 422);

  $res = mysqli_query($con, "SELECT sender_id FROM chat_messages WHERE id = $msgId");
  if ($row = mysqli_fetch_assoc($res)) {
    if ($row["sender_id"] == $userId) {
      $stmt = mysqli_prepare($con, "UPDATE chat_messages SET content = ? WHERE id = ?");
      mysqli_stmt_bind_param($stmt, "si", $content, $msgId);
      mysqli_stmt_execute($stmt);
      json_response(["ok" => true]);
    }
  }
  json_response(["ok" => false, "message" => "Access denied"], 403);
}

if ($action === "user_search" && $_SERVER["REQUEST_METHOD"] === "GET") {
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  $query = trim($_GET["q"] ?? "");
  if (strlen($query) < 2) json_response(["ok" => true, "users" => []]);

  $userId = (int)$_SESSION["user_id"];
  $stmt = mysqli_prepare($con, "SELECT id, fullname, email FROM users WHERE (fullname LIKE ? OR email LIKE ?) AND id != ? LIMIT 10");
  $searchTerm = "%$query%";
  mysqli_stmt_bind_param($stmt, "ssi", $searchTerm, $searchTerm, $userId);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $users = [];
  while ($row = mysqli_fetch_assoc($res)) {
    $users[] = $row;
  }
  mysqli_stmt_close($stmt);
  json_response(["ok" => true, "users" => $users]);
}

if ($action === "chat_ai" && $_SERVER["REQUEST_METHOD"] === "POST") {
  if (!isset($_SESSION["user_id"])) json_response(["ok" => false], 401);
  $input = json_decode(file_get_contents("php://input"), true);
  $msg = strtolower(trim($input["message"] ?? ""));
  
  $response = "I'm sorry, I don't have that specific information yet. Try asking about **events**, **departments**, **placements**, or **campus facilities**! You can also type '**help**' to see what I can do.";
  
  // Rule-based logic with enriched knowledge
  if (preg_match("/\b(hello|hi|hey|greetings|who are you|what is your name)\b/", $msg)) {
    $response = "Greetings! I'm your **BVRIT Hyderabad AI Assistant**. 🎓\n\nI can help you with:\n🔹 Upcoming campus events\n🔹 Department information\n🔹 Placement records\n🔹 Campus facilities (Canteen, Library)\n🔹 Portal navigation help\n\nHow can I assist you today?";
  } 
  elseif (preg_match("/\b(help|menu|what can you do)\b/", $msg)) {
    $response = "I'm here to make your campus life easier! Here's what you can ask me:\n\n" .
                "📅 **Events**: \"List all events\", \"Tell me about Hackathon\"\n" .
                "🏢 **Departments**: \"Information about CSE\", \"IT department details\"\n" .
                "💼 **Placements**: \"Placement statistics\", \"Top recruiters\"\n" .
                "📍 **Facilities**: \"Canteen timing\", \"Library resources\"\n" .
                "📝 **Portal**: \"How to register?\", \"How to post on bulletin?\"";
  }
  // CAMPUS INFO
  elseif (preg_match("/\b(bvrit|college|campus|where|address|location|ranking|rank)\b/", $msg)) {
    if (preg_match("/\b(address|where|location)\b/", $msg)) {
      $response = "📍 **BVRIT Hyderabad College of Engineering for Women** is located in:\n\n" .
                  "Bachupally, Hyderabad, Telangana 500090.\n\n" .
                  "It's easily accessible via Miyapur Metro Station (about 5km away).";
    } else {
      $response = "BVRIT Hyderabad (BVRITH) was established in 2012 and is the second engineering college for women from Sri Vishnu Educational Society. 🏆\n\n" .
                  "🌟 **Highlights:**\n" .
                  "• NIRF Ranked among top engineering colleges.\n" .
                  "• NAAC A+ Accredited.\n" .
                  "• Known for its focus on innovation and women empowerment in technology.";
    }
  }
  // DEPARTMENTS
  elseif (preg_match("/\b(dept|department|cse|it|ece|eee|aiml|data science)\b/", $msg)) {
    if (preg_match("/\bcse\b/", $msg)) {
      $response = "💻 **Computer Science & Engineering (CSE):**\n" .
                  "The largest department at BVRITH, focusing on software development, AI, and Cloud Computing. It has state-of-the-art labs and a very high placement record.";
    } elseif (preg_match("/\bit\b/", $msg)) {
      $response = "🌐 **Information Technology (IT):**\n" .
                  "Focuses on information systems, web technologies, and networking. The curriculum is highly industry-oriented.";
    } elseif (preg_match("/\bece\b/", $msg)) {
      $response = "🔌 **Electronics & Communication Engineering (ECE):**\n" .
                  "Focuses on VLSI, Embedded Systems, and IoT. Students get exposure to both hardware and software domains.";
    } else {
      $response = "BVRIT Hyderabad offers undergraduate programs in:\n" .
                  "• CSE (Computer Science & Engineering)\n" .
                  "• IT (Information Technology)\n" .
                  "• ECE (Electronics & Communication Engineering)\n" .
                  "• EEE (Electrical & Electronics Engineering)\n" .
                  "• AI & ML (Artificial Intelligence and Machine Learning)\n" .
                  "• CS & BS (Computer Science and Business Systems)";
    }
  }
  // PLACEMENTS
  elseif (preg_match("/\b(placement|job|recruit|salary|package|company|companies)\b/", $msg)) {
    $response = "🎓 **Placement Highlights at BVRITH:**\n\n" .
                "🚀 **Highest Package:** Rs 54+ LPA (Atlassian/Adobe/Microsoft)\n" .
                "🤝 **Top Recruiters:** Amazon, Google, Microsoft, Qualcomm, TCS, Capgemini, and more.\n" .
                "📈 **Consistency:** Over 90% placement record for eligible students every year.\n\n" .
                "You can check the latest placement news in the community bulletin!";
  }
  // FACILITIES
  elseif (preg_match("/\b(canteen|food|lunch|library|book|lab|hostel|bus|transport)\b/", $msg)) {
    if (preg_match("/\b(canteen|food|lunch)\b/", $msg)) {
      $response = "🍴 **Campus Canteen:**\n" .
                  "Offers a variety of North and South Indian dishes. \n" .
                  "⏰ **Timings:** 8:30 AM to 5:30 PM.\n" .
                  "The food is hygienic and very affordable for students.";
    } elseif (preg_match("/\b(library|book)\b/", $msg)) {
      $response = "📚 **Central Library:**\n" .
                  "Houses over 30,000 volumes and has access to digital journals like IEEE and Springer.\n" .
                  "⏰ **Timings:** 8:00 AM to 7:00 PM.";
    } else {
      $response = "BVRITH provides excellent facilities including:\n" .
                  "• 🏠 Comfortable on-campus Hostels\n" .
                  "• 🚌 Extensive Bus Transport across Hyderabad\n" .
                  "• 🧪 Specialized Innovation & Robotics Labs\n" .
                  "• ⚽ Sports grounds for Basketball, Volleyball, and more.";
    }
  }
  // SEARCH EVENTS OR SPECIFIC EVENT DETAILS
  elseif (preg_match("/\b(event|happen|upcoming|detail|about|tell me about|summarize|list all|summary|join|register)\b/", $msg)) {
    // CHECK FOR SUMMARIZATION REQUEST
    if (preg_match("/\b(summarize|summary|list all|all events)\b/", $msg)) {
      $res = mysqli_query($con, "SELECT event_title, event_datetime, location, details FROM events WHERE publish_status = 'approved' AND event_datetime >= NOW() ORDER BY event_datetime ASC LIMIT 10");
      if (mysqli_num_rows($res) > 0) {
        $response = "🚀 **Campus Event Summary** 🚀\n\n";
        $response .= "I've found " . mysqli_num_rows($res) . " upcoming events for you:\n\n";
        while ($row = mysqli_fetch_assoc($res)) {
          $dateStr = date("M j", strtotime($row["event_datetime"]));
          $shortDetails = strlen($row["details"]) > 80 ? substr($row["details"], 0, 80) . "..." : $row["details"];
          $response .= "🔹 **" . $row["event_title"] . "** (" . $dateStr . ")\n";
          $response .= "   📍 " . $row["location"] . "\n";
          $response .= "   📝 " . $shortDetails . "\n\n";
        }
        $response .= "Would you like more details on any of these? Just ask me about the event name!";
      } else {
        $response = "There are no upcoming events currently scheduled. Check back later for new workshops and fests!";
      }
    } elseif (preg_match("/\b(how to register|how to join|register for|join event)\b/", $msg)) {
      $response = "To register for an event:\n1. Browse the **Events** section.\n2. Click on the event card to see details.\n3. Click the **Register** button.\n4. If it's a paid event, you'll need to upload payment proof in your **My Portal** section afterwards!";
    } else {
      // Try to extract event name if asking "about X"
      $searchTerm = "";
      if (preg_match("/(?:about|detail|tell me about|info on|what is)\s+(.+)/", $msg, $matches)) {
        $searchTerm = trim($matches[1]);
      }

      if ($searchTerm) {
        $stmt = mysqli_prepare($con, "SELECT event_title, event_datetime, location, details, event_price FROM events WHERE (event_title LIKE ? OR details LIKE ?) AND publish_status = 'approved' LIMIT 1");
        $likeTerm = "%$searchTerm%";
        mysqli_stmt_bind_param($stmt, "ss", $likeTerm, $likeTerm);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
          $response = "Here are the details for **" . $row["event_title"] . "**:\n";
          $response .= "📅 Date: " . date("M j, Y, g:i A", strtotime($row["event_datetime"])) . "\n";
          $response .= "📍 Venue: " . $row["location"] . "\n";
          $response .= "💰 Fee: Rs " . $row["event_price"] . "\n";
          $response .= "📝 Details: " . $row["details"];
        } else {
          $response = "I couldn't find an event matching '" . $searchTerm . "'. Try searching for 'upcoming events' to see what's new!";
        }
        mysqli_stmt_close($stmt);
      } else {
        $res = mysqli_query($con, "SELECT event_title, event_datetime FROM events WHERE publish_status = 'approved' AND event_datetime >= NOW() ORDER BY event_datetime ASC LIMIT 3");
        if (mysqli_num_rows($res) > 0) {
          $response = "Here are the top 3 upcoming events:\n";
          while ($row = mysqli_fetch_assoc($res)) {
            $response .= "📅 " . $row["event_title"] . " on " . date("M j, Y", strtotime($row["event_datetime"])) . "\n";
          }
          $response .= "\nYou can ask me for details about any of these!";
        } else {
          $response = "No upcoming events are listed at the moment. Stay tuned for updates!";
        }
      }
    }
  }
  // SEARCH COMMUNITY BULLETIN POSTS
  elseif (preg_match("/\b(post|bulletin|community|query|ask)\b/", $msg)) {
    $searchTerm = "";
    if (preg_match("/(?:post|bulletin|query|about)\s+(.+)/", $msg, $matches)) {
      $searchTerm = trim($matches[1]);
    }

    if ($searchTerm) {
      $stmt = mysqli_prepare($con, "SELECT author_name, content, created_at FROM community_posts WHERE content LIKE ? AND status = 'approved' ORDER BY created_at DESC LIMIT 2");
      $likeTerm = "%$searchTerm%";
      mysqli_stmt_bind_param($stmt, "s", $likeTerm);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      if (mysqli_num_rows($res) > 0) {
        $response = "I found some community posts about '" . $searchTerm . "':\n\n";
        while ($row = mysqli_fetch_assoc($res)) {
          $response .= "👤 **" . $row["author_name"] . "**:\n\"" . $row["content"] . "\"\n";
          $response .= "🕒 " . date("M j, Y", strtotime($row["created_at"])) . "\n\n";
        }
      } else {
        $response = "I couldn't find any community posts matching '" . $searchTerm . "'. Why not post your query in the Bulletin section?";
      }
      mysqli_stmt_close($stmt);
    } else {
      $response = "The Community Bulletin is where students share updates and ask queries. You can ask me to 'search posts about [topic]'!";
    }
  }
  elseif (preg_match("/\b(placement|package|job|salary|highest)\b/", $msg)) {
    $response = "BVRIT has an excellent placement record! 🚀\n" .
                "• Highest Package: ₹52 LPA (2023-24) or ₹46.38 LPA (2024-25).\n" .
                "• Top recruiters include Amazon, Microsoft, Adobe, and more.\n" .
                "• Our placement cell provides dedicated training and guidance for all students.";
  }
  elseif (preg_match("/\b(facility|hostel|gym|sports|amenities|cafeteria)\b/", $msg)) {
    $response = "The BVRIT Hyderabad campus offers top-tier facilities:\n" .
                "🏢 Hostels: Safe and comfortable girls' hostels on-campus.\n" .
                "📚 Library: Large collection with digital resources.\n" .
                "🏋️ Gym & Sports: Modern fitness center and multiple sports grounds.\n" .
                "☕ Cafeteria: Hygienic and variety-rich food options.\n" .
                "🏥 Health: Medical clinic available for students and staff.";
  }
  elseif (preg_match("/\b(about|who|founder|established|history|bv raju)\b/", $msg)) {
    $response = "BVRIT HYDERABAD College of Engineering for Women was established in 2012 by the Sri Vishnu Educational Society. " .
                "It was founded by Padma Bhushan Dr. B.V. Raju to empower women in technical education. " .
                "Our current Chairman is Sri K. V. Vishnu Raju.";
  }
  elseif (preg_match("/\b(admission|cutoff|eamcet|gate)\b/", $msg)) {
    $response = "Admission info:\n" .
                "• B.Tech: Based on TS EAMCET / JEE Main scores.\n" .
                "• M.Tech: Based on GATE / TS PGECET scores.\n" .
                "Keep an eye on the official JNTUH notifications for cut-offs!";
  }
  elseif (preg_match("/\b(dept|department|cse|it|ece|eee|mechanical|mech)\b/", $msg)) {
    if (strpos($msg, "cse") !== false) {
      $response = "The CSE Department is the hub of innovation! 💻 They focus on AI, ML, Data Science, and Software Engineering. They regularly organize hackathons and workshops.";
    } elseif (strpos($msg, "it") !== false) {
      $response = "The IT Department focuses on Information Systems, Cybersecurity, and Cloud Computing, preparing students for the modern tech landscape.";
    } elseif (strpos($msg, "ece") !== false) {
      $response = "The ECE Department covers Electronics, VLSI, and Communication Systems, with well-equipped labs for hands-on learning.";
    } else {
      $response = "BVRIT Hyderabad offers several core departments:\n• CSE (Computer Science)\n• IT (Information Technology)\n• ECE (Electronics & Communication)\n• EEE (Electrical & Electronics)\nAsk about a specific one for more details!";
    }
  }
  elseif (preg_match("/\b(dashboard|attendance|gpa|cgpa|deadline)\b/", $msg)) {
    $response = "You can view your real-time Attendance, GPA, and Deadlines on your Student Dashboard! Just click 'Dashboard' in the top navigation bar. If you're an admin, you'll see a 'User Directory' there too.";
  }
  elseif (preg_match("/\b(payment|pay|fee|cost|how much)\b/", $msg)) {
    $response = "For paid events, you can see the fee on the event card. After registering, go to **My Portal** to upload your payment proof (QR or Link). Once the admin approves it, your ticket will be generated!";
  }
  elseif (preg_match("/\b(ticket|qr code|entry|admit)\b/", $msg)) {
    $response = "Once your registration is confirmed by the admin, a QR ticket will be generated in your **My Portal** section. You can show this at the venue for entry!";
  }
  elseif (preg_match("/\b(notification|alert|notif)\b/", $msg)) {
    $response = "We have real-time notifications! You'll get an alert (heart icon) for message updates, registration status, and event broadcasts. Make sure to enable browser notifications when prompted!";
  }
  elseif (preg_match("/\b(help|portal|feature|how to)\b/", $msg)) {
    $response = "I can help you navigate the portal:\n" .
                "1. Events: Register for workshops and fests.\n" .
                "2. Bulletin: Post queries or updates for the campus community.\n" .
                "3. Chat: Connect with peers or start group discussions.\n" .
                "4. Dashboard: Track your academic performance.";
  }

  json_response([
    "ok" => true,
    "message" => $response,
    "sender" => "Campus AI",
    "created_at" => date("Y-m-d H:i:s")
  ]);
}

json_response(["ok" => false, "message" => "Invalid request"], 400);
?>
