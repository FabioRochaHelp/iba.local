/** Formatação para exibição (pt-BR). */

export function formatDate(iso) {
  if (!iso) return '—'
  const [y, m, d] = String(iso).slice(0, 10).split('-')
  return d && m && y ? `${d}/${m}/${y}` : '—'
}

export function formatPhone(digits) {
  const s = String(digits || '')
  if (s.length === 11) return `(${s.slice(0, 2)}) ${s.slice(2, 7)}-${s.slice(7)}`
  if (s.length === 10) return `(${s.slice(0, 2)}) ${s.slice(2, 6)}-${s.slice(6)}`
  return s || '—'
}

const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
export function formatMoney(value) {
  const n = Number(value)
  return Number.isFinite(n) ? brl.format(n) : '—'
}

/** Link do WhatsApp com texto codificado (nunca interpolar HTML). */
export function whatsappUrl(phone, message = '') {
  const digits = String(phone || '').replace(/\D/g, '')
  if (digits.length < 10 || digits.length > 11) return null
  const text = message ? `?text=${encodeURIComponent(message)}` : ''
  return `https://wa.me/55${digits}${text}`
}

export const athleteStatus = {
  ativo: { label: 'Ativo', severity: 'success' },
  inativo: { label: 'Inativo', severity: 'secondary' },
  trancado: { label: 'Trancado', severity: 'warn' }
}

export const discountTypes = [
  { value: 'nenhum', label: 'Sem desconto' },
  { value: 'percentual', label: 'Percentual (%)' },
  { value: 'valor', label: 'Valor fixo (R$)' },
  { value: 'cortesia', label: 'Cortesia (100%)' }
]

export function finalFee(fee, type, value) {
  const f = Number(fee) || 0
  const v = Number(value) || 0
  if (type === 'cortesia') return 0
  if (type === 'percentual') return Math.max(0, f - (f * v) / 100)
  if (type === 'valor') return Math.max(0, f - v)
  return f
}

export function initials(name) {
  return String(name || '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0])
    .join('')
    .toUpperCase()
}

export function debounce(fn, ms = 350) {
  let t
  return (...args) => {
    clearTimeout(t)
    t = setTimeout(() => fn(...args), ms)
  }
}

/** Data local de hoje em AAAA-MM-DD (toISOString usaria UTC e erraria à noite). */
export function todayIso() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const MONTHS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro']
const MONTHS_SHORT = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']

/** "2026-04" -> "abril de 2026" (ou "abr/26" no formato curto). */
export function formatMonth(ym, short = false) {
  const [y, m] = String(ym || '').split('-').map(Number)
  if (!y || !m) return '—'
  return short ? `${MONTHS_SHORT[m - 1]}/${String(y).slice(2)}` : `${MONTHS[m - 1]} de ${y}`
}

export function currentMonth() {
  return todayIso().slice(0, 7)
}

export function shiftMonth(ym, delta) {
  const [y, m] = ym.split('-').map(Number)
  const d = new Date(y, m - 1 + delta, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

export const invoiceStatus = {
  aberta: { label: 'Em aberto', severity: 'warn', icon: 'pi pi-clock' },
  parcial: { label: 'Parcial', severity: 'info', icon: 'pi pi-percentage' },
  paga: { label: 'Paga', severity: 'success', icon: 'pi pi-check-circle' },
  cortesia: { label: 'Cortesia', severity: 'secondary', icon: 'pi pi-heart' },
  cancelada: { label: 'Cancelada', severity: 'contrast', icon: 'pi pi-ban' },
  atrasada: { label: 'Atrasada', severity: 'danger', icon: 'pi pi-exclamation-triangle' }
}

export const paymentMethods = [
  { value: 'pix', label: 'Pix' },
  { value: 'dinheiro', label: 'Dinheiro' },
  { value: 'cartao', label: 'Cartão' },
  { value: 'transferencia', label: 'Transferência' }
]
export const paymentMethodLabel = (v) => paymentMethods.find((m) => m.value === v)?.label || v

export const uniformStatus = {
  pendente: { label: 'A receber', severity: 'warn', icon: 'pi pi-clock' },
  pago_parcial: { label: 'Parcial', severity: 'info', icon: 'pi pi-percentage' },
  pago: { label: 'Pago', severity: 'success', icon: 'pi pi-check-circle' },
  cancelado: { label: 'Cancelado', severity: 'contrast', icon: 'pi pi-ban' }
}

export const DEFAULT_SIZES = ['4', '6', '8', '10', '12', '14', '16', 'PP', 'P', 'M', 'G', 'GG']

export const sponsorshipMethods = [
  ...paymentMethods,
  { value: 'produto', label: 'Produto/serviço' }
]

export const WEEKDAYS = [
  { value: 1, label: 'Segunda', short: 'Seg' },
  { value: 2, label: 'Terça', short: 'Ter' },
  { value: 3, label: 'Quarta', short: 'Qua' },
  { value: 4, label: 'Quinta', short: 'Qui' },
  { value: 5, label: 'Sexta', short: 'Sex' },
  { value: 6, label: 'Sábado', short: 'Sáb' },
  { value: 7, label: 'Domingo', short: 'Dom' }
]
export const weekdayLabel = (n, short = false) => WEEKDAYS.find((w) => w.value === Number(n))?.[short ? 'short' : 'label'] || '—'
/** Dia da semana ISO (1 = segunda ... 7 = domingo). */
export const isoWeekday = (d = new Date()) => ((d.getDay() + 6) % 7) + 1

export const attendanceStatus = {
  presente: { label: 'Presente', short: 'P', icon: 'pi pi-check', severity: 'success' },
  falta: { label: 'Falta', short: 'F', icon: 'pi pi-times', severity: 'danger' },
  justificada: { label: 'Justificada', short: 'J', icon: 'pi pi-file', severity: 'info' }
}

export const ROLE_LABELS = { admin: 'Administrador', professor: 'Professor', atleta: 'Atleta', responsavel: 'Responsável' }
export const STAFF_ROLES = ['admin', 'professor']
export const PORTAL_ROLES = ['atleta', 'responsavel']

export const criterionCategories = {
  tecnico: 'Técnico',
  tatico: 'Tático',
  fisico: 'Físico',
  comportamental: 'Comportamental'
}

export const noteTypes = {
  ponto_forte: { label: 'Ponto forte', icon: 'pi pi-thumbs-up', severity: 'success' },
  a_melhorar: { label: 'A melhorar', icon: 'pi pi-arrow-up-right', severity: 'warn' },
  comportamento: { label: 'Comportamento', icon: 'pi pi-users', severity: 'info' },
  geral: { label: 'Geral', icon: 'pi pi-comment', severity: 'secondary' }
}

export const goalStatus = {
  em_andamento: { label: 'Em andamento', icon: 'pi pi-spinner', severity: 'info' },
  atingida: { label: 'Atingida', icon: 'pi pi-trophy', severity: 'success' },
  cancelada: { label: 'Cancelada', icon: 'pi pi-ban', severity: 'secondary' }
}

export const formatNumber = (v, digits = 1, minDigits = digits) =>
  v === null || v === undefined || v === '' ? '—' : Number(v).toLocaleString('pt-BR', { minimumFractionDigits: minDigits, maximumFractionDigits: digits })
