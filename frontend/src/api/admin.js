import api from './client'

export const adminApi = {
  stats:     ()              => api.get('/admin/stats'),
  plans:     ()              => api.get('/admin/plans'),
  users:     (q = '')        => api.get('/admin/users', { params: { q } }),
  updateUser: (id, data)     => api.patch(`/admin/users/${id}`, data),
  endpoints: (q = '')        => api.get('/admin/endpoints', { params: { q } }),
  settings:       ()         => api.get('/admin/settings'),
  updateSettings: (data)     => api.put('/admin/settings', data),
}
