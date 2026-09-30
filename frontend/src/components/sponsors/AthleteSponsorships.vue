<template>
  <section v-if="entries.length" class="box">
    <h3>Patrocínios destinados ao atleta</h3>
    <ul>
      <li v-for="e in entries" :key="e.id">
        <span>{{ formatDate(e.received_at) }} · {{ e.sponsor_name || e.description || 'Patrocínio' }}</span>
        <strong>{{ formatMoney(e.amount) }}</strong>
      </li>
    </ul>
    <p class="total">Total: <strong>{{ formatMoney(sum) }}</strong></p>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { sponsorsApi } from '@/api/sponsors'
import { formatDate, formatMoney } from '@/utils/format'

const props = defineProps({ athleteId: { type: Number, required: true } })
const entries = ref([])
const sum = ref('0.00')

onMounted(async () => {
  try {
    const res = await sponsorsApi.entries({ athlete_id: props.athleteId, per_page: 50 })
    entries.value = res.data
    sum.value = res.meta.sum
  } catch {
    /* seção opcional */
  }
})
</script>

<style scoped>
.box { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--iba-border); }
h3 { font-size: .9rem; margin-bottom: .5rem; }
ul { list-style: none; padding: 0; margin: 0; display: grid; gap: .35rem; }
li { display: flex; justify-content: space-between; gap: 1rem; font-size: .9rem; }
.total { text-align: right; margin: .5rem 0 0; }
</style>
