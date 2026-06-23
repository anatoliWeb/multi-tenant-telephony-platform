<template><section class="token-details"><h4>{{ token.name }}</h4><p>Owner: {{ token.owner.name }}</p><p>Status: {{ token.status }}</p><p>Scopes: {{ token.scopes.join(', ') || 'None' }}</p></section></template>
<script setup lang="ts">import type { TokenListItem } from '../types/tokens.types'; defineProps<{ token: TokenListItem }>();</script>
<style scoped>.token-details{display:grid;gap:8px;color:#cbd5e1}.token-details h4{margin:0;color:#f8fafc}.token-details p{margin:0;font-size:12px}</style>
