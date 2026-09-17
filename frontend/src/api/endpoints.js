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

  replayRequest: (token, requestId, targetUrl) =>
    api.post(`/endpoints/${token}/requests/${requestId}/replay`, { target_url: targetUrl }),

  // Delete a selected set of requests (bulk).
  deleteRequests: (token, ids) =>
    api.delete(`/endpoints/${token}/requests`, { data: { ids } }),

  clearRequests: (token) =>
    api.delete(`/endpoints/${token}/requests`),
}
