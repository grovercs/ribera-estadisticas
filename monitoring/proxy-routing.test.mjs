import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const require = createRequire(new URL('../frontend/package.json', import.meta.url));
const testing = require('next/experimental/testing/server');
const doesProxyMatch = testing.unstable_doesProxyMatch ?? testing.unstable_doesMiddlewareMatch;
const source = readFileSync(new URL('../frontend/src/proxy.ts', import.meta.url), 'utf8');
const config = runInNewContext(`(${source.split('export const config = ')[1].trim().replace(/;$/, '')})`);

test('heartbeat bypasses login proxy; dashboard and other endpoints remain protected', () => {
  for (const [url, expected] of [
    ['/.netlify/functions/direccion1-heartbeat', false],
    ['/.netlify/functions/direccion1-heartbeat?probe=1', false],
    ['/dashboard', true],
    ['/dashboard/ventas', true],
    ['/login', true],
    ['/.netlify/functions/direccion1-heartbeat-other', true],
    ['/.netlify/functions/direccion1-monitor', true],
    ['/_next/static/app.js', false],
  ]) {
    assert.equal(doesProxyMatch({ config, nextConfig: {}, url }), expected, url);
  }
});
