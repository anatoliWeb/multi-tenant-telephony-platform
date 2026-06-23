<template>
  <section class="user-details">
    <h4>{{ user.name }}</h4>
    <p>{{ user.email }}</p>
    <p>Roles: {{ user.roles.join(', ') || 'None' }}</p>
    <p>Status: {{ user.status }}</p>
  </section>
</template>

<script setup lang="ts">
import type { UserListItem } from '../types/users.types';

defineProps<{ user: UserListItem }>();
</script>

<style scoped>
.user-details{display:grid;gap:8px;color:#cbd5e1}
.user-details h4{margin:0;color:#f8fafc}
.user-details p{margin:0;font-size:12px}
</style>
