<template><section class="role-details"><h4>{{ role.label }}</h4><p>{{ role.description || 'No description' }}</p><p>Permissions: {{ role.permissions_count }}</p><p>Users: {{ role.users_count }}</p></section></template>
<script setup lang="ts">import type { RoleListItem } from '../types/roles.types'; defineProps<{ role: RoleListItem }>();</script>
<style scoped>.role-details{display:grid;gap:8px;color:#cbd5e1}.role-details h4{margin:0;color:#f8fafc}.role-details p{margin:0;font-size:12px}</style>

