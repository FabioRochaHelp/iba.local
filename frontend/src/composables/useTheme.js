import { ref } from 'vue'

const KEY = 'iba-theme'
const isDark = ref(false)

function read() {
  try {
    return localStorage.getItem(KEY)
  } catch {
    return null
  }
}

function write(value) {
  try {
    localStorage.setItem(KEY, value)
  } catch {
    /* armazenamento indisponível: preferência só vale nesta aba */
  }
}

function apply(dark) {
  isDark.value = dark
  document.documentElement.classList.toggle('iba-dark', dark)
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', '#111111')
}

export function applyStoredTheme() {
  const stored = read()
  const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches
  apply(stored ? stored === 'dark' : Boolean(prefersDark))
}

export function useTheme() {
  function toggle() {
    apply(!isDark.value)
    write(isDark.value ? 'dark' : 'light')
  }
  return { isDark, toggle }
}
