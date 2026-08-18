<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/door-art.php';

$homeTeamMembers = [
    ['name' => 'Daniel Joseph', 'role' => 'Co-Founder and CEO', 'picture' => 'daniel-portrait', 'initials' => 'DJ'],
    ['name' => 'Tal Joseph', 'role' => 'Co-Founder', 'picture' => 'tal-portrait', 'initials' => 'TJ'],
    ['name' => 'Charles Rue', 'role' => 'Field Operations Manager', 'picture' => 'charles-portrait', 'initials' => 'CR'],
    ['name' => 'Maurice Williams', 'role' => 'Senior Technician', 'picture' => 'maurice-portrait', 'initials' => 'MW'],
    ['name' => 'Nicholas Roccaforte', 'role' => 'Technician', 'picture' => 'nicholas-portrait', 'initials' => 'NR'],
    ['name' => 'Ivory Tianga', 'role' => 'Customer Service Representative', 'picture' => null, 'initials' => 'IT'],
    ['name' => 'Aman Kharga', 'role' => 'Operations Manager', 'picture' => null, 'initials' => 'AK'],
];
?>
<section class="twins-brand-why-scene" data-home-scene="why-twins" data-home-why aria-labelledby="twins-brand-why-title" data-home-reveal data-home-motion>
  <div class="twins-brand-why-copy">
    <span class="twins-brand-kicker">Why Twins</span>
    <h2 id="twins-brand-why-title">We take care of garage doors and the people who own them.</h2>
    <ol>
      <li><strong>Options before work begins</strong><span>Review the findings and exact price before approving a repair.</span></li>
      <li><strong>Real local technicians</strong><span>Meet the people and equipment behind the service.</span></li>
      <li><strong>Call or book online</strong><span>Use the verified contact path shown for your market.</span></li>
      <li><strong>Verified customer feedback</strong><span>Read feedback from the captured Google Business Profile collection.</span></li>
    </ol>
    <span class="twins-brand-character-stage">
      <img src="<?= htmlspecialchars($experience->asset('twin-right'), ENT_QUOTES, 'UTF-8') ?>" width="297" height="538" alt="" aria-hidden="true" loading="lazy" decoding="async">
      <a class="twins-brand-cta twins-brand-why-action" href="<?= htmlspecialchars($homeRoutes['team'], ENT_QUOTES, 'UTF-8') ?>">Meet the Team</a>
    </span>
  </div>
  <div
    class="twins-brand-home-team-roster"
    role="region"
    aria-label="The Twins Garage Doors team"
    tabindex="0"
  >
    <div class="twins-brand-home-team-track">
      <?php foreach ($homeTeamMembers as $teamMember): ?>
        <article class="twins-brand-home-team-person">
          <div class="twins-brand-home-team-media">
            <?php if (is_string($teamMember['picture'])): ?>
              <?php
              $logicalKey = $teamMember['picture'];
              $sizes = '(max-width: 768px) 44vw, 190px';
              $class = 'twins-brand-home-team-photo';
              $loading = 'lazy';
              require dirname(__DIR__) . '/picture.php';
              ?>
            <?php else: ?>
              <?= twins_brand_door_avatar($teamMember['initials'], 'twins-brand-home-team-avatar') ?>
            <?php endif; ?>
          </div>
          <h3><?= htmlspecialchars($teamMember['name'], ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="twins-brand-home-team-role"><?= htmlspecialchars($teamMember['role'], ENT_QUOTES, 'UTF-8') ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="twins-brand-home-team-controls" data-home-team-controls hidden>
    <button type="button" class="twins-brand-home-team-arrow" data-home-team-prev aria-label="Previous team members">&#8249;</button>
    <button type="button" class="twins-brand-home-team-arrow" data-home-team-next aria-label="Next team members">&#8250;</button>
  </div>
</section>

<section class="twins-brand-door-scene" data-home-scene="doors-and-brands" aria-labelledby="twins-brand-doors-title" data-home-reveal>
  <div class="twins-brand-door-media">
    <?php
    $logicalKey = 'door-builder-before-after';
    $sizes = '(max-width: 768px) 100vw, 58vw';
    $class = 'twins-brand-door-photo';
    $loading = 'lazy';
    require dirname(__DIR__) . '/picture.php';
    ?>
  </div>
  <div class="twins-brand-door-copy">
    <span class="twins-brand-kicker">Repair or replace</span>
    <h2 id="twins-brand-doors-title">A new door, without the pressure.</h2>
    <p>If repair is still the sound choice, we explain it. If replacement makes more sense, compare available construction and design options.</p>
    <a class="twins-brand-cta" href="<?= htmlspecialchars($homeRoutes['door-builder'], ENT_QUOTES, 'UTF-8') ?>">Design Your Door</a>
    <a href="<?= htmlspecialchars($homeRoutes['garage-doors'], ENT_QUOTES, 'UTF-8') ?>">Explore Garage Doors</a>
  </div>
  <div class="twins-brand-partner-rail" data-home-partners aria-label="Brands installed by Twins">
    <span>Brands installed by Twins</span><strong>Clopay</strong><strong>LiftMaster</strong>
  </div>
</section>
