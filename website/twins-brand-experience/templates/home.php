<?php
declare(strict_types=1);

$reviewSummary = require dirname(__DIR__) . '/config/review-summary.php';
require dirname(__DIR__) . '/components/nav-data.php';

$homeProblemPaths = [
    ['label' => 'Door will not open', 'route' => 'repair', 'copy' => 'Could be a spring, the opener, or a snapped cable. Do not force it, that makes it worse.'],
    ['label' => 'Door will not close', 'route' => 'repair', 'copy' => 'Usually the safety sensors or the track. One of the most common calls we run.'],
    ['label' => 'Loud bang, then stuck', 'route' => 'spring-repair', 'copy' => 'That bang was almost certainly a spring breaking. Do not lift the door.'],
    ['label' => 'Crooked or off track', 'route' => 'repair', 'copy' => 'Stop using the door. A door off its track can come out of the opening.'],
    ['label' => 'Grinding or squealing', 'route' => 'repair', 'copy' => 'Rollers, hinges, or a dry track asking for help before something bigger breaks.'],
    ['label' => 'Opener will not respond', 'route' => 'openers', 'copy' => 'Remote, logic board, or power. We repair every major opener brand.'],
    ['label' => 'I hit it with the car', 'route' => 'repair', 'copy' => 'It happens more than you would think. Panels and tracks can often be repaired.'],
    ['label' => 'Need a new garage door', 'route' => 'installation', 'copy' => 'Design your door online, then we measure and quote it in person.'],
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

// Five cost-intent-first questions from the approved 2026-08-18 homepage copy.
// Every dollar figure traces to docs/marketing/website-rebuild/data/price-ranges.json
// and the job count to its 24-month completed-jobs window. The FAQPage JSON-LD in
// components/home/structured-data.php mirrors this list exactly.
$homeFaqs = [
    ['question' => 'How much does it cost to repair a garage door in Madison?', 'answer' => 'Spring replacement, the most common repair, usually lands between $575 and $1,225 on the whole ticket, and cable repairs run $325 to $625, based on our own completed jobs. You approve a flat quote before any work starts, and the service call is $0 when we do the repair.'],
    ['question' => 'How much does a new garage door cost?', 'answer' => 'A new installed single door typically runs $2,625 to $3,525, and a double $3,425 to $4,400, depending on insulation, windows, and style. Financing through GoodLeap means monthly payments are available on new doors. Our Madison cost guide breaks down real numbers from real local jobs.'],
    ['question' => 'Can I fix my garage door myself?', 'answer' => 'Please do not touch the springs or cables. They are under hundreds of pounds of tension and injure people every year. Sensors, remotes, and lubrication are fair DIY territory. Anything attached to the spring system is a call to us, and with $0 service call with repair, the diagnosis costs you nothing.'],
    ['question' => 'How fast can you get to my house?', 'answer' => 'We run same-day and emergency service across the Madison area, with more than 1,600 completed jobs in the last two years. Call us, tell us what the door is doing, and we will give you an honest arrival window, not a vague "sometime this week."'],
    ['question' => 'Do you guarantee your work?', 'answer' => 'Yes, and we named it: "Done Right, or We Make It Right." If something about our work is not right, we come back and fix it. No arguing, no fine print. We are licensed, insured, and local, so making it right is a short drive for us.'],
];

$homeServiceRoutes = [
    'repair' => $experience->route('repair', $marketKey),
    'installation' => $experience->route('installation', $marketKey),
    'opener-repair' => $experience->route('opener-repair', $marketKey),
    'emergency-service' => $experience->route('emergency-service', $marketKey),
];

// Numbered services 01 to 04 from the approved homepage copy. The spring range
// is the whole-ticket figure from data/price-ranges.json (n=432).
$homeServices = [
    ['id' => 'repair', 'label' => 'Garage Door Repair', 'route' => 'repair', 'picture' => 'technician-at-work', 'heading' => 'Springs, cables, rollers, tracks, panels.', 'copy' => 'Your tech diagnoses the problem in front of you and quotes a flat price before touching a wrench. Most spring replacements run $575 to $1,225.'],
    ['id' => 'installation', 'label' => 'Garage Door Installation', 'route' => 'installation', 'picture' => 'door-builder-before-after', 'heading' => 'New doors built for Wisconsin winters.', 'copy' => 'New Clopay doors, from insulated steel to modern full-view glass. Design your door online, then we measure and quote it in person. Financing through GoodLeap, monthly payments available on new doors.'],
    ['id' => 'opener', 'label' => 'Garage Door Openers', 'route' => 'opener-repair', 'picture' => 'technician-at-work', 'heading' => 'Openers installed and repaired.', 'copy' => 'We install LiftMaster openers and repair every major brand, belt or chain, smart or basic. If the door is fine but the motor gave up, this is your card.'],
    ['id' => 'urgent', 'label' => 'Emergency Garage Door Help', 'route' => 'emergency-service', 'picture' => 'crew-fleet', 'heading' => 'Stuck door? We get moving.', 'copy' => 'Door stuck open with a snowstorm coming? We run same-day and emergency service across the Madison area. Call us and we will get a tech headed your way.'],
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
      <p>Same-day garage door repair across Madison and southern Wisconsin. Real local techs your neighbors know by name, and a flat price you approve before any work starts.</p>
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
      <span>Family owned by twin brothers</span>
      <span>Same-day and emergency service</span>
      <span>You approve a flat price before any work starts</span>
      <span>Real local technicians, not a call center</span>
      <span aria-hidden="true">Licensed and insured</span>
      <span aria-hidden="true">Family owned by twin brothers</span>
      <span aria-hidden="true">Same-day and emergency service</span>
      <span aria-hidden="true">You approve a flat price before any work starts</span>
      <span aria-hidden="true">Real local technicians, not a call center</span>
    </div>
  </div>

  <?php require dirname(__DIR__) . '/components/home/company-story.php'; ?>
  <?php require dirname(__DIR__) . '/components/home/service-showcase.php'; ?>

  <section class="twins-brand-review-proof" data-section="review-slider" data-home-scene="customer-stories" data-review-wall data-home-reveal data-home-motion>
    <?php $reviewHeading = 'The reviews name names.'; ?>
    <?php require dirname(__DIR__) . '/components/review-slider.php'; ?>
  </section>

  <?php require dirname(__DIR__) . '/components/home/service-journey.php'; ?>
  <?php require dirname(__DIR__) . '/components/home/why-doors.php'; ?>
  <?php require dirname(__DIR__) . '/components/home/closing.php'; ?>
  <?php require dirname(__DIR__) . '/components/home/structured-data.php'; ?>
</div>
