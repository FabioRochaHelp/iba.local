<template>
  <figure class="chart">
    <figcaption class="chart__head">
      <div>
        <h2>Receita por mês</h2>
        <p class="iba-muted">Previsto × recebido nos últimos {{ series.length }} meses (por mês de referência)</p>
      </div>
      <div class="chart__tools">
        <ul class="legend" aria-label="Legenda">
          <li><span class="swatch swatch--expected" aria-hidden="true"></span>Previsto</li>
          <li><span class="swatch swatch--received" aria-hidden="true"></span>Recebido</li>
        </ul>
        <Button
          :label="showTable ? 'Ver gráfico' : 'Ver tabela'" :icon="showTable ? 'pi pi-chart-bar' : 'pi pi-table'" text size="small"
          severity="secondary" @click="showTable = !showTable"
        />
      </div>
    </figcaption>

    <table v-if="showTable" class="chart__table">
      <thead><tr><th scope="col">Mês</th><th scope="col">Previsto</th><th scope="col">Recebido</th><th scope="col">%</th></tr></thead>
      <tbody>
        <tr v-for="m in series" :key="m.month">
          <th scope="row">{{ formatMonth(m.month) }}</th>
          <td>{{ formatMoney(m.expected) }}</td>
          <td>{{ formatMoney(m.received) }}</td>
          <td>{{ pct(m) }}</td>
        </tr>
      </tbody>
    </table>

    <div v-else ref="box" class="chart__plot" @mouseleave="hover = null">
      <svg :width="width" :height="HEIGHT" role="img" :aria-label="ariaLabel">
        <!-- grade e eixo Y (recessivos) -->
        <g v-for="t in ticks" :key="t" class="grid">
          <line :x1="PAD.left" :x2="width - PAD.right" :y1="y(t)" :y2="y(t)" />
          <text :x="PAD.left - 8" :y="y(t)" dy="0.32em" text-anchor="end">{{ shortMoney(t) }}</text>
        </g>

        <g v-for="(m, i) in series" :key="m.month">
          <!-- faixa de hover maior que as barras -->
          <rect
            class="hit" :x="bandX(i)" :y="PAD.top" :width="band" :height="plotH" tabindex="0" :aria-label="`${formatMonth(m.month)}: previsto ${formatMoney(m.expected)}, recebido ${formatMoney(m.received)}`"
            @mouseenter="hover = i" @focus="hover = i"
          />
          <rect v-if="hover === i" class="band-hl" :x="bandX(i)" :y="PAD.top" :width="band" :height="plotH" />
          <path class="bar bar--expected" :d="barPath(barX(i, 0), Number(m.expected))" />
          <path class="bar bar--received" :d="barPath(barX(i, 1), Number(m.received))" />
          <text class="xlabel" :x="bandX(i) + band / 2" :y="HEIGHT - 8" text-anchor="middle">{{ formatMonth(m.month, true) }}</text>
        </g>
        <line class="baseline" :x1="PAD.left" :x2="width - PAD.right" :y1="y(0)" :y2="y(0)" />
      </svg>

      <div v-if="hover !== null" class="tooltip" :style="tooltipStyle" role="status">
        <strong>{{ formatMonth(series[hover].month) }}</strong>
        <div><span class="swatch swatch--expected" aria-hidden="true"></span>Previsto <b>{{ formatMoney(series[hover].expected) }}</b></div>
        <div><span class="swatch swatch--received" aria-hidden="true"></span>Recebido <b>{{ formatMoney(series[hover].received) }}</b></div>
        <div class="iba-muted">{{ pct(series[hover]) }} do previsto</div>
      </div>
    </div>
  </figure>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch, nextTick } from 'vue'
import Button from 'primevue/button'
import { formatMoney, formatMonth } from '@/utils/format'

const props = defineProps({ series: { type: Array, required: true } })

const HEIGHT = 240
const PAD = { top: 12, right: 8, bottom: 28, left: 56 }
const BAR_MAX = 24
const GAP = 2

const box = ref(null)
const width = ref(600)
const hover = ref(null)
const showTable = ref(false)
let observer = null

const plotH = HEIGHT - PAD.top - PAD.bottom
const band = computed(() => (width.value - PAD.left - PAD.right) / Math.max(1, props.series.length))
const barW = computed(() => Math.min(BAR_MAX, (band.value * 0.6 - GAP) / 2))

const maxValue = computed(() => Math.max(1, ...props.series.flatMap((m) => [Number(m.expected), Number(m.received)])))
const step = computed(() => {
  const raw = maxValue.value / 4
  const mag = 10 ** Math.floor(Math.log10(raw))
  return [1, 2, 2.5, 5, 10].map((s) => s * mag).find((s) => s >= raw)
})
const ticks = computed(() => Array.from({ length: Math.ceil(maxValue.value / step.value) + 1 }, (_, i) => i * step.value))
const top = computed(() => ticks.value[ticks.value.length - 1])

const y = (v) => PAD.top + plotH - (v / top.value) * plotH
const bandX = (i) => PAD.left + i * band.value
const barX = (i, k) => bandX(i) + band.value / 2 - barW.value - GAP / 2 + k * (barW.value + GAP)

/** Coluna com cantos arredondados só no topo (4px), quadrada na base. */
function barPath(x, v) {
  if (v <= 0) return ''
  const w = barW.value
  const h = Math.max(1, y(0) - y(v))
  const r = Math.min(4, w / 2, h)
  const y0 = y(0)
  return `M${x},${y0} V${y0 - h + r} Q${x},${y0 - h} ${x + r},${y0 - h} H${x + w - r} Q${x + w},${y0 - h} ${x + w},${y0 - h + r} V${y0} Z`
}

const shortMoney = (v) => (v >= 1000 ? `R$ ${(v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mil` : `R$ ${v}`)
const pct = (m) => (Number(m.expected) > 0 ? `${Math.round((Number(m.received) / Number(m.expected)) * 100)}%` : '—')

const tooltipStyle = computed(() => {
  const x = bandX(hover.value) + band.value / 2
  const flip = x > width.value * 0.65
  return { left: `${x}px`, transform: flip ? 'translateX(calc(-100% - 12px))' : 'translateX(12px)' }
})

const ariaLabel = computed(() =>
  `Gráfico de colunas: previsto e recebido por mês. ${props.series.map((m) => `${formatMonth(m.month)}: previsto ${formatMoney(m.expected)}, recebido ${formatMoney(m.received)}`).join('; ')}`
)

function observe() {
  observer?.disconnect()
  if (!box.value) return
  observer = new ResizeObserver(([entry]) => {
    width.value = Math.max(280, Math.floor(entry.contentRect.width))
  })
  observer.observe(box.value)
}

onMounted(observe)
watch(showTable, async (t) => {
  if (!t) {
    await nextTick()
    observe()
  }
})
onBeforeUnmount(() => observer?.disconnect())
</script>

<style scoped>
.chart { margin: 0; }
.chart__head { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; align-items: flex-start; margin-bottom: .75rem; }
.chart__head p { margin: .2rem 0 0; font-size: .85rem; }
.chart__tools { display: flex; align-items: center; gap: .75rem; }
.legend { display: flex; gap: 1rem; list-style: none; margin: 0; padding: 0; font-size: .85rem; color: var(--iba-text); }
.legend li { display: flex; align-items: center; gap: .4rem; }
.swatch { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: .35rem; }
.swatch--expected { background: var(--c-expected); }
.swatch--received { background: var(--c-received); }

.chart__plot { position: relative; width: 100%; }
svg { display: block; overflow: visible; }
.grid line { stroke: var(--iba-border); stroke-width: 1; }
.grid text, .xlabel { fill: var(--iba-text-muted); font-size: 11px; }
.baseline { stroke: var(--iba-text-muted); stroke-width: 1; }
.bar--expected { fill: var(--c-expected); }
.bar--received { fill: var(--c-received); }
.hit { fill: transparent; cursor: pointer; outline: none; }
.hit:focus-visible { stroke: var(--iba-gold); stroke-width: 2; }
.band-hl { fill: var(--iba-text); opacity: .05; pointer-events: none; }

.tooltip {
  position: absolute; top: 8px; pointer-events: none; z-index: 2;
  background: var(--iba-card); color: var(--iba-text); border: 1px solid var(--iba-border);
  box-shadow: var(--iba-shadow); border-radius: 8px; padding: .5rem .7rem; font-size: .8rem; white-space: nowrap;
  display: grid; gap: .2rem;
}
.tooltip b { margin-left: .35rem; }

.chart__table { width: 100%; border-collapse: collapse; font-size: .9rem; }
.chart__table th, .chart__table td { padding: .45rem .5rem; border-bottom: 1px solid var(--iba-border); text-align: right; }
.chart__table th:first-child { text-align: left; font-weight: 500; }
.chart__table thead th { font-size: .75rem; text-transform: uppercase; color: var(--iba-text-muted); }
</style>
