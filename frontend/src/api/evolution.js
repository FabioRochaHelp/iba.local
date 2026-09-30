import { http } from './http'

const id = (v) => Number(v)

export const evolutionApi = {
  summary: (athleteId) => http.get(`/athletes/${id(athleteId)}/evolution`).then((r) => r.data.data),
  criteria: () => http.get('/evaluation-criteria').then((r) => r.data.data),
  createCriterion: (payload) => http.post('/evaluation-criteria', payload).then((r) => r.data.data),
  updateCriterion: (criterionId, payload) => http.put(`/evaluation-criteria/${id(criterionId)}`, payload).then((r) => r.data.data),
  addEvaluation: (athleteId, payload) => http.post(`/athletes/${id(athleteId)}/evaluations`, payload).then((r) => r.data.data),
  deleteEvaluation: (evaluationId) => http.delete(`/evaluations/${id(evaluationId)}`),
  addMeasurement: (athleteId, payload) => http.post(`/athletes/${id(athleteId)}/measurements`, payload).then((r) => r.data.data),
  deleteMeasurement: (measurementId) => http.delete(`/measurements/${id(measurementId)}`),
  addNote: (athleteId, payload) => http.post(`/athletes/${id(athleteId)}/notes`, payload).then((r) => r.data.data),
  setNoteVisibility: (noteId, visible) => http.put(`/notes/${id(noteId)}/visibility`, { visible_to_athlete: visible }),
  deleteNote: (noteId) => http.delete(`/notes/${id(noteId)}`),
  addGoal: (athleteId, payload) => http.post(`/athletes/${id(athleteId)}/goals`, payload).then((r) => r.data.data),
  updateGoal: (goalId, payload) => http.put(`/goals/${id(goalId)}`, payload),
  deleteGoal: (goalId) => http.delete(`/goals/${id(goalId)}`)
}

export const portalApi = {
  athletes: () => http.get('/portal/athletes').then((r) => r.data.data),
  overview: (athleteId) => http.get(`/portal/athletes/${id(athleteId)}`).then((r) => r.data.data)
}
