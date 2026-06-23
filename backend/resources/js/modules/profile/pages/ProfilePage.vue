<template>
  <section class="profile-page">
    <header class="c-card profile-page__hero">
      <span class="profile-page__avatar">{{ initials }}</span>
      <div class="profile-page__identity">
        <h2>{{ profile.name }}</h2>
        <p>{{ profile.email }}</p>
      </div>
    </header>

    <div class="profile-page__grid">
      <article class="c-card profile-page__card">
        <h3>Account Overview</h3>
        <ProfileMetaRow label="Roles" :value="profile.roles.join(', ') || 'No roles assigned'" />
        <ProfileMetaRow label="Permissions" :value="String(profile.permissionsCount)" />
        <ProfileMetaRow label="Member Since" :value="profile.memberSince" />
        <ProfileMetaRow label="Last Active" :value="profile.lastActiveAt" />
      </article>

      <article class="c-card profile-page__card">
        <h3>Sessions & Devices</h3>
        <p class="profile-page__muted">Session and device tracking panel will be connected in the next security phase.</p>
      </article>

      <article class="c-card profile-page__card">
        <h3>Security</h3>
        <p class="profile-page__muted">MFA setup, trusted devices, and password policy controls will be managed here.</p>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import ProfileMetaRow from '../components/ProfileMetaRow.vue';
import { profileService } from '../services/profile.service';
import type { ProfileSummary } from '../types/profile.types';

/**
 * User-account page is intentionally isolated from platform settings:
 * - profile concerns are user-centric (identity, sessions, security)
 * - platform settings remain system-centric (runtime/infrastructure toggles)
 * This separation keeps account-center growth scalable for tenant/org features.
 */
const profile = ref<ProfileSummary>({
  name: 'Admin User',
  email: 'admin@saas.local',
  roles: [],
  permissionsCount: 0,
  memberSince: '-',
  lastActiveAt: '-',
});

const initials = computed(() => {
  return profile.value.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');
});

onMounted(async () => {
  profile.value = await profileService.fetchSummary();
});
</script>

<style scoped>
.profile-page{display:grid;gap:12px}
.profile-page__hero{margin-top:0;display:flex;align-items:center;gap:12px}
.profile-page__avatar{width:52px;height:52px;border-radius:999px;background:linear-gradient(130deg, rgba(59,130,246,.35), rgba(99,102,241,.35));display:inline-flex;align-items:center;justify-content:center;color:#f8fafc;font-weight:700}
.profile-page__identity h2{margin:0;font-size:18px;color:#f8fafc}
.profile-page__identity p{margin:4px 0 0;color:#94a3b8;font-size:13px}
.profile-page__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.profile-page__card{margin-top:0}
.profile-page__card h3{margin:0 0 8px;color:#f8fafc;font-size:15px}
.profile-page__muted{margin:0;color:#94a3b8;font-size:12px;line-height:1.5}
@media (max-width:1080px){.profile-page__grid{grid-template-columns:1fr}}
</style>

