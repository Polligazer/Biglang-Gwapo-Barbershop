<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
require_admin_page(); // protected: unauthenticated visitors are redirected to login.php
$csrf = csrf_token();
$adminName = $_SESSION['admin']['name'];
$adminRole = $_SESSION['admin']['role'] ?? 'global_admin';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="api" content="../api/">
<meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
<title>Admin · Biglang Gwapo Barbershop</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{cream:'#fff8f2',ink:'#4d3e3e',coral:'#fc7e65',navy:'#134373',blue:'#315b8c',rust:'#a43c29',sand:'#fcf2e4',sand2:'#f7edde',sand3:'#ebe1d3'},fontFamily:{serif:['"Playfair Display"','serif'],sans:['"Plus Jakarta Sans"','sans-serif']}}}}</script>
</head>
<body class="font-sans bg-cream text-ink min-h-screen">
<aside class="bg-ink text-sand3 lg:fixed lg:inset-y-0 lg:w-72 flex lg:flex-col justify-between p-4 lg:py-6 gap-4">
  <div class="flex lg:flex-col gap-4 lg:gap-6 items-center lg:items-stretch overflow-x-auto">
    <div class="lg:px-4 shrink-0"><p class="font-serif text-2xl text-sand">Biglang Gwapo</p><p class="text-[10px] font-bold tracking-widest text-coral uppercase">Barbershop</p></div>
    <nav class="flex lg:flex-col gap-1" aria-label="Main">
      <a href="#dashboard" data-nav="dashboard" class="nav">Dashboard</a>
      <a href="#calendar" data-nav="calendar" class="nav">Calendar</a>
      <a href="#appointments" data-nav="appointments" class="nav">Appointments</a>
      <a href="#barbers" data-nav="barbers" class="nav">Barbers</a>
      <a href="#availability" data-nav="availability" class="nav">Unavailable Dates</a>
      <?php if ($adminRole === 'global_admin'): ?><a href="#services" data-nav="services" class="nav">Services</a><?php endif; ?>
      <a href="#inventory" data-nav="inventory" class="nav">Inventory</a>
      <?php if ($adminRole === 'global_admin'): ?><a href="#admins" data-nav="admins" class="nav">Admin Users</a><a href="#audit" data-nav="audit" class="nav">Audit Log</a><?php endif; ?>
    </nav>
  </div>
  <a href="../index.php" class="text-xs font-semibold px-4 py-2 rounded hover:bg-white/10 shrink-0">← Public site</a>
  <button data-action="logout" class="text-left text-xs font-bold uppercase tracking-wider px-4 py-2 rounded bg-white/10 hover:bg-white/20 shrink-0">Sign out</button>
</aside>
<style>.nav{display:block;padding:.5rem 1rem;border-radius:.25rem;font-weight:600;white-space:nowrap}.nav:hover{background:rgba(255,255,255,.08)}.nav.on{background:#fc7e65;color:#fff}</style>
<main id="main" class="lg:ml-72 p-4 sm:p-8 max-w-6xl" data-admin="<?= htmlspecialchars($adminName, ENT_QUOTES) ?>" data-role="<?= htmlspecialchars($adminRole, ENT_QUOTES) ?>" aria-live="polite"></main>
<div id="modal" class="fixed inset-0 z-40 hidden items-start justify-center overflow-y-auto bg-ink/60 p-4 sm:p-10" role="dialog" aria-modal="true"></div>
<div id="toasts" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>
<script src="../assets/admin.js"></script>
</body></html>
