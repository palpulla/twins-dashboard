<?php
declare(strict_types=1);

// Email-capture popup - template-owned chrome, like header.php / footer.php.
// Spec + approved copy (byte-for-byte):
//   docs/marketing/website-rebuild/copy/popup-email-capture.md
//
// Sealed-system split: this component owns the markup for both environments;
// the behavior contract (20s / exit-intent trigger, focus trap, Esc, 180-day
// twins-popup-v1 localStorage suppression) lives in twins-brand.js, which is
// contract-banned from transport. The one network submission ships ONLY in the
// production-only script (production-cutover/production-popup.js), which posts
// to the endpoint the SERVER declares on data-popup-endpoint below. The client
// never selects a URL. On staging the fields are inert (no <form>, no named
// controls) and finishing the flow reveals the standing private-preview notice
// through the same guarded status pattern the quote preview uses.

$twinsPopupConfig = require dirname(__DIR__) . '/config/popup.php';
if (!is_array($twinsPopupConfig) || ($twinsPopupConfig['enabled'] ?? false) !== true) {
    return;
}

// Route suppression, enforced server-side (the staging host additionally gates
// in twins_overhaul_should_render_popup before rendering this component):
// never on the contact page, the legal/thank-you family, campaign-preserve
// routes, or the door builder.
$twinsPopupClassification = isset($context['classification']) && is_string($context['classification'])
    ? $context['classification']
    : null;
if (in_array($twinsPopupClassification, ['campaign-preserve', 'legal-preserve', 'contact-brand', 'builder'], true)) {
    return;
}

$twinsPopupEndpoint = null;
if ($environment === 'production') {
    // The endpoint is a server-side declaration, exactly like the callback
    // form's data-callback-endpoint. Without a valid declaration the popup
    // does not render at all: a popup that cannot submit anywhere must not
    // ask for an email address.
    if (!defined('TWINS_POPUP_LEAD_ENDPOINT') || !is_string(TWINS_POPUP_LEAD_ENDPOINT)) {
        return;
    }
    $twinsPopupParts = parse_url(TWINS_POPUP_LEAD_ENDPOINT);
    if (
        !is_array($twinsPopupParts)
        || ($twinsPopupParts['scheme'] ?? '') !== 'https'
        || ($twinsPopupParts['host'] ?? '') !== 'jwrpjuqaynownxaoeayi.supabase.co'
        || isset($twinsPopupParts['user'])
        || isset($twinsPopupParts['pass'])
        || isset($twinsPopupParts['query'])
        || isset($twinsPopupParts['fragment'])
    ) {
        return;
    }
    $twinsPopupEndpoint = TWINS_POPUP_LEAD_ENDPOINT;
}
?>
<div class="twins-popup" data-twins-popup data-popup-rendered-at="<?= (int) time() ?>"<?= $twinsPopupEndpoint !== null ? ' data-popup-endpoint="' . htmlspecialchars($twinsPopupEndpoint, ENT_QUOTES, 'UTF-8') . '"' : '' ?> hidden>
  <div class="twins-popup-dialog" role="dialog" aria-modal="true" aria-labelledby="twins-popup-title" data-popup-dialog>
    <button type="button" class="twins-popup-close" data-popup-close aria-label="Close"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M5 5 L19 19 M19 5 L5 19" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round"/></svg></button>
    <div class="twins-popup-art" aria-hidden="true">
      <img src="<?= htmlspecialchars($experience->asset('twin-right'), ENT_QUOTES, 'UTF-8') ?>" width="297" height="538" alt="" loading="lazy" decoding="async">
    </div>
    <div class="twins-popup-body">
      <h2 class="twins-popup-title" id="twins-popup-title">FIRST DIBS ON TUNE-UP SEASON</h2>
      <p class="twins-popup-copy">One useful email when it matters. Spring and fall maintenance reminders, current offers like the $49 tune-up, and what Wisconsin weather is about to do to your garage door. No spam, unsubscribe any time.</p>
      <?php if ($environment === 'production'): ?>
      <form class="twins-popup-form" novalidate>
        <label class="twins-popup-field">Email address
          <input type="email" name="email" autocomplete="email" required aria-describedby="twins-popup-status">
        </label>
        <input type="text" name="website" class="twins-popup-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button type="submit" class="twins-popup-submit">SIGN ME UP</button>
        <p id="twins-popup-status" class="twins-popup-status" role="status" aria-live="polite" hidden data-popup-status>Thanks! You are on the list.</p>
      </form>
      <?php else: ?>
      <div class="twins-popup-form twins-popup-form--preview" role="form" aria-labelledby="twins-popup-title" data-popup-preview>
        <label class="twins-popup-field">Email address
          <input type="email" autocomplete="email" aria-describedby="twins-popup-status">
        </label>
        <button type="button" class="twins-popup-submit" data-preview-finalize>SIGN ME UP</button>
        <p id="twins-popup-status" class="twins-popup-status" role="status" aria-live="polite" hidden data-preview-status>This private staging preview does not submit or store lead information.</p>
      </div>
      <?php endif; ?>
      <button type="button" class="twins-popup-decline" data-popup-decline>No thanks</button>
      <p class="twins-popup-fineprint">We send a handful of emails a year. Unsubscribe with one click.</p>
      <?php if ($environment === 'staging'): ?>
      <p class="twins-popup-staging-note">This private staging preview does not submit or store lead information.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
