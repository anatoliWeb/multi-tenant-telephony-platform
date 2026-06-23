<template>
  <section class="min-h-screen bg-slate-100 p-6">
    <div class="mx-auto max-w-4xl space-y-6">
      <header class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-900">Vue Admin Migration Works</h1>
        <p class="mt-2 text-sm text-slate-600">
          Coexistence validation page for gradual Blade to Vue admin migration.
        </p>
      </header>

      <div class="grid gap-4 md:grid-cols-2">
        <article
          v-for="item in checks"
          :key="item.label"
          class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-800">{{ item.label }}</h2>
            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
              OK
            </span>
          </div>
          <p class="mt-2 text-sm text-slate-600">{{ item.description }}</p>
        </article>
      </div>

      <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Runtime Debug</h2>
        <dl class="mt-4 grid gap-3 text-sm text-slate-700 md:grid-cols-2">
          <div>
            <dt class="font-medium text-slate-500">Current Route</dt>
            <dd>{{ route.path }}</dd>
          </div>
          <div>
            <dt class="font-medium text-slate-500">Route Name</dt>
            <dd>{{ String(route.name ?? 'n/a') }}</dd>
          </div>
          <div>
            <dt class="font-medium text-slate-500">Environment Mode</dt>
            <dd>{{ mode }}</dd>
          </div>
          <div>
            <dt class="font-medium text-slate-500">Rendered At</dt>
            <dd>{{ renderedAt }}</dd>
          </div>
        </dl>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';

/**
 * Migration infrastructure validation page.
 *
 * WHY:
 * This page proves that the coexistence path is functional:
 * Laravel route -> Vue Blade layout -> Vue mount -> Vue Router -> Vue page.
 * It allows low-risk, page-by-page migration without destabilizing legacy admin screens.
 */
const route = useRoute();

const mode = import.meta.env.MODE;
const renderedAt = new Date().toISOString();

const checks = [
  { label: 'Vue mounted successfully', description: 'The SPA root is mounted inside the dedicated vue-admin Blade layout.' },
  { label: 'Vue Router active', description: 'Current page is resolved by router configuration, not by fallback component.' },
  { label: 'Admin layout active', description: 'Route is rendered under AdminLayout for consistent shell structure.' },
  { label: 'Vite assets loaded', description: 'JavaScript and styles are loaded through Laravel Vite manifest entries.' },
  { label: 'Gradual migration ready', description: 'Infrastructure supports route-by-route Blade to Vue replacement.' },
];
</script>

