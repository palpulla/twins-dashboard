# WordPress snippet mirrors

These files mirror WPCode snippets on twinsgaragedoors.com. **WPCode is the source of
truth; these are copies.** Nothing here deploys. After editing a snippet in wp-admin,
update the matching file here in the same change so the repo does not drift.

WPCode is **Lite**, so there is **no revision history and no undo**. Copy the existing
snippet body somewhere before you edit it.

## Which file is which snippet

| File | Snippet | Site |
|---|---|---|
| `forms-engine.snippet.html` | **6777** and **7326** | **both** — see below |
| `gform1-lp-lead-intake.snippet.php` | 7165 | main site |
| `thank-you-chooser.snippet.html` | 7330 | main site |

### The forms engine is deployed TWICE

The same engine exists as two independent WPCode snippets, because WPCode Lite is
per-site on multisite:

- **`/wi` subsite → snippet 6777.** Serves `/wi/contact-us/` and the other `/wi` pages.
- **Main site → snippet 7326.** Serves main-site landing pages such as
  `/madison-tune-up-lp/`.

**Editing one does not touch the other.** On 2026-08-10 the main-site copy was patched
first, `/wi` kept serving the old code, and it read as a caching problem for a while
before turning out to be a second copy. If you change this file, apply it to **both**
snippet IDs and verify each site separately.

## Verifying a change actually landed

1. **Saving via browser automation needs a textarea sync.** `cm.CodeMirror.setValue()`
   alone does not save: the form serializes the backing textarea, so the first Update
   silently reverts. Call `cm.save()`, set the backing textarea, click Update, then
   **reload the editor and re-read** to confirm.
2. **Hosting is WordPress.com Atomic**, not SiteGround. Responses carry
   `x-ac: … _atomic_ams` and `server-timing: a8c-cdn`. Edge caching propagates per POP,
   so a fresh edit can read stale on one node while already live on another. Re-check a
   few times with a random query string before concluding the save failed. WP Rocket's
   "Clear and Preload Cache" is on the **main site** admin bar only and does not clear
   `/wi`.
3. Confirm on the public page, not just in the editor:
   `curl -s 'https://twinsgaragedoors.com/wi/contact-us/?cb=1' | grep -c 'lp-lead-intake'`

## Anti-spam, as of 2026-08-10

Both paths now drop spam **server-side**. The history is worth keeping, because the
obvious fix was the wrong one twice over.

**Gravity Forms path (7165).** GF's spam detection was working the whole time —
1,147 spam entries against 162 real ones. But `gform_after_submission` fires for spam
entries too, and the snippet had no status check, so every spam submission GF caught
was forwarded to `lp-lead-intake` and created in GHL as source "Website LP". That junk
is what made the Call Booking Rate tile read 31.5% instead of the real 74-90%. Fixed
with a `rgar( $entry, 'status' ) === 'spam'` guard. GF's own honeypot was also off and
is now on; it renders as an extra field with a rotating decoy label.

**Wizard path (6777 / 7326).** The honeypot field existed and was checked, but only in
the browser, and the payload hardcoded `website: ""` so the server never saw it. A
client-side check is not a control: anything that posts without running the script skips
it entirely. The value is now forwarded and `lp-lead-intake` decides. The endpoint
already had the check (`pick(body, "website", "company_website", "url")`) and returns
`ok` either way, so a bot gets no signal that it was dropped.

**What a honeypot still will not stop:** humans typing into the form to sell you
services. Those are handled per-contact on the GHL side, never by a rule that guesses
from a domain or company name.

## Testing the forms without polluting the CRM

Phone **608-555-0199** merges into the existing `form pipelinetest` GHL contact
(`6fun9SqGsxdv49W8V6OA`), so it does not create new junk. Still delete the `lp_leads`
row afterwards. The Gravity Forms form has required Address/City/ZIP and a Service
checkbox — a submission missing them fails validation and proves nothing about the
honeypot.
