'use client'

import { useState, useEffect, useCallback, useMemo, useRef } from 'react'
import { useRouter } from 'next/navigation'
import { createClient } from '@/lib/supabase/client'
import { RefreshCw, CheckCircle, AlertCircle, Loader2, WifiOff } from 'lucide-react'

interface SyncRequest {
  id: string
  status: 'pending' | 'running' | 'success' | 'failed'
  source: string
  requested_at: string
  started_at: string | null
  finished_at: string | null
  error_message: string | null
}

interface SyncButtonProps {
  initialActiveRequest: SyncRequest | null
  userId: string
  variant?: 'default' | 'header'
  mode?: 'local_erp' | 'supabase'
  currentGeneratedAt?: string | null
}

const POLL_INTERVAL_MS = 3000 // 3 segundos durante actualización activa
const LONG_RUNNING_MINUTES = 10
const TIMEOUT_MINUTES = 60
const FINISHED_DISPLAY_MS = 5000
const MAX_POLL_ERRORS = 3

export default function SyncButton({ initialActiveRequest, userId, variant = 'default', mode = 'supabase', currentGeneratedAt }: SyncButtonProps) {
  const router = useRouter()
  const [activeRequest, setActiveRequest] = useState<SyncRequest | null>(initialActiveRequest)
  const [justFinished, setJustFinished] = useState<'success' | 'failed' | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isRefreshingLocal, setIsRefreshingLocal] = useState(false)
  const [lastError, setLastError] = useState<string | null>(null)
  const [pollErrorCount, setPollErrorCount] = useState(0)

  const supabase = useMemo(() => createClient(), [])

  // Refs para controlar timeouts, carreras y frescura del snapshot
  const finishTimeoutRef = useRef<NodeJS.Timeout | null>(null)
  const mountedRef = useRef(true)
  const baselineGeneratedAtRef = useRef<string | null>(currentGeneratedAt || null)

  useEffect(() => {
    mountedRef.current = true
    return () => {
      mountedRef.current = false
      if (finishTimeoutRef.current) {
        clearTimeout(finishTimeoutRef.current)
      }
    }
  }, [])

  useEffect(() => {
    if (currentGeneratedAt) {
      baselineGeneratedAtRef.current = currentGeneratedAt
    }
  }, [currentGeneratedAt])

  const clearFinishTimeout = useCallback(() => {
    if (finishTimeoutRef.current) {
      clearTimeout(finishTimeoutRef.current)
      finishTimeoutRef.current = null
    }
  }, [])

  const isActive = mode !== 'local_erp' && (activeRequest?.status === 'pending' || activeRequest?.status === 'running')

  const fetchRequest = useCallback(async (id: string): Promise<SyncRequest | null> => {
    const { data, error } = await supabase
      .from('sync_requests')
      .select('id, status, source, requested_at, started_at, finished_at, error_message')
      .eq('id', id)
      .single()

    if (error || !data) {
      if (mountedRef.current) {
        setPollErrorCount((prev) => prev + 1)
      }
      return null
    }

    if (mountedRef.current) {
      setPollErrorCount(0)
    }
    return data as SyncRequest
  }, [supabase])

  // Polling de la solicitud activa (únicamente en modo supabase)
  useEffect(() => {
    if (mode === 'local_erp') return
    if (!activeRequest || !isActive) return

    let intervalId: NodeJS.Timeout
    let isCancelled = false

    const poll = async () => {
      const updated = await fetchRequest(activeRequest.id)
      if (isCancelled || !updated || !mountedRef.current) return

      setActiveRequest(updated)

      if (updated.status === 'success') {
        clearFinishTimeout()

        // Esperar activamente a que el nuevo snapshot esté publicado y visible en Supabase
        const baselineGenAt = baselineGeneratedAtRef.current
        for (let i = 0; i < 12; i++) {
          if (isCancelled || !mountedRef.current) return
          try {
            const { data: snap } = await supabase
              .from('dashboard_snapshots')
              .select('generated_at')
              .eq('scope', 'store_dashboard')
              .eq('year', 2026)
              .eq('periodo', 'year')
              .eq('anio_ant', 'todos')
              .maybeSingle()

            if (snap?.generated_at) {
              if (!baselineGenAt || new Date(snap.generated_at).getTime() > new Date(baselineGenAt).getTime()) {
                baselineGeneratedAtRef.current = snap.generated_at
                break
              }
            }
          } catch {
            // Reintentar en siguiente ciclo
          }
          await new Promise((res) => setTimeout(res, 500))
        }

        // Breve margen de seguridad para consistencia en réplicas/pooler
        await new Promise((res) => setTimeout(res, 300))
        if (isCancelled || !mountedRef.current) return

        setJustFinished('success')
        router.refresh()
        finishTimeoutRef.current = setTimeout(() => {
          if (mountedRef.current) {
            setJustFinished(null)
          }
        }, FINISHED_DISPLAY_MS)
      } else if (updated.status === 'failed') {
        clearFinishTimeout()
        setJustFinished('failed')
        setLastError(updated.error_message)
        finishTimeoutRef.current = setTimeout(() => {
          if (mountedRef.current) {
            setJustFinished(null)
          }
        }, FINISHED_DISPLAY_MS)
      }
    }

    intervalId = setInterval(poll, POLL_INTERVAL_MS)
    poll() // primera comprobación inmediata

    return () => {
      isCancelled = true
      clearInterval(intervalId)
    }
  }, [mode, activeRequest, isActive, fetchRequest, router, clearFinishTimeout, supabase])

  const handleClick = async () => {
    if (mode === 'local_erp') {
      if (isRefreshingLocal) return
      setIsRefreshingLocal(true)
      try {
        router.refresh()
        await new Promise((resolve) => setTimeout(resolve, 800))
      } finally {
        if (mountedRef.current) {
          setIsRefreshingLocal(false)
        }
      }
      return
    }

    if (isActive || isSubmitting) return

    setIsSubmitting(true)
    setJustFinished(null)
    clearFinishTimeout()
    setLastError(null)
    setPollErrorCount(0)

    try {
      // Capturar timestamp del snapshot vigente previo a la solicitud
      try {
        const { data: currentSnap } = await supabase
          .from('dashboard_snapshots')
          .select('generated_at')
          .eq('scope', 'store_dashboard')
          .eq('year', 2026)
          .eq('periodo', 'year')
          .eq('anio_ant', 'todos')
          .maybeSingle()

        if (currentSnap?.generated_at) {
          baselineGeneratedAtRef.current = currentSnap.generated_at
        }
      } catch {
        // Si falla, se conserva el del ref
      }

      const { data, error } = await supabase
        .from('sync_requests')
        .insert({
          dataset: 'dashboard_snapshot',
          source: 'manual',
          requested_by: userId,
        })
        .select('id, status, source, requested_at, started_at, finished_at, error_message')
        .single()

      if (error) {
        // 23505 = unique violation en Postgres
        if (error.code === '23505') {
          setLastError('Ya hay una actualización en curso.')
        } else {
          setLastError(error.message)
        }
        return
      }

      if (data && mountedRef.current) {
        setActiveRequest(data as SyncRequest)
      }
    } finally {
      if (mountedRef.current) {
        setIsSubmitting(false)
      }
    }
  }

  const runningMinutes = activeRequest?.started_at
    ? Math.floor((Date.now() - new Date(activeRequest.started_at).getTime()) / 60000)
    : activeRequest?.status === 'running'
      ? Math.floor((Date.now() - new Date(activeRequest.requested_at).getTime()) / 60000)
      : activeRequest?.status === 'pending'
        ? Math.floor((Date.now() - new Date(activeRequest.requested_at).getTime()) / 60000)
        : 0

  const isLongRunning = isActive && runningMinutes > LONG_RUNNING_MINUTES
  const isTimedOut = isActive && runningMinutes > TIMEOUT_MINUTES
  const isConnectionLost = isActive && pollErrorCount >= MAX_POLL_ERRORS

  // Determinar texto y estado visual del botón
  let buttonText = 'Actualizar datos'
  let icon = <RefreshCw className="w-4 h-4" />
  let buttonClass =
    'bg-[#206393] hover:bg-[#184a70] text-white border border-[#206393]'
  let disabled = false

  if (mode === 'local_erp' && isRefreshingLocal) {
    buttonText = 'Actualizando...'
    icon = <Loader2 className="w-4 h-4 animate-spin" />
    buttonClass = 'bg-[#9aa0a6] text-white border border-[#9aa0a6] cursor-not-allowed'
    disabled = true
  } else if (justFinished === 'success') {
    buttonText = 'Datos actualizados'
    icon = <CheckCircle className="w-4 h-4" />
    buttonClass = 'bg-emerald-600 hover:bg-emerald-700 text-white border border-emerald-600'
  } else if (justFinished === 'failed') {
    buttonText = 'Error al actualizar'
    icon = <AlertCircle className="w-4 h-4" />
    buttonClass = 'bg-rose-600 hover:bg-rose-700 text-white border border-rose-600'
  } else if (isConnectionLost) {
    buttonText = 'Sincronizando...'
    icon = <WifiOff className="w-4 h-4" />
    buttonClass = 'bg-amber-500 text-white border border-amber-500 cursor-not-allowed'
    disabled = true
  } else if (isActive) {
    buttonText = 'Sincronizando...'
    icon = <Loader2 className="w-4 h-4 animate-spin" />
    buttonClass = 'bg-[#9aa0a6] text-white border border-[#9aa0a6] cursor-not-allowed'
    disabled = true
  } else if (isSubmitting) {
    buttonText = 'Solicitando...'
    icon = <Loader2 className="w-4 h-4 animate-spin" />
    buttonClass = 'bg-[#9aa0a6] text-white border border-[#9aa0a6] cursor-not-allowed'
    disabled = true
  }

  const presentationClass = variant === 'header'
    ? 'border border-white/50 bg-white/15 text-white hover:bg-white/25'
    : buttonClass
  const sizeClass = variant === 'header'
    ? 'gap-1 px-1.5 py-0.5 text-[10px] [&_svg]:h-3.5 [&_svg]:w-3.5'
    : 'gap-2 px-3 py-1.5 text-sm md:gap-1.5 md:px-2 md:py-1 md:text-xs'

  return (
    <div className="flex flex-col items-end gap-1 md:gap-0.5">
      <button
        onClick={handleClick}
        disabled={disabled}
        className={`inline-flex items-center rounded-lg font-semibold transition-colors ${sizeClass} ${presentationClass}`}
      >
        {icon}
        <span>{buttonText}</span>
      </button>

      {variant === 'default' && lastError && !isActive && (
        <p className="text-xs text-rose-600 font-medium max-w-[220px] text-right">
          {lastError}
        </p>
      )}

      {variant === 'default' && isConnectionLost && (
        <p className="text-xs text-amber-600 font-medium max-w-[220px] text-right">
          Problema de conexión. Esperando que se restablezca...
        </p>
      )}

      {variant === 'default' && isLongRunning && !isTimedOut && !isConnectionLost && (
        <p className="text-xs text-amber-600 font-medium max-w-[220px] text-right">
          La sincronización está tardando más de lo habitual...
        </p>
      )}

      {variant === 'default' && isTimedOut && (
        <p className="text-xs text-rose-600 font-medium max-w-[220px] text-right">
          La sincronización ha superado el tiempo máximo. Inténtalo de nuevo más tarde.
        </p>
      )}
    </div>
  )
}
