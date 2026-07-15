<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-careers-page">
  <nav class="twins-brand-page-nav" aria-label="Careers page navigation">
    <a href="#why-twins">Why Twins</a>
    <a href="#roles">Ways to contribute</a>
    <a href="#process">What happens next</a>
    <a href="#apply">Application preview</a>
  </nav>

  <section class="twins-brand-careers-hero" aria-labelledby="twins-brand-careers-page-title">
    <div class="twins-brand-careers-hero-copy">
      <span class="twins-brand-kicker">Careers at Twins Garage Doors</span>
      <h1 id="twins-brand-careers-page-title">Do work you are proud to put your name on.</h1>
      <p>We want dependable people who care about customers, craftsmanship, and showing up ready. Tell us where you could make the crew stronger.</p>
      <a class="twins-brand-cta" href="#apply">Preview the application</a>
    </div>
    <div class="twins-brand-careers-hero-image">
      <?php
      $logicalKey = 'crew-fleet';
      $sizes = '(max-width: 900px) 100vw, 52vw';
      $class = 'twins-brand-careers-crew-photo';
      $loading = 'eager';
      require dirname(__DIR__) . '/components/picture.php';
      ?>
    </div>
  </section>

  <section class="twins-brand-careers-ribbon" aria-label="What applicants can expect">
    <span>Clear expectations</span>
    <span>A customer-first crew</span>
    <span>Room to learn the craft</span>
  </section>

  <section class="twins-brand-careers-values" id="why-twins" aria-labelledby="twins-brand-careers-values-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">What winning looks like here</span>
      <h2 id="twins-brand-careers-values-title">Skill matters. How you show up matters more.</h2>
      <p>Garage doors can be taught. Reliability, care, and ownership are the foundation. These are the habits we want every customer and teammate to feel.</p>
    </div>
    <div class="twins-brand-value-grid">
      <article>
        <h3>Own the outcome</h3>
        <p>Communicate clearly, follow through, and leave the work better than you found it.</p>
      </article>
      <article>
        <h3>Treat people right</h3>
        <p>Respect the customer, respect the crew, and make the next person’s job easier.</p>
      </article>
      <article>
        <h3>Keep learning</h3>
        <p>Ask good questions, practice the details, and stay coachable as the work changes.</p>
      </article>
    </div>
  </section>

  <section class="twins-brand-careers-roles" id="roles" aria-labelledby="twins-brand-careers-roles-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">Find your lane</span>
      <h2 id="twins-brand-careers-roles-title">Where could you help Twins win?</h2>
    </div>
    <div class="twins-brand-role-grid">
      <article><h3>Service and repair</h3><p>Diagnose problems, explain options, and restore safe operation.</p></article>
      <article><h3>Installations</h3><p>Deliver clean, careful work on new and replacement doors.</p></article>
      <article><h3>Sales and estimates</h3><p>Listen well, educate clearly, and help customers choose confidently.</p></article>
      <article><h3>Customer care and operations</h3><p>Keep communication, scheduling, and the day moving smoothly.</p></article>
      <article><h3>Something else</h3><p>Tell us about a skill set that could make the team stronger.</p></article>
    </div>
    <p>Submitting your interest does not guarantee that a position is currently open.</p>
    <div class="twins-brand-careers-work-image">
      <?php
      $logicalKey = 'technician-at-work';
      $sizes = '(max-width: 900px) 100vw, 48vw';
      $class = 'twins-brand-careers-technician-photo';
      $loading = 'lazy';
      require dirname(__DIR__) . '/components/picture.php';
      ?>
    </div>
  </section>

  <section class="twins-brand-careers-process" id="process" aria-labelledby="twins-brand-careers-process-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">A simple hiring path</span>
      <h2 id="twins-brand-careers-process-title">No black hole. Just clear next steps.</h2>
      <?php if ($environment === 'staging'): ?>
        <p>This private staging page previews the intended path only. It cannot send an application or contact an applicant.</p>
      <?php else: ?>
        <p>The application experience explains each step and the next action available to an applicant.</p>
      <?php endif; ?>
    </div>
    <div class="twins-brand-process-grid">
      <article><strong>01</strong><h3>Share your interest</h3><p>Preview the essentials and the area where you could contribute.</p></article>
      <article><strong>02</strong><h3>Quick screen</h3><p>See where job-related screening would fit after an application is received.</p></article>
      <article><strong>03</strong><h3>Meet the team</h3><p>See where an interview would fit for applicants selected to continue.</p></article>
      <article><strong>04</strong><h3>Clear decision</h3><p>See how the intended path reaches a documented next step after an interview.</p></article>
    </div>
  </section>

  <section class="twins-brand-careers-application" id="apply" aria-labelledby="twins-brand-careers-application-title">
    <span class="twins-brand-kicker">Application experience</span>
    <h2 id="twins-brand-careers-application-title">Tell us where you could make an impact.</h2>
    <p>Submitting your interest does not guarantee that a position is currently open.</p>
    <?= $experience->applicationAdapter()->renderExperience($context) ?>
  </section>

  <section class="twins-brand-final-cta" aria-labelledby="twins-brand-careers-quote-title">
    <h2 id="twins-brand-careers-quote-title">Need garage door help instead?</h2>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  </section>
</main>
