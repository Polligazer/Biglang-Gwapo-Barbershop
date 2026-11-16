<?php require __DIR__ . '/lib/layout.php'; page_head('Home', 'home'); ?>
<section class="max-w-6xl mx-auto px-4 sm:px-6 pt-14 pb-12 grid lg:grid-cols-2 gap-10 items-center">
  <div>
    <p class="text-[10px] font-bold tracking-widest uppercase text-coral">Bespoke atelier · Est. 2016</p>
    <h1 class="font-serif text-5xl leading-tight mt-3">Timeless Craftsmanship, <em class="text-navy font-normal">Modern Distinction</em></h1>
    <p class="mt-5 text-lg text-ink/80 max-w-lg">Tailored haircuts, traditional hot towel straight-razor shaves, and artisanal beard sculpting in an elevated gentlemen's lounge.</p>
    <div class="mt-7 flex flex-wrap gap-3">
      <a href="book.php" class="bg-coral text-white font-bold uppercase tracking-wider text-xs px-6 py-3.5 rounded hover:opacity-90">Book Appointment →</a>
      <a href="services.php" class="bg-sand3 font-bold uppercase tracking-wider text-xs px-6 py-3.5 rounded hover:bg-sand3/70">Explore Services</a>
    </div>
  </div>
  <div class="rounded-lg bg-gradient-to-br from-navy to-ink text-sand p-10 shadow-xl min-h-[260px] flex flex-col justify-end">
    <p class="text-[10px] font-bold tracking-widest uppercase text-coral">Certified Master Guild</p>
    <p class="font-serif text-3xl mt-2">Clean razors. No compromise.</p>
  </div>
</section>
<section class="max-w-6xl mx-auto px-4 sm:px-6 grid sm:grid-cols-3 gap-4 text-sm">
  <div class="bg-white rounded-lg shadow-sm p-5"><p class="text-[10px] font-bold tracking-widest uppercase text-navy">Service hours</p><p class="font-semibold mt-1">Daily 9:00 AM – 6:00 PM</p></div>
  <div class="bg-white rounded-lg shadow-sm p-5"><p class="text-[10px] font-bold tracking-widest uppercase text-navy">Locations</p><p class="font-semibold mt-1">Three ateliers across Greater Boston</p></div>
  <div class="bg-white rounded-lg shadow-sm p-5"><p class="text-[10px] font-bold tracking-widest uppercase text-rust">Lounge hospitality</p><p class="font-semibold mt-1">Complimentary craft pour</p></div>
</section>
<section class="max-w-6xl mx-auto px-4 sm:px-6 mt-16">
  <div class="flex items-end justify-between gap-4 mb-6"><div><p class="text-[10px] font-bold tracking-widest uppercase text-navy">Precision rituals</p><h2 class="font-serif text-4xl">Master Grooming Services</h2></div>
    <a href="services.php" class="text-xs font-bold uppercase tracking-wider text-rust">View complete menu →</a></div>
  <div id="featured" class="grid md:grid-cols-3 gap-5"><p class="text-sm text-ink/60">Loading services…</p></div>
</section>
<section class="max-w-6xl mx-auto px-4 sm:px-6 mt-16">
  <p class="text-[10px] font-bold tracking-widest uppercase text-navy">Find your chair</p><h2 class="font-serif text-4xl mb-6">Our Branches</h2>
  <div id="branch-list" class="grid md:grid-cols-3 gap-5"><p class="text-sm text-ink/60">Loading branches…</p></div>
</section>
<?php page_foot(); ?>
