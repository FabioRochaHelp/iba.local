import { useToast } from 'primevue/usetoast'

/** Mostra mensagens de erro da API de forma padronizada. */
export function useApiError() {
  const toast = useToast()

  function notify(error, summary = 'Erro') {
    toast.add({ severity: 'error', summary, detail: error?.userMessage || 'Erro inesperado.', life: 5000 })
  }

  return { notify }
}
