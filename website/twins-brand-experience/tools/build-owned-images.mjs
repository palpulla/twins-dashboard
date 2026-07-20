import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const check = process.argv.length === 3 && process.argv[2] === '--check';
if (process.argv.length > (check ? 3 : 2)) throw new Error('No caller-selected asset paths are accepted.');

const specs = [
  { logicalName: 'crew-fleet', original: 'assets/images/team/twins-crew-fleet.jpeg', sourceLocator: 'videos/twins-garage-doors-reel/capture/assets/best-garage-door-repair-installation-nea.jpeg', sourceEvidence: 'videos/twins-garage-doors-reel/capture/extracted/tokens.json:701-705', widths: [768, 1280, 1920], alt: 'The Twins Garage Doors crew with their branded service fleet' },
  { logicalName: 'tal-portrait', original: 'assets/images/team/tal-joseph.jpeg', sourceLocator: 'twins-content-engine/assets/instagram/library/Team Photos/tal profile pic.jpeg', sourceEvidence: 'owner-approved Twins team-photo library', widths: [480, 768, 1066], alt: 'Twins Garage Doors co-founder Tal Joseph' },
  { logicalName: 'technician-at-work', original: 'assets/images/team/twins-technician-at-work.png', sourceLocator: '/Users/daniel/Documents/Codex/2026-07-09/twins-garage-doors-brochure-redesign/outputs/assets/technician.png', sourceEvidence: 'owner-approved Twins brochure asset', widths: [480, 768, 924], alt: 'A Twins Garage Doors technician working on a garage door' },
  { logicalName: 'daniel-portrait', original: 'assets/images/team/daniel-joseph.jpeg', sourceLocator: 'https://twinsgaragedoors.com/wp-content/uploads/2026/05/Gemini_Generated_Image_8ry9ar8ry9ar8ry9-1.jpg', sourceEvidence: 'owner-published production /our-team/ page, captured 2026-07-17', widths: [480, 768, 1066], alt: 'Twins Garage Doors co-founder and CEO Daniel Joseph' },
  { logicalName: 'charles-portrait', original: 'assets/images/team/charles-rue.jpeg', sourceLocator: 'https://twinsgaragedoors.com/wp-content/uploads/2026/05/charles-rue-twins-garage-doors.jpg', sourceEvidence: 'owner-published production /our-team/ page, captured 2026-07-17', widths: [480, 768, 1066], alt: 'Twins Garage Doors field operations manager Charles Rue' },
  { logicalName: 'maurice-portrait', original: 'assets/images/team/maurice-williams.jpeg', sourceLocator: 'https://twinsgaragedoors.com/wp-content/uploads/2026/05/WhatsApp-Image-2026-05-07-at-13.28.13-1.jpeg', sourceEvidence: 'owner-published production /our-team/ page, captured 2026-07-17', widths: [480, 768, 1066], alt: 'Twins Garage Doors senior technician Maurice Williams' },
  { logicalName: 'nicholas-portrait', original: 'assets/images/team/nicholas-roccaforte.jpeg', sourceLocator: 'https://twinsgaragedoors.com/wp-content/uploads/2026/05/WhatsApp-Image-2026-04-30-at-10.30.33-1.jpeg', sourceEvidence: 'owner-published production /our-team/ page, captured 2026-07-17', widths: [480, 768, 1066], alt: 'Twins Garage Doors technician Nicholas Roccaforte' },
];

const brandSpecs = [
  { logicalName: 'logo', path: 'assets/images/brand/twins-logo.png', sha256: 'cc63412115076e387953b81e9d936a3d40559afa2edc314b912a66b79d0bc0f0', mime: 'image/png', width: 711, height: 325 },
  { logicalName: 'twin-left', path: 'assets/images/brand/twin-left.png', sha256: '267ce3a33a3bbee09f9517409523c09246ac0488182625baeb9e4cdac84b293a', mime: 'image/png', width: 196, height: 534 },
  { logicalName: 'twin-right', path: 'assets/images/brand/twin-right.png', sha256: '29daf3e0c87133635c59a22e4560fb56ac819762a7ac8e84ebfe253bfccf75fe', mime: 'image/png', width: 297, height: 538 },
  { logicalName: 'truck-original', path: 'assets/images/brand/twins-service-truck-cutout.png', sha256: 'ecd200b41f69334cf97c73bc9d85a3b59288b8174f2e9aae5c30fd27d9940bf3', mime: 'image/png', width: 1398, height: 821 },
  { logicalName: 'truck-webp', path: 'assets/images/brand/twins-service-truck-cutout.webp', sha256: 'df91d2f10c7facc90fb336f8dd229d28e80d66c6ce9d79f6d0efdc32d7127e6e', mime: 'image/webp', width: 1398, height: 821 },
].map(asset => ({ ...asset, sourceLocator: `website/staging-safety/mu-plugins/twins-staging-assets/${path.basename(asset.path)}` }));

const doorBuilderSpec = {
  logicalName: 'door-builder-before-after',
  source: '../../docs/website-overhaul/reference-sources/door-builder/twins-before-after-install-source.png',
  derivative: 'assets/images/door-builder/twins-before-after-install.webp',
  sourceSha256: '86e5c945b84c38fe5d1fe176024d443669edcdf3c77001f3e99a0a464c22138a',
  derivativeSha256: 'e9a0b6c0d5c1a25b711103a132647ab50cbb4c9b3b120c97124f000537d6e346',
  sourceLocator: 'docs/marketing/creative/2026-07-04-meta-challenger/financing_install.png',
  sourceEvidence: 'docs/marketing/creative/2026-07-04-meta-challenger/README.md:58-63; commit e27c4ae2fb37de2b6da594795b1866689c5e1746',
  crop: { left: 0, top: 0, width: 1080, height: 930 },
  approvedAlt: 'Before and after view of a real Twins garage door installation',
};

const sha256 = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const outputName = (original, width) => original.replace(/\.(jpeg|png)$/i, `-${width}w.webp`);

const assets = [];
for (const spec of specs) {
  const originalBytes = await fs.readFile(path.join(root, spec.original));
  const originalMeta = await sharp(originalBytes).metadata();
  const derivatives = [];
  for (const width of spec.widths) {
    const relative = outputName(spec.original, width);
    const absolute = path.join(root, relative);
    const generatedBytes = await sharp(originalBytes).rotate().resize({ width, withoutEnlargement: true })
      .webp({ quality: 82, effort: 6, smartSubsample: true }).toBuffer();
    let bytes = generatedBytes;
    if (check) {
      const committedBytes = await fs.readFile(absolute);
      if (Buffer.compare(generatedBytes, committedBytes) !== 0) throw new Error(`Derivative byte drift: ${relative}`);
      bytes = committedBytes;
    } else {
      await fs.writeFile(absolute, generatedBytes, { flag: 'w', mode: 0o644 });
    }
    const meta = await sharp(bytes).metadata();
    if (meta.format !== 'webp') throw new Error(`Derivative MIME drift: ${relative}`);
    derivatives.push({ path: relative, sha256: sha256(bytes), mime: 'image/webp', width: meta.width, height: meta.height });
  }
  assets.push({
    logicalName: spec.logicalName,
    importLocator: spec.sourceLocator,
    sourceEvidence: spec.sourceEvidence,
    original: { path: spec.original, sha256: sha256(originalBytes), mime: originalMeta.format === 'jpeg' ? 'image/jpeg' : 'image/png', width: originalMeta.width, height: originalMeta.height },
    approvedAlt: spec.alt,
    allowedUse: ['home', spec.logicalName === 'tal-portrait' ? 'team' : 'careers', 'team'],
    derivatives,
  });
}

const brandAssets = [];
for (const spec of brandSpecs) {
  const bytes = await fs.readFile(path.join(root, spec.path));
  const meta = await sharp(bytes).metadata();
  const expectedFormat = spec.mime === 'image/png' ? 'png' : 'webp';
  if (sha256(bytes) !== spec.sha256 || meta.format !== expectedFormat || meta.width !== spec.width || meta.height !== spec.height) throw new Error(`Brand asset mismatch: ${spec.path}`);
  brandAssets.push(spec);
}

const doorSourceBytes = await fs.readFile(path.join(root, doorBuilderSpec.source));
const doorSourceMeta = await sharp(doorSourceBytes).metadata();
if (sha256(doorSourceBytes) !== doorBuilderSpec.sourceSha256 || doorSourceMeta.format !== 'png' || doorSourceMeta.width !== 1080 || doorSourceMeta.height !== 1080) throw new Error('Door-builder source drift.');
const generatedDoorBytes = await sharp(doorSourceBytes, { failOn: 'error' }).extract(doorBuilderSpec.crop)
  .webp({ quality: 82, effort: 6, smartSubsample: true }).toBuffer();
if (sha256(generatedDoorBytes) !== doorBuilderSpec.derivativeSha256 || generatedDoorBytes.length !== 149790) throw new Error('Door-builder deterministic derivative drift.');
const doorDerivativePath = path.join(root, doorBuilderSpec.derivative);
let doorDerivativeBytes = generatedDoorBytes;
if (check) {
  const committedBytes = await fs.readFile(doorDerivativePath);
  if (Buffer.compare(generatedDoorBytes, committedBytes) !== 0) throw new Error('Door-builder derivative byte drift.');
  doorDerivativeBytes = committedBytes;
} else {
  await fs.writeFile(doorDerivativePath, generatedDoorBytes, { flag: 'w', mode: 0o644 });
}
const doorDerivativeMeta = await sharp(doorDerivativeBytes).metadata();
if (doorDerivativeMeta.format !== 'webp' || doorDerivativeMeta.width !== 1080 || doorDerivativeMeta.height !== 930) throw new Error('Door-builder derivative geometry drift.');
const doorBuilderAssets = [{
  logicalName: doorBuilderSpec.logicalName,
  source: { path: doorBuilderSpec.source, sha256: doorBuilderSpec.sourceSha256, mime: 'image/png', width: 1080, height: 1080, importLocator: doorBuilderSpec.sourceLocator, sourceEvidence: doorBuilderSpec.sourceEvidence },
  derivative: { path: doorBuilderSpec.derivative, sha256: doorBuilderSpec.derivativeSha256, size: 149790, mime: 'image/webp', width: 1080, height: 930, crop: doorBuilderSpec.crop },
  approvedAlt: doorBuilderSpec.approvedAlt,
  allowedUse: ['home', 'door-builder'],
}];

const fontSpecs = [
  { path: 'assets/fonts/lilita-one-regular.woff2', family: 'Lilita One', weight: '400', sha256: '8d6cd0f298738a92ca9bf6e13f54a9191afd06ce04ea00ebbf24499c017191b7', license: 'OFL-1.1', sourceLocator: 'https://github.com/google/fonts/blob/main/ofl/lilitaone/OFL.txt' },
  { path: 'assets/fonts/nunito-variable.woff2', family: 'Nunito', weight: '200-1000', sha256: 'ba344451eab25b217a165363b1982048a5e5830a0daf36577973955a04cac793', license: 'OFL-1.1', sourceLocator: 'https://github.com/google/fonts/blob/main/ofl/nunito/OFL.txt' },
];
const fonts = [];
for (const font of fontSpecs) {
  const bytes = await fs.readFile(path.join(root, font.path));
  if (sha256(bytes) !== font.sha256) throw new Error(`Font hash mismatch: ${font.path}`);
  fonts.push({ ...font, mime: 'font/woff2' });
}

const manifest = {
  schemaVersion: 1,
  generator: { name: 'sharp', version: sharp.versions.sharp, libvips: sharp.versions.vips, libwebp: sharp.versions.webp, settings: { rotate: true, withoutEnlargement: true, webpQuality: 82, webpEffort: 6, smartSubsample: true } },
  fonts,
  brandAssets,
  doorBuilderAssets,
  assets,
};
const manifestPath = path.join(root, 'assets/owned-assets.provenance.json');
if (check) {
  const committed = JSON.parse(await fs.readFile(manifestPath, 'utf8'));
  if (JSON.stringify(committed) !== JSON.stringify(manifest)) throw new Error('Owned-asset provenance drift.');
} else {
  await fs.writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`, { flag: 'w' });
}
