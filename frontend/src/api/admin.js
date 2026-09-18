import api from './client'
import { endpointsApi } from './endpoints'

export const adminApi = {
  stats:     ()              => api.get('/admin/stats'),
  plans:     ()              => api.get('/admin/plans'),
  users:     (q = '', page = 1, perPage = 10) => api.get('/admin/users', { params: { q, page, per_page: perPage } }),
  updateUser: (id, data)     => api.patch(`/admin/users/${id}`, data),
  endpoints: (q = '', page = 1, perPage = 10) => api.get('/admin/endpoints', { params: { q, page, per_page: perPage } }),
  settings:       ()         => api.get('/admin/settings'),
  updateSettings: (data)     => api.put('/admin/settings', data),

  // Endpoint management reuses the owner-scoped endpoints API — the backend
  // grants admins access to every endpoint through the same routes.
  updateEndpoint: (token, data) => endpointsApi.update(token, data),
  deleteEndpoint: (token)       => endpointsApi.remove(token),
}
