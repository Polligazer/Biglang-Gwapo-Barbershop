<?php require __DIR__ . '/lib/layout.php'; page_head('About & Branches', 'branches'); ?>
<section class="max-w-6xl mx-auto px-4 sm:px-6 pt-12">
  <p class="text-[10px] font-bold tracking-widest uppercase text-coral">About the atelier</p>
  <h1 class="font-serif text-5xl mt-2 max-w-3xl">A decade of dedication to pure craft</h1>
  <p class="mt-4 text-ink/80 max-w-2xl">Sovereign Grooming Co. pairs old-world straight-razor technique with a modern lounge experience. Every chair is led by a guild-trained master barber, and every visit includes a hot lather finish.</p>
  <div class="grid sm:grid-cols-3 gap-4 mt-8 text-center">
    <div class="bg-white rounded-lg shadow-sm p-5"><p class="font-serif text-4xl text-navy">10+</p><p class="text-xs uppercase tracking-wider text-ink/60">Years of craft</p></div>
    <div class="bg-white rounded-lg shadow-sm p-5"><p class="font-serif text-4xl text-navy">3</p><p class="text-xs uppercase tracking-wider text-ink/60">Branches</p></div>
    <div class="bg-white rounded-lg shadow-sm p-5"><p class="font-serif text-4xl text-navy">4.9</p><p class="text-xs uppercase tracking-wider text-ink/60">Client rating</p></div>
  </div>
</section>
<section class="max-w-6xl mx-auto px-4 sm:px-6 mt-14">
  <h2 class="font-serif text-4xl mb-6">Branch information</h2>
  <div id="branches" class="grid md:grid-cols-3 gap-5"><p class="text-sm text-ink/60">Loading branches…</p></div>
</section>
<?php page_foot(); ?>
