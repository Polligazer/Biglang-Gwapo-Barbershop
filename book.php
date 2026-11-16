<?php require __DIR__ . '/lib/layout.php'; page_head('Book Appointment', 'book'); ?>
<section class="max-w-3xl mx-auto px-4 sm:px-6 pt-10">
  <p class="text-[10px] font-bold tracking-widest uppercase text-coral">Reserve your chair · no account needed</p>
  <h1 class="font-serif text-4xl mt-2 mb-6">Book an Appointment</h1>
  <div class="bg-sand rounded-lg p-4 mb-6 text-sm">
    <p><b>Payment:</b> Payment is collected in-store after your service. No deposit or online payment is required.</p>
    <p class="mt-1"><b>Late arrival policy:</b> Arriving 15 minutes late may result in your appointment time being forfeited.</p>
  </div>
  <div id="steps" class="flex gap-2 text-[10px] font-bold uppercase tracking-widest mb-6"></div>
  <div id="flow" aria-live="polite"><p class="text-sm text-ink/60">Loading…</p></div>
</section>
<?php page_foot(); ?>
