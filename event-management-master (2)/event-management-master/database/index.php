<?php
session_start();
if (empty($_SESSION["portal_csrf"])) {
  $_SESSION["portal_csrf"] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BVRIT Hyderabad | Campus Portal</title>
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
      --glow:     rgba(245,166,35,0.18);
      --radius:   14px;
      --fs-xs:    0.72rem;
      --fs-sm:    0.85rem;
      --fs-base:  1rem;
      --fs-lg:    1.15rem;
      --fs-xl:    1.5rem;
      --fs-2xl:   2.2rem;
      --fs-3xl:   3.4rem;
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

    /* ── NOISE GRAIN OVERLAY ── */
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
      height: 64px;
      display: flex; align-items: center;
    }
    .navbar {
      width: 100%; max-width: 1280px; margin: 0 auto;
      display: flex; align-items: center; justify-content: space-between;
    }
    .brand {
      display: flex; align-items: center; gap: 10px;
      text-decoration: none; flex-shrink: 0;
    }
    .brand-logo {
      width: 36px; height: 36px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      border-radius: 8px;
      display: grid; place-items: center;
      font-family: 'Syne', sans-serif;
      font-weight: 800; font-size: 0.85rem;
      color: #fff; letter-spacing: -0.5px;
      box-shadow: 0 0 18px rgba(245,166,35,0.4);
    }
    .brand-text {
      font-family: 'Syne', sans-serif;
      font-weight: 700; font-size: 1.05rem;
      color: var(--text); letter-spacing: -0.3px;
    }
    .brand-sub {
      font-size: var(--fs-xs);
      color: var(--accent);
      font-weight: 500;
      display: block;
      line-height: 1;
      margin-top: 1px;
    }
    .nav-links {
      display: flex; list-style: none; gap: 0.2rem; margin-left: auto;
      align-items: center;
    }
    .nav-links li {
      display: flex;
      align-items: center;
    }
    .nav-links a {
      text-decoration: none;
      color: var(--muted);
      font-size: var(--fs-sm);
      font-weight: 500;
      padding: 8px 14px;
      border-radius: 8px;
      transition: var(--transition);
      display: flex;
      align-items: center;
      line-height: 1.2;
    }
    .nav-links a:hover { color: var(--text); background: var(--border); }
    
    #userContainer {
      display: flex;
      align-items: center;
      gap: 12px;
      padding-left: 10px;
    }

    .notif-bell {
      position: relative; cursor: pointer; color: var(--muted);
      transition: var(--transition);
      display: flex; 
      align-items: center; 
      justify-content: center;
      padding: 8px;
      border-radius: 8px;
    }
    .notif-bell:hover { color: var(--accent); background: var(--border); }
    .notif-bell svg { display: block; }
    .notif-badge {
      position: absolute; top: -5px; right: -8px;
      background: var(--accent2); color: #fff; font-size: 10px;
      width: 16px; height: 16px; border-radius: 50%;
      display: none; place-items: center;
    }
    .notif-dropdown {
      position: absolute; top: 100%; right: 0; width: 320px;
      background: var(--navy-mid); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 1rem; display: none;
      box-shadow: 0 10px 40px rgba(0,0,0,0.4); z-index: 1000;
    }
    .notif-dropdown.open { display: block; }
    .notif-item {
      padding: 0.8rem; border-bottom: 1px solid var(--border);
      font-size: var(--fs-xs); color: var(--muted);
    }
    .notif-item:last-child { border: none; }
    .notif-item strong { color: var(--text); display: block; margin-bottom: 4px; }
    .notif-item.unread { border-left: 3px solid var(--accent); background: rgba(245,166,35,0.05); }

    .dashboard-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem; margin-top: 2rem;
    }
    .dash-card {
      background: var(--navy-card); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 1.5rem;
      transition: var(--transition);
    }
    .dash-card:hover { transform: translateY(-5px); border-color: rgba(245,166,35,0.2); }
    .dash-card h4 { font-family: 'Syne', sans-serif; color: var(--accent); margin-bottom: 1rem; font-size: 1rem; }
    .metric-val { font-size: 2.2rem; font-weight: 800; color: var(--text); }
    .metric-label { font-size: var(--fs-xs); color: var(--muted); margin-top: 4px; }
    .deadline-list, .resource-list { list-style: none; margin-top: 1rem; }
    .deadline-item, .resource-item {
      display: flex; justify-content: space-between; align-items: center;
      padding: 0.8rem 0; border-bottom: 1px solid var(--border);
    }
    .deadline-item:last-child, .resource-item:last-child { border: none; }
    .deadline-info span { font-size: var(--fs-xs); color: var(--muted); }
    .days-left { background: rgba(232,55,107,0.1); color: var(--accent2); padding: 2px 8px; border-radius: 4px; font-size: 10px; }
    .res-link { color: var(--teal); text-decoration: none; font-size: var(--fs-sm); }
    .res-link:hover { text-decoration: underline; }

    .feedback-stars { display: flex; gap: 8px; margin: 1rem 0; }
    .star { cursor: pointer; font-size: 1.5rem; color: var(--muted); transition: var(--transition); }
    .star.active { color: var(--accent); }

    /* ── CHAT UI ── */
    .chat-fab {
      position: fixed; bottom: 2rem; right: 2rem; z-index: 1000;
      width: 56px; height: 56px; border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #fff; display: grid; place-items: center; cursor: pointer;
      box-shadow: 0 8px 32px rgba(245,166,35,0.4);
      transition: var(--transition);
    }
    .chat-fab:hover { transform: scale(1.1) rotate(5deg); }
    .chat-fab svg { width: 24px; height: 24px; }

    .chat-drawer {
      position: fixed; top: 0; right: -400px; width: 380px; height: 100vh;
      background: var(--navy-mid); border-left: 1px solid var(--border);
      z-index: 1100; transition: 0.4s cubic-bezier(.4,0,.2,1);
      display: flex; flex-direction: column;
      box-shadow: -10px 0 40px rgba(0,0,0,0.5);
    }
    .chat-drawer.open { right: 0; }
    .chat-header {
      padding: 1.5rem; border-bottom: 1px solid var(--border);
      display: flex; justify-content: space-between; align-items: center;
    }
    .chat-body { flex: 1; overflow-y: auto; padding: 1rem; }
    .chat-list-item {
      padding: 1rem; border-radius: 12px; margin-bottom: 0.5rem;
      cursor: pointer; transition: var(--transition); border: 1px solid transparent;
      display: flex; gap: 12px; align-items: center;
    }
    .chat-list-item:hover { background: var(--border); }
    .chat-list-item.active { background: var(--border); border-color: var(--accent); }
    .chat-avatar {
      width: 40px; height: 40px; border-radius: 10px;
      background: var(--navy-card); display: grid; place-items: center;
      font-weight: 700; color: var(--accent);
    }
    .chat-info { flex: 1; overflow: hidden; }
    .chat-info h4 { font-size: 0.9rem; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-info p { font-size: 0.75rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .message-pane { display: none; flex-direction: column; height: 100%; }
    .message-pane.active { display: flex; }
    .message-list { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 1rem; }
    .message { max-width: 80%; padding: 0.8rem 1rem; border-radius: 14px; position: relative; font-size: 0.85rem; }
    .message.sent { align-self: flex-end; background: var(--accent); color: #fff; border-bottom-right-radius: 2px; }
    .message.received { align-self: flex-start; background: var(--navy-card); color: var(--text); border-bottom-left-radius: 2px; }
    .msg-meta { font-size: 0.65rem; opacity: 0.7; margin-top: 4px; display: block; }
    
    .chat-input-area { padding: 1rem; border-top: 1px solid var(--border); display: flex; gap: 8px; align-items: center; }
    .chat-input-area input { flex: 1; background: var(--navy-card); border: 1px solid var(--border); padding: 8px 14px; border-radius: 10px; color: var(--text); outline: none; }
    .chat-input-area button { background: none; border: none; color: var(--accent); cursor: pointer; display: grid; place-items: center; }

    .group-invite-badge { background: rgba(0,212,180,0.1); color: var(--teal); padding: 2px 6px; border-radius: 4px; font-size: 0.6rem; margin-top: 4px; display: inline-block; }

    .menu-toggle { display: none; }

    /* ── HERO ── */
    .hero {
      position: relative;
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
      padding: 80px 2rem 4rem;
    }
    .hero-bg {
      position: absolute; inset: 0;
      background: var(--navy);
    }
    .hero-video {
      position: absolute;
      top: 50%;
      left: 50%;
      min-width: 100%;
      min-height: 100%;
      width: auto;
      height: auto;
      z-index: 0;
      transform: translate(-50%, -50%);
      object-fit: cover;
      opacity: 0.4;
    }
    .hero-overlay {
      position: absolute;
      inset: 0;
      background: 
        radial-gradient(ellipse 80% 60% at 20% 50%, rgba(245,166,35,0.12) 0%, transparent 60%),
        radial-gradient(ellipse 60% 70% at 80% 30%, rgba(232,55,107,0.10) 0%, transparent 55%),
        radial-gradient(ellipse 50% 60% at 60% 90%, rgba(0,212,180,0.08) 0%, transparent 50%);
      z-index: 1;
    }
    /* Grid lines */
    .hero-overlay::after {
      content: '';
      position: absolute; inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
      background-size: 60px 60px;
    }
    .hero-content {
      position: relative; z-index: 2;
      max-width: 820px; text-align: center;
    }
    .hero-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(245,166,35,0.12);
      border: 1px solid rgba(245,166,35,0.3);
      border-radius: 100px;
      padding: 6px 16px;
      font-size: var(--fs-xs);
      font-weight: 600;
      color: var(--accent);
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 1.6rem;
      animation: fadeDown 0.6s ease both;
    }
    .hero-badge .dot {
      width: 6px; height: 6px;
      background: var(--accent);
      border-radius: 50%;
      animation: pulse 1.8s ease-in-out infinite;
    }
    @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(0.7)} }
    @keyframes fadeDown { from{opacity:0;transform:translateY(-16px)} to{opacity:1;transform:translateY(0)} }
    @keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }

    .hero-title {
      font-family: 'Syne', sans-serif;
      font-size: clamp(2.4rem, 6vw, var(--fs-3xl));
      font-weight: 800;
      line-height: 1.08;
      letter-spacing: -1.5px;
      margin-bottom: 1.2rem;
      animation: fadeUp 0.7s 0.1s ease both;
    }
    .hero-title .highlight {
      background: linear-gradient(90deg, var(--accent), var(--accent2));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .hero-tagline {
      color: var(--muted);
      font-size: var(--fs-lg);
      font-weight: 300;
      max-width: 520px;
      margin: 0 auto 2.4rem;
      line-height: 1.65;
      animation: fadeUp 0.7s 0.2s ease both;
    }
    .hero-search {
      display: flex; gap: 0;
      max-width: 520px; margin: 0 auto 2.5rem;
      background: rgba(255,255,255,0.06);
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 0 40px rgba(245,166,35,0.08);
      animation: fadeUp 0.7s 0.3s ease both;
      transition: border-color var(--transition), box-shadow var(--transition);
    }
    .hero-search:focus-within {
      border-color: rgba(245,166,35,0.4);
      box-shadow: 0 0 40px rgba(245,166,35,0.18);
    }
    .hero-search input {
      flex: 1; background: transparent; border: none; outline: none;
      padding: 14px 18px;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: var(--fs-base);
    }
    .hero-search input::placeholder { color: var(--muted); }
    .hero-stats {
      display: flex; gap: 2.5rem; justify-content: center;
      animation: fadeIn 0.8s 0.45s ease both;
    }
    .stat { text-align: center; }
    .stat-num {
      font-family: 'Syne', sans-serif;
      font-size: var(--fs-xl);
      font-weight: 700;
      color: var(--text);
    }
    .stat-num span { color: var(--accent); }
    .stat-label { font-size: var(--fs-xs); color: var(--muted); font-weight: 500; margin-top: 2px; }

    /* ── SECTION SHELL ── */
    .section-shell {
      max-width: 1280px; margin: 0 auto;
      padding: 5rem 2rem;
    }
    .section-header {
      margin-bottom: 2.5rem;
    }
    .section-header h2 {
      font-family: 'Syne', sans-serif;
      font-size: var(--fs-2xl);
      font-weight: 800;
      letter-spacing: -0.8px;
      margin-bottom: 0.4rem;
    }
    .section-header p { color: var(--muted); font-size: var(--fs-base); }

    /* ── TOPBAR ── */
    .community-topbar {
      display: flex; justify-content: flex-end;
      margin-bottom: 1.5rem;
    }

    /* ── BUTTON ── */
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #fff; border: none; cursor: pointer;
      padding: 10px 22px;
      border-radius: 10px;
      font-family: 'DM Sans', sans-serif;
      font-size: var(--fs-sm);
      font-weight: 600;
      letter-spacing: 0.02em;
      transition: var(--transition);
      position: relative; overflow: hidden;
    }
    .btn:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(245,166,35,0.3); }
    .btn:active { transform: translateY(0); }
    .btn-ghost {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--muted);
    }
    .btn-ghost:hover { border-color: rgba(255,255,255,0.2); color: var(--text); box-shadow: none; }

    /* ── FILTERS ── */
    .filter-wrap {
      display: flex; flex-direction: column; gap: 1.2rem;
      margin-bottom: 2.5rem;
    }
    .filter-controls {
      display: flex; flex-wrap: wrap; gap: 1rem; align-items: center;
      width: 100%;
    }
    .pill-switch {
      display: flex; gap: 8px; flex-wrap: wrap;
      align-items: center;
    }
    .pill-label {
      font-size: var(--fs-xs);
      color: var(--muted);
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-right: 4px;
      min-width: 80px;
    }
    .pill {
      background: rgba(255,255,255,0.03);
      border: 1px solid var(--border);
      color: var(--muted);
      padding: 6px 16px;
      border-radius: 100px;
      font-family: 'DM Sans', sans-serif;
      font-size: var(--fs-xs);
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
      white-space: nowrap;
    }
    .pill:hover { border-color: rgba(245,166,35,0.4); color: var(--accent); }
    .pill.active {
      background: rgba(245,166,35,0.15);
      border-color: var(--accent);
      color: var(--accent);
    }
    .sort-select {
      display: flex; align-items: center; gap: 8px;
      margin-left: auto;
    }
    .sort-select label { font-size: var(--fs-xs); color: var(--muted); }
    .sort-select select {
      background: var(--navy-card);
      border: 1px solid var(--border);
      color: var(--text);
      padding: 6px 12px;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: var(--fs-xs);
      outline: none;
      cursor: pointer;
    }

    /* ── CARDS GRID ── */
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem;
    }
    .event-card {
      background: var(--navy-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      cursor: pointer;
      transition: var(--transition);
      position: relative;
    }
    .event-card:hover {
      transform: translateY(-4px);
      border-color: rgba(245,166,35,0.3);
      box-shadow: 0 16px 48px rgba(0,0,0,0.35), 0 0 0 1px rgba(245,166,35,0.1);
    }
    .card-announcement-bell {
      position: absolute;
      top: 12px;
      right: 12px;
      z-index: 10;
      width: 32px;
      height: 32px;
      background: rgba(10,15,46,0.6);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(245,166,35,0.3);
      border-radius: 50%;
      display: grid;
      place-items: center;
      color: var(--accent);
      cursor: pointer;
      transition: var(--transition);
      animation: bellShake 2s infinite ease-in-out;
    }
    .card-announcement-bell:hover {
      background: var(--accent);
      color: #fff;
      transform: scale(1.1);
      animation: none;
    }
    @keyframes bellShake {
      0%, 100% { transform: rotate(0); }
      10%, 30% { transform: rotate(15deg); }
      20%, 40% { transform: rotate(-15deg); }
      50% { transform: rotate(0); }
    }
    
    .announcement-popup {
      position: fixed;
      z-index: 2000;
      background: var(--navy-mid);
      border: 1px solid var(--accent);
      border-radius: 12px;
      padding: 1.2rem;
      width: 280px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.5);
      display: none;
      animation: scaleIn 0.2s ease;
    }
    .announcement-popup::before {
      content: '';
      position: absolute;
      top: -6px; right: 20px;
      width: 12px; height: 12px;
      background: var(--navy-mid);
      border-top: 1px solid var(--accent);
      border-left: 1px solid var(--accent);
      transform: rotate(45deg);
    }
    .announcement-popup-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 8px;
      color: var(--accent);
      font-family: 'Syne', sans-serif;
      font-weight: 700;
      font-size: 0.85rem;
    }
    .announcement-popup-body {
      font-size: 0.8rem;
      color: var(--text);
      line-height: 1.5;
    }
    .card-color-bar {
      height: 4px;
      background: linear-gradient(90deg, var(--accent), var(--accent2));
    }
    .card-color-bar.sports { background: linear-gradient(90deg, var(--teal), #0099ff); }
    .card-color-bar.academic { background: linear-gradient(90deg, #7c6bff, #b06bff); }
    .card-body { padding: 1.2rem 1.4rem; }
    .card-tags {
      display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 0.8rem;
    }
    .tag {
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      padding: 3px 9px;
      border-radius: 100px;
    }
    .tag-category { background: rgba(245,166,35,0.15); color: var(--accent); }
    .tag-dept { background: rgba(0,212,180,0.12); color: var(--teal); }
    .tag-sports { background: rgba(0,153,255,0.12); color: #5bb8ff; }
    .tag-academic { background: rgba(124,107,255,0.15); color: #a98dff; }
    .card-title {
      font-family: 'Syne', sans-serif;
      font-size: 1.05rem;
      font-weight: 700;
      margin-bottom: 0.6rem;
      line-height: 1.3;
    }
    .card-meta {
      display: flex; flex-direction: column; gap: 4px;
      margin-bottom: 1rem;
    }
    .card-meta-row {
      display: flex; align-items: center; gap: 6px;
      font-size: var(--fs-xs); color: var(--muted);
    }
    .card-meta-row svg { flex-shrink: 0; opacity: 0.7; }
    .card-footer {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.8rem 1.4rem;
      border-top: 1px solid var(--border);
    }
    .card-fee {
      font-family: 'Syne', sans-serif;
      font-size: var(--fs-sm);
      font-weight: 700;
      color: var(--accent);
    }
    .card-fee.free { color: var(--teal); }
    .card-team {
      font-size: var(--fs-xs); color: var(--muted);
      display: flex; align-items: center; gap: 5px;
    }

    /* ── COMMUNITY GRID ── */
    .community-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1.2rem;
    }
    .post-card {
      background: var(--navy-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.3rem 1.4rem;
      transition: var(--transition);
    }
    .post-card:hover {
      border-color: rgba(255,255,255,0.14);
      box-shadow: 0 8px 32px rgba(0,0,0,0.25);
    }
    .post-header {
      display: flex; align-items: center; gap: 10px; margin-bottom: 0.8rem;
    }
    .post-avatar {
      width: 36px; height: 36px; border-radius: 10px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      display: grid; place-items: center;
      font-family: 'Syne', sans-serif;
      font-weight: 700; font-size: 0.8rem; color: #fff;
      flex-shrink: 0;
    }
    .post-meta { font-size: var(--fs-xs); color: var(--muted); margin-top: 2px; }
    .post-author { font-weight: 600; font-size: var(--fs-sm); }
    .post-content { font-size: var(--fs-sm); color: var(--muted); line-height: 1.6; }
    .post-footer {
      display: flex; justify-content: space-between; align-items: center;
      margin-top: 1rem; padding-top: 0.8rem;
      border-top: 1px solid rgba(255,255,255,0.05);
    }
    .post-time { font-size: 0.68rem; color: var(--muted); }
    .post-like-btn {
      background: rgba(255,255,255,0.05);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--muted);
      font-size: 0.72rem;
      padding: 4px 10px;
      cursor: pointer;
      display: flex; align-items: center;
      transition: var(--transition);
    }
    .post-like-btn:hover {
      background: rgba(255,255,255,0.1);
      color: var(--accent2);
      border-color: rgba(232,55,107,0.3);
    }

    /* ── MODALS ── */
    .modal-backdrop {
      position: fixed; inset: 0; z-index: 1000;
      background: rgba(10,15,46,0.85);
      backdrop-filter: blur(8px);
      display: none; place-items: center;
      padding: 1rem;
    }
    .modal-backdrop.open { display: grid; }
    .modal-card {
      background: var(--navy-mid);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 2rem;
      width: 100%; max-width: 500px;
      max-height: 90vh;
      overflow-y: auto;
      position: relative;
    }
    .modal-card h3 {
      font-family: 'Syne', sans-serif;
      font-size: var(--fs-xl);
      font-weight: 700;
      margin-bottom: 1.4rem;
    }
    .close-btn {
      position: absolute; top: 1rem; right: 1rem;
      background: rgba(255,255,255,0.07); border: none;
      color: var(--muted); width: 32px; height: 32px;
      border-radius: 8px; font-size: 1.2rem; cursor: pointer;
      display: grid; place-items: center;
      transition: var(--transition);
    }
    .close-btn:hover { background: rgba(255,255,255,0.14); color: var(--text); }

    /* FORM */
    .new-post-form {
      display: flex; flex-direction: column; gap: 0.8rem;
    }
    .new-post-form input,
    .new-post-form textarea,
    .new-post-form select {
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 11px 14px;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: var(--fs-sm);
      outline: none;
      transition: border-color var(--transition);
      width: 100%;
    }
    .new-post-form input:focus,
    .new-post-form textarea:focus,
    .new-post-form select:focus {
      border-color: rgba(245,166,35,0.45);
    }
    .new-post-form textarea { min-height: 100px; resize: vertical; }
    .new-post-form select option { background: var(--navy-mid); }
    .new-post-form .btn { margin-top: 0.4rem; width: 100%; justify-content: center; }

    /* EVENT MODAL CONTENT */
    .event-detail-header { margin-bottom: 1.2rem; }
    .event-detail-title {
      font-family: 'Syne', sans-serif;
      font-size: var(--fs-xl);
      font-weight: 700;
      margin-bottom: 0.6rem;
      line-height: 1.2;
    }
    .event-detail-tags { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 1rem; }
    .event-detail-meta { display: flex; flex-direction: column; gap: 8px; margin-bottom: 1.2rem; }
    .event-detail-meta-row {
      display: flex; align-items: center; gap: 8px;
      font-size: var(--fs-sm); color: var(--muted);
    }
    .event-detail-desc {
      font-size: var(--fs-sm); color: var(--muted);
      line-height: 1.7; border-top: 1px solid var(--border);
      padding-top: 1rem; margin-top: 0.5rem;
    }
    .event-detail-stats {
      display: flex; gap: 1.5rem; margin-bottom: 1.2rem;
    }
    .detail-stat {
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 0.7rem 1rem;
      flex: 1; text-align: center;
    }
    .detail-stat-val {
      font-family: 'Syne', sans-serif;
      font-size: 1.1rem; font-weight: 700;
      color: var(--accent);
    }
    .detail-stat-label { font-size: var(--fs-xs); color: var(--muted); margin-top: 3px; }

    /* ── EMPTY STATE ── */
    .empty-state {
      grid-column: 1/-1;
      text-align: center; padding: 4rem 2rem;
      color: var(--muted);
    }
    .empty-state-icon { font-size: 2.5rem; margin-bottom: 0.8rem; opacity: 0.4; }
    .empty-state p { font-size: var(--fs-sm); }

    /* ── SEARCH BAR ── */
    .search-input-wrap {
      position: relative;
      flex: 1;
      min-width: 280px;
      max-width: 450px;
    }
    .search-input-wrap input {
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 12px 14px 12px 42px;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: var(--fs-sm);
      width: 100%;
      outline: none;
      transition: var(--transition);
      box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
    }
    .search-input-wrap input:focus {
      border-color: rgba(245,166,35,0.45);
      background: rgba(255,255,255,0.07);
      box-shadow: 0 0 0 4px var(--glow);
    }
    .search-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      pointer-events: none;
      opacity: 0.6;
    }

    /* ── NEW UI OVERLAYS ── */
    .auth-required-overlay {
      position: absolute; inset: 0;
      background: rgba(10,15,46,0.6);
      backdrop-filter: blur(4px);
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      z-index: 5; border-radius: var(--radius);
      padding: 2rem; text-align: center;
      animation: fadeIn 0.3s ease;
    }
    .auth-required-overlay h4 { font-family: 'Syne', sans-serif; margin-bottom: 0.5rem; color: var(--accent); }
    .auth-required-overlay p { font-size: 0.8rem; color: var(--muted); margin-bottom: 1.5rem; }

    /* ── LOADING STATES ── */
    .loading-spinner {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 2px solid rgba(255,255,255,0.1);
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      vertical-align: middle;
      margin-right: 8px;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    .loading-overlay {
      position: absolute; inset: 0;
      background: rgba(10,15,46,0.4);
      backdrop-filter: blur(2px);
      display: flex; align-items: center; justify-content: center;
      z-index: 10;
      border-radius: var(--radius);
      animation: fadeIn 0.2s ease;
    }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }

    /* ── SCROLLBAR ── */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--navy); }
    ::-webkit-scrollbar-thumb { background: var(--navy-card); border-radius: 3px; }

    /* ── RIPPLE ── */
    .ripple-effect {
      position: absolute; border-radius: 50%;
      background: rgba(255,255,255,0.2);
      width: 4px; height: 4px;
      animation: rippleAnim 0.6s linear;
      pointer-events: none;
    }
    @keyframes rippleAnim {
      to { transform: scale(80); opacity: 0; }
    }

    /* ── SLIDE / SCALE ANIMATIONS ── */
    .slide-in { animation: slideUp 0.3s ease; }
    .scale-in { animation: scaleIn 0.25s ease; }
    @keyframes slideUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    @keyframes scaleIn { from{opacity:0;transform:scale(0.94)} to{opacity:1;transform:scale(1)} }

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

    /* ── RESPONSIVE ── */
    @media (max-width: 640px) {
      .nav-links { display: none; }
      .menu-toggle {
        display: block; margin-left: auto;
        background: transparent; border: 1px solid var(--border);
        color: var(--muted); padding: 6px 12px; border-radius: 8px;
        font-family: 'DM Sans', sans-serif; font-size: var(--fs-xs); cursor: pointer;
      }
      .hero-stats { gap: 1.5rem; }
      .filter-wrap { flex-direction: column; align-items: flex-start; }
      .sort-select { margin-left: 0; }
    }
  </style>
  <script src="js/chat.js"></script>
</head>
<body>

  <header class="site-header" id="top">
    <nav class="navbar">
      <a class="brand" href="#top">
        <div class="brand-logo">BV</div>
          <div>
            <span class="brand-text">BVRIT Hyderabad</span>
            <span class="brand-sub">Campus Portal</span>
          </div>
      </a>
      <ul class="nav-links">
        <li><a href="#dashboard" id="dashboardNavLink" style="display:none">Dashboard</a></li>
        <li><a href="#events">Events</a></li>
        <li><a href="#community">Bulletin</a></li>
        <li><a href="#about">About</a></li>
        <li id="portalNavLink" style="display: none;"><a href="javascript:void(0)" onclick="openModal('portalModal')">My Portal</a></li>
        <li id="authContainer" style="display: flex; gap: 10px; align-items: center;">
          <button class="btn ripple" onclick="openModal('loginModal')" style="background: transparent; border: 1px solid var(--border); color: var(--text); padding: 7px 16px; font-size: 0.8rem;">Login</button>
          <button class="btn ripple" onclick="openModal('signupModal')" style="padding: 7px 16px; font-size: 0.8rem;">Sign Up</button>
        </li>
        <li id="userContainer" style="display: none; gap: 12px; align-items: center;">
          <span id="userNameDisplay" style="font-size: var(--fs-sm); font-weight: 500; display: inline-flex; align-items: center;"></span>
          <div class="notif-bell" id="notifBell" style="display:flex">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            <span class="notif-badge" id="notifBadge">0</span>
            <div class="notif-dropdown" id="notifDropdown">
              <div style="font-weight:700; font-size:var(--fs-sm); border-bottom:1px solid var(--border); padding-bottom:0.5rem; margin-bottom:0.5rem">Notifications</div>
              <div id="notifList"></div>
            </div>
          </div>
          <button class="btn ripple" onclick="logout()" style="background: transparent; border: 1px solid var(--border); color: var(--accent2); padding: 5px 14px; font-size: 0.8rem; height: 32px; display: inline-flex; align-items: center; justify-content: center;">Logout</button>
        </li>
      </ul>
      <button class="menu-toggle" id="menuToggle">☰ Menu</button>
    </nav>
  </header>

  <main>
    <!-- HERO -->
    <section class="hero" id="about">
      <div class="hero-bg">
        <video autoplay muted loop playsinline class="hero-video">
          <source src="videos/campus_bg.mp4" type="video/mp4">
          Your browser does not support the video tag.
        </video>
        <div class="hero-overlay"></div>
      </div>
      <div class="hero-content">
        <div class="hero-badge">
          <span class="dot"></span>
          Campus Portal — Live
        </div>
        <h1 class="hero-title">
          BVRIT Hyderabad<br>
          <span class="highlight">College of Engineering</span>
        </h1>
        <p class="hero-tagline">Discover events, connect with peers, and stay plugged in to everything happening on campus.</p>
        <form class="hero-search" id="heroSearchForm" role="search" onsubmit="event.preventDefault(); document.getElementById('heroSearchBtn').click();">
          <input type="text" id="heroSearch" placeholder="Search events, clubs, departments…" aria-label="Search campus portal">
          <button type="button" class="btn" id="heroSearchBtn" style="border-radius:0 10px 10px 0;margin:2px;padding:10px 20px;">Search</button>
        </form>
        <div class="hero-stats">
          <div class="stat"><div class="stat-num" id="statEvents">0<span>+</span></div><div class="stat-label">Events</div></div>
          <div class="stat"><div class="stat-num" id="statPosts">0<span>+</span></div><div class="stat-label">Posts</div></div>
          <div class="stat"><div class="stat-num">4<span>+</span></div><div class="stat-label">Departments</div></div>
        </div>
      </div>
    </section>

    <!-- DASHBOARD -->
    <section class="dashboard-section section-shell" id="dashboard" style="display:none">
      <div class="section-header">
        <h2>Student Dashboard</h2>
        <p>Your academic performance, attendance, and resources at a glance.</p>
      </div>

      <div class="dashboard-grid">
        <!-- Attendance -->
        <div class="dash-card">
          <h4>Attendance Overview</h4>
          <div class="metric-val" id="dashAttendance">0%</div>
          <div class="metric-label">Current average attendance</div>
          <div style="margin-top:1.5rem; height:8px; background:var(--border); border-radius:4px; overflow:hidden;">
            <div id="attendanceBar" style="height:100%; background:var(--teal); width:0%; transition: width 1s ease;"></div>
          </div>
        </div>

        <!-- Academic -->
        <div class="dash-card">
          <h4>Academic Performance</h4>
          <div class="metric-val" id="dashGPA">0.00</div>
          <div class="metric-label">Current GPA (CGPA)</div>
          <div class="metric-label" style="margin-top:0.8rem">Credits Earned: <span id="dashCredits" style="color:var(--text)">0</span></div>
        </div>

        <!-- Deadlines -->
        <div class="dash-card">
          <h4>Upcoming Deadlines</h4>
          <ul class="deadline-list" id="deadlineList">
            <li class="deadline-item">Loading...</li>
          </ul>
        </div>

        <!-- Resources -->
        <div class="dash-card">
          <h4>Notes & Resources</h4>
          <ul class="resource-list" id="resourceList">
            <li class="resource-item">Loading...</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- ADMIN DASHBOARD -->
    <section class="admin-section section-shell" id="adminDashboard" style="display:none">
      <div class="section-header">
        <h2>Admin Overview</h2>
        <p>System metrics, active users, and event participation.</p>
      </div>
      <div class="dashboard-grid" id="adminGrid">
        <div class="dash-card">
          <h4>Active Users</h4>
          <div id="activeUsersList" style="max-height:300px; overflow-y:auto;"></div>
        </div>
        <div class="dash-card">
          <h4>Participation Stats</h4>
          <div id="eventStatsList"></div>
        </div>
      </div>
    </section>

    <!-- EVENTS -->
    <section class="events-section section-shell" id="events">
      <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
        <div class="section-header" style="margin-bottom:0">
          <h2>Events</h2>
          <p>Upcoming and past college events across departments.</p>
        </div>
        <button class="btn ripple" id="newEventBtn">+ Add Event</button>
      </div>

      <div class="filter-wrap">
        <div class="filter-controls">
          <div class="search-input-wrap">
            <div class="search-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
            <input type="text" id="eventSearch" placeholder="Search events by title or location...">
          </div>
          <div class="sort-select">
            <label for="dateSort">Sort:</label>
            <select id="dateSort">
              <option value="latest">Latest first</option>
              <option value="earliest">Earliest first</option>
            </select>
          </div>
        </div>

        <div class="pill-switch" id="categorySwitch">
          <span class="pill-label">Category:</span>
          <button class="pill active" data-filter-type="category" data-filter="all">All</button>
          <button class="pill" data-filter-type="category" data-filter="technical">Technical</button>
          <button class="pill" data-filter-type="category" data-filter="cultural">Cultural</button>
          <button class="pill" data-filter-type="category" data-filter="sports">Sports</button>
          <button class="pill" data-filter-type="category" data-filter="workshop">Workshop</button>
          <button class="pill" data-filter-type="category" data-filter="seminar">Seminar</button>
          <button class="pill" data-filter-type="category" data-filter="gaming">Gaming</button>
          <button class="pill" data-filter-type="category" data-filter="social">Social</button>
        </div>

        <div class="pill-switch" id="deptSwitch">
          <span class="pill-label">Department:</span>
          <button class="pill active" data-filter-type="department" data-filter="all">All Depts</button>
          <button class="pill" data-filter-type="department" data-filter="cse">CSE</button>
          <button class="pill" data-filter-type="department" data-filter="ece">ECE</button>
          <button class="pill" data-filter-type="department" data-filter="it">IT</button>
          <button class="pill" data-filter-type="department" data-filter="eee">EEE</button>
          <button class="pill" data-filter-type="department" data-filter="mech">Mech</button>
          <button class="pill" data-filter-type="department" data-filter="civil">Civil</button>
          <button class="pill" data-filter-type="department" data-filter="bsh">BSH</button>
          <button class="pill" data-filter-type="department" data-filter="mba">MBA</button>
        </div>
      </div>

      <div class="cards-grid" id="eventsGrid"></div>
    </section>

    <!-- COMMUNITY -->
    <section class="community-section section-shell" id="community">
      <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
        <div class="section-header" style="margin-bottom:0">
          <h2>Community Bulletin</h2>
          <p>Announcements, study groups, and campus updates.</p>
        </div>
        <div style="display:flex; gap: 10px; align-items: center; flex-wrap: wrap;">
          <div class="search-input-wrap">
            <div class="search-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
            <input type="text" id="postSearch" placeholder="Search posts...">
          </div>
          <button class="btn ripple" id="newPostBtn">+ New Post</button>
        </div>
      </div>
      <div class="community-grid" id="postsGrid" style="position: relative;"></div>
    </section>
  </main>

  <!-- EVENT DETAIL MODAL -->
  <div class="modal-backdrop" id="eventModal">
    <div class="modal-card slide-in">
      <button class="close-btn" data-close-modal="eventModal" aria-label="Close">&times;</button>
      <div class="modal-content" id="eventModalContent"></div>
      
      <div id="feedbackSection" style="margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--border); display:none;">
        <h4 style="font-family:'Syne', sans-serif; color:var(--accent); margin-bottom:1rem">Share Feedback</h4>
        <div class="feedback-stars" id="starRating">
          <span class="star active" data-rating="1">★</span>
          <span class="star active" data-rating="2">★</span>
          <span class="star active" data-rating="3">★</span>
          <span class="star active" data-rating="4">★</span>
          <span class="star active" data-rating="5">★</span>
        </div>
        <textarea id="feedbackComment" placeholder="Any suggestions or comments?" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid var(--border); border-radius:10px; padding:0.8rem; color:var(--text); font-family:inherit; margin-bottom:1rem; min-height:80px; outline:none;"></textarea>
        <button class="btn ripple" id="submitFeedbackBtn" style="width:100%">Submit Feedback</button>
      </div>
    </div>
  </div>

  <!-- LOGIN MODAL -->
  <div class="modal-backdrop" id="loginModal">
    <div class="modal-card scale-in">
      <button class="close-btn" data-close-modal="loginModal" aria-label="Close">&times;</button>
      <h3>Login to Campus Portal</h3>
      <form id="loginForm" class="new-post-form">
        <div id="loginStatus" style="font-size: var(--fs-xs); color: var(--accent2); margin-bottom: 10px; display: none;"></div>
        <input type="email" id="loginEmail" placeholder="Email Address" required>
        <input type="password" id="loginPassword" placeholder="Password" required>
        <button type="submit" class="btn ripple">Login</button>
      </form>
      <p style="font-size: var(--fs-xs); color: var(--muted); margin-top: 1.5rem; text-align: center;">Don't have an account? <a href="javascript:void(0)" onclick="closeModal('loginModal'); openModal('signupModal')" style="color: var(--accent);">Sign up</a></p>
    </div>
  </div>

  <!-- SIGNUP MODAL -->
  <div class="modal-backdrop" id="signupModal">
    <div class="modal-card scale-in">
      <button class="close-btn" data-close-modal="signupModal" aria-label="Close">&times;</button>
      <h3>Create Student Account</h3>
      <form id="signupForm" class="new-post-form">
        <div id="signupStatus" style="font-size: var(--fs-xs); color: var(--accent2); margin-bottom: 10px; display: none;"></div>
        <input type="text" id="signupName" placeholder="Full Name" required>
        <input type="email" id="signupEmail" placeholder="Email Address" required>
        <input type="password" id="signupPassword" placeholder="Password (min 6 chars)" minlength="6" required>
        <button type="submit" class="btn ripple">Sign Up</button>
      </form>
      <p style="font-size: var(--fs-xs); color: var(--muted); margin-top: 1.5rem; text-align: center;">Already have an account? <a href="javascript:void(0)" onclick="closeModal('signupModal'); openModal('loginModal')" style="color: var(--accent);">Login</a></p>
    </div>
  </div>

  <!-- NEW POST MODAL -->
  <div class="modal-backdrop" id="postModal">
    <div class="modal-card scale-in">
      <button class="close-btn" data-close-modal="postModal" aria-label="Close">&times;</button>
      <h3>Share a Community Post</h3>
      <div id="postAuthOverlay" class="auth-required-overlay" style="display: none;">
        <h4>Login Required</h4>
        <p>You must be logged in to share a post with the BVRIT community.</p>
        <button class="btn ripple" onclick="closeModal('postModal'); openModal('loginModal')">Login Now</button>
      </div>
      <form id="postForm" class="new-post-form">
        <input type="text" id="postAuthor" placeholder="Your name" maxlength="120" required>
        <input type="text" id="postMeta" placeholder="Department / Year (e.g. CSE / 3rd Year)" maxlength="180" required>
        <textarea id="postContent" placeholder="Share an announcement, update, or request…" maxlength="2000" required></textarea>
        <div class="char-count" style="font-size: 0.7rem; color: var(--muted); text-align: right; margin-top: -5px;">0 / 2000</div>
        <button type="submit" class="btn ripple">Publish Post</button>
      </form>
    </div>
  </div>

  <!-- NEW EVENT MODAL -->
  <div class="modal-backdrop" id="eventSubmitModal">
    <div class="modal-card scale-in">
      <button class="close-btn" data-close-modal="eventSubmitModal" aria-label="Close">&times;</button>
      <h3>Suggest a New Event</h3>
      <div id="eventAuthOverlay" class="auth-required-overlay" style="display: none;">
        <h4>Login Required</h4>
        <p>You must be logged in to submit a new event suggestion.</p>
        <button class="btn ripple" onclick="closeModal('eventSubmitModal'); openModal('loginModal')">Login Now</button>
      </div>
      <form id="eventForm" class="new-post-form">
        <input type="text" id="eventTitle" placeholder="Event title" maxlength="180" required>
        <div style="display: flex; gap: 10px;">
          <div style="flex: 1;">
            <label style="font-size: 0.7rem; color: var(--muted); margin-bottom: 4px; display: block;">Date & Time</label>
            <input type="datetime-local" id="eventDateTime" required>
          </div>
          <div style="flex: 1;">
            <label style="font-size: 0.7rem; color: var(--muted); margin-bottom: 4px; display: block;">Location</label>
            <input type="text" id="eventLocation" placeholder="Campus Venue" maxlength="255" required>
          </div>
        </div>
        <div style="display: flex; gap: 10px;">
          <select id="eventCategory" required>
            <option value="">Select category</option>
            <option value="technical">Technical</option>
            <option value="cultural">Cultural</option>
            <option value="sports">Sports</option>
            <option value="workshop">Workshop</option>
            <option value="seminar">Seminar</option>
            <option value="gaming">Gaming</option>
            <option value="social">Social</option>
          </select>
          <select id="eventDepartment" required>
            <option value="">Select department</option>
            <option value="cse">CSE</option>
            <option value="ece">ECE</option>
            <option value="it">IT</option>
            <option value="eee">EEE</option>
            <option value="mech">Mech</option>
            <option value="civil">Civil</option>
            <option value="bsh">BSH</option>
            <option value="mba">MBA</option>
          </select>
        </div>
        <textarea id="eventDetails" placeholder="Detailed description of the event..." maxlength="3000" required></textarea>
        <div class="char-count" style="font-size: 0.7rem; color: var(--muted); text-align: right; margin-top: -5px;">0 / 3000</div>
        <div style="display: flex; gap: 10px;">
          <div style="flex: 1;">
            <label style="font-size: 0.7rem; color: var(--muted); margin-bottom: 4px; display: block;">Entry Fee (₹)</label>
            <input type="number" id="eventFee" placeholder="0 for Free" min="0" value="0" required>
          </div>
          <div style="flex: 1;">
            <label style="font-size: 0.7rem; color: var(--muted); margin-bottom: 4px; display: block;">Max Team Size</label>
            <input type="number" id="eventTeamSize" placeholder="1 for Solo" min="1" max="100" value="1" required>
          </div>
        </div>
        <button type="submit" class="btn ripple">Submit for Approval</button>
      </form>
    </div>
  </div>

  <!-- MY PORTAL MODAL -->
  <div class="modal-backdrop" id="portalModal">
    <div class="modal-card scale-in" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
      <button class="close-btn" data-close-modal="portalModal" aria-label="Close">&times;</button>
      <h2 style="font-family: 'Syne', sans-serif; margin-bottom: 1.5rem;">My Campus Portal</h2>
      
      <div style="margin-bottom: 2.5rem;">
        <h4 style="color: var(--accent); margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
          Registered Events
        </h4>
        <div id="portalRegistrations" class="community-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
          <div class="empty-state"><p>Loading registrations...</p></div>
        </div>
      </div>

      <div>
        <h4 style="color: var(--accent2); margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
          My Submissions
        </h4>
        <div id="portalPosts" class="community-grid">
          <div class="empty-state"><p>Loading posts...</p></div>
        </div>
      </div>
    </div>
  </div>

  <div class="announcement-popup" id="announcementPopup">
    <div class="announcement-popup-header">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
      ADMIN BROADCAST
    </div>
    <div class="announcement-popup-body" id="announcementPopupBody"></div>
  </div>

  <div id="toastContainer"></div>

  <!-- CHAT SYSTEM -->
  <div class="chat-fab" id="chatFab" style="display: none;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
  </div>

  <div class="chat-drawer" id="chatDrawer">
    <!-- Chat List View -->
    <div id="chatListView" style="display: flex; flex-direction: column; height: 100%;">
      <div class="chat-header">
        <h3 style="font-family:'Syne', sans-serif; font-size: 1.2rem;">Messages</h3>
        <div style="display:flex; gap: 10px;">
          <button class="btn ripple" id="createGroupBtn" style="padding: 5px 10px; font-size: 0.7rem;">+ Group</button>
          <button class="btn-ghost" id="closeChatBtn">&times;</button>
        </div>
      </div>
      <div class="chat-body" id="chatListContainer">
        <!-- Chats will be loaded here -->
      </div>
      <div style="padding: 1rem; border-top: 1px solid var(--border);">
        <input type="text" id="joinGroupInput" placeholder="Enter Invite Code" style="width:100%; background:var(--navy-card); border:1px solid var(--border); padding:8px 14px; border-radius:10px; color:var(--text); font-size: 0.8rem; outline: none;">
      </div>
    </div>

    <!-- Active Message View -->
    <div id="messagePane" class="message-pane">
      <div class="chat-header">
        <div style="display:flex; align-items:center; gap:10px;">
          <button class="btn-ghost" id="backToChatList" style="padding:0; margin-right:5px;">&larr;</button>
          <div class="chat-avatar" id="activeChatAvatar">?</div>
          <div>
            <h4 id="activeChatTitle" style="font-size: 0.95rem; margin-bottom: 2px;">Chat Name</h4>
            <span id="activeChatStatus" style="font-size: 0.65rem; color: var(--teal);">online</span>
          </div>
        </div>
        <button class="btn-ghost" id="closeChatBtn2">&times;</button>
      </div>
      <div class="message-list" id="messageList">
        <!-- Messages will be loaded here -->
      </div>
      <div class="chat-input-area">
        <input type="file" id="chatFileInput" style="display: none;">
        <button id="chatFileBtn" title="Upload File">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
        </button>
        <input type="text" id="chatInput" placeholder="Type a message...">
        <button id="chatSendBtn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
        </button>
      </div>
    </div>
  </div>

  <script>
    const API_BASE = "./backend/portal_api.php";
    window.PORTAL_CSRF_TOKEN = "<?php echo htmlspecialchars($_SESSION['portal_csrf'], ENT_QUOTES, 'UTF-8'); ?>";

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

    let events = [];
    let posts  = [];
    let currentUser = null;
    let categoryFilter = "all";
    let deptFilter     = "all";
    let eventSearchQuery = "";
    let postSearchQuery  = "";

    async function checkAuth() {
      try {
        const res = await fetch(`${API_BASE}?action=me`);
        const data = await res.json();
        if (data.ok) {
          currentUser = data.user;
          updateAuthUI();
        }
      } catch (err) { console.error("Auth check failed", err); }
    }
    function updateAuthUI() {
      const authContainer = document.getElementById("authContainer");
      const userContainer = document.getElementById("userContainer");
      const userNameDisplay = document.getElementById("userNameDisplay");
      const portalNavLink = document.getElementById("portalNavLink");
      const dashboardNavLink = document.getElementById("dashboardNavLink");
      const dashboardSection = document.getElementById("dashboard");
      const adminDashboard = document.getElementById("adminDashboard");
      const notifBell = document.getElementById("notifBell");

      if (currentUser) {
        authContainer.style.display = "none";
        userContainer.style.display = "flex";
        portalNavLink.style.display = "flex";
        dashboardNavLink.style.display = "flex";
        dashboardSection.style.display = "block";
        notifBell.style.display = "flex";
        document.getElementById("chatFab").style.display = "grid";
        userNameDisplay.textContent = `Hi, ${currentUser.name.split(' ')[0]}`;
        
        if (currentUser.role === 'admin') {
          adminDashboard.style.display = "block";
          loadAdminDashboard();
        } else {
          adminDashboard.style.display = "none";
        }

        const postAuthorInput = document.getElementById("postAuthor");
        if (postAuthorInput) postAuthorInput.value = currentUser.name;
        
        loadNotifications();
        loadDashboard();
        loadPortalData(true);
        if (typeof Chat !== 'undefined') Chat.init();
      } else {
        authContainer.style.display = "flex";
        userContainer.style.display = "none";
        portalNavLink.style.display = "none";
        dashboardNavLink.style.display = "none";
        dashboardSection.style.display = "none";
        adminDashboard.style.display = "none";
        notifBell.style.display = "none";
        document.getElementById("chatFab").style.display = "none";
      }
    }

    async function loadNotifications() {
      try {
        const res = await fetch(`${API_BASE}?action=notifications`);
        const data = await res.json();
        if (!data.ok) return;
        
        const badge = document.getElementById("notifBadge");
        const list = document.getElementById("notifList");
        const unread = data.notifications.filter(n => !n.isRead).length;
        
        badge.textContent = unread;
        badge.style.display = unread > 0 ? "grid" : "none";
        
        list.innerHTML = data.notifications.map(n => `
          <div class="notif-item ${n.isRead ? '' : 'unread'}" onclick="markNotifRead(${n.id})">
            <strong>${n.title}</strong>
            ${n.message}
            <div style="font-size: 0.6rem; color: var(--muted); margin-top: 4px;">${n.time}</div>
          </div>
        `).join("") || '<div class="notif-item">No new notifications</div>';
      } catch (err) { console.error("Notif fetch failed", err); }
    }

    async function markNotifRead(id) {
      await fetch(`${API_BASE}?action=mark_notif_read`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, csrf: window.PORTAL_CSRF_TOKEN })
      });
      loadNotifications();
    }

    async function loadDashboard() {
      try {
        const res = await fetch(`${API_BASE}?action=student_dashboard`);
        const data = await res.json();
        if (!data.ok) return;
        
        const { metrics, deadlines, resources } = data;
        document.getElementById("dashAttendance").textContent = `${metrics.attendance_pct}%`;
        document.getElementById("attendanceBar").style.width = `${metrics.attendance_pct}%`;
        document.getElementById("dashGPA").textContent = metrics.gpa;
        document.getElementById("dashCredits").textContent = metrics.credits_earned;
        
        document.getElementById("deadlineList").innerHTML = deadlines.map(d => `
          <li class="deadline-item">
            <div class="deadline-info">
              <div>${d.title}</div>
              <span>Due: ${d.due}</span>
            </div>
            <div class="days-left">${d.daysLeft}d left</div>
          </li>
        `).join("");
        
        document.getElementById("resourceList").innerHTML = resources.map(r => `
          <li class="resource-item">
            <span>${r.title}</span>
            <a href="${r.link}" class="res-link" target="_blank">${r.type.toUpperCase()}</a>
          </li>
        `).join("");
      } catch (err) { console.error("Dashboard fetch failed", err); }
    }

    async function loadAdminDashboard() {
      try {
        const res = await fetch(`${API_BASE}?action=admin_dashboard`);
        const data = await res.json();
        if (!data.ok) return;
        
        document.getElementById("activeUsersList").innerHTML = data.users.map(u => `
          <div class="resource-item" style="font-size: 0.75rem;">
            <div>
              <strong>${u.fullname}</strong>
              <div style="opacity:0.6">${u.email}</div>
            </div>
            <div style="opacity:0.5">Joined ${new Date(u.created_at).toLocaleDateString()}</div>
          </div>
        `).join("");
        
        document.getElementById("eventStatsList").innerHTML = data.stats.map(s => `
          <div class="deadline-item">
            <span>${s.event_title}</span>
            <div class="days-left" style="background:var(--teal); color:#fff">${s.registrants} joined</div>
          </div>
        `).join("");
      } catch (err) { console.error("Admin dashboard fetch failed", err); }
    }

    let currentRating = 5;
    function setupFeedback() {
      const stars = document.querySelectorAll(".star");
      stars.forEach(s => {
        s.onclick = () => {
          currentRating = parseInt(s.dataset.rating);
          stars.forEach(st => {
            st.classList.toggle("active", parseInt(st.dataset.rating) <= currentRating);
          });
        };
      });
      
      document.getElementById("submitFeedbackBtn").onclick = async (e) => {
        const eventId = e.target.dataset.eventId;
        const comment = document.getElementById("feedbackComment").value;
        const res = await fetch(`${API_BASE}?action=feedback`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            event_id: eventId,
            rating: currentRating,
            comment: comment,
            csrf: window.PORTAL_CSRF_TOKEN
          })
        });
        const data = await res.json();
        if (data.ok) {
          showToast("Feedback submitted! Thank you.", "success");
          document.getElementById("feedbackSection").style.display = "none";
        }
      };
    }

    async function loadPortalData(silent = false) {
      if (!currentUser) return;
      try {
        const res = await fetch(`${API_BASE}?action=my_portal`);
        const data = await res.json();
        if (data.ok) {
          renderPortal(data.registrations, data.posts);
        }
      } catch (err) { console.error("Portal data fetch failed", err); }
    }

    function renderPortal(regs, myPosts) {
      const regGrid = document.getElementById("portalRegistrations");
      const postGrid = document.getElementById("portalPosts");

      if (!regs.length) {
        regGrid.innerHTML = `<div class="empty-state"><p>You haven't registered for any events yet.</p></div>`;
      } else {
        regGrid.innerHTML = regs.map(e => `
          <div class="event-card" style="padding: 1.2rem; border: 1px solid ${e.regStatus === 'waitlisted' ? 'rgba(232,55,107,0.3)' : 'var(--border)'}">
            <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:8px;">
              <span class="tag ${tagColor(e.category)}">${e.category}</span>
              <span class="tag" style="background: ${e.regStatus==='confirmed' ? 'rgba(0,212,180,0.1)' : 'rgba(245,166,35,0.1)'}; color: ${e.regStatus==='confirmed' ? 'var(--teal)' : 'var(--accent)'}; font-size: 0.6rem;">
                ${e.regStatus.toUpperCase()}
              </span>
            </div>
            <div class="card-title" style="font-size: 1rem; margin: 8px 0;">${e.title}</div>
            <div class="card-meta" style="margin-bottom: 15px;">
              <div class="card-meta-row">${iconCalendar} ${fmtDate(e.datetime)}</div>
            </div>
            
            ${e.announcements && e.announcements.length > 0 ? `
              <div style="margin-bottom: 15px; background: rgba(245,166,35,0.05); border-left: 2px solid var(--accent); padding: 10px; border-radius: 4px;">
                <div style="font-size: 0.65rem; color: var(--accent); font-weight: 600; margin-bottom: 4px; display: flex; align-items: center; gap: 4px;">
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                  LATEST ANNOUNCEMENT
                </div>
                <div style="font-size: 0.75rem; color: var(--text); line-height: 1.4;">${e.announcements[0].message}</div>
                <div style="font-size: 0.6rem; color: var(--muted); margin-top: 4px;">${new Date(e.announcements[0].created_at).toLocaleDateString()}</div>
              </div>
            ` : ''}

            <div style="display:flex; gap:8px;">
              <a href="register.php?event_id=${e.id}" class="btn" style="flex:1; justify-content:center; font-size: 0.75rem; background: rgba(255,255,255,0.05); border: 1px solid var(--border);">View Ticket</a>
              <a href="backend/portal_api.php?action=export_calendar&id=${e.id}" class="btn" title="Add to Calendar" style="width:40px; justify-content:center; background: rgba(0,212,180,0.1); color: var(--teal); border: 1px solid rgba(0,212,180,0.3);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
              </a>
            </div>
          </div>
        `).join("");
      }

      if (!myPosts.length) {
        postGrid.innerHTML = `<div class="empty-state"><p>You haven't shared any posts yet.</p></div>`;
      } else {
        postGrid.innerHTML = myPosts.map(p => `
          <div class="post-card">
            <div class="post-header">
              <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                <span class="tag" style="background: ${p.status==='approved' ? 'rgba(0,212,180,0.1)' : 'rgba(245,166,35,0.1)'}; color: ${p.status==='approved' ? 'var(--teal)' : 'var(--accent)'};">
                  ${p.status.toUpperCase()}
                </span>
                <span class="post-time">${fmtDate(p.time)}</span>
              </div>
            </div>
            <div class="post-content" style="margin-top: 10px;">${p.content}</div>
            <div class="post-footer">
              <span style="font-size: 0.7rem; color: var(--muted);">♥ ${p.likes} likes</span>
            </div>
          </div>
        `).join("");
      }
    }

    async function logout() {
      await fetch(`${API_BASE}?action=logout`);
      currentUser = null;
      updateAuthUI();
      location.reload(); // Simplest way to clear states
    }

    function showLoading(containerId) {
      const container = document.getElementById(containerId);
      const overlay = document.createElement("div");
      overlay.className = "loading-overlay";
      overlay.innerHTML = `<div class="loading-spinner"></div>`;
      container.style.position = "relative";
      container.appendChild(overlay);
    }

    function hideLoading(containerId) {
      const container = document.getElementById(containerId);
      const overlay = container.querySelector(".loading-overlay");
      if (overlay) overlay.remove();
    }

    async function loadEvents(silent = false) {
      if (!silent) showLoading("eventsGrid");
      try {
        const res = await fetch(`${API_BASE}?action=events`);
        const data = await res.json();
        if (data.ok) {
          events = data.events;
          renderEvents();
        }
      } catch (err) { console.error("Failed to load events", err); }
      finally { if (!silent) hideLoading("eventsGrid"); }
    }

    async function loadPosts(silent = false) {
      if (!silent) showLoading("postsGrid");
      try {
        const res = await fetch(`${API_BASE}?action=posts`);
        const data = await res.json();
        if (data.ok) {
          posts = data.posts;
          renderPosts();
        }
      } catch (err) { console.error("Failed to load posts", err); }
      finally { if (!silent) hideLoading("postsGrid"); }
    }

    // Periodic Polling every 10 seconds
    setInterval(() => {
      loadEvents(true);
      loadPosts(true);
      loadPortalData(true);
    }, 10000);

    async function likePost(id) {
      if (!currentUser) {
        openModal('loginModal');
        return;
      }
      try {
        const res = await fetch(`${API_BASE}?action=like_post`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ post_id: id, csrf: window.PORTAL_CSRF_TOKEN })
        });
        const data = await res.json();
        if (data.ok) {
          const post = posts.find(p => p.id === id);
          if (post) {
            post.likes = data.likes;
            renderPosts();
          }
        }
      } catch (err) { console.error("Failed to like post", err); }
    }

    /* ── ICON SVGs ── */
    const iconCalendar = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>`;
    const iconPin      = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>`;
    const iconUsers    = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>`;

    function fmtDate(dateStr) {
      if (!dateStr) return "Date TBD";
      const d = new Date(dateStr);
      if (isNaN(d.getTime())) return "Invalid Date";
      return d.toLocaleDateString("en-IN", { day:"numeric", month:"short", year:"numeric" })
           + " · " + d.toLocaleTimeString("en-IN", { hour:"2-digit", minute:"2-digit" });
    }
    function tagColor(cat) {
      return { cultural:"tag-category", academic:"tag-academic", sports:"tag-sports" }[cat] || "tag-category";
    }
    function barColor(cat) {
      return { academic:"academic", sports:"sports" }[cat] || "";
    }
    function initials(name) {
      return name.split(" ").map(w=>w[0]).join("").slice(0,2).toUpperCase();
    }

    /* ── RENDER EVENTS ── */
    function renderEvents() {
      const grid = document.getElementById("eventsGrid");
      let filtered = events.filter(e => {
        const matchesFilters = (categoryFilter === "all" || e.category === categoryFilter) &&
                             (deptFilter === "all" || e.department === deptFilter);
        const matchesSearch = !eventSearchQuery || 
                             e.title.toLowerCase().includes(eventSearchQuery) || 
                             e.details.toLowerCase().includes(eventSearchQuery) ||
                             e.category.toLowerCase().includes(eventSearchQuery) ||
                             e.department.toLowerCase().includes(eventSearchQuery) ||
                             e.location.toLowerCase().includes(eventSearchQuery);
        return matchesFilters && matchesSearch;
      });
      const sort = document.getElementById("dateSort").value;
      filtered.sort((a,b) => sort==="latest"
        ? new Date(b.datetime)-new Date(a.datetime)
        : new Date(a.datetime)-new Date(b.datetime)
      );
      if (!filtered.length) {
        grid.innerHTML = `<div class="empty-state"><div class="empty-state-icon">📭</div><p>No events match this filter.</p></div>`;
        return;
      }
      grid.innerHTML = filtered.map(e => `
        <div class="event-card" data-id="${e.id}">
          <div class="card-image-wrap" style="height: 140px; background: var(--navy-mid); overflow: hidden; position: relative;">
            <img src="${e.thumb}" alt="${e.title}" 
                 onerror="this.src='https://images.unsplash.com/photo-1540575861501-7cf05a4b125a?auto=format&fit=crop&q=80&w=600'; this.onerror=null;"
                 style="width: 100%; height: 100%; object-fit: cover; opacity: 0.6; transition: var(--transition);">
            <div class="card-color-bar ${barColor(e.category)}" style="position: absolute; bottom: 0; left: 0; right: 0;"></div>
            ${e.announcement ? `
              <div class="card-announcement-bell" onclick="showAnnouncement(event, \`${e.announcement.replace(/'/g, "\\'")}\`)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
              </div>
            ` : ''}
          </div>
          <div class="card-body">
            <div class="card-tags">
              <span class="tag ${tagColor(e.category)}">${e.category}</span>
              <span class="tag tag-dept">${e.department.toUpperCase()}</span>
            </div>
            <div class="card-title">${e.title}</div>
            <div class="card-meta">
              <div class="card-meta-row">${iconCalendar} ${fmtDate(e.datetime)}</div>
              <div class="card-meta-row">${iconPin} ${e.location}</div>
            </div>
          </div>
          <div class="card-footer">
            <div class="card-fee ${e.fee===0?'free':''}">${e.fee===0?"Free":"₹"+e.fee}</div>
            ${e.isRegistered ? 
              `<div class="tag" style="background: rgba(0,212,180,0.1); color: var(--teal); border: 1px solid rgba(0,212,180,0.3); padding: 4px 12px; font-size: 0.7rem; border-radius: 6px;">✓ Registered</div>` :
              (currentUser ? 
                `<a href="register.php?event_id=${e.id}" class="btn ripple" style="padding: 6px 14px; font-size: 0.75rem; border-radius: 8px;">Register</a>` :
                `<button class="btn ripple" onclick="openModal('loginModal')" style="padding: 6px 14px; font-size: 0.75rem; border-radius: 8px; background: transparent; border: 1px solid var(--border); color: var(--muted);">Login to Register</button>`
              )
            }
          </div>
        </div>
      `).join("");
      grid.querySelectorAll(".event-card").forEach(card => {
        card.addEventListener("click", () => openEventModal(+card.dataset.id));
      });
      document.getElementById("statEvents").innerHTML = events.length + "<span>+</span>";
    }

    function showAnnouncement(event, message) {
      event.stopPropagation();
      const popup = document.getElementById("announcementPopup");
      const body = document.getElementById("announcementPopupBody");
      body.textContent = message;
      
      const rect = event.currentTarget.getBoundingClientRect();
      popup.style.display = "block";
      popup.style.top = (rect.bottom + 10 + window.scrollY) + "px";
      popup.style.left = (rect.left - 240 + window.scrollX) + "px";
      
      // Auto hide after 5 seconds or click away
      setTimeout(() => {
        document.addEventListener('click', hideAnnouncement);
      }, 100);
    }

    function hideAnnouncement() {
      document.getElementById("announcementPopup").style.display = "none";
      document.removeEventListener('click', hideAnnouncement);
    }

    /* ── EVENT MODAL ── */
    function openEventModal(id) {
      const e = events.find(ev=>ev.id===id); if(!e) return;
      document.getElementById("eventModalContent").innerHTML = `
        <div style="height: 180px; background: var(--navy-mid); border-radius: 12px; overflow: hidden; margin-bottom: 1.5rem; position: relative;">
          <img src="${e.thumb}" alt="${e.title}" 
               onerror="this.src='https://images.unsplash.com/photo-1540575861501-7cf05a4b125a?auto=format&fit=crop&q=80&w=600'; this.onerror=null;"
               style="width: 100%; height: 100%; object-fit: cover; opacity: 0.7;">
          <div class="card-color-bar ${barColor(e.category)}" style="position: absolute; bottom: 0; left: 0; right: 0;"></div>
        </div>
        <div class="event-detail-header">
          <div class="event-detail-tags">
            <span class="tag ${tagColor(e.category)}">${e.category}</span>
            <span class="tag tag-dept">${e.department.toUpperCase()}</span>
          </div>
          <div class="event-detail-title">${e.title}</div>
        </div>
        <div class="event-detail-stats">
          <div class="detail-stat">
            <div class="detail-stat-val">${e.fee===0?"Free":"₹"+e.fee}</div>
            <div class="detail-stat-label">Entry Fee</div>
          </div>
          <div class="detail-stat">
            <div class="detail-stat-val">${e.teamSize}</div>
            <div class="detail-stat-label">Team Size</div>
          </div>
        </div>
        <div class="event-detail-meta">
          <div class="event-detail-meta-row">${iconCalendar} ${fmtDate(e.datetime)}</div>
          <div class="event-detail-meta-row">${iconPin} ${e.location}</div>
        </div>
        ${e.announcement ? `
          <div style="margin-bottom: 1.5rem; background: rgba(245,166,35,0.08); border-left: 3px solid var(--accent); padding: 12px 16px; border-radius: 8px;">
            <div style="font-size: 0.7rem; color: var(--accent); font-weight: 700; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
              LATEST BROADCAST
            </div>
            <div style="font-size: 0.85rem; color: var(--text); line-height: 1.5;">${e.announcement}</div>
          </div>
        ` : ''}
        <div class="event-detail-desc">${e.details}</div>
        
        <div id="eventParticipantsSection" style="margin-top: 1.5rem; display: none;">
          <h4 style="font-family: 'Syne', sans-serif; font-size: 0.9rem; color: var(--accent); margin-bottom: 0.8rem;">Participants</h4>
          <div id="eventParticipantsList" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
        </div>

        <div style="margin-top: 2rem; display: flex; flex-direction: column; gap: 10px;">
          ${e.isRegistered ? 
            `<div class="btn" style="width: 100%; justify-content: center; padding: 12px; font-weight: 600; background: rgba(0,212,180,0.1); color: var(--teal); border: 1px solid rgba(0,212,180,0.3); cursor: default;">✓ Registered for this Event</div>` :
            (currentUser ? 
              `<a href="register.php?event_id=${e.id}" class="btn ripple" style="width: 100%; justify-content: center; padding: 12px; font-weight: 600;">Register for this Event</a>` :
              `<button class="btn ripple" onclick="closeModal('eventModal'); openModal('loginModal')" style="width: 100%; justify-content: center; padding: 12px; font-weight: 600; background: transparent; border: 1px solid var(--border); color: var(--muted);">Login to Register</button>`
            )
          }
          ${currentUser && e.creator_id ? `<button class="btn ripple" onclick="startDirectChat(${e.creator_id})" style="width: 100%; justify-content: center; padding: 12px; font-weight: 600; background: rgba(245,166,35,0.1); color: var(--accent); border: 1px solid var(--accent);">Message Organizer</button>` : ''}
        </div>
      `;
      
      // Feedback logic
      const feedbackSection = document.getElementById("feedbackSection");
      const submitBtn = document.getElementById("submitFeedbackBtn");
      const isPast = new Date(e.datetime) < new Date();
      if (currentUser && isPast) {
        feedbackSection.style.display = "block";
        submitBtn.dataset.eventId = e.id;
      } else {
        feedbackSection.style.display = "none";
      }

      // Load participants
      fetch(`${API_BASE}?action=event_participants&id=${e.id}`)
        .then(res => res.json())
        .then(data => {
          if (data.ok && data.participants.length > 0) {
            document.getElementById("eventParticipantsSection").style.display = "block";
            document.getElementById("eventParticipantsList").innerHTML = data.participants.map(p => `
              <div class="participant-tag" title="Chat with ${p.fullname}" onclick="startDirectChat(${p.id})" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border); padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: var(--transition);">
                <div class="chat-avatar" style="width: 20px; height: 20px; font-size: 0.6rem; background: var(--navy-card);">${initials(p.fullname)}</div>
                ${p.fullname.split(' ')[0]}
              </div>
            `).join("");
          }
        });

      document.getElementById("eventModal").classList.add("open");
    }

    /* ── RENDER POSTS ── */
    function renderPosts() {
      const grid = document.getElementById("postsGrid");
      let filtered = posts.filter(p => {
        return !postSearchQuery || 
               p.author.toLowerCase().includes(postSearchQuery) || 
               p.content.toLowerCase().includes(postSearchQuery) || 
               p.meta.toLowerCase().includes(postSearchQuery);
      });
      if (!filtered.length) {
        grid.innerHTML = `<div class="empty-state"><div class="empty-state-icon">💬</div><p>${postSearchQuery ? 'No posts match your search.' : 'No posts yet. Be the first!'}</p></div>`;
        return;
      }
      grid.innerHTML = filtered.map(p => `
        <div class="post-card">
          <div class="post-header">
            <div class="post-avatar">${initials(p.author)}</div>
            <div>
              <div class="post-author">${p.author}</div>
              <div class="post-meta">${p.meta}</div>
            </div>
          </div>
          <div class="post-content">${p.content}</div>
          <div class="post-footer">
            <div class="post-time">${p.time}</div>
            <button class="post-like-btn" onclick="likePost(${p.id})">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"></path></svg>
              ${p.likes}
            </button>
          </div>
        </div>
      `).join("");
      document.getElementById("statPosts").innerHTML = posts.length + "<span>+</span>";
    }

    /* ── FILTERS ── */
    document.getElementById("heroSearch").addEventListener("input", e => {
      eventSearchQuery = e.target.value.toLowerCase();
      // Also update the dedicated event search input to keep them in sync
      const eventSearchInput = document.getElementById("eventSearch");
      if (eventSearchInput) eventSearchInput.value = e.target.value;
      renderEvents();
    });

    document.getElementById("heroSearchBtn").addEventListener("click", () => {
      const heroSearch = document.getElementById("heroSearch");
      eventSearchQuery = heroSearch.value.toLowerCase();
      
      // Sync with event search input
      const eventSearchInput = document.getElementById("eventSearch");
      if (eventSearchInput) eventSearchInput.value = heroSearch.value;
      
      renderEvents();
      // Scroll to events section
      document.getElementById("events").scrollIntoView({ behavior: 'smooth' });
    });

    document.getElementById("eventSearch").addEventListener("input", e => {
      eventSearchQuery = e.target.value.toLowerCase();
      // Sync back to hero search
      const heroSearch = document.getElementById("heroSearch");
      if (heroSearch) heroSearch.value = e.target.value;
      renderEvents();
    });
    document.getElementById("postSearch").addEventListener("input", e => {
      postSearchQuery = e.target.value.toLowerCase();
      renderPosts();
    });

    document.getElementById("categorySwitch").addEventListener("click", e => {
      const btn = e.target.closest(".pill"); if(!btn) return;
      document.querySelectorAll("#categorySwitch .pill").forEach(b=>b.classList.remove("active"));
      btn.classList.add("active");
      categoryFilter = btn.dataset.filter;
      renderEvents();
    });
    document.getElementById("deptSwitch").addEventListener("click", e => {
      const btn = e.target.closest(".pill"); if(!btn) return;
      document.querySelectorAll("#deptSwitch .pill").forEach(b=>b.classList.remove("active"));
      btn.classList.add("active");
      deptFilter = btn.dataset.filter;
      renderEvents();
    });
    document.getElementById("dateSort").addEventListener("change", renderEvents);

    /* ── MODALS ── */
    function openModal(id) {
      if (id === 'postModal' && !currentUser) {
        document.getElementById('postAuthOverlay').style.display = 'flex';
      } else if (id === 'postModal') {
        document.getElementById('postAuthOverlay').style.display = 'none';
      }
      
      if (id === 'eventSubmitModal' && !currentUser) {
        document.getElementById('eventAuthOverlay').style.display = 'flex';
      } else if (id === 'eventSubmitModal') {
        document.getElementById('eventAuthOverlay').style.display = 'none';
      }

      document.getElementById(id).classList.add("open");
    }
    function closeModal(id){ document.getElementById(id).classList.remove("open"); }

    document.getElementById("newEventBtn").addEventListener("click", () => openModal("eventSubmitModal"));
    document.getElementById("newPostBtn").addEventListener("click",  () => openModal("postModal"));

    document.querySelectorAll("[data-close-modal]").forEach(btn => {
      btn.addEventListener("click", () => closeModal(btn.dataset.closeModal));
    });
    document.querySelectorAll(".modal-backdrop").forEach(backdrop => {
      backdrop.addEventListener("click", e => { if(e.target===backdrop) closeModal(backdrop.id); });
    });

    /* ── FORMS ── */
    document.getElementById("signupForm").addEventListener("submit", async e => {
      e.preventDefault();
      const status = document.getElementById("signupStatus");
      status.style.display = "none";
      
      const fullname = document.getElementById("signupName").value.trim();
      const email = document.getElementById("signupEmail").value.trim();
      const password = document.getElementById("signupPassword").value;

      try {
        const res = await fetch(`${API_BASE}?action=signup`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ fullname, email, password, csrf: window.PORTAL_CSRF_TOKEN })
        });
        const data = await res.json();
        if (data.ok) {
          currentUser = data.user;
          updateAuthUI();
          showToast("Account created! Welcome to BVRIT Portal.", "success");
          setTimeout(() => {
            closeModal("signupModal");
            // Re-render to update the buttons
            renderEvents();
          }, 1500);
        } else {
          status.textContent = data.message;
          status.style.color = "var(--accent2)";
          status.style.display = "block";
        }
      } catch (err) { 
        status.textContent = "Registration failed.";
        status.style.display = "block";
      }
    });

    document.getElementById("loginForm").addEventListener("submit", async e => {
      e.preventDefault();
      const status = document.getElementById("loginStatus");
      status.style.display = "none";
      
      const email = document.getElementById("loginEmail").value.trim();
      const password = document.getElementById("loginPassword").value;

      try {
        const res = await fetch(`${API_BASE}?action=login`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ email, password, csrf: window.PORTAL_CSRF_TOKEN })
        });
        const data = await res.json();
        if (data.ok) {
          currentUser = data.user;
          updateAuthUI();
          closeModal("loginModal");
          // Re-render to update the buttons
          renderEvents();
        } else {
          status.textContent = data.message;
          status.style.display = "block";
        }
      } catch (err) { 
        status.textContent = "Login failed.";
        status.style.display = "block";
      }
    });

    const updateCharCount = (el, limit) => {
      const countEl = el.parentElement.querySelector(".char-count");
      if (countEl) countEl.textContent = `${el.value.length} / ${limit}`;
    };

    document.getElementById("postContent").addEventListener("input", e => updateCharCount(e.target, 2000));
    document.getElementById("eventDetails").addEventListener("input", e => updateCharCount(e.target, 3000));

    document.getElementById("postForm").addEventListener("submit", async e => {
      e.preventDefault();
      const author = document.getElementById("postAuthor").value.trim();
      const meta = document.getElementById("postMeta").value.trim();
      const content = document.getElementById("postContent").value.trim();

      if (!author || !meta || !content) return;

      try {
        const res = await fetch(`${API_BASE}?action=posts`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            author,
            meta,
            content,
            csrf: window.PORTAL_CSRF_TOKEN
          })
        });
        const data = await res.json();
        if (data.ok) {
          e.target.reset();
          closeModal("postModal");
          showToast("Post submitted for approval!", "success");
        } else {
          showToast(data.message || "Failed to submit post.", "error");
        }
      } catch (err) {
        showToast("Network error while submitting post.", "error");
      }
    });

    document.getElementById("eventForm").addEventListener("submit", async e => {
      e.preventDefault();
      const title = document.getElementById("eventTitle").value.trim();
      const date = document.getElementById("eventDateTime").value;
      const location = document.getElementById("eventLocation").value.trim();
      const category = document.getElementById("eventCategory").value;
      const department = document.getElementById("eventDepartment").value;
      const details = document.getElementById("eventDetails").value.trim();
      const fee = +document.getElementById("eventFee").value;
      const teamSize = +document.getElementById("eventTeamSize").value;

      if (!title || !date || !location || !category || !department || !details) {
        showToast("Please fill all event fields.", "error");
        return;
      }

      try {
        const res = await fetch(`${API_BASE}?action=events`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            title,
            datetime: date.replace("T", " "),
            location,
            category,
            department,
            details,
            fee,
            team_size: teamSize,
            csrf: window.PORTAL_CSRF_TOKEN || ""
          })
        });
        const payload = await res.json();
        if (!res.ok || !payload.ok) {
          showToast(payload.message || "Failed to submit event.", "error");
          return;
        }
        e.target.reset();
        closeModal("eventSubmitModal");
        showToast("Event submitted and pending admin approval!", "success");
      } catch (err) {
        showToast("Network error while submitting event.", "error");
      }
    });

    /* ── RIPPLE ── */
    document.querySelectorAll(".ripple").forEach(el => {
      el.addEventListener("click", function(e) {
        const r = document.createElement("span");
        r.className = "ripple-effect";
        const rect = this.getBoundingClientRect();
        r.style.left = (e.clientX-rect.left-2)+"px";
        r.style.top  = (e.clientY-rect.top-2)+"px";
        this.appendChild(r);
        setTimeout(()=>r.remove(), 650);
      });
    });

    /* ── INIT ── */
    document.addEventListener("DOMContentLoaded", () => {
      checkAuth();
      loadEvents();
      loadPosts();
      setupFeedback();
      
      // Notif dropdown toggle
      const notifBell = document.getElementById("notifBell");
      if (notifBell) {
        notifBell.onclick = (e) => {
          e.stopPropagation();
          document.getElementById("notifDropdown").classList.toggle("open");
        };
      }
      document.addEventListener("click", () => {
        const dd = document.getElementById("notifDropdown");
        if (dd) dd.classList.remove("open");
      });
      const dd = document.getElementById("notifDropdown");
      if (dd) dd.onclick = (e) => e.stopPropagation();
    });
  </script>
</body>
</html>