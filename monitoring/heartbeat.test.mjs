import test from 'node:test';
import assert from 'node:assert/strict';
import { receiveHeartbeat, monitorHeartbeat, sendAlert, HEARTBEAT_KEY, STATE_KEY, MAX_SILENCE_MS } from './heartbeat.mjs';

const base = Date.parse('2026-10-31T12:00:00Z');
const env = {
  RIBERA_MONITOR_ENABLED: 'true', RIBERA_HEARTBEAT_SECRET: 'x'.repeat(64),
  RIBERA_RESEND_API_KEY: 'fake-test-only', RIBERA_ALERT_TO: 'test@example.com',
  RIBERA_ALERT_FROM: 'monitor@example.com',
};
function fixture() {
  const data = new Map();
  const sent = [];
  return {
    data, sent,
    store: { get: async key => structuredClone(data.get(key) ?? null),
      setJSON: async (key, value) => data.set(key, structuredClone(value)) },
    send: async message => { sent.push(message); }, env,
  };
}
function request(token = env.RIBERA_HEARTBEAT_SECRET, method = 'POST') {
  return new Request('https://example.com/heartbeat', { method, headers: { Authorization: `Bearer ${token}` } });
}

test('unauthorized pings and wrong methods cannot update the heartbeat', async () => {
  const f = fixture();
  assert.equal((await receiveHeartbeat(request('bad'), f)).status, 401);
  assert.equal((await receiveHeartbeat(request(undefined, 'GET'), f)).status, 405);
  assert.equal(f.data.size, 0);
});
test('server clock is authoritative and no secret is stored with heartbeat', async () => {
  const f = fixture();
  assert.equal((await receiveHeartbeat(request(), { ...f, now: () => base })).status, 204);
  assert.deepEqual(f.data.get(HEARTBEAT_KEY), { seenAt: new Date(base).toISOString() });
});
test('no first heartbeat also alerts after arming grace, never immediately', async () => {
  const f = fixture();
  assert.equal((await monitorHeartbeat({ ...f, now: () => base })).status, 'arming');
  assert.equal(f.sent.length, 0);
  assert.equal((await monitorHeartbeat({ ...f, now: () => base + MAX_SILENCE_MS })).status, 'offline');
  assert.equal(f.sent.length, 1);
});
test('one outage email, one recovery email, then a separate new incident', async () => {
  const f = fixture();
  await receiveHeartbeat(request(), { ...f, now: () => base });
  await monitorHeartbeat({ ...f, now: () => base });
  await monitorHeartbeat({ ...f, now: () => base + MAX_SILENCE_MS });
  await monitorHeartbeat({ ...f, now: () => base + MAX_SILENCE_MS * 2 });
  assert.equal(f.sent.length, 1);
  await receiveHeartbeat(request(), { ...f, now: () => base + MAX_SILENCE_MS * 2 });
  await monitorHeartbeat({ ...f, now: () => base + MAX_SILENCE_MS * 2 });
  await monitorHeartbeat({ ...f, now: () => base + MAX_SILENCE_MS * 2 + 1000 });
  assert.equal(f.sent.length, 2);
  await monitorHeartbeat({ ...f, now: () => base + MAX_SILENCE_MS * 3 });
  assert.equal(f.sent.length, 3);
  assert.notEqual(f.sent[0].key, f.sent[2].key);
});
test('mail failure retries same identity and does not pretend delivery succeeded', async () => {
  const f = fixture();
  await monitorHeartbeat({ ...f, now: () => base });
  const failed = [];
  await assert.rejects(monitorHeartbeat({ ...f, now: () => base + MAX_SILENCE_MS,
    send: async message => { failed.push(message); throw new Error('provider unavailable'); } }));
  assert.equal(f.data.get(STATE_KEY).incident.notified, false);
  await monitorHeartbeat({ ...f, now: () => base + MAX_SILENCE_MS + 60_000 });
  assert.deepEqual(failed[0], f.sent[0]);
});
test('disabled monitoring never sends or mutates state', async () => {
  const f = fixture();
  assert.equal((await monitorHeartbeat({ ...f, env: { RIBERA_MONITOR_ENABLED: 'false' } })).status, 'disabled');
  assert.equal(f.data.size, 0);
});
test('mailer uses server-only key and rejects unsuccessful provider response', async () => {
  const message = { key: 'incident-1', subject: 'test', text: 'test body' };
  let captured;
  await sendAlert(message, { env, fetchImpl: async (url, options) => {
    captured = { url, options }; return new Response('{}', { status: 200 });
  } });
  assert.equal(captured.url, 'https://api.resend.com/emails');
  assert.equal(captured.options.headers['Idempotency-Key'], 'incident-1');
  assert.deepEqual(JSON.parse(captured.options.body).to, ['test@example.com']);
  await assert.rejects(sendAlert(message, { env, fetchImpl: async () => new Response('', { status: 429 }) }));
});

test('five-minute beats remain healthy; alert starts at fifteen minutes of silence', async () => {
  assert.equal(MAX_SILENCE_MS, 15 * 60_000);
  const f = fixture();
  for (const minute of [0, 5, 10]) {
    await receiveHeartbeat(request(), { ...f, now: () => base + minute * 60_000 });
    assert.equal((await monitorHeartbeat({ ...f, now: () => base + minute * 60_000 })).status, 'healthy');
  }
  assert.equal((await monitorHeartbeat({ ...f, now: () => base + 25 * 60_000 - 1 })).status, 'healthy');
  assert.equal(f.sent.length, 0);
  assert.equal((await monitorHeartbeat({ ...f, now: () => base + 25 * 60_000 })).status, 'offline');
  assert.match(f.sent[0].subject, /quince minutos/);
});
