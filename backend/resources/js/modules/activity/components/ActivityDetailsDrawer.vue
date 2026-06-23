<template><section class="activity-details"><h4>{{ log.action }}</h4><p>Module: {{ log.module }}</p><p>Entity: {{ log.entity }}</p><p>Status: {{ log.status }}</p><p>Description: {{ log.description }}</p><p>IP: {{ log.ip_address || '-' }}</p></section></template>
<script setup lang="ts">import type { ActivityLogItem } from '../types/activity.types'; defineProps<{ log: ActivityLogItem }>();</script>
<style scoped>.activity-details{display:grid;gap:8px;color:#cbd5e1}.activity-details h4{margin:0;color:#f8fafc}.activity-details p{margin:0;font-size:12px}</style>
