import { getStore } from '@netlify/blobs';
import { receiveHeartbeat } from '../../../monitoring/heartbeat.mjs';

export default async function handler(request) {
  try {
    return await receiveHeartbeat(request, {
      env: process.env,
      store: getStore({ name: 'ribera-availability', consistency: 'strong' }),
    });
  } catch {
    console.error('No se pudo guardar la señal de Direccion1.');
    return new Response(null, { status: 503 });
  }
}
