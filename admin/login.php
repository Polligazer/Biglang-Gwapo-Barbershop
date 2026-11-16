<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
start_session();
if (!empty($_SESSION['admin'])) { header('Location: index.php'); exit; }
$csrf = csrf_token();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
<title>Admin Login · Biglang Gwapo Barbershop</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{cream:'#fff8f2',ink:'#4d3e3e',coral:'#fc7e65',navy:'#134373',rust:'#a43c29',sand:'#fcf2e4',sand3:'#ebe1d3'},fontFamily:{serif:['"Playfair Display"','serif'],sans:['"Plus Jakarta Sans"','sans-serif']}}}}</script>
</head>
<body class="font-sans bg-cream min-h-screen grid lg:grid-cols-2 text-ink">
<aside class="hidden lg:flex bg-ink text-sand flex-col justify-between p-12">
  <div><p class="font-serif text-3xl">Biglang Gwapo</p><p class="text-[10px] font-bold tracking-widest text-coral uppercase">Barbershop</p></div>
  <p class="font-serif text-4xl leading-snug">Every fade, taper, and razor stroke <em>deliberate.</em></p>
</aside>
<main class="flex items-center justify-center p-6">
  <form id="f" novalidate class="w-full max-w-sm bg-white rounded-lg shadow p-8 space-y-4">
    <h1 class="font-serif text-3xl">Admin sign in</h1>
    <p class="text-sm text-ink/70 -mt-2">Staff only. <a href="../index.php" class="underline">Back to the public site</a></p>
    <div><label class="block text-[10px] font-bold tracking-widest uppercase mb-1" for="u">Username</label>
      <input id="u" name="username" autocomplete="username" maxlength="50" class="w-full bg-cream border border-sand3 rounded px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-coral/50">
      <p class="text-xs text-rust min-h-[1rem] mt-1" data-err="username"></p></div>
    <div><label class="block text-[10px] font-bold tracking-widest uppercase mb-1" for="p">Password</label>
      <input id="p" name="password" type="password" autocomplete="current-password" maxlength="200" class="w-full bg-cream border border-sand3 rounded px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-coral/50">
      <p class="text-xs text-rust min-h-[1rem] mt-1" data-err="password"></p></div>
    <p id="msg" class="text-sm text-rust min-h-[1.25rem]" role="alert"></p>
    <button class="w-full bg-coral text-white font-bold uppercase tracking-wider text-sm py-3 rounded hover:opacity-90 disabled:opacity-50">Sign in</button>
  </form>
</main>
<script>
const f=document.getElementById('f'),msg=document.getElementById('msg');
f.addEventListener('submit',async e=>{
  e.preventDefault(); msg.textContent=''; f.querySelectorAll('[data-err]').forEach(p=>p.textContent='');
  const username=f.username.value.trim(),password=f.password.value;
  if(!username||!password){ if(!username)f.querySelector('[data-err=username]').textContent='Required'; if(!password)f.querySelector('[data-err=password]').textContent='Required'; return; }
  const btn=f.querySelector('button'); btn.disabled=true;
  try{
    const r=await fetch('../api/auth.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name=csrf]').content},body:JSON.stringify({action:'login',username,password})});
    const d=await r.json();
    if(d.ok) location.href='index.php'; else msg.textContent=d.error||'Sign-in failed';
  }catch{ msg.textContent='Network error. Please try again.'; }
  btn.disabled=false;
});
</script>
</body></html>
