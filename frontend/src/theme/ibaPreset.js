/**
 * Tema "Irmãos da Bola Academy" para o PrimeVue.
 * Cores extraídas do logo: escudo preto, borda/letreiro dourado,
 * camisas amarela e azul-celeste.
 *
 * O botão primário é dourado com texto preto (contraste ~8:1, WCAG AA),
 * como o letreiro do escudo.
 */
import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

const gold = {
  50: '#fbf7ec',
  100: '#f5ecd0',
  200: '#ecdba3',
  300: '#e5c76b',
  400: '#d7b453',
  500: '#c9a23f',
  600: '#a8852f',
  700: '#8c6a1f',
  800: '#6e5319',
  900: '#564116',
  950: '#30240b'
}

// Neutros levemente quentes (combinam com o dourado).
const warm = {
  0: '#ffffff',
  50: '#faf9f6',
  100: '#f3f1ea',
  200: '#e6e2d7',
  300: '#d2cdbf',
  400: '#a8a293',
  500: '#7f7a6d',
  600: '#5f5b51',
  700: '#45423b',
  800: '#2c2a26',
  900: '#1e1e1e',
  950: '#111111'
}

export const IbaPreset = definePreset(Aura, {
  primitive: {
    borderRadius: { none: '0', xs: '4px', sm: '6px', md: '8px', lg: '12px', xl: '16px' }
  },
  semantic: {
    primary: gold,
    focusRing: { width: '2px', style: 'solid', color: '{primary.600}', offset: '2px' },
    colorScheme: {
      light: {
        surface: warm,
        primary: {
          color: '{primary.500}',
          contrastColor: '#111111',
          hoverColor: '{primary.600}',
          activeColor: '{primary.700}'
        },
        highlight: {
          background: '{primary.50}',
          focusBackground: '{primary.100}',
          color: '{primary.800}',
          focusColor: '{primary.900}'
        },
        text: { color: '#1e1e1e', mutedColor: '#5f5b51' }
      },
      dark: {
        surface: {
          0: '#ffffff',
          50: '#f3f1ea',
          100: '#d2cdbf',
          200: '#a8a293',
          300: '#7f7a6d',
          400: '#5f5b51',
          500: '#45423b',
          600: '#34322d',
          700: '#2a2825',
          800: '#1e1e1e',
          900: '#161616',
          950: '#111111'
        },
        primary: {
          color: '{primary.400}',
          contrastColor: '#111111',
          hoverColor: '{primary.300}',
          activeColor: '{primary.200}'
        },
        highlight: {
          background: 'rgba(201, 162, 63, .16)',
          focusBackground: 'rgba(201, 162, 63, .24)',
          color: 'rgba(255, 255, 255, .87)',
          focusColor: 'rgba(255, 255, 255, .87)'
        }
      }
    }
  }
})

export const darkModeSelector = '.iba-dark'
