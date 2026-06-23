<template><section class="permission-details"><h4>{{ permission.label }}</h4><p>Module: {{ permission.module }}</p><p>Type: {{ permission.type }}</p><p>Used by roles: {{ permission.used_by_roles.join(', ') || 'None' }}</p><p>{{ permission.description || 'No description' }}</p></section></template>
<script setup lang="ts">import type { PermissionListItem } from '../types/permissions.types'; defineProps<{ permission: PermissionListItem }>();</script>
<style scoped>.permission-details{display:grid;gap:8px;color:#cbd5e1}.permission-details h4{margin:0;color:#f8fafc}.permission-details p{margin:0;font-size:12px}</style>

