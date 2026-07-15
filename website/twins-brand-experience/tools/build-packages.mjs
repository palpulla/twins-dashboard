import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const website = path.resolve(root, '..');
const dist = path.join(root, 'dist');
const allowedArguments = new Set(['', '--check']);
const argument = process.argv[2] || '';
if (process.argv.length > 3 || !allowedArguments.has(argument)) fail('INVALID_ARGUMENT');

const MAX_FILES = 4096;
const MAX_FILE_SIZE = 64 * 1024 * 1024;
const manifests = {
  staging: readJson(path.join(root, 'manifests/staging-runtime.json')),
  verification: readJson(path.join(root, 'manifests/host-verification.json')),
};

function fail(status, detail = '') {
  process.stdout.write(`${JSON.stringify({ status, detail, writeAuthority: false, productionWriteAuthority: false })}\n`);
  process.exit(1);
}

function readJson(file) {
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch {
    fail('MANIFEST_INVALID', path.basename(file));
  }
}

function sha256(bytes) {
  return crypto.createHash('sha256').update(bytes).digest('hex');
}

function byteSort(a, b) {
  return Buffer.from(a).compare(Buffer.from(b));
}

function safeRelative(value) {
  return typeof value === 'string' && value !== '' && !path.isAbsolute(value) &&
    !value.split('/').some(part => part === '' || part === '.' || part === '..' || part.includes('\\'));
}

function resolveBeneath(base, relative) {
  if (!safeRelative(relative)) fail('PATH_INVALID', relative);
  const resolved = path.resolve(base, relative);
  if (!resolved.startsWith(`${base}${path.sep}`)) fail('PATH_ESCAPE', relative);
  return resolved;
}

function verifySource(entry, destinationRequired) {
  if (!entry || !['deploy', 'verify-prerequisite', 'verify'].includes(entry.role)) fail('ROLE_INVALID');
  if (!/^(?:twins-brand-experience|staging-safety)\//.test(entry.source)) fail('SOURCE_NAMESPACE_INVALID', entry.source);
  if (!Number.isSafeInteger(entry.size) || entry.size < 0 || entry.size > MAX_FILE_SIZE) fail('SOURCE_SIZE_INVALID', entry.source);
  if (!/^[a-f0-9]{64}$/.test(entry.sha256)) fail('SOURCE_HASH_INVALID', entry.source);
  if (destinationRequired && !safeRelative(entry.destination)) fail('DESTINATION_INVALID', entry.destination);
  if (!destinationRequired && Object.hasOwn(entry, 'destination')) fail('VERIFICATION_DESTINATION_FORBIDDEN', entry.source);
  const source = resolveBeneath(website, entry.source);
  let stat;
  try { stat = fs.lstatSync(source); } catch { fail('SOURCE_MISSING', entry.source); }
  if (!stat.isFile() || stat.isSymbolicLink()) fail('SOURCE_NOT_REGULAR', entry.source);
  const bytes = fs.readFileSync(source);
  if (bytes.length !== entry.size || sha256(bytes) !== entry.sha256) fail('SOURCE_DRIFT', entry.source);
  return { source, bytes };
}

function validateManifest(manifest, kind) {
  if (manifest.schemaVersion !== 1 || manifest.productionWriteAuthority !== false || !Array.isArray(manifest.files)) {
    fail('MANIFEST_SCHEMA_INVALID', kind);
  }
  if (manifest.files.length === 0 || manifest.files.length > MAX_FILES) fail('MANIFEST_FILE_COUNT_INVALID', kind);
  if (kind === 'staging' && (manifest.applicationIdentity !== 'https://danielj140.sg-host.com/' || manifest.environment !== 'staging')) {
    fail('APPLICATION_IDENTITY_INVALID');
  }
  const seenSource = new Set();
  const seenDestination = new Set();
  let previous = null;
  for (const entry of manifest.files) {
    verifySource(entry, kind === 'staging');
    if (seenSource.has(entry.source)) fail('DUPLICATE_SOURCE', entry.source);
    seenSource.add(entry.source);
    if (kind === 'staging') {
      if (seenDestination.has(entry.destination)) fail('DUPLICATE_DESTINATION', entry.destination);
      seenDestination.add(entry.destination);
      if (previous !== null && byteSort(previous, entry.destination) >= 0) fail('MANIFEST_NOT_SORTED', entry.destination);
      previous = entry.destination;
      if (entry.role === 'deploy' && (/\/(?:production|tests)\//.test(`/${entry.source}/`) || !/^(?:twins-brand-experience|twins-staging-overhaul)\//.test(entry.destination))) {
        fail('STAGING_DEPLOY_SCOPE_INVALID', entry.source);
      }
    }
  }
}

function canonicalHash(entries, destinationField) {
  const hash = crypto.createHash('sha256');
  const sorted = [...entries].sort((a, b) => byteSort(destinationField ? a.destination : a.source, destinationField ? b.destination : b.source));
  for (const entry of sorted) {
    const fields = [destinationField ? entry.destination : entry.source, String(entry.size), entry.sha256];
    for (const field of fields) {
      const bytes = Buffer.from(field);
      hash.update(Buffer.from(`${bytes.length}:`));
      hash.update(bytes);
    }
  }
  return hash.digest('hex');
}

function listRegularFiles(base) {
  if (!fs.existsSync(base)) return [];
  const files = [];
  const walk = directory => {
    for (const name of fs.readdirSync(directory).sort(byteSort)) {
      const absolute = path.join(directory, name);
      const stat = fs.lstatSync(absolute);
      if (stat.isSymbolicLink()) fail('OUTPUT_SYMLINK', path.relative(base, absolute));
      if (stat.isDirectory()) walk(absolute);
      else if (stat.isFile()) files.push(path.relative(base, absolute).split(path.sep).join('/'));
      else fail('OUTPUT_NOT_REGULAR', path.relative(base, absolute));
    }
  };
  walk(base);
  return files.sort(byteSort);
}

function copyRegular(source, destination) {
  fs.mkdirSync(path.dirname(destination), { recursive: true, mode: 0o755 });
  fs.copyFileSync(source, destination);
  fs.chmodSync(destination, 0o644);
}

validateManifest(manifests.staging, 'staging');
validateManifest(manifests.verification, 'verification');

const deployEntries = manifests.staging.files.filter(entry => entry.role === 'deploy');
const prerequisiteEntries = manifests.staging.files.filter(entry => entry.role === 'verify-prerequisite');
const packageHashes = {
  deployPackageSha256: canonicalHash(deployEntries, true),
  prerequisiteSetSha256: canonicalHash(prerequisiteEntries, true),
  hostVerificationSha256: canonicalHash(manifests.verification.files, false),
};

const stagingBase = path.join(dist, 'staging-runtime');
const stagingPayload = path.join(stagingBase, 'wp-content/mu-plugins');
const verificationBase = path.join(dist, 'host-verification');
const metadata = Buffer.from(`${JSON.stringify({
  schemaVersion: 1,
  productionWriteAuthority: false,
  ...packageHashes,
}, null, 2)}\n`);

if (argument === '') {
  fs.rmSync(stagingBase, { recursive: true, force: true });
  fs.rmSync(verificationBase, { recursive: true, force: true });
  for (const entry of deployEntries) copyRegular(resolveBeneath(website, entry.source), resolveBeneath(stagingPayload, entry.destination));
  for (const entry of manifests.verification.files) copyRegular(resolveBeneath(website, entry.source), resolveBeneath(verificationBase, entry.source));
  copyRegular(path.join(root, 'manifests/staging-runtime.json'), path.join(stagingBase, 'staging-runtime.json'));
  copyRegular(path.join(root, 'manifests/host-verification.json'), path.join(verificationBase, 'host-verification.json'));
  fs.writeFileSync(path.join(stagingBase, 'package-metadata.json'), metadata, { mode: 0o644 });
  fs.writeFileSync(path.join(verificationBase, 'package-metadata.json'), metadata, { mode: 0o644 });
} else {
  const expectedStaging = deployEntries.map(entry => `wp-content/mu-plugins/${entry.destination}`)
    .concat(['package-metadata.json', 'staging-runtime.json']).sort(byteSort);
  const expectedVerification = manifests.verification.files.map(entry => entry.source)
    .concat(['host-verification.json', 'package-metadata.json']).sort(byteSort);
  if (JSON.stringify(listRegularFiles(stagingBase)) !== JSON.stringify(expectedStaging)) fail('STAGING_OUTPUT_NOT_CLOSED');
  if (JSON.stringify(listRegularFiles(verificationBase)) !== JSON.stringify(expectedVerification)) fail('VERIFICATION_OUTPUT_NOT_CLOSED');
  for (const entry of deployEntries) {
    const copied = fs.readFileSync(resolveBeneath(stagingPayload, entry.destination));
    if (copied.length !== entry.size || sha256(copied) !== entry.sha256) fail('STAGING_COPY_DRIFT', entry.destination);
  }
  for (const entry of manifests.verification.files) {
    const copied = fs.readFileSync(resolveBeneath(verificationBase, entry.source));
    if (copied.length !== entry.size || sha256(copied) !== entry.sha256) fail('VERIFICATION_COPY_DRIFT', entry.source);
  }
  if (!fs.readFileSync(path.join(stagingBase, 'package-metadata.json')).equals(metadata) ||
      !fs.readFileSync(path.join(verificationBase, 'package-metadata.json')).equals(metadata)) fail('PACKAGE_METADATA_DRIFT');
  if (!fs.readFileSync(path.join(stagingBase, 'staging-runtime.json')).equals(fs.readFileSync(path.join(root, 'manifests/staging-runtime.json')))) fail('STAGING_MANIFEST_COPY_DRIFT');
  if (!fs.readFileSync(path.join(verificationBase, 'host-verification.json')).equals(fs.readFileSync(path.join(root, 'manifests/host-verification.json')))) fail('VERIFICATION_MANIFEST_COPY_DRIFT');
}

process.stdout.write(`${JSON.stringify({
  status: argument === '--check' ? 'STAGING_PACKAGES_VERIFIED' : 'STAGING_PACKAGES_BUILT',
  writeAuthority: false,
  productionWriteAuthority: false,
  productionPackage: 'DEFERRED',
  ...packageHashes,
})}\n`);
