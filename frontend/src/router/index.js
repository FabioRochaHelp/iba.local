import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const ADMIN = ['admin']
const ALL = ['admin', 'professor'] // equipe
const PORTAL = ['atleta', 'responsavel']
const EVERYONE = [...ALL, ...PORTAL]

const routes = [
  {
    path: '/login',
    component: () => import('@/layouts/AuthLayout.vue'),
    meta: { public: true },
    children: [
      { path: '', name: 'login', component: () => import('@/views/auth/LoginView.vue'), meta: { public: true, title: 'Entrar' } }
    ]
  },
  {
    path: '/trocar-senha',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [
      {
        path: '',
        name: 'change-password',
        component: () => import('@/views/auth/ChangePasswordView.vue'),
        meta: { roles: EVERYONE, title: 'Trocar senha' }
      }
    ]
  },
  {
    path: '/',
    component: () => import('@/layouts/AppLayout.vue'),
    meta: { roles: EVERYONE },
    children: [
      { path: '', name: 'dashboard', component: () => import('@/views/DashboardView.vue'), meta: { roles: ALL, title: 'Painel' } },
      { path: 'atletas', name: 'athletes', component: () => import('@/views/athletes/AthleteListView.vue'), meta: { roles: ALL, title: 'Atletas' } },
      { path: 'atletas/novo', name: 'athlete-new', component: () => import('@/views/athletes/AthleteFormView.vue'), meta: { roles: ADMIN, title: 'Novo atleta' } },
      { path: 'atletas/:id(\\d+)', name: 'athlete-show', component: () => import('@/views/athletes/AthleteDetailView.vue'), meta: { roles: ALL, title: 'Ficha do atleta' } },
      { path: 'atletas/:id(\\d+)/editar', name: 'athlete-edit', component: () => import('@/views/athletes/AthleteFormView.vue'), meta: { roles: ADMIN, title: 'Editar atleta' } },
      { path: 'responsaveis', name: 'guardians', component: () => import('@/views/guardians/GuardianListView.vue'), meta: { roles: ADMIN, title: 'Responsáveis' } },
      { path: 'mensalidades', name: 'invoices', component: () => import('@/views/finance/InvoicesView.vue'), meta: { roles: ADMIN, title: 'Mensalidades' } },
      { path: 'inadimplencia', name: 'delinquency', component: () => import('@/views/finance/DelinquencyView.vue'), meta: { roles: ADMIN, title: 'Inadimplência' } },
      { path: 'planos', name: 'plans', component: () => import('@/views/finance/PlansView.vue'), meta: { roles: ADMIN, title: 'Planos' } },
      { path: 'uniformes', name: 'uniforms', component: () => import('@/views/uniforms/UniformsView.vue'), meta: { roles: ADMIN, title: 'Uniformes' } },
      { path: 'patrocinios', name: 'sponsors', component: () => import('@/views/sponsors/SponsorsView.vue'), meta: { roles: ADMIN, title: 'Patrocínios' } },
      { path: 'turmas', name: 'classes', component: () => import('@/views/classes/ClassesView.vue'), meta: { roles: ALL, title: 'Turmas' } },
      { path: 'turmas/:id(\\d+)', name: 'class-show', component: () => import('@/views/classes/ClassDetailView.vue'), meta: { roles: ALL, title: 'Turma' } },
      { path: 'chamada', name: 'attendance', component: () => import('@/views/attendance/AttendanceView.vue'), meta: { roles: ALL, title: 'Chamada' } },
      { path: 'chamada/:classId(\\d+)', name: 'attendance-class', component: () => import('@/views/attendance/AttendanceView.vue'), meta: { roles: ALL, title: 'Chamada' } },
      { path: 'usuarios', name: 'users', component: () => import('@/views/admin/UsersView.vue'), meta: { roles: ADMIN, title: 'Usuários' } },
      { path: 'auditoria', name: 'audit', component: () => import('@/views/admin/AuditView.vue'), meta: { roles: ADMIN, title: 'Auditoria' } },
      { path: 'meu-espaco', name: 'portal', component: () => import('@/views/portal/PortalView.vue'), meta: { roles: PORTAL, title: 'Meu espaço' } },
      { path: 'minha-conta', name: 'account', component: () => import('@/views/auth/ChangePasswordView.vue'), meta: { roles: EVERYONE, title: 'Minha conta' } },
      { path: 'acesso-negado', name: 'forbidden', component: () => import('@/views/ForbiddenView.vue'), meta: { roles: EVERYONE, title: 'Acesso negado' } }
    ]
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue'), meta: { public: true, title: 'Página não encontrada' } }
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 })
})

/** Aceita só caminhos internos (evita open redirect em ?redirect=). */
export function safeRedirect(value) {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') && !value.includes('\\')
    ? value
    : '/'
}

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.init()

  if (to.meta.public) {
    if (to.name === 'login' && auth.isAuthenticated) return { name: 'dashboard' }
    return true
  }

  if (!auth.isAuthenticated) {
    return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} }
  }

  if (auth.mustChangePassword && to.name !== 'change-password') {
    return { name: 'change-password' }
  }

  // Atleta/responsável: a "página inicial" é o portal.
  if (auth.isPortal && to.name === 'dashboard') return { name: 'portal' }

  // Todas as rotas protegidas declaram perfis (negar por padrão).
  const roles = to.matched.flatMap((r) => r.meta.roles || [])
  if (!roles.length || !auth.hasRole(to.meta.roles || roles)) {
    if (auth.isPortal) return { name: 'portal' }
    return to.name === 'forbidden' ? true : { name: 'forbidden' }
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Irmãos da Bola Academy` : 'Irmãos da Bola Academy'
})

export { ADMIN, ALL, PORTAL, EVERYONE }
