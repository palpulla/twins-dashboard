<?php
declare(strict_types=1);

$reviewSummary = require dirname(__DIR__) . '/config/review-summary.php';
require dirname(__DIR__) . '/components/nav-data.php';

$homeProblemPaths = [
    ['label' => 'Door will not open or close', 'route' => 'repair', 'copy' => 'The door is stuck, heavy, crooked, or unresponsive.'],
    ['label' => 'Broken spring', 'route' => 'spring-repair', 'copy' => 'You heard a bang, see a spring gap, or the door feels unusually heavy.'],
    ['label' => 'Loud, shaking, or off track', 'route' => 'repair', 'copy' => 'The door scrapes, tilts, rattles, or no longer travels smoothly.'],
    ['label' => 'Opener or remote problem', 'route' => 'openers', 'copy' => 'The motor, wall control, remote, or safety sensors are acting up.'],
    ['label' => 'Damaged hardware or weather seal', 'route' => 'repair', 'copy' => 'Cables, rollers, tracks, panels, or seals need attention.'],
    ['label' => 'Need a new garage door', 'route' => 'installation', 'copy' => 'Compare replacement options and design a door for your home.'],
];

$homeRoutes = [
    'repair' => $experience->route('repair', $marketKey),
    'spring-repair' => $experience->route('spring-repair', $marketKey),
    'openers' => $experience->route('openers', $marketKey),
    'opener-repair' => $experience->route('opener-repair', $marketKey),
    'emergency-service' => $experience->route('emergency-service', $marketKey),
    'installation' => $experience->route('installation', $marketKey),
    'offers' => $experience->route('offers', $marketKey),
    'team' => $experience->route('team', $marketKey),
    'reviews' => $experience->route('reviews', $marketKey),
    'garage-doors' => $experience->route('garage-doors', $marketKey),
    'door-builder' => $experience->route('door-builder', $marketKey),
    'maintenance-plans' => $experience->route('maintenance-plans', $marketKey),
    'protection-plans' => $experience->route('protection-plans', $marketKey),
    'financing' => $experience->route('financing', $marketKey),
    'faqs' => $experience->route('faqs', $marketKey),
];

if (in_array($marketKey, ['main', 'wi'], true)) {
    $homeRoutes['cost-guide'] = $experience->route('cost-guide', $marketKey);
}

$homeAreaLinks = array_slice($marketCityLinks, 0, 12);

$homeFaqs = [
    ['question' => 'How do I know whether to repair or replace the door?', 'answer' => 'A technician inspects the door, explains what can be repaired, and gives you the exact price before work begins. You can compare that with replacement options and decide without pressure.'],
    ['question' => 'What should I do if a spring breaks?', 'answer' => 'Stop using the door, keep people and vehicles clear, and do not touch the spring or loose cables. Springs are under dangerous tension and should be handled by trained professionals.'],
    ['question' => 'Why can’t the website give one repair price?', 'answer' => 'The price depends on the failed part, the door, and what the inspection finds. Twins provides the exact price before work begins rather than publishing a one-size-fits-all number.'],
    ['question' => 'Can I book online?', 'answer' => 'Yes. Use Book Online to start the current booking flow, or call the verified number shown for your service area.'],
];

$homeServiceRoutes = [
    'repair' => $experience->route('repair', $marketKey),
    'installation' => $experience->route('installation', $marketKey),
    'opener-repair' => $experience->route('opener-repair', $marketKey),
    'emergency-service' => $experience->route('emergency-service', $marketKey),
];

$homeServices = [
    ['id' => 'repair', 'label' => 'Garage Door Repair', 'route' => 'repair', 'picture' => 'technician-at-work', 'heading' => 'Find the cause. Fix the door.', 'copy' => 'We inspect the springs, cables, rollers, tracks, sections, balance, and opener connection before pricing the repair.'],
    ['id' => 'installation', 'label' => 'Garage Door Installation', 'route' => 'installation', 'picture' => 'door-builder-before-after', 'heading' => 'A new door that fits the home.', 'copy' => 'Compare construction, insulation, windows, and finishes after the opening is measured and the options are explained.'],
    ['id' => 'opener', 'label' => 'Opener Repair', 'route' => 'opener-repair', 'picture' => 'technician-at-work', 'heading' => 'Get the controls talking again.', 'copy' => 'We diagnose the motor, wall control, remotes, sensors, travel, and the door itself before recommending the next step.'],
    ['id' => 'urgent', 'label' => 'Emergency Garage Door Help', 'route' => 'emergency-service', 'picture' => 'crew-fleet', 'heading' => 'Secure the opening first.', 'copy' => 'If the door looks unsafe, stop using it, keep people and vehicles clear, and call the verified number for your market.'],
];
?>
<div id="twins-overhaul-main" class="twins-brand-page twins-brand-home">
  <section class="twins-brand-hero" data-section="brand-hero" data-home-motion>
    <div class="twins-brand-hero-media" aria-hidden="true">
      <svg class="twins-brand-hero-door" viewBox="0 0 640 330" focusable="false" aria-hidden="true">
        <rect x="0" y="0" width="640" height="316" rx="10" fill="#d7dce2"/>
        <rect x="12" y="12" width="616" height="292" rx="6" fill="#eef1f5"/>
        <g>
          <rect x="24" y="24" width="139" height="58" rx="4" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
          <rect x="175" y="24" width="139" height="58" rx="4" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
          <rect x="326" y="24" width="139" height="58" rx="4" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
          <rect x="477" y="24" width="139" height="58" rx="4" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
        </g>
        <?php foreach ([94, 164, 234] as $twinsHeroDoorRowY): ?>
          <?php foreach ([24, 175, 326, 477] as $twinsHeroDoorColX): ?>
            <rect x="<?= $twinsHeroDoorColX ?>" y="<?= $twinsHeroDoorRowY ?>" width="139" height="58" rx="4" fill="#f8fafc" stroke="#d3d9e0" stroke-width="2"/>
          <?php endforeach; ?>
        <?php endforeach; ?>
        <ellipse cx="320" cy="322" rx="300" ry="8" fill="rgba(3, 18, 43, .35)"/>
      </svg>
      <img class="twins-brand-hero-twin twins-brand-hero-twin--left" aria-hidden="true" src="<?= htmlspecialchars($experience->asset('twin-left'), ENT_QUOTES, 'UTF-8') ?>" width="196" height="534" alt="" decoding="async" fetchpriority="high">
      <img class="twins-brand-hero-twin twins-brand-hero-twin--right" aria-hidden="true" src="<?= htmlspecialchars($experience->asset('twin-right'), ENT_QUOTES, 'UTF-8') ?>" width="297" height="538" alt="" decoding="async" fetchpriority="high">
    </div>
    <div class="twins-brand-hero-copy">
      <span class="twins-brand-hero-tag">$0 Service Call With Repair</span>
      <span class="twins-brand-kicker"><?= htmlspecialchars($market['label'], ENT_QUOTES, 'UTF-8') ?> residential garage door repair</span>
      <h1>Garage door trouble? <em>Call the Twins.</em></h1>
      <p>Local technicians, a clear diagnosis, and the exact price before anything gets fixed.</p>
      <div class="twins-brand-hero-actions">
        <?php if (($booking['mode'] ?? null) === 'dialog'): ?>
          <button type="button" class="twins-brand-cta twins-brand-cta--book" data-twins-booking-open>Book Online</button>
        <?php else: ?>
          <a class="twins-brand-cta twins-brand-cta--book" href="<?= htmlspecialchars($booking['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Book Online</a>
        <?php endif; ?>
        <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call Now</a>
      </div>
      <p class="twins-brand-hero-proof">
        <span aria-hidden="true">★★★★★</span>
        <strong data-twins-live-rating><?= htmlspecialchars(number_format($reviewSummary['ratingValue'], 1), ENT_QUOTES, 'UTF-8') ?></strong>
        from <span data-twins-live-count><?= htmlspecialchars($reviewSummary['displayCount'], ENT_QUOTES, 'UTF-8') ?></span>+ verified reviews
      </p>
    </div>
    <a class="twins-brand-scroll-cue" href="#twins-brand-company-title" aria-label="Meet the Twins team">⌄</a>
  </section>

  <div class="twins-brand-proof-ticker" data-home-motion data-home-ticker tabindex="0" aria-label="Service promises">
    <div class="twins-brand-proof-track">
      <span>Licensed and insured</span>
      <span>You see the exact price before we start</span>
      <span>Springs, openers, cables, and new doors</span>
      <span>Real local technicians, not a call center</span>
      <span aria-hidden="true">Licensed and insured</span>
      <span aria-hidden="true">You see the exact price before we start</span>
      <span aria-hidden="true">Springs, openers, cables, and new doors</span>
      <span aria-hidden="true">Real local technicians, not a call center</span>
    </div>
  </div>

  <?php require dirname(__DIR__) . '/components/home/company-story.php'; ?>
  <?php require dirname(__DIR__) . '/components/home/service-showcase.php'; ?>

  <section class="twins-brand-review-proof" data-section="review-slider" data-home-scene="customer-stories" data-review-wall data-home-reveal data-home-motion>
    <?php $reviewHeading = 'The kind of service people remember.'; ?>
    <?php require dirname(__DIR__) . '/components/review-slider.php'; ?>
  </section>

  <?php require dirname(__DIR__) . '/components/home/service-journey.php'; ?>
  <?php require dirname(__DIR__) . '/components/home/why-doors.php'; ?>
  <?php require dirname(__DIR__) . '/components/home/closing.php'; ?>
</div>
