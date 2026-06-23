<template>
  <section class="auth-layout">
    <!--
      Auth shell placeholder.
      Keeping a dedicated layout boundary enables clean separation
      between authentication pages and admin workspace pages.
    -->
    <router-view />
  </section>
</template>

<style scoped>
.auth-layout {
  min-height: 100vh;
}
</style>

