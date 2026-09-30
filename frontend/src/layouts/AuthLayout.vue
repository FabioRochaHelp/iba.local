<template>
  <main class="auth">
    <div class="auth__glow" aria-hidden="true"></div>
    <div class="auth__content">
      <img src="/img/logo.jpg" alt="Irmãos da Bola Academy" class="auth__logo" width="140" height="140" />
      <RouterView />
      <p class="auth__footer">© {{ year }} Irmãos da Bola Academy</p>
    </div>
  </main>
</template>

<script setup>
const year = new Date().getFullYear()
</script>

<style scoped>
.auth {
  position: relative;
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 2rem 1rem;
  background: var(--iba-black);
  overflow: hidden;
}

.auth__glow {
  position: absolute;
  inset: -20%;
  background:
    radial-gradient(circle at 50% 30%, rgba(201, 162, 63, .28), transparent 45%),
    radial-gradient(circle at 20% 90%, rgba(62, 134, 184, .12), transparent 40%),
    radial-gradient(circle at 85% 80%, rgba(242, 194, 48, .10), transparent 40%);
  pointer-events: none;
}

.auth__content {
  position: relative;
  width: 100%;
  max-width: 400px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
}

.auth__logo {
  width: 140px;
  height: 140px;
  border-radius: 50%;
  object-fit: cover;
  background: #fff;
  border: 3px solid var(--iba-gold);
  box-shadow: 0 0 0 6px rgba(201, 162, 63, .15), 0 12px 40px rgba(0, 0, 0, .5);
}

.auth__footer {
  margin: 0;
  color: #7f7a6d;
  font-size: .8rem;
}
</style>
