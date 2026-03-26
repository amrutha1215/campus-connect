<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>BVRIT Hyderabad | Event Registration</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --navy:     #0a0f2e;
      --navy-mid: #111a45;
      --navy-card:#161f50;
      --accent:   #f5a623;
      --accent2:  #e8376b;
      --teal:     #00d4b4;
      --text:     #e8ecff;
      --muted:    #8490c8;
      --border:   rgba(255,255,255,0.08);
      --radius:   14px;
      --fs-xs:    0.72rem;
      --fs-sm:    0.85rem;
      --fs-base:  1rem;
      --fs-lg:    1.15rem;
      --fs-xl:    1.5rem;
      --fs-2xl:   2.2rem;
      --transition: 0.28s cubic-bezier(.4,0,.2,1);
    }

    html { scroll-behavior: smooth; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--navy);
      color: var(--text);
      min-height: 100vh;
      overflow-x: hidden;
    }

    body::before {
      content: '';
      position: fixed; inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
      pointer-events: none; z-index: 9999; opacity: 0.4;
    }

    /* ── NAVBAR ── */
    .site-header {
      position: fixed; top: 0; left: 0; right: 0; z-index: 900;
      padding: 0 2rem;
      backdrop-filter: blur(18px) saturate(160%);
      background: rgba(10,15,46,0.82);
      border-bottom: 1px solid var(--border);
      height: 64px; display: flex; align-items: center;
    }
    .navbar {
      width: 100%; max-width: 1280px; margin: 0 auto;
      display: flex; align-items: center; gap: 2rem;
    }
    .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0; }
    .brand-logo {
      width: 36px; height: 36px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      border-radius: 8px; display: grid; place-items: center;
      font-family: 'Syne', sans-serif; font-weight: 800; font-size: 0.85rem;
      color: #fff; letter-spacing: -0.5px;
      box-shadow: 0 0 18px rgba(245,166,35,0.4);
    }
    .brand-text { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.05rem; color: var(--text); letter-spacing: -0.3px; }
    .brand-sub  { font-size: var(--fs-xs); color: var(--accent); font-weight: 500; display: block; line-height: 1; margin-top: 1px; }
    .nav-links  { display: flex; list-style: none; gap: 0.2rem; margin-left: auto; }
    .nav-links a { text-decoration: none; color: var(--muted); font-size: var(--fs-sm); font-weight: 500; padding: 6px 14px; border-radius: 8px; transition: var(--transition); }
    .nav-links a:hover { color: var(--text); background: var(--border); }

    /* ── PAGE ── */
    .page-wrap { min-height: 100vh; padding-top: 64px; position: relative; display: flex; flex-direction: column; }

    .page-bg {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background:
        radial-gradient(ellipse 60% 50% at 10% 30%, rgba(245,166,35,0.10) 0%, transparent 60%),
        radial-gradient(ellipse 50% 60% at 90% 70%, rgba(232,55,107,0.09) 0%, transparent 55%),
        radial-gradient(ellipse 40% 50% at 55% 100%, rgba(0,212,180,0.07) 0%, transparent 50%);
    }
    .page-bg::after {
      content: ''; position: absolute; inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
      background-size: 60px 60px;
    }

    /* ── HERO ── */
    .hero-strip { position: relative; z-index: 1; padding: 56px 2rem 40px; text-align: center; }
    .breadcrumb {
      display: flex; align-items: center; justify-content: center; gap: 6px;
      font-size: var(--fs-xs); color: var(--muted); margin-bottom: 20px;
    }
    .breadcrumb a { color: var(--muted); text-decoration: none; transition: color var(--transition); }
    .breadcrumb a:hover { color: var(--accent); }
    .breadcrumb-sep { opacity: 0.4; }
    .hero-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(245,166,35,0.12); border: 1px solid rgba(245,166,35,0.3);
      border-radius: 100px; padding: 6px 16px;
      font-size: var(--fs-xs); font-weight: 600; color: var(--accent);
      letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 1.2rem;
    }
    .hero-badge .dot { width: 6px; height: 6px; background: var(--accent); border-radius: 50%; animation: pulse 1.8s ease-in-out infinite; }
    @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(0.7)} }
    .hero-strip h1 {
      font-family: 'Syne', sans-serif;
      font-size: clamp(2rem, 5vw, 3rem); font-weight: 800;
      letter-spacing: -1.5px; line-height: 1.08; margin-bottom: 12px;
    }
    .hero-strip h1 .highlight {
      background: linear-gradient(90deg, var(--accent), var(--accent2));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .hero-strip p { font-size: var(--fs-base); color: var(--muted); font-weight: 300; }

    /* ── COUNTDOWN ── */
  .countdown-wrap { position: relative; z-index: 1; display: none; justify-content: center; gap: 0; margin-bottom: 56px; }
  .countdown-wrap.active { display: flex; }
  .cd-card {
      background: var(--navy-card); border: 1px solid var(--border);
      padding: 16px 24px; text-align: center;
    }
    .cd-card:first-child { border-radius: 12px 0 0 12px; }
    .cd-card:last-child  { border-radius: 0 12px 12px 0; }
    .cd-card + .cd-card  { border-left: none; }
    .cd-num {
      font-family: 'Syne', sans-serif; font-size: 2.2rem; font-weight: 800;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1;
    }
    .cd-label { font-size: 0.65rem; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); margin-top: 5px; }

    /* ── FORM ── */
    .form-section { position: relative; z-index: 1; display: flex; justify-content: center; padding: 0 20px 80px; }
    .form-card { width: 100%; max-width: 540px; background: var(--navy-card); border: 1px solid var(--border); border-radius: 18px; overflow: hidden; }
    .card-top-bar { height: 4px; background: linear-gradient(90deg, var(--accent), var(--accent2)); }
    .card-body { padding: 2rem 2rem 0; }
    .card-body h2 { font-family: 'Syne', sans-serif; font-size: var(--fs-xl); font-weight: 700; margin-bottom: 4px; }
    .card-body .sub { font-size: var(--fs-sm); color: var(--muted); margin-bottom: 1.8rem; }

    .form-group { margin-bottom: 14px; }
    .form-group label { display: block; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px; color: var(--muted); text-transform: uppercase; margin-bottom: 7px; }
    .form-group input {
      width: 100%; background: rgba(255,255,255,0.04); border: 1px solid var(--border);
      border-radius: 10px; padding: 12px 16px;
      font-family: 'DM Sans', sans-serif; font-size: var(--fs-sm); color: var(--text);
      outline: none; transition: border-color var(--transition), background var(--transition);
    }
    .form-group input::placeholder { color: var(--muted); }
    .form-group input:focus { border-color: rgba(245,166,35,0.45); background: rgba(245,166,35,0.04); }
    .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

    .alert { display: none; padding: 12px 16px; border-radius: 10px; font-size: var(--fs-sm); margin-bottom: 16px; border: 1px solid rgba(232,55,107,0.3); background: rgba(232,55,107,0.1); color: #f87171; }
    .alert.show { display: block; }

    .card-footer-form { padding: 1.5rem 2rem 2rem; }
    .btn-submit {
      width: 100%; padding: 14px; font-family: 'DM Sans', sans-serif;
      font-size: var(--fs-sm); font-weight: 600; color: #fff;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      border: none; border-radius: 10px; cursor: pointer;
      position: relative; overflow: hidden;
      transition: transform var(--transition), box-shadow var(--transition), opacity var(--transition);
    }
    .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(245,166,35,0.28); }
    .btn-submit:active { transform: translateY(0); }
    .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

    /* ── RIPPLE ── */
    .ripple-effect { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.18); width: 4px; height: 4px; animation: rippleAnim 0.6s linear; pointer-events: none; }
    @keyframes rippleAnim { to { transform: scale(80); opacity: 0; } }

    /* ── TOASTS ── */
    #toastContainer {
      position: fixed; bottom: 30px; right: 30px; z-index: 9999;
      display: flex; flex-direction: column; gap: 10px;
    }
    .toast {
      background: var(--navy-mid);
      border: 1px solid var(--border);
      border-left: 4px solid var(--accent);
      color: var(--text);
      padding: 14px 24px;
      border-radius: 10px;
      font-size: var(--fs-sm);
      box-shadow: 0 10px 30px rgba(0,0,0,0.4);
      animation: toastSlideIn 0.3s cubic-bezier(.4,0,.2,1) both;
      min-width: 280px;
      max-width: 400px;
      display: flex; align-items: center; gap: 12px;
    }
    .toast.error { border-left-color: var(--accent2); }
    .toast.success { border-left-color: var(--teal); }
    @keyframes toastSlideIn {
      from { transform: translateX(100px); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    .toast.fade-out {
      animation: toastFadeOut 0.4s ease forwards;
    }
    @keyframes toastFadeOut {
      to { transform: translateY(10px); opacity: 0; }
    }

    /* ── SUCCESS ── */
    .success-card {
      display: none; width: 100%; max-width: 540px;
      background: var(--navy-card); border: 1px solid rgba(0,212,180,0.2);
      border-radius: 18px; overflow: hidden;
      animation: fadeUp 0.4s ease forwards;
    }
    @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    .success-bar { height: 4px; background: linear-gradient(90deg, var(--teal), #00c9ff); }
    .success-body { padding: 2rem; text-align: center; }
    .success-icon {
      width: 56px; height: 56px; border-radius: 14px;
      background: rgba(0,212,180,0.12); border: 1px solid rgba(0,212,180,0.3);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem; margin: 0 auto 20px;
    }

    /* ── PAYMENT CARD ── */
    .payment-card {
      display: none; width: 100%; max-width: 540px;
      background: var(--navy-card); border: 1px solid rgba(245,166,35,0.2);
      border-radius: 18px; overflow: hidden;
      animation: fadeUp 0.4s ease forwards;
    }
    .payment-bar { height: 4px; background: linear-gradient(90deg, var(--accent), var(--accent2)); }
    .payment-body { padding: 2rem; text-align: center; }
    .payment-icon {
      width: 56px; height: 56px; border-radius: 14px;
      background: rgba(245,166,35,0.12); border: 1px solid rgba(245,166,35,0.3);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem; margin: 0 auto 20px;
    }
    .payment-body h2 { font-family: 'Syne', sans-serif; font-size: var(--fs-xl); font-weight: 700; margin-bottom: 6px; }
    .payment-body .sub { font-size: var(--fs-sm); color: var(--muted); margin-bottom: 1.8rem; }
    
    .payment-details {
      background: rgba(255,255,255,0.03); border: 1px dashed var(--border);
      border-radius: 12px; padding: 20px; margin-bottom: 1.8rem;
    }
    .payment-qr-img { 
      width: 100%; max-width: 200px; height: 200px; 
      object-fit: contain; border-radius: 12px; 
      margin: 0 auto 15px; display: block; 
      background: #fff; padding: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .btn-link { 
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 20px; background: var(--accent); color: #fff;
      border-radius: 8px; text-decoration: none; font-size: var(--fs-sm); font-weight: 600;
      transition: var(--transition); margin-bottom: 15px;
    }
    .btn-link:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(245,166,35,0.3); }

    .upload-box {
      border: 2px dashed var(--border); border-radius: 12px; padding: 20px;
      cursor: pointer; transition: var(--transition); position: relative;
    }
    .upload-box:hover { border-color: var(--accent); background: rgba(245,166,35,0.03); }
    .upload-box input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .upload-icon { font-size: 1.5rem; color: var(--muted); margin-bottom: 8px; }
    .upload-text { font-size: var(--fs-xs); color: var(--muted); }
    .upload-filename { font-size: var(--fs-sm); color: var(--accent); font-weight: 600; margin-top: 5px; display: none; }

    .success-body h2 { font-family: 'Syne', sans-serif; font-size: var(--fs-xl); font-weight: 700; margin-bottom: 6px; }
    .success-body .sub { font-size: var(--fs-sm); color: var(--muted); margin-bottom: 1.8rem; }

    .qr-wrap { background: rgba(255,255,255,0.04); border: 1px solid var(--border); border-radius: 14px; padding: 20px; margin-bottom: 1.4rem; display: inline-block; }
    .qr-wrap img { border-radius: 8px; display: block; }

    .ticket-table { width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 1.4rem; text-align: left; }
    .ticket-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; font-size: var(--fs-xs); }
    .ticket-row + .ticket-row { border-top: 1px solid var(--border); }
    .ticket-key { color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; }
    .ticket-val { color: var(--text); font-weight: 500; }

    .btn-print { padding: 12px 28px; background: transparent; border: 1px solid var(--border); border-radius: 10px; color: var(--muted); font-family: 'DM Sans', sans-serif; font-size: var(--fs-sm); cursor: pointer; transition: var(--transition); }
    .btn-print:hover { border-color: var(--teal); color: var(--teal); }

    /* ── FOOTER ── */
    footer { position: relative; z-index: 1; border-top: 1px solid var(--border); padding: 22px 40px; display: flex; justify-content: space-between; align-items: center; font-size: var(--fs-xs); color: var(--muted); margin-top: auto; }

    /* ── SPINNER ── */
    .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.15); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; vertical-align: middle; margin-right: 6px; }
    @keyframes spin { to { transform: rotate(360deg); } }

    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--navy); }
    ::-webkit-scrollbar-thumb { background: var(--navy-card); border-radius: 3px; }

    @media (max-width: 600px) {
      .nav-links { display: none; }
      .hero-strip { padding: 48px 1.2rem 32px; }
      .categories-strip { padding: 0 1.2rem 36px; }
      .card-body { padding: 1.4rem 1.4rem 0; }
      .card-footer-form { padding: 1.2rem 1.4rem 1.6rem; }
      .success-body { padding: 1.4rem; }
      .row-2 { grid-template-columns: 1fr; }
      .cd-card { padding: 12px 16px; border-radius: 10px !important; border-left: 1px solid var(--border) !important; }
      .cd-num { font-size: 1.6rem; }
      footer { flex-direction: column; gap: 6px; text-align: center; padding: 18px 20px; }
    }
  </style>
</head>
<body>

<header class="site-header">
  <nav class="navbar">
    <a class="brand" href="index.php">
      <div class="brand-logo">BV</div>
      <div>
        <span class="brand-text">BVRIT Hyderabad</span>
        <span class="brand-sub">Campus Portal</span>
      </div>
    </a>
    <ul class="nav-links">
      <li><a href="index.php#events">Events</a></li>
      <li><a href="index.php#community">Bulletin</a></li>
      <li><a href="index.php#about">About</a></li>
    </ul>
  </nav>
</header>

<div class="page-wrap">
  <div class="page-bg"></div>

  <section class="hero-strip">
    <div class="breadcrumb">
      <a href="index.php">Home</a>
      <span class="breadcrumb-sep">›</span>
      <a href="index.php#events">Events</a>
      <span class="breadcrumb-sep">›</span>
      <span>Register</span>
    </div>
    <div class="hero-badge"><span class="dot"></span>Registration Open</div>
    <h1>Register for<br><span class="highlight" id="event_title_display">Event</span></h1>
    <p id="event_desc_display">Secure your spot for the most exciting event of the semester.</p>
    <div id="event_meta_display" style="margin-top: 15px; font-size: var(--fs-xs); color: var(--accent); display: flex; justify-content: center; gap: 20px;"></div>
  </section>

  <div class="countdown-wrap">
    <div class="cd-card"><div class="cd-num" id="cd-days">00</div><div class="cd-label">Days</div></div>
    <div class="cd-card"><div class="cd-num" id="cd-hours">00</div><div class="cd-label">Hours</div></div>
    <div class="cd-card"><div class="cd-num" id="cd-mins">00</div><div class="cd-label">Mins</div></div>
    <div class="cd-card"><div class="cd-num" id="cd-secs">00</div><div class="cd-label">Secs</div></div>
  </div>

  <section class="form-section">

    <div class="form-card" id="form_card">
      <div class="card-top-bar"></div>
      <div class="card-body">
        <h2>Complete Registration</h2>
        <p class="sub">Fill in your details below to confirm your seat.</p>
        <div class="alert" id="error_msg"></div>
        <form id="signup_form" onsubmit="return false">
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" placeholder="Your full name" required />
          </div>
          <div class="row-2">
            <div class="form-group">
              <label>Mobile Number</label>
              <input type="text" name="mobile" placeholder="10-digit number" pattern="[0-9]{10}" title="Enter a valid 10-digit mobile number" required />
            </div>
            <div class="form-group">
              <label>Branch / Dept</label>
              <input type="text" name="branch" placeholder="e.g. CSE, ECE" required />
            </div>
          </div>
          <div class="form-group">
            <label>College Name</label>
            <input type="text" name="college" placeholder="Your college / institution" required />
          </div>
          <input type="hidden" name="event_id" value="" />
        </form>
      </div>
      <div class="card-footer-form">
        <button class="btn-submit" id="register_btn" type="button">Register Now →</button>
      </div>
    </div>

    <div class="success-card" id="registration_success">
      <div class="success-bar"></div>
      <div class="success-body">
        <div class="success-icon">✓</div>
        <h2 id="success_title">You're Registered!</h2>
        <p class="sub" id="success_sub">Show this QR code at the event entry gate.</p>
        <div id="success_qr_wrap" class="qr-wrap">
          <div id="qrcode_container"></div>
        </div>
        <div class="ticket-table" id="ticket_details"></div>
        <button class="btn-print" id="print_btn" onclick="window.print()">🖨 &nbsp;Print Ticket</button>
      </div>
    </div>

    <div class="payment-card" id="payment_card">
      <div class="payment-bar"></div>
      <div class="payment-body">
        <div class="payment-icon">💰</div>
        <h2>Payment Required</h2>
        <p class="sub">Please complete the payment to confirm your registration.</p>
        
        <div class="payment-details" id="payment_details_box">
          <div id="payment_qr_container" style="display:none;">
            <p style="font-size: 0.75rem; color: var(--muted); margin-bottom: 12px; font-weight: 600;">SCAN TO PAY</p>
            <img id="payment_qr_img" src="" class="payment-qr-img">
          </div>
          <div id="payment_link_container" style="display:none;">
            <p style="font-size: 0.75rem; color: var(--muted); margin-bottom: 12px; font-weight: 600;">PAY VIA LINK</p>
            <a id="payment_link_url" href="#" target="_blank" class="btn-link">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
              Pay Now
            </a>
          </div>
          <div id="payment_fallback" style="display:block; color:var(--muted); font-size:var(--fs-xs);">
            Please contact the event organizer for payment details if not shown above.
          </div>
        </div>

        <div style="text-align: left; margin-bottom: 1.5rem;">
          <label style="display: block; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">UPLOAD PAYMENT PROOF</label>
          <div class="upload-box" id="dropZone">
            <div class="upload-icon">📸</div>
            <div class="upload-text">Select screenshot or drag & drop</div>
            <div class="upload-filename" id="fileName">No file chosen</div>
            <input type="file" id="proof_input" accept="image/*" onchange="handleFileSelect(this)">
          </div>
        </div>

        <button class="btn-submit" id="submit_proof_btn">Submit Payment Proof →</button>
      </div>
    </div>

  </section>

  <footer>
    <span>© 2025 BVRIT Hyderabad College of Engineering</span>
    <span>Campus Portal · CSE Department</span>
  </footer>
</div>

  <div id="toastContainer"></div>

<script>
  function showToast(msg, type = 'info', duration = 4000) {
    const container = document.getElementById("toastContainer");
    const toast = document.createElement("div");
    toast.className = `toast ${type}`;
    let icon = 'ℹ️';
    if (type === 'success') icon = '✅';
    if (type === 'error') icon = '⚠️';
    toast.innerHTML = `<span>${icon}</span> <span>${msg}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
      toast.classList.add("fade-out");
      setTimeout(() => toast.remove(), 400);
    }, duration);
  }

  // Countdown Logic
  let countdownTarget = null;
  function updateCountdown() {
    if (!countdownTarget) return;
    const diff = countdownTarget - new Date();
    if (diff <= 0) {
      document.querySelector('.countdown-wrap').classList.remove('active');
      return;
    }
    document.querySelector('.countdown-wrap').classList.add('active');
    const pad = n => String(Math.floor(n)).padStart(2, '0');
    document.getElementById('cd-days').textContent  = pad(diff / 86400000);
    document.getElementById('cd-hours').textContent = pad((diff % 86400000) / 3600000);
    document.getElementById('cd-mins').textContent  = pad((diff % 3600000) / 60000);
    document.getElementById('cd-secs').textContent  = pad((diff % 60000) / 1000);
  }
  setInterval(updateCountdown, 1000);

  // Ripple
  function addRipple(el) {
    el.addEventListener('click', function(e) {
      const r = document.createElement('span');
      r.className = 'ripple-effect';
      const rect = this.getBoundingClientRect();
      r.style.left = (e.clientX - rect.left - 2) + 'px';
      r.style.top  = (e.clientY - rect.top  - 2) + 'px';
      this.appendChild(r);
      setTimeout(() => r.remove(), 650);
    });
  }
  addRipple(document.getElementById('register_btn'));

  // Pre-fill event_id from URL param
  const eid = parseInt(new URLSearchParams(window.location.search).get('event_id') || '0', 10);
  if (eid) {
    document.querySelector('input[name="event_id"]').value = eid;
    fetch(`backend/portal_api.php?action=event_details&id=${eid}`)
      .then(res => res.json())
      .then(data => {
        if (data.ok) {
          const e = data.event;
          document.getElementById('event_title_display').textContent = e.title;
          document.getElementById('event_desc_display').textContent = e.details;
          document.getElementById('event_meta_display').innerHTML = `
            <span>📅 ${new Date(e.datetime).toLocaleDateString()}</span>
            <span>📍 ${e.location}</span>
            <span>💰 ${e.fee === 0 ? 'Free' : '₹' + e.fee}</span>
          `;
          
          if (e.datetime) {
            countdownTarget = new Date(e.datetime);
            updateCountdown();
          }

          // Check if full
          if (e.currentRegistrations >= e.teamSize) {
            document.getElementById('register_btn').innerHTML = 'Join Waitlist →';
            document.getElementById('register_btn').style.background = 'linear-gradient(135deg, #e8376b 0%, #f5a623 100%)';
            const info = document.createElement('p');
            info.style.fontSize = '0.7rem';
            info.style.color = 'var(--accent2)';
            info.style.marginTop = '10px';
            info.style.textAlign = 'center';
            info.textContent = 'This event is currently at full capacity. You will be added to the waitlist.';
            document.getElementById('register_btn').parentElement.appendChild(info);
          }
        }
      });
  }

  // Check auth and pre-fill
  let userEmail = '';
  fetch('backend/portal_api.php?action=me')
    .then(res => res.json())
    .then(data => {
      if (data.ok) {
        document.querySelector('input[name="full_name"]').value = data.user.name;
        userEmail = data.user.email || '';
      } else {
        // Not logged in — redirect to login on main page
        showToast("Please login first. Redirecting...", "error");
        setTimeout(() => {
          window.location.href = "index.php";
        }, 2000);
      }
    });

  // Form submit
  document.getElementById('register_btn').addEventListener('click', async function() {
    const errorMsg = document.getElementById('error_msg');
    errorMsg.classList.remove('show');

    const formData = new FormData(document.getElementById('signup_form'));
    const data = {};
    formData.forEach((v, k) => data[k] = v.trim());

    if (!data.event_id || data.event_id === "0") {
      errorMsg.textContent = 'Invalid event selected. Please return to the events page.';
      errorMsg.classList.add('show'); return;
    }

    if (!data.full_name || !data.mobile || !data.branch || !data.college) {
      errorMsg.textContent = 'Please fill in all required fields.';
      errorMsg.classList.add('show'); return;
    }
    if (!/^[0-9]{10}$/.test(data.mobile)) {
      errorMsg.textContent = 'Please enter a valid 10-digit mobile number.';
      errorMsg.classList.add('show'); return;
    }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Processing…';

    try {
      const res = await fetch('backend/portal_api.php?action=register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      const result = await res.json();
      
      if (result.ok) {
        if (result.status === 'pending_payment') {
          // Show Payment Card instead of redirecting
          document.getElementById('form_card').style.display = 'none';
          document.getElementById('payment_card').style.display = 'block';
          
          if (result.payment_qr || result.payment_link) {
            document.getElementById('payment_fallback').style.display = 'none';
          }
          
          if (result.payment_qr) {
            document.getElementById('payment_qr_container').style.display = 'block';
            document.getElementById('payment_fallback').style.display = 'none';
            
            // Clean path resolution
            let qrUrl = result.payment_qr;
            if (!qrUrl.startsWith('http')) {
              // Ensure it points to root images folder
              qrUrl = qrUrl.startsWith('images/') ? qrUrl : 'images/' + qrUrl;
              qrUrl = './' + qrUrl;
            }
            
            const img = document.getElementById('payment_qr_img');
            img.src = qrUrl;
            img.onerror = function() {
              this.style.display = 'none';
              document.getElementById('payment_fallback').style.display = 'block';
              document.getElementById('payment_fallback').innerHTML = 
                `<div style="color:var(--red); font-size:11px; margin-top:10px;">
                  ⚠️ Error loading QR code. Please contact the event organizer.
                </div>`;
            };
          }
          if (result.payment_link) {
            document.getElementById('payment_link_container').style.display = 'block';
            document.getElementById('payment_link_url').href = result.payment_link;
          }
          
          // Store reg_id for proof upload
          document.getElementById('submit_proof_btn').dataset.regId = result.reg_id;
          return;
        }

        if (result.status === 'waitlisted') {
          showToast("You've been added to the waitlist.", "info");
          setTimeout(() => {
            window.location.href = "index.php#myportal";
          }, 1500);
          return;
        }

        document.getElementById('form_card').style.display = 'none';
        const success = document.getElementById('registration_success');
        success.style.display = 'block';

        const qrData = `BVRIT-Campus|EventID:${data.event_id}|Name:${data.full_name}|Email:${userEmail}`;
        const qrUrl  = `https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=${encodeURIComponent(qrData)}&bgcolor=161f50&color=f5a623&margin=0`;
        document.getElementById('qrcode_container').innerHTML =
          `<img src="${qrUrl}" alt="QR Code" style="width:160px;height:160px;border-radius:6px;display:block;">`;

        document.getElementById('ticket_details').innerHTML = [
          ['Event', document.getElementById('event_title_display').textContent],
          ['Name', data.full_name], ['Email', userEmail],
          ['Mobile', data.mobile],  ['College', data.college], ['Branch', data.branch]
        ].map(([k,v]) =>
          `<div class="ticket-row"><span class="ticket-key">${k}</span><span class="ticket-val">${v}</span></div>`
        ).join('');

        if ('Notification' in window && Notification.permission === 'granted') {
          new Notification('BVRIT Campus Portal — Registered!', { body: `Successfully registered for ${document.getElementById('event_title_display').textContent}.` });
        }
      } else {
        errorMsg.textContent = result.message || 'Registration failed.';
        errorMsg.classList.add('show');
        btn.disabled = false;
        btn.innerHTML = 'Register Now →';
      }
    } catch (err) {
      errorMsg.textContent = 'Network error. Please try again.';
      errorMsg.classList.add('show');
      btn.disabled = false;
      btn.innerHTML = 'Register Now →';
    }
  });

  function handleFileSelect(input) {
    const fileName = input.files[0] ? input.files[0].name : "Choose a file or drag it here";
    const fileNameDisplay = document.getElementById("fileName");
    fileNameDisplay.textContent = fileName;
    fileNameDisplay.style.display = "block";
    document.querySelector(".upload-text").style.display = "none";
  }

  document.getElementById('submit_proof_btn').addEventListener('click', async function() {
    const regId = this.dataset.regId;
    const file = document.getElementById('proof_input').files[0];
    
    if (!file) {
      showToast("Please upload payment proof", "error");
      return;
    }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Uploading…';

    const formData = new FormData();
    formData.append('reg_id', regId);
    formData.append('proof', file);
    formData.append('csrf', window.PORTAL_CSRF_TOKEN);

    try {
      const res = await fetch('backend/portal_api.php?action=upload_payment_proof', {
        method: 'POST',
        body: formData
      });
      const result = await res.json();
      
      if (result.ok) {
        document.getElementById('payment_card').style.display = 'none';
        const success = document.getElementById('registration_success');
        
        // Customize success message for pending approval
        document.getElementById('success_title').textContent = "Proof Submitted!";
        document.getElementById('success_sub').textContent = "Your payment is being verified. You will be notified once confirmed.";
        document.getElementById('success_qr_wrap').style.display = 'none';
        document.getElementById('print_btn').style.display = 'none';
        
        success.style.display = 'block';
        
        // Show ticket info anyway
        const formDataObj = new FormData(document.getElementById('signup_form'));
        document.getElementById('ticket_details').innerHTML = [
          ['Event', document.getElementById('event_title_display').textContent],
          ['Status', 'AWAITING CONFIRMATION'],
          ['Name', formDataObj.get('full_name')],
          ['Mobile', formDataObj.get('mobile')],
          ['College', formDataObj.get('college')],
          ['Branch', formDataObj.get('branch')]
        ].map(([k,v]) =>
          `<div class="ticket-row"><span class="ticket-key">${k}</span><span class="ticket-val" style="${k==='Status'?'color:var(--accent);font-weight:700':''}">${v}</span></div>`
        ).join('');

        showToast("Payment proof submitted!", "success");
      } else {
        showToast(result.message || "Upload failed", "error");
        btn.disabled = false;
        btn.innerHTML = 'Submit Payment Proof →';
      }
    } catch (err) {
      showToast("Network error during upload", "error");
      btn.disabled = false;
      btn.innerHTML = 'Submit Payment Proof →';
    }
  });
</script>
</body>
</html>