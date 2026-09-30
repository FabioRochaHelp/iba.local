<template>
  <section class="iba-card iba-card--accent state">
    <i class="pi pi-lock" aria-hidden="true"></i>
    <h1>Acesso negado</h1>
    <p class="iba-muted">Seu perfil não tem permissão para acessar esta área.</p>
    <RouterLink :to="{ name: 'dashboard' }"><Button label="Voltar ao painel" icon="pi pi-home" /></RouterLink>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
</script>

<style scoped>
.state { max-width: 480px; margin: 3rem auto; text-align: center; display: grid; gap: .75rem; justify-items: center; }
.state > i { font-size: 2.5rem; color: var(--iba-gold); }
</style>
