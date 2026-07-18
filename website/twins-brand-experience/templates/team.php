<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}

$teamOwners = [
    [
        'name' => 'Daniel Joseph',
        'role' => 'Co-Founder and CEO',
        'picture' => 'daniel-portrait',
        'paras' => [
            'Daniel Joseph is the Co-Founder and CEO of Twins Garage Doors. He helped build the company from the ground up with a focus on honest service, quality workmanship, and a better customer experience in the garage door industry.',
            'Daniel leads the company vision, operations, team development, and customer standards. His role is centered on building a strong company culture, training reliable technicians, and making sure every customer receives professional, transparent service from start to finish.',
            'Outside of work, Daniel is an accomplished triathlete. He competed in two U.S. Collegiate National Championships, completed a full Ironman, and participated in a 70.3 World Championship. That same discipline, endurance, and commitment to constant improvement shape the way he leads Twins Garage Doors every day.',
            'With a background in Sustainable Environmental Design from the University of California, Davis, Daniel brings a practical and systems-focused approach to home service. His goal is to build a garage door company that homeowners can trust and employees can be proud to grow with.',
        ],
    ],
    [
        'name' => 'Tal Joseph',
        'role' => 'Co-Founder',
        'picture' => 'tal-portrait',
        'paras' => [
            'Tal Joseph is the Co-Founder of Twins Garage Doors. He brings a strong technical and engineering background to the company, having worked hands-on as a garage door technician before pursuing graduate studies.',
            'Tal earned his degree in Chemical Engineering from Yale and is currently pursuing a PhD in Mechanical Engineering at MIT. His engineering background has helped shape the company focus on precision, training, systems, and long-term service quality.',
            'Although Tal is no longer in the field day to day, his technical experience continues to influence how Twins Garage Doors trains technicians, improves processes, and approaches garage door service with a higher level of professionalism.',
        ],
    ],
];

$teamPromises = [
    ['Local and owner-operated', 'We are not a franchise. The Joseph twins answer to every customer personally.'],
    ['Same crew, every job', 'You will see the same trained faces on every visit. No rotating subcontractors.'],
    ['Honest, upfront pricing', 'What we tell you on the phone is what you pay when the job is done.'],
];

$teamTechnicians = [
    [
        'name' => 'Charles Rue',
        'role' => 'Field Operations Manager',
        'picture' => 'charles-portrait',
        'paras' => [
            'Hi, I am Charles. I am 36, married, and have lived in the Madison area for 20 years. I share my home with my wife and two dogs. Off the clock, I am usually outdoors. Traveling, riding motorcycles, and snowmobiling are my main hobbies.',
            'I have been with Twins Garage Doors for 5 years. What I love most about the work is meeting new people and walking them through their garage door project, from the first phone call to the final adjustment.',
        ],
    ],
    [
        'name' => 'Maurice Williams',
        'role' => 'Senior Technician',
        'picture' => 'maurice-portrait',
        'paras' => [
            'Born and raised in Chicago, Maurice Williams brings big-city work ethic, precision, and personality to every garage door he services. Maurice is known for his professionalism, attention to detail, and ability to make customers feel comfortable and informed throughout the entire process.',
            'As a Senior Technician at Twins Garage Doors, Maurice handles everything from broken springs and opener installations to complete door replacements. Customers appreciate Maurice not only for his technical skill, but for the energy he brings to every job.',
            'When Maurice pulls into your driveway, you will know that you are getting someone who genuinely cares about doing the job right the first time.',
        ],
    ],
    [
        'name' => 'Nicholas Roccaforte',
        'role' => 'Technician',
        'picture' => 'nicholas-portrait',
        'paras' => [
            'Hi, I am Nicholas with Twins Garage Doors. I take pride in being straightforward, easy to work with, and delivering real results. My goal is to make sure every customer ends up with a safe, reliable, and properly functioning garage door.',
            'I also believe in educating homeowners so you fully understand your system and feel confident in the work I recommend.',
            'In March 2026, I completed the GDF Better Your Best Technician Training program through Garage Door Freedom Academy. The course covered technical excellence, sales and service best practices, and mindset mastery.',
        ],
    ],
];
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-team-page">
  <section class="twins-brand-page-hero" aria-labelledby="twins-brand-team-title">
    <span class="twins-brand-kicker">Our Team</span>
    <h1 id="twins-brand-team-title">Meet the Twins Crew</h1>
    <p>The people behind every garage door we install, repair, and stand behind.</p>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  </section>

  <section class="twins-brand-team-owners" aria-labelledby="twins-brand-owners-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">The Joseph Brothers</span>
      <h2 id="twins-brand-owners-title">Built by twins, run like family</h2>
    </div>
    <?php foreach ($teamOwners as $teamOwner): ?>
      <article class="twins-brand-person-feature">
        <div class="twins-brand-person-feature-photo">
          <?php
          $logicalKey = $teamOwner['picture'];
          $sizes = '(max-width: 700px) 100vw, 34vw';
          $class = 'twins-brand-person-photo';
          $loading = 'lazy';
          require dirname(__DIR__) . '/components/picture.php';
          ?>
        </div>
        <div class="twins-brand-person-feature-copy">
          <h3><?= htmlspecialchars($teamOwner['name'], ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="twins-brand-person-role"><?= htmlspecialchars($teamOwner['role'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php foreach ($teamOwner['paras'] as $teamPara): ?>
            <p><?= htmlspecialchars($teamPara, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endforeach; ?>
        </div>
      </article>
    <?php endforeach; ?>
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

  <section class="twins-brand-team-technicians" aria-labelledby="twins-brand-technicians-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">Who is showing up at your door</span>
      <h2 id="twins-brand-technicians-title">Trained, background-checked, and proud of the work</h2>
    </div>
    <div class="twins-brand-technician-grid">
      <?php foreach ($teamTechnicians as $teamTechnician): ?>
        <article class="twins-brand-person-card">
          <?php
          $logicalKey = $teamTechnician['picture'];
          $sizes = '(max-width: 700px) 100vw, 30vw';
          $class = 'twins-brand-person-photo';
          $loading = 'lazy';
          require dirname(__DIR__) . '/components/picture.php';
          ?>
          <h3><?= htmlspecialchars($teamTechnician['name'], ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="twins-brand-person-role"><?= htmlspecialchars($teamTechnician['role'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php foreach ($teamTechnician['paras'] as $teamPara): ?>
            <p><?= htmlspecialchars($teamPara, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endforeach; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="twins-brand-team-careers" aria-labelledby="twins-brand-team-careers-title">
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
