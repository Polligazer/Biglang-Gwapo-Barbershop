<?php require __DIR__ . '/lib/layout.php'; page_head('Products & Services', 'services'); ?>
<section class="max-w-6xl mx-auto px-4 sm:px-6 pt-12">
  <p class="text-[10px] font-bold tracking-widest uppercase text-coral">Products &amp; services</p>
  <h1 class="font-serif text-5xl mt-2">The Service Menu</h1>
  <p class="mt-3 text-ink/80 max-w-2xl">Mix and match: tick several services (a cut with a hot towel shave, say) and we'll schedule them back-to-back with one barber.</p>
  <div id="catalog" class="mt-8 space-y-10"><p class="text-sm text-ink/60">Loading services…</p></div>
</section>
<section class="max-w-6xl mx-auto px-4 sm:px-6 mt-16">
  <p class="text-[10px] font-bold tracking-widest uppercase text-navy">Apothecary</p><h2 class="font-serif text-4xl mb-6">Retail Products</h2>
  <div id="products" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4"><p class="text-sm text-ink/60">Loading products…</p></div>
</section>
<div id="cartbar" class="hidden fixed bottom-0 inset-x-0 z-30 bg-white border-t-4 border-coral shadow-2xl">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4"><p class="text-sm font-semibold" id="cartinfo"></p>
    <a href="book.php" class="bg-coral text-white font-bold uppercase tracking-wider text-xs px-5 py-3 rounded">Continue to booking →</a></div></div>
<?php page_foot(); ?>
