<?php
// Shared layout for PUBLIC pages (navbar + footer). Starts a session only to issue the booking CSRF token.
declare(strict_types=1);
require_once __DIR__ . '/core.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function page_head(string $title, string $active): void
{
    $csrf = csrf_token();
    $links = ['home' => ['index.php', 'Home'], 'services' => ['services.php', 'Services'], 'branches' => ['branches.php', 'Branches']];
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= h($csrf) ?>">
<title><?= h($title) ?> · Biglang Gwapo Barbershop</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{cream:'#fff8f2',ink:'#4d3e3e',coral:'#fc7e65',navy:'#134373',blue:'#315b8c',rust:'#a43c29',sand:'#fcf2e4',sand2:'#f7edde',sand3:'#ebe1d3'},fontFamily:{serif:['"Playfair Display"','serif'],sans:['"Plus Jakarta Sans"','sans-serif']}}}}</script>
</head>
<body class="font-sans bg-cream text-ink min-h-screen flex flex-col" data-page="<?= h($active) ?>">
<header class="bg-navy text-white sticky top-0 z-30">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
    <a href="index.php" class="font-serif text-xl leading-tight">Biglang Gwapo <span class="block text-[10px] font-sans font-bold tracking-widest text-coral uppercase">Barbershop</span></a>
    <nav class="hidden md:flex items-center gap-1 text-sm font-semibold" aria-label="Main">
      <?php foreach ($links as $k => [$href, $label]): ?>
        <a href="<?= $href ?>" class="px-3 py-2 rounded <?= $k === $active ? 'bg-white/15' : 'hover:bg-white/10' ?>"><?= $label ?></a>
      <?php endforeach; ?>
      <a href="book.php" class="ml-2 bg-coral text-white px-4 py-2 rounded font-bold uppercase tracking-wider text-xs hover:opacity-90">Book Now</a>
      <a href="admin/login.php" class="ml-3 text-[11px] text-white/60 hover:text-white border border-white/25 rounded px-2 py-1">Admin Login</a>
    </nav>
    <button data-menu class="md:hidden px-3 py-2 rounded bg-white/10 text-sm font-semibold" aria-label="Menu" aria-controls="mnav">Menu</button>
  </div>
  <nav id="mnav" class="hidden md:hidden px-4 pb-4 flex flex-col gap-1 text-sm font-semibold">
    <?php foreach ($links as [$href, $label]): ?><a href="<?= $href ?>" class="px-3 py-2 rounded hover:bg-white/10"><?= $label ?></a><?php endforeach; ?>
    <a href="book.php" class="bg-coral text-center px-4 py-2 rounded font-bold uppercase tracking-wider text-xs">Book Now</a>
    <a href="admin/login.php" class="text-[11px] text-white/60 px-3 py-2">Admin Login</a>
  </nav>
</header>
<main class="flex-1">
<?php
}

function page_foot(): void
{
    ?></main>
<footer class="bg-ink text-sand3 mt-16">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 grid sm:grid-cols-3 gap-8 text-sm">
    <div><p class="font-serif text-xl text-sand">Biglang Gwapo Barbershop</p><p class="mt-2 text-sand3/80">Refined craft, bespoke barbering, and modern grooming rituals.</p></div>
    <div><p class="text-[10px] font-bold tracking-widest uppercase text-coral mb-2">Explore</p>
      <a class="block hover:text-white" href="services.php">Services &amp; Products</a><a class="block hover:text-white" href="branches.php">About &amp; Branches</a><a class="block hover:text-white" href="book.php">Book an appointment</a></div>
    <div><p class="text-[10px] font-bold tracking-widest uppercase text-coral mb-2">Hours</p><p>Daily 9:00 AM – 6:00 PM</p><a href="admin/login.php" class="inline-block mt-3 text-xs text-sand3/60 hover:text-white">Admin Login</a></div>
  </div>
</footer>
<script src="assets/public.js"></script>
</body></html>
<?php
}
