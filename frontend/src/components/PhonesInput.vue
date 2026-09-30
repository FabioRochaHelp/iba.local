<template>
  <div class="phones">
    <div v-for="(item, i) in model" :key="i" class="phones__row">
      <InputText
        v-model="item.phone" :invalid="!!errorFor(i)" placeholder="(18) 99999-9999" inputmode="tel"
        maxlength="30" :aria-label="`Telefone ${i + 1}`" class="phones__number"
      />
      <InputText
        v-model="item.label" placeholder="Ex.: mãe, pai" maxlength="40" :aria-label="`Identificação do telefone ${i + 1}`"
        class="phones__label"
      />
      <label class="phones__wa">
        <Checkbox v-model="item.is_whatsapp" binary :input-id="`wa-${uid}-${i}`" />
        <span>WhatsApp</span>
      </label>
      <Button
        icon="pi pi-trash" text rounded severity="danger" :disabled="model.length === 1"
        :aria-label="`Remover telefone ${i + 1}`" @click="remove(i)"
      />
      <small v-if="errorFor(i)" class="field__error phones__error">{{ errorFor(i) }}</small>
    </div>
    <Button v-if="model.length < 5" label="Adicionar telefone" icon="pi pi-plus" text size="small" @click="add" />
    <small v-if="errors[prefix]" class="field__error">{{ errors[prefix] }}</small>
  </div>
</template>

<script setup>
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'

const model = defineModel({ type: Array, required: true })
const props = defineProps({
  errors: { type: Object, default: () => ({}) },
  prefix: { type: String, default: 'phones' }
})

const uid = Math.random().toString(36).slice(2, 8)

function errorFor(i) {
  return props.errors[`${props.prefix}.${i}`] || props.errors[`${props.prefix}.${i}.phone`]
}

function add() {
  model.value.push({ phone: '', label: '', is_whatsapp: true })
}

function remove(i) {
  model.value.splice(i, 1)
}
</script>

<style scoped>
.phones { display: grid; gap: .5rem; }
.phones__row { display: grid; grid-template-columns: minmax(150px, 1fr) minmax(110px, .8fr) auto auto; gap: .5rem; align-items: center; }
.phones__wa { display: flex; align-items: center; gap: .35rem; font-size: .85rem; white-space: nowrap; }
.phones__error { grid-column: 1 / -1; }
.field__error { color: var(--iba-danger); }
@media (max-width: 600px) {
  .phones__row { grid-template-columns: 1fr auto; }
  .phones__label { grid-column: 1; }
  .phones__wa { grid-column: 1; }
}
</style>
