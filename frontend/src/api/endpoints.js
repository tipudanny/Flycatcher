import api from './client'

export const endpointsApi = {
  list: ()            => api.get('/endpoints'),
  get: (token)        => api.get(`/endpoints/${token}`),
  create: (data = {}) => api.post('/endpoints', data),
  update: (token, data) => api.put(`/endpoints/${token}`, data),
  remove: (token)     => api.delete(`/endpoints/${token}`),

  listRequests: (token, page = 1) =>
    api.get(`/endpoints/${token}/requests`, { params: { page, per_page: 25 } }),

  getRequest: (token, requestId) =>
    api.get(`/endpoints/${token}/requests/${requestId}`),

  deleteRequest: (token, requestId) =>
    api.delete(`/endpoints/${token}/requests/${requestId}`),

  clearRequests: (token) =>
    api.delete(`/endpoints/${token}/requests`),
}
