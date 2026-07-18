<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}

require_once dirname(__DIR__) . '/components/door-art.php';

$teamMembers = [
    [
        'name' => 'Daniel Joseph',
        'role' => 'Co-Founder and CEO',
        'picture' => 'daniel-portrait',
        'initials' => 'DJ',
        'paras' => [
            'Daniel Joseph is the Co-Founder and CEO of Twins Garage Doors. He helped build the company from the ground up with a focus on honest service, quality workmanship, and a better customer experience in the garage door industry.',
            'Daniel leads the company vision, operations, team development, and customer standards. His goal is to build a garage door company that homeowners can trust and employees can be proud to grow with.',
            'Outside of work, Daniel is an accomplished triathlete. He competed in two U.S. Collegiate National Championships, completed a full Ironman, and participated in a 70.3 World Championship. That same discipline shapes the way he leads Twins every day.',
        ],
    ],
    [
        'name' => 'Tal Joseph',
        'role' => 'Co-Founder',
        'picture' => 'tal-portrait',
        'initials' => 'TJ',
        'paras' => [
            'Tal Joseph is the Co-Founder of Twins Garage Doors. He brings a strong technical and engineering background to the company, having worked hands-on as a garage door technician before pursuing graduate studies.',
            'Tal earned his degree in Chemical Engineering from Yale and is currently pursuing a PhD in Mechanical Engineering at MIT. His engineering background continues to shape how Twins trains technicians, improves processes, and approaches garage door service.',
        ],
    ],
    [
        'name' => 'Charles Rue',
        'role' => 'Field Operations Manager',
        'picture' => 'charles-portrait',
        'initials' => 'CR',
        'paras' => [
            'Hi, I am Charles. I am 36, married, and have lived in the Madison area for 20 years. I share my home with my wife and two dogs. Off the clock, I am usually outdoors. Traveling, riding motorcycles, and snowmobiling are my main hobbies.',
            'I have been with Twins Garage Doors for 5 years. What I love most about the work is meeting new people and walking them through their garage door project, from the first phone call to the final adjustment.',
        ],
    ],
    [
        'name' => 'Maurice Williams',
        'role' => 'Senior Technician',
        'picture' => 'maurice-portrait',
        'initials' => 'MW',
        'paras' => [
            'Born and raised in Chicago, Maurice Williams brings big-city work ethic, precision, and personality to every garage door he services. Maurice is known for his professionalism, attention to detail, and ability to make customers feel comfortable and informed.',
            'As a Senior Technician, Maurice handles everything from broken springs and opener installations to complete door replacements. When Maurice pulls into your driveway, you know you are getting someone who cares about doing the job right the first time.',
        ],
    ],
    [
        'name' => 'Nicholas Roccaforte',
        'role' => 'Technician',
        'picture' => null,
        'initials' => 'NR',
        'paras' => [
            'Hi, I am Nicholas with Twins Garage Doors. I take pride in being straightforward, easy to work with, and delivering real results. My goal is to make sure every customer ends up with a safe, reliable, and properly functioning garage door.',
            'I believe in educating homeowners so you fully understand your system and feel confident in the work I recommend. In March 2026, I completed the GDF Better Your Best Technician Training program through Garage Door Freedom Academy.',
        ],
    ],
    [
        'name' => 'Ivory Tianga',
        'role' => 'Customer Service Representative',
        'picture' => null,
        'initials' => 'IT',
        'paras' => [
            'Ivory Tianga is the friendly voice you reach when you call Twins Garage Doors. She answers questions, books appointments, and keeps you informed from your first call to the finished job.',
            'If anything changes with your appointment, Ivory makes sure you hear about it first.',
        ],
    ],
    [
        'name' => 'Aman Kharga',
        'role' => 'Operations Manager',
        'picture' => null,
        'initials' => 'AK',
        'paras' => [
            'Aman Kharga keeps the behind-the-scenes side of Twins running smoothly. He coordinates scheduling, systems, and support so our technicians can focus on the door in front of them.',
            'His job is simple to describe and hard to do: make every visit feel effortless for the customer.',
        ],
    ],
];

$teamPromises = [
    ['Local and owner-operated', 'We are not a franchise. The Joseph twins answer to every customer personally.'],
    ['Same crew, every job', 'You will see the same trained faces on every visit. No rotating subcontractors.'],
    ['Honest, upfront pricing', 'What we tell you on the phone is what you pay when the job is done.'],
];
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-team-page">
  <section class="twins-brand-page-hero" aria-labelledby="twins-brand-team-title">
    <span class="twins-brand-kicker">Our Team</span>
    <h1 id="twins-brand-team-title">Meet the Twins Crew</h1>
    <p>The people behind every garage door we install, repair, and stand behind.</p>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  </section>

  <section class="twins-brand-team-crew-members" aria-labelledby="twins-brand-crew-members-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">Built by twins, run like family</span>
      <h2 id="twins-brand-crew-members-title">Who is behind the door</h2>
    </div>
    <div class="twins-brand-crew-grid">
      <?php foreach ($teamMembers as $teamMember): ?>
        <article class="twins-brand-person-card">
          <div class="twins-brand-person-media">
            <?php if (is_string($teamMember['picture'])): ?>
              <?php
              $logicalKey = $teamMember['picture'];
              $sizes = '(max-width: 700px) 100vw, 30vw';
              $class = 'twins-brand-person-photo';
              $loading = 'lazy';
              require dirname(__DIR__) . '/components/picture.php';
              ?>
            <?php else: ?>
              <?= twins_brand_door_avatar($teamMember['initials']) ?>
            <?php endif; ?>
          </div>
          <div class="twins-brand-person-body">
            <h3><?= htmlspecialchars($teamMember['name'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="twins-brand-person-role"><?= htmlspecialchars($teamMember['role'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php foreach ($teamMember['paras'] as $teamPara): ?>
              <p><?= htmlspecialchars($teamPara, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="twins-brand-trust-ribbon" aria-label="How the Twins crew works">
    <?php foreach ($teamPromises as [$teamPromiseTitle, $teamPromiseBody]): ?>
      <span><strong><?= htmlspecialchars($teamPromiseTitle, ENT_QUOTES, 'UTF-8') ?>.</strong> <?= htmlspecialchars($teamPromiseBody, ENT_QUOTES, 'UTF-8') ?></span>
    <?php endforeach; ?>
  </section>

  <section class="twins-brand-team-crew" aria-labelledby="twins-brand-crew-title">
    <div class="twins-brand-team-crew-image">
      <?php
      $logicalKey = 'crew-fleet';
      $sizes = '(max-width: 900px) 100vw, 62vw';
      $class = 'twins-brand-team-crew-photo';
      $loading = 'lazy';
      require dirname(__DIR__) . '/components/picture.php';
      ?>
    </div>
    <div class="twins-brand-team-crew-copy">
      <span class="twins-brand-kicker">Local people. Branded fleet.</span>
      <h2 id="twins-brand-crew-title">A crew focused on doing the work right</h2>
      <p>Clear communication, careful work, and follow-through shape the experience we want every customer to have.</p>
    </div>
  </section>

  <section class="twins-brand-team-careers" aria-labelledby="twins-brand-team-careers-title">
    <?= twins_brand_door_art('door-open', 'twins-brand-cta-art', 'team-careers') ?>
    <span class="twins-brand-kicker">Join the crew</span>
    <h2 id="twins-brand-team-careers-title">See where your skills could contribute</h2>
    <a href="<?= htmlspecialchars($experience->route('careers', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Explore Careers</a>
  </section>

  <section class="twins-brand-final-cta" aria-labelledby="twins-brand-team-quote-title">
    <h2 id="twins-brand-team-quote-title">Have the Twins crew look at your door</h2>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  </section>
</main>
