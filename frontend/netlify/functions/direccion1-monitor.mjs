import { getStore } from '@netlify/blobs';
import { monitorHeartbeat } from '../../../monitoring/heartbeat.mjs';

export default async function handler() {
  if (process.env.RIBERA_MONITOR_ENABLED !== 'true') return new Response(null, { status: 204 });
  try {
    const result = await monitorHeartbeat({
      env: process.env,
      store: getStore({ name: 'ribera-availability', consistency: 'strong' }),
    });
    console.info(`Disponibilidad Direccion1: ${result.status}`);
    return new Response(null, { status: 204 });
  } catch (error) {
    console.error(`Fallo de vigilancia Direccion1: ${error.message}`);
    throw error;
  }
}

export const config = { schedule: '* * * * *' };
