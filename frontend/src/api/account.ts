import { apiPut } from './client'

export function changePassword(current_password: string, password: string, password_confirmation: string) {
  return apiPut<{ message: string }>('/auth/me/password', {
    current_password,
    password,
    password_confirmation,
  })
}
