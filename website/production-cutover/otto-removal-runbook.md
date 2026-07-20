# OTTO / Search Atlas removal — production cutover runbook

Remove the Metasync / Search Atlas **OTTO** integration from production
(twinsgaragedoors.com). Validated end to end on staging 2026-07-19.

## Why this matters

OTTO injects a client-side script (`dynamic_optimization.js`) that overlays its
**own** titles, meta descriptions, schema, and redirects, fetched from the
Search Atlas dashboard at page load. On the new site the overhaul supplies all
of that itself (Waves 1–4: managed titles/meta, LocalBusiness/Service/FAQPage
schema, per-market NAP). If OTTO is left active, its overlay **fights and
overrides the new site's SEO** — and OTTO-style behaviour is exactly what made
the `/garage-door-repair/` situation confusing (that one turned out to be a
missing-page permalink guess, but OTTO would cause real, invisible overrides).

On **staging** the plugin was already gone — only inert DB/file residue
remained, now cleaned. On **production** OTTO is very likely still **active**,
so this is a real removal, not just cleanup.

## When / who

- **In the cutover window, right after the production package deploys**, so the
  new site's own SEO is already serving before OTTO's overlay is removed (avoids
  a gap where the old site briefly loses OTTO's SEO). Removing it earlier is
  fine too; just accept a short window where the *old* site runs without OTTO.
- Needs: production **SSH** + **WP-admin** + the **Search Atlas / OTTO dashboard
  login**.
- Preconditions: the release-runbook step-0 full backup verified restorable.

> NOTE: production may use a different DB table prefix and blog IDs than staging
> (staging is `wp8y_` + subsites 3/4/5). Every command below derives the real
> prefix/tables dynamically — do not hardcode `wp8y_`.

## 1. Inventory (snapshot before touching anything)

```bash
cd ~/www/<PROD_DOCROOT>/public_html
# plugin present / active?
wp plugin list --format=csv | grep -iE 'metasync|search.?atlas|otto' || echo "no plugin row"
# is the OTTO script injected on the front end? (check a rendered page's source)
# (from a browser or authenticated curl) look for: id="sa-dynamic-optimization"
#   or src*="dynamic_optimization" or "searchatlas.com"
# options across all blogs:
wp site list --field=url | while read u; do
  echo "== $u =="; wp --url="$u" option list --search='metasync*' --format=csv 2>/dev/null | tail -n +2
done
# tables + rows:
DBN=$(wp config get DB_NAME); DBU=$(wp config get DB_USER); DBP=$(wp config get DB_PASSWORD); DBH=$(wp config get DB_HOST)
MYSQL="mysql -h$DBH -u$DBU -p$DBP $DBN -N"
$MYSQL -e 'SHOW TABLES LIKE "%metasync%"'
ls -la wp-content/metasync_data/ 2>/dev/null
```

## 2. Back up the OTTO data (reversible)

```bash
STAMP=$(date +%Y%m%d-%H%M%S)
DBN=$(wp config get DB_NAME); DBU=$(wp config get DB_USER); DBP=$(wp config get DB_PASSWORD); DBH=$(wp config get DB_HOST)
TABLES=$(mysql -h$DBH -u$DBU -p$DBP $DBN -N -e 'SHOW TABLES LIKE "%metasync%"' | tr '\n' ' ')
# tables
mysqldump -h$DBH -u$DBU -p$DBP $DBN $TABLES | gzip > ~/before-otto-cleanup-$STAMP.sql.gz
# options (one *_options table per blog)
for OT in $(mysql -h$DBH -u$DBU -p$DBP $DBN -N -e 'SHOW TABLES LIKE "%options"'); do
  mysqldump -h$DBH -u$DBU -p$DBP $DBN "$OT" \
    --where="option_name LIKE 'metasync%' OR option_name LIKE 'searchatlas%'"
done | gzip > ~/before-otto-options-$STAMP.sql.gz
echo "backups: ~/before-otto-cleanup-$STAMP.sql.gz  ~/before-otto-options-$STAMP.sql.gz"
zcat ~/before-otto-cleanup-$STAMP.sql.gz | grep -c 'CREATE TABLE'   # sanity: == table count
```
Copy both `.sql.gz` off-host before proceeding.

## 3. Deactivate + delete the plugin

```bash
# find the exact plugin slug from the inventory, e.g. "metasync/metasync.php"
SLUG=$(wp plugin list --field=name | grep -iE 'metasync|search.?atlas' | head -1)
wp plugin deactivate "$SLUG" --network 2>/dev/null; wp plugin deactivate "$SLUG" 2>/dev/null
wp plugin delete "$SLUG"
wp plugin list --format=csv | grep -iE 'metasync|search.?atlas' || echo "plugin gone"
```

## 4. Disconnect Search Atlas (manual — stops the remote overlay at the source)

In the **Search Atlas / OTTO dashboard**: remove `twinsgaragedoors.com` from the
connected sites (or pause/delete the OTTO project) and **revoke the API key**.
This guarantees OTTO stops serving dynamic changes even if any stray script tag
survives. Also confirm no OTTO script is hardcoded in a WPCode snippet or the
theme header (grep for `dynamic_optimization` / `searchatlas`); remove if found.

## 5. Clean the DB residue + data dir

```bash
DBN=$(wp config get DB_NAME); DBU=$(wp config get DB_USER); DBP=$(wp config get DB_PASSWORD); DBH=$(wp config get DB_HOST)
MYSQL="mysql -h$DBH -u$DBU -p$DBP $DBN -N"
# drop all metasync tables
DROP=$($MYSQL -e 'SHOW TABLES LIKE "%metasync%"' | paste -sd, -)
[ -n "$DROP" ] && $MYSQL -e "SET FOREIGN_KEY_CHECKS=0; DROP TABLE IF EXISTS $DROP; SET FOREIGN_KEY_CHECKS=1;"
# delete metasync options from every *_options table
for OT in $($MYSQL -e 'SHOW TABLES LIKE "%options"'); do
  $MYSQL -e "DELETE FROM $OT WHERE option_name LIKE 'metasync%' OR option_name LIKE 'searchatlas%' OR option_name LIKE '%otto%';"
done
# archive (don't delete) the data dir
mv wp-content/metasync_data ~/metasync_data-removed-$STAMP 2>/dev/null || true
```

## 6. Verify

```bash
MYSQL="mysql -h$(wp config get DB_HOST) -u$(wp config get DB_USER) -p$(wp config get DB_PASSWORD) $(wp config get DB_NAME) -N"
$MYSQL -e 'SHOW TABLES LIKE "%metasync%"' | wc -l    # expect 0
wp site list --field=url | while read u; do wp --url="$u" option list --search='metasync*' --format=count; done  # each 0
```
Then, on a rendered page: **no** `sa-dynamic-optimization` / `dynamic_optimization`
script, and titles/meta/schema come from the overhaul (view a service page —
expect `Service` + `FAQPage` + `Breadcrumb`, the new title/meta, and no
client-side URL change). Flush the SiteGround cache.

## Rollback

Re-import the backups (`~/before-otto-cleanup-*.sql.gz`,
`~/before-otto-options-*.sql.gz`), restore `metasync_data-removed-*`, and
reinstall/reactivate the plugin — or restore the full-site BlogVault point from
release-runbook step 0. Then flush cache and re-verify.
