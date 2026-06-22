import { cpSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const standaloneRoot = path.join(root, '.next', 'standalone');
const staticSource = path.join(root, '.next', 'static');
const staticTarget = path.join(standaloneRoot, '.next', 'static');
const publicSource = path.join(root, 'public');
const publicTarget = path.join(standaloneRoot, 'public');

if (!existsSync(standaloneRoot)) {
  console.log('Standalone output not found; skipping asset sync.');
  process.exit(0);
}

if (!existsSync(staticSource)) {
  throw new Error('Missing .next/static. Run `npm run build` first.');
}

mkdirSync(path.dirname(staticTarget), { recursive: true });
rmSync(staticTarget, { recursive: true, force: true });
cpSync(staticSource, staticTarget, { recursive: true });

if (existsSync(publicSource)) {
  rmSync(publicTarget, { recursive: true, force: true });
  cpSync(publicSource, publicTarget, { recursive: true });
}

console.log('Synced standalone assets (.next/static, public).');
