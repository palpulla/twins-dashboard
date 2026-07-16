const test = require('node:test');
const assert = require('node:assert/strict');
const childProcess = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..');
const LOADER = path.join(ROOT, 'mu-plugins', 'twins-staging-overhaul.php');
const SAFETY = path.join(ROOT, 'mu-plugins', 'twins-staging-safety.php');
const PACKAGE = path.join(ROOT, 'mu-plugins', 'twins-staging-overhaul');
const IL_TOOL = path.join(ROOT, 'tools', 'staging-il-provision.php');

const phpProbe = childProcess.spawnSync('php', ['-v'], { encoding: 'utf8' });
const hasPhp = phpProbe.status === 0;

function runPhp(script, args = []) {
  const result = childProcess.spawnSync('php', [path.join(__dirname, script), ...args], {
    encoding: 'utf8',
    timeout: 120_000,
  });
  assert.equal(result.status, 0, `${script} failed:\n${result.stdout}\n${result.stderr}`);
  return result.stdout.trim();
}

function phpTest(name, callback) {
  test(name, { skip: !hasPhp && 'PHP runtime is not installed locally' }, callback);
}

phpTest('all recovered PHP sources pass syntax validation', () => {
  const files = [];
  const visit = (directory) => {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
      const target = path.join(directory, entry.name);
      if (entry.isDirectory()) visit(target);
      if (entry.isFile() && target.endsWith('.php')) files.push(target);
    }
  };
  visit(ROOT);
  assert.ok(files.length >= 10, 'expected the recovered PHP package and harnesses');
  for (const file of files) {
    const result = childProcess.spawnSync('php', ['-l', file], { encoding: 'utf8' });
    assert.equal(result.status, 0, `${file} failed syntax validation:\n${result.stderr}`);
  }
});

phpTest('foundation harness validates fixed regions and navigation', () => {
  assert.equal(runPhp('staging-overhaul-foundation-harness.php', [LOADER]), 'STAGING_OVERHAUL_FOUNDATION_HARNESS_OK');
});

phpTest('root overhaul harness validates inert semantic rendering', () => {
  assert.equal(runPhp('staging-overhaul-harness.php', [LOADER]), 'STAGING_OVERHAUL_HARNESS_OK');
});

phpTest('builder harness validates the fixed local 23-product experience', () => {
  assert.equal(runPhp('staging-overhaul-builder-harness.php', [PACKAGE]), 'STAGING_OVERHAUL_BUILDER_HARNESS_OK');
});

phpTest('cost harness validates both fixed Wisconsin cost pages', () => {
  assert.equal(runPhp('staging-overhaul-cost-harness.php', [PACKAGE]), 'STAGING_OVERHAUL_COST_HARNESS_OK');
});

phpTest('bootstrap harness fails closed for every missing or false gate', () => {
  for (const scenario of ['missingEnvironment', 'wrongEnvironment', 'missingSafetyFlag', 'falseSafetyFlag', 'missingCronDisable', 'falseCronDisable']) {
    const output = JSON.parse(runPhp('staging-overhaul-bootstrap-harness.php', [LOADER, scenario]));
    assert.deepEqual(output, {
      scenario,
      status: 'refused',
      response: 503,
      implementationLoads: 0,
      hookRegistrations: 0,
    });
  }
});

phpTest('renderer harness validates every approved request state', () => {
  for (const scenario of ['routes', 'asset-versions', 'hooks', 'blog-index', 'campaign', 'family-once', 'path-contact-context', 'service-brand-chrome', 'catalog-brand-chrome', 'home-brand', 'team-brand', 'careers-brand', 'reviews-brand', 'contact-brand', 'ineligible', 'article', 'unknown-blog']) {
    assert.equal(
      runPhp('staging-overhaul-renderers-harness.php', [LOADER, scenario]),
      `STAGING_OVERHAUL_RENDERERS_HARNESS_OK:${scenario}`,
    );
  }
});

phpTest('portable brand asset versioning rejects fixed-file boundary failures', () => {
  assert.equal(
    runPhp('staging-overhaul-brand-asset-harness.php', [path.join(PACKAGE, 'renderers.php')]),
    'STAGING_OVERHAUL_BRAND_ASSET_HARNESS_OK',
  );
});

phpTest('staging brand adapters are fixed-origin, fail-closed, and invoke no side-effect primitive', () => {
  assert.equal(
    runPhp('staging-brand-adapters-harness.php', [LOADER, path.resolve(ROOT, '..', 'twins-brand-experience')]),
    'STAGING_BRAND_ADAPTERS_HARNESS_OK',
  );
});

phpTest('legacy image harness removes only the two pinned stale candidates', () => {
  assert.equal(runPhp('staging-legacy-image-srcset-harness.php', [SAFETY]), 'STAGING_LEGACY_IMAGE_SRCSET_HARNESS_OK');
});

phpTest('Illinois provisioner harness preserves its fixed fail-closed contract', () => {
  assert.equal(runPhp('staging-il-provision-harness.php', [IL_TOOL]), 'STAGING_IL_PROVISION_HARNESS_OK');
});
