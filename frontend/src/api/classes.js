import { http } from './http'

const id = (v) => Number(v)

export const classesApi = {
  list: (params) => http.get('/classes', { params }).then((r) => r.data.data),
  get: (classId) => http.get(`/classes/${id(classId)}`).then((r) => r.data.data),
  create: (payload) => http.post('/classes', payload).then((r) => r.data.data),
  update: (classId, payload) => http.put(`/classes/${id(classId)}`, payload).then((r) => r.data.data),
  setAthletes: (classId, athleteIds) => http.put(`/classes/${id(classId)}/athletes`, { athlete_ids: athleteIds }).then((r) => r.data.data),
  suggestions: (classId) => http.get(`/classes/${id(classId)}/suggestions`).then((r) => r.data.data),
  coaches: () => http.get('/coaches').then((r) => r.data.data),
  report: (classId, params) => http.get(`/classes/${id(classId)}/report`, { params }).then((r) => r.data.data),
  openSession: (classId, date) => http.post(`/classes/${id(classId)}/sessions`, { date }).then((r) => r.data.data),
  session: (sessionId) => http.get(`/sessions/${id(sessionId)}`).then((r) => r.data.data),
  saveAttendance: (sessionId, payload) => http.put(`/sessions/${id(sessionId)}/attendance`, payload).then((r) => r.data.data),
  athleteAttendance: (athleteId, params) => http.get(`/athletes/${id(athleteId)}/attendance`, { params }).then((r) => r.data.data)
}
