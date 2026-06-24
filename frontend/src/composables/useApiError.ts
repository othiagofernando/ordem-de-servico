import { ref } from 'vue'
import { ApiError } from '../api/client'

export interface ApiErrorInfo {
  message: string
  isForbidden: boolean
  isNotFound: boolean
  fieldErrors?: Record<string, string>
}

export function useApiError() {
  const error = ref<ApiErrorInfo | null>(null)

  function parseError(e: unknown): ApiErrorInfo {
    if (e instanceof ApiError) {
      const info: ApiErrorInfo = {
        message: e.message,
        isForbidden: e.isForbidden,
        isNotFound: e.status === 404,
      }

      // Extrai erros por campo se disponível (validação)
      const data = e.data as { errors?: Record<string, string[]> }
      if (data?.errors) {
        info.fieldErrors = Object.entries(data.errors).reduce(
          (acc, [key, msgs]) => {
            acc[key] = msgs[0] || 'Campo inválido'
            return acc
          },
          {} as Record<string, string>,
        )
      }

      return info
    }

    return {
      message: 'Erro inesperado. Tente novamente.',
      isForbidden: false,
      isNotFound: false,
    }
  }

  function setError(e: unknown) {
    error.value = parseError(e)
  }

  function clearError() {
    error.value = null
  }

  function handleError(e: unknown) {
    setError(e)
  }

  return { error, parseError, setError, clearError, handleError }
}
