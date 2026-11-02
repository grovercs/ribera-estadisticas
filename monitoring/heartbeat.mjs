import { createHash, timingSafeEqual } from 'node:crypto';

export const MAX_SILENCE_MS = 15 * 60 * 1000;
export const HEARTBEAT_KEY = 'direccion1/heartbeat';
export const STATE_KEY = 'direccion1/notification-state';

export function authorized(header, secret) {
  if (typeof secret !== 'string' || secret.length < 32) return false;
  const hash = value => createHash('sha256').update(value).digest();
  return timingSafeEqual(hash(header || ''), hash(`Bearer ${secret}`));
}

export async function receiveHeartbeat(request, { store, env, now = Date.now }) {
  if (request.method !== 'POST') return new Response(null, { status: 405, headers: { Allow: 'POST' } });
  if (!env.RIBERA_HEARTBEAT_SECRET || env.RIBERA_HEARTBEAT_SECRET.length < 32) {
    return new Response(null, { status: 503 });
  }
  if (!authorized(request.headers.get('authorization'), env.RIBERA_HEARTBEAT_SECRET)) {
    return new Response(null, { status: 401 });
  }
  // Use the receiver's clock, never a timestamp supplied by the computer.
  await store.setJSON(HEARTBEAT_KEY, { seenAt: new Date(now()).toISOString() });
  return new Response(null, { status: 204, headers: { 'Cache-Control': 'no-store' } });
}

function emailSettings(env) {
  const to = env.RIBERA_ALERT_TO;
  const from = env.RIBERA_ALERT_FROM;
  if (!env.RIBERA_RESEND_API_KEY || !to || !from || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) {
    throw new Error('Falta configurar RIBERA_RESEND_API_KEY, RIBERA_ALERT_TO o RIBERA_ALERT_FROM.');
  }
  return { to, from };
}

export async function sendAlert(message, { env, fetchImpl = fetch }) {
  const { to, from } = emailSettings(env);
  const response = await fetchImpl('https://api.resend.com/emails', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${env.RIBERA_RESEND_API_KEY}`,
      'Content-Type': 'application/json',
      'Idempotency-Key': message.key,
    },
    body: JSON.stringify({ from, to: [to], subject: message.subject, text: message.text }),
    signal: AbortSignal.timeout(10_000),
  });
  if (!response.ok) throw new Error(`El proveedor de correo rechazo el aviso: HTTP ${response.status}.`);
}

export async function monitorHeartbeat({ store, env, now = Date.now, send = sendAlert }) {
  if (env.RIBERA_MONITOR_ENABLED !== 'true') return { status: 'disabled' };
  emailSettings(env);
  const time = now();
  let state = await store.get(STATE_KEY, { type: 'json' });
  if (!state) {
    state = { armedAt: new Date(time).toISOString(), incident: null };
    await store.setJSON(STATE_KEY, state);
  }
  const heartbeat = await store.get(HEARTBEAT_KEY, { type: 'json' });
  const lastSeen = heartbeat?.seenAt || state.armedAt;
  const lastSeenMs = Date.parse(lastSeen);
  if (!Number.isFinite(lastSeenMs) || lastSeenMs > time + 10_000) {
    throw new Error('Estado de vigilancia no valido.');
  }
  const offline = time - lastSeenMs >= MAX_SILENCE_MS;
  if (!offline) {
    if (state.incident?.notified) {
      const incident = state.incident;
      await send({
        key: `ribera-direccion1-recovery-${incident.id}`,
        subject: 'Ribera: Direccion1 vuelve a enviar señal',
        text: 'Netlify vuelve a recibir la señal de Direccion1. La tarea vuelve a comunicar. Esta señal no comprueba el calculo ni la publicacion del snapshot.',
      }, { env });
    }
    if (state.incident) await store.setJSON(STATE_KEY, { ...state, incident: null });
    return { status: heartbeat ? 'healthy' : 'arming' };
  }
  if (!state.incident) {
    state = {
      ...state,
      incident: {
        id: createHash('sha256').update(lastSeen).digest('hex').slice(0, 24),
        lastSeen,
        notified: false,
      },
    };
    // Save identity before delivery so a failed send retries the same incident.
    await store.setJSON(STATE_KEY, state);
  }
  if (!state.incident.notified) {
    const incident = state.incident;
    await send({
      key: `ribera-direccion1-offline-${incident.id}`,
      subject: 'Ribera: Direccion1 lleva quince minutos sin señal',
      text: `No llega la señal periodica de la tarea de sincronizacion de Direccion1.\nUltima señal o inicio de vigilancia (UTC): ${incident.lastSeen}\nComprueba alimentacion, conexion a Internet y la tarea Ribera Sync - Process Manual Requests.\nEste aviso detecta ausencia de señal; no determina por si solo la causa ni el estado de Integral.`,
    }, { env });
    await store.setJSON(STATE_KEY, { ...state, incident: { ...state.incident, notified: true } });
  }
  return { status: 'offline' };
}
