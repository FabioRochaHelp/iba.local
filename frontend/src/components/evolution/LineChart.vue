<template>
  <figure class="lchart">
    <figcaption class="lchart__head">
      <h3>{{ title }}</h3>
      <Button
        v-if="points.length" :label="showTable ? 'Gráfico' : 'Tabela'" :icon="showTable ? 'pi pi-chart-line' : 'pi pi-table'" text size="small"
        severity="secondary" @click="showTable = !showTable"
      />
    </figcaption>

    <p v-if="!points.length" class="iba-muted empty">Sem registros ainda.</p>

    <table v-else-if="showTable" class="lchart__table">
      <thead><tr><th scope="col">Data</th><th scope="col">{{ valueLabel }}</th></tr></thead>
      <tbody>
        <tr v-for="(p, i) in points" :key="i"><th scope="row">{{ formatDate(p.date) }}</th><td>{{ fmt(p.value) }}</td></tr>
      </tbody>
    </table>

    <div v-else ref="box" class="lchart__plot" @mouseleave="hover = null">
      <svg :width="width" :height="HEIGHT" role="img" :aria-label="ariaLabel">
        <g v-for="t in ticks" :key="t" class="grid">
          <line :x1="PAD.left" :x2="width - PAD.right" :y1="y(t)" :y2="y(t)" />
          <text :x="PAD.left - 6" :y="y(t)" dy="0.32em" text-anchor="end">{{ fmtTick(t) }}</text>
        </g>
        <path v-if="points.length > 1" class="line" :d="linePath" />
        <line v-if="hover !== null" class="crosshair" :x1="x(hover)" :x2="x(hover)" :y1="PAD.top" :y2="HEIGHT - PAD.bottom" />
        <g v-for="(p, i) in points" :key="i">
          <circle class="dot" :cx="x(i)" :cy="y(p.value)" :r="hover === i ? 6 : 4.5" />
          <!-- alvo de hover maior que o marcador -->
          <rect
            class="hit" :x="x(i) - hitW / 2" :y="PAD.top" :width="hitW" :height="HEIGHT - PAD.top - PAD.bottom" tabindex="0"
            :aria-label="`${formatDate(p.date)}: ${fmt(p.value)}`" @mouseenter="hover = i" @focus="hover = i" @blur="hover = null"
          />
        </g>
        <text v-for="(p, i) in xLabels" :key="`x${i}`" class="xlabel" :x="x(p.index)" :y="HEIGHT - 6" text-anchor="middle">{{ shortDate(p.date) }}</text>
        <!-- rótulo direto só no último ponto -->
        <text v-if="points.length" class="last" :x="x(points.length - 1)" :y="y(points[points.length - 1].value) - 10" text-anchor="middle">
          {{ fmt(points[points.length - 1].value) }}
        </text>
      </svg>
      <div v-if="hover !== null" class="tip" :style="tipStyle" role="status">
        <strong>{{ formatDate(points[hover].date) }}</strong>
        <span>{{ valueLabel }}: <b>{{ fmt(points[hover].value) }}</b></span>
        <span v-if="hover > 0" class="iba-muted">{{ deltaText(hover) }}</span>
      </div>
    </div>
  </figure>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Button from 'primevue/button'
import { formatDate } from '@/utils/format'

/**
 * Linha de uma série (evolução no tempo). Sem legenda: o título nomeia a série.
 * Cor validada (daltonismo + contraste) via token --c-expected.
 */
const props = defineProps({
  title: { type: String, required: true },
  points: { type: Array, required: true }, // [{ date: 'AAAA-MM-DD', value: number }] em ordem cronológica
  valueLabel: { type: String, default: 'Valor' },
  unit: { type: String, default: '' },
  digits: { type: Number, default: 1 },
  min: { type: Number, default: null },
  max: { type: Number, default: null },
  lowerIsBetter: { type: Boolean, default: false }
})

const HEIGHT = 190
const PAD = { top: 22, right: 18, bottom: 26, left: 44 }
const box = ref(null)
const width = ref(400)
const hover = ref(null)
const showTable = ref(false)
let observer = null

const values = computed(() => props.points.map((p) => Number(p.value)))
const domain = computed(() => {
  let lo = props.min ?? Math.min(...values.value)
  let hi = props.max ?? Math.max(...values.value)
  if (!Number.isFinite(lo) || !Number.isFinite(hi)) return [0, 1]
  if (lo === hi) {
    lo -= 1
    hi += 1
  }
  if (props.min === null) lo -= (hi - lo) * 0.15
  if (props.max === null) hi += (hi - lo) * 0.15
  return [lo, hi]
})
const ticks = computed(() => {
  const [lo, hi] = domain.value
  const raw = (hi - lo) / 4
  const mag = 10 ** Math.floor(Math.log10(raw))
  const step = [1, 2, 2.5, 5, 10].map((s) => s * mag).find((s) => s >= raw)
  const out = []
  for (let t = Math.ceil(lo / step) * step; t <= hi + 1e-9; t += step) out.push(Number(t.toFixed(6)))
  return out
})

const plotW = computed(() => width.value - PAD.left - PAD.right)
const x = (i) => PAD.left + (props.points.length === 1 ? plotW.value / 2 : (i / (props.points.length - 1)) * plotW.value)
const y = (v) => {
  const [lo, hi] = domain.value
  return PAD.top + (1 - (v - lo) / (hi - lo)) * (HEIGHT - PAD.top - PAD.bottom)
}
const hitW = computed(() => Math.max(24, plotW.value / Math.max(1, props.points.length)))
const linePath = computed(() => props.points.map((p, i) => `${i ? 'L' : 'M'}${x(i)},${y(Number(p.value))}`).join(' '))

// No máximo ~6 rótulos no eixo X para não sobrepor.
const xLabels = computed(() => {
  const n = props.points.length
  const every = Math.max(1, Math.ceil(n / 6))
  return props.points.map((p, index) => ({ ...p, index })).filter((p) => p.index % every === 0 || p.index === n - 1)
})

const fmt = (v) => `${Number(v).toLocaleString('pt-BR', { minimumFractionDigits: props.digits, maximumFractionDigits: props.digits })}${props.unit ? ` ${props.unit}` : ''}`
const fmtTick = (v) => Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 1 })
const shortDate = (d) => { const [yy, m] = String(d).split('-'); return `${m}/${yy.slice(2)}` }

function deltaText(i) {
  const d = Number(props.points[i].value) - Number(props.points[i - 1].value)
  if (Math.abs(d) < 1e-9) return 'igual à anterior'
  const better = props.lowerIsBetter ? d < 0 : d > 0
  return `${d > 0 ? '+' : '−'}${fmt(Math.abs(d))} vs. anterior (${better ? 'melhorou' : 'piorou'})`
}

const tipStyle = computed(() => {
  const px = x(hover.value)
  return { left: `${px}px`, transform: px > width.value * 0.6 ? 'translateX(calc(-100% - 10px))' : 'translateX(10px)' }
})
const ariaLabel = computed(() => `${props.title}: ${props.points.map((p) => `${formatDate(p.date)} ${fmt(p.value)}`).join('; ')}`)

function observe() {
  observer?.disconnect()
  if (!box.value) return
  observer = new ResizeObserver(([e]) => { width.value = Math.max(240, Math.floor(e.contentRect.width)) })
  observer.observe(box.value)
}
onMounted(observe)
watch(showTable, async (t) => { if (!t) { await nextTick(); observe() } })
watch(() => props.points.length, async () => { await nextTick(); observe() })
onBeforeUnmount(() => observer?.disconnect())
</script>

<style scoped>
.lchart { margin: 0; }
.lchart__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: .25rem; }
.lchart__head h3 { font-size: .85rem; }
.empty { font-size: .85rem; padding: 1.5rem 0; text-align: center; }
.lchart__plot { position: relative; width: 100%; }
svg { display: block; overflow: visible; }
.grid line { stroke: var(--iba-border); }
.grid text, .xlabel { fill: var(--iba-text-muted); font-size: 10.5px; }
.line { fill: none; stroke: var(--c-expected); stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; }
.dot { fill: var(--c-expected); stroke: var(--iba-card); stroke-width: 2; }
.crosshair { stroke: var(--iba-text-muted); stroke-dasharray: 3 3; opacity: .6; }
.hit { fill: transparent; cursor: pointer; outline: none; }
.hit:focus-visible { stroke: var(--iba-gold); stroke-width: 2; }
.last { fill: var(--iba-text); font-size: 11px; font-weight: 700; }
.tip {
  position: absolute; top: 4px; pointer-events: none; z-index: 2; display: grid; gap: .15rem;
  background: var(--iba-card); color: var(--iba-text); border: 1px solid var(--iba-border); box-shadow: var(--iba-shadow);
  border-radius: 8px; padding: .45rem .65rem; font-size: .78rem; white-space: nowrap;
}
.lchart__table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.lchart__table th, .lchart__table td { padding: .35rem .4rem; border-bottom: 1px solid var(--iba-border); text-align: right; }
.lchart__table th:first-child { text-align: left; font-weight: 500; }
.lchart__table thead th { font-size: .7rem; text-transform: uppercase; color: var(--iba-text-muted); }
</style>
