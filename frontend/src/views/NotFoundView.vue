<template>
  <main class="nf">
    <img src="/img/logo.jpg" alt="Irmãos da Bola Academy" width="96" height="96" />
    <h1>Bola fora!</h1>
    <p>A página que você procura não existe.</p>
    <RouterLink to="/"><Button label="Voltar ao início" icon="pi pi-home" /></RouterLink>
  </main>
</template>

<script setup>
import Button from 'primevue/button'
</script>

<style scoped>
.nf {
  min-height: 100vh;
  display: grid;
  place-content: center;
  justify-items: center;
  gap: .75rem;
  text-align: center;
  background: var(--iba-black);
  color: #d2cdbf;
  padding: 1rem;
}
.nf img { border-radius: 50%; border: 2px solid var(--iba-gold); background: #fff; }
.nf h1 { color: var(--iba-gold-light); font-size: 2rem; }
</style>
