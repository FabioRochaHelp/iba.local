<template>
  <div :class="['layout', { 'layout--menu-open': menuOpen }]">
    <a href="#conteudo" class="skip-link">Pular para o conteúdo</a>

    <aside class="sidebar" aria-label="Menu principal">
      <RouterLink :to="{ name: auth.isPortal ? 'portal' : 'dashboard' }" class="sidebar__brand" @click="menuOpen = false">
        <img src="/img/logo.jpg" alt="" width="56" height="56" />
        <span class="sidebar__brand-text">
          <strong>Irmãos da Bola</strong>
          <small>Academy</small>
        </span>
      </RouterLink>
      <div class="iba-ribbon"></div>

      <nav class="sidebar__nav">
        <template v-for="section in visibleNav" :key="section.label">
          <p class="sidebar__section">{{ section.label }}</p>
          <ul>
            <li v-for="item in section.items" :key="item.label">
              <RouterLink
                v-if="item.to" :to="item.to" class="sidebar__link" active-class="" exact-active-class="is-active"
                @click="menuOpen = false"
              >
                <i :class="item.icon" aria-hidden="true"></i>
                <span>{{ item.label }}</span>
              </RouterLink>
              <span v-else class="sidebar__link is-disabled" aria-disabled="true">
                <i :class="item.icon" aria-hidden="true"></i>
                <span>{{ item.label }}</span>
                <em>em breve</em>
              </span>
            </li>
          </ul>
        </template>
      </nav>
    </aside>

    <div class="backdrop" aria-hidden="true" @click="menuOpen = false"></div>

    <div class="main">
      <header class="topbar">
        <Button
          icon="pi pi-bars" text rounded severity="secondary" class="topbar__menu" aria-label="Abrir menu"
          @click="menuOpen = !menuOpen"
        />
        <h2 class="topbar__title">{{ route.meta.title }}</h2>

        <div class="topbar__actions">
          <Button
            v-tooltip.bottom="'Alternar tema'" :icon="isDark ? 'pi pi-sun' : 'pi pi-moon'" text rounded
            severity="secondary" :aria-label="isDark ? 'Usar tema claro' : 'Usar tema escuro'"
            @click="toggle"
          />
          <Button
            type="button" text severity="secondary" class="topbar__user" aria-haspopup="true"
            aria-controls="user-menu" @click="userMenu.toggle($event)"
          >
            <span class="avatar" aria-hidden="true">{{ initials }}</span>
            <span class="topbar__user-info">
              <strong>{{ auth.user?.name }}</strong>
              <small>{{ ROLE_LABELS[auth.user?.role] }}</small>
            </span>
            <i class="pi pi-angle-down" aria-hidden="true"></i>
          </Button>
          <Menu id="user-menu" ref="userMenu" :model="userItems" popup />
        </div>
      </header>

      <main id="conteudo" class="content" tabindex="-1">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { useAuthStore } from '@/stores/auth'
import { useTheme } from '@/composables/useTheme'
import { navigation } from '@/navigation'
import { ROLE_LABELS } from '@/utils/format'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { isDark, toggle } = useTheme()

const menuOpen = ref(false)
const userMenu = ref()

const visibleNav = computed(() =>
  navigation
    .map((s) => ({ ...s, items: s.items.filter((i) => auth.hasRole(i.roles)) }))
    .filter((s) => s.items.length)
)

const initials = computed(() =>
  (auth.user?.name || '?')
    .split(/\s+/)
    .slice(0, 2)
    .map((p) => p[0])
    .join('')
    .toUpperCase()
)

const userItems = [
  { label: 'Minha conta', icon: 'pi pi-user', command: () => router.push({ name: 'account' }) },
  { separator: true },
  {
    label: 'Sair',
    icon: 'pi pi-sign-out',
    command: async () => {
      await auth.logout()
      router.replace({ name: 'login' })
    }
  }
]
</script>

<style scoped>
.layout { min-height: 100vh; }

.skip-link {
  position: absolute;
  left: -999px;
  top: 0;
  z-index: 100;
  background: var(--iba-gold);
  color: #111;
  padding: .5rem 1rem;
}
.skip-link:focus { left: 1rem; }

/* Sidebar — o "escudo" preto */
.sidebar {
  position: fixed;
  inset: 0 auto 0 0;
  width: var(--iba-sidebar-w);
  background: var(--iba-black);
  color: #d2cdbf;
  display: flex;
  flex-direction: column;
  z-index: 40;
  transition: transform .2s ease;
}

.sidebar__brand {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: 1rem;
  text-decoration: none;
}

.sidebar__brand img {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid var(--iba-gold);
  object-fit: cover;
}

.sidebar__brand-text { display: flex; flex-direction: column; line-height: 1.1; }
.sidebar__brand-text strong {
  font-family: var(--iba-font-title);
  font-weight: 800;
  text-transform: uppercase;
  color: var(--iba-gold-light);
  font-size: 1rem;
}
.sidebar__brand-text small {
  font-family: var(--iba-font-title);
  font-weight: 700;
  letter-spacing: .3em;
  text-transform: uppercase;
  color: #a8a293;
  font-size: .65rem;
}

.sidebar__nav { overflow-y: auto; padding: .5rem 0 1.5rem; flex: 1; }
.sidebar__nav ul { list-style: none; margin: 0; padding: 0; }

.sidebar__section {
  margin: 1rem 1.25rem .35rem;
  font-size: .68rem;
  font-weight: 600;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: #7f7a6d;
}

.sidebar__link {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .6rem 1.25rem;
  color: #d2cdbf;
  text-decoration: none;
  border-left: 3px solid transparent;
  transition: background .15s, color .15s;
}
.sidebar__link:hover:not(.is-disabled) { background: var(--iba-black-soft); color: #fff; }
.sidebar__link.is-active {
  background: linear-gradient(90deg, rgba(201, 162, 63, .18), transparent);
  border-left-color: var(--iba-gold);
  color: var(--iba-gold-light);
  font-weight: 600;
}
.sidebar__link.is-disabled { opacity: .45; cursor: not-allowed; }
.sidebar__link em {
  margin-left: auto;
  font-style: normal;
  font-size: .65rem;
  border: 1px solid #45423b;
  border-radius: 999px;
  padding: 0 .45rem;
}

/* Conteúdo */
.main { margin-left: var(--iba-sidebar-w); min-height: 100vh; display: flex; flex-direction: column; }

.topbar {
  position: sticky;
  top: 0;
  z-index: 30;
  height: var(--iba-topbar-h);
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: 0 1.5rem;
  background: var(--iba-card);
  border-bottom: 2px solid var(--iba-gold);
}

.topbar__menu { display: none; }
.topbar__title { font-size: 1rem; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.topbar__actions { display: flex; align-items: center; gap: .25rem; }
.topbar__user { display: flex; align-items: center; gap: .6rem; }
.topbar__user-info { display: flex; flex-direction: column; align-items: flex-start; line-height: 1.15; }
.topbar__user-info strong { color: var(--iba-text); font-size: .85rem; }
.topbar__user-info small { color: var(--iba-text-muted); font-size: .72rem; }

.avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: var(--iba-black);
  color: var(--iba-gold-light);
  font-weight: 700;
  font-size: .8rem;
  border: 2px solid var(--iba-gold);
}

.content { padding: 1.5rem; flex: 1; outline: none; }

.backdrop { display: none; }

@media (max-width: 960px) {
  .sidebar { transform: translateX(-100%); }
  .layout--menu-open .sidebar { transform: translateX(0); }
  .layout--menu-open .backdrop {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .5);
    z-index: 35;
  }
  .main { margin-left: 0; }
  .topbar { padding: 0 1rem; }
  .topbar__menu { display: inline-flex; }
  .topbar__user-info { display: none; }
  .content { padding: 1rem; }
}
</style>
