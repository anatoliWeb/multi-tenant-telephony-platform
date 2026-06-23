<template>
  <BaseDropdown>
    <template #trigger="{ isOpen }">
      <button type="button" class="profile-chip" :class="{ 'is-open': isOpen }" :aria-label="t('common.topbar.openAccountPanel')">
        <span class="profile-chip__avatar">{{ initials }}</span>

        <span class="profile-chip__identity">
          <span class="profile-chip__name" :title="name">{{ name }}</span>
          <span class="profile-chip__subtitle" :title="email">{{ email }}</span>
        </span>

        <span class="profile-chip__caret" aria-hidden="true">
          <svg viewBox="0 0 20 20">
            <path d="M5.7 7.5a1 1 0 0 1 1.4 0L10 10.4l2.9-2.9a1 1 0 1 1 1.4 1.4l-3.6 3.6a1 1 0 0 1-1.4 0L5.7 8.9a1 1 0 0 1 0-1.4z" />
          </svg>
        </span>
      </button>
    </template>

    <template #default="{ close }">
      <section class="account-panel" :title="name">
        <div class="account-panel__identity">
          <span class="account-panel__avatar">{{ initials }}</span>
          <div class="account-panel__text">
            <p class="account-panel__name">{{ name }}</p>
            <p class="account-panel__email">{{ email }}</p>
          </div>
        </div>

        <div class="account-panel__divider" />

        <div class="account-panel__actions">
          <button type="button" class="account-action" :class="{ 'is-active': isActive('/profile') }" @click="navigate('/profile', close)">{{ t('common.actions.profile') }}</button>
          <button type="button" class="account-action" :class="{ 'is-active': isActive('/settings') }" @click="navigate('/settings', close)">{{ t('common.actions.settings') }}</button>
          <button type="button" class="account-action" :class="{ 'is-active': isActive('/billing') }" @click="navigate('/billing', close)">{{ t('common.actions.billing') }}</button>
        </div>

        <div class="account-panel__divider" />

        <button type="button" class="account-action account-action--danger" @click="handleLogout(close)">{{ t('common.actions.logout') }}</button>
      </section>
    </template>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

import BaseDropdown from './BaseDropdown.vue';

/**
 * Profile chip + floating account panel for SaaS shell identity actions.
 *
 * WHY THIS PATTERN:
 * Compact identity chips work better than oversized user blocks in crowded
 * topbars because they preserve horizontal space while still exposing context.
 * The floating panel keeps identity and actions grouped in a reusable popover
 * model that can later host workspace/org switching without architecture churn.
 */
interface Props {
  name?: string;
  email?: string;
}

const emit = defineEmits<{
  logout: [];
}>();

const props = withDefaults(defineProps<Props>(), {
  name: 'Admin User',
  email: 'admin@saas.local',
});

const route = useRoute();
const router = useRouter();
const { t } = useI18n({ useScope: 'global' });

const initials = computed(() => {
  return props.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');
});

const isActive = (path: string): boolean => route.path === path;

/**
 * Account navigation intentionally routes to dedicated account/platform pages.
 * This keeps the topbar menu as a real navigation hub instead of static actions
 * and prepares account-center extensibility (tenant settings, billing, orgs).
 */
const navigate = async (path: string, close: () => void): Promise<void> => {
  if (route.path !== path) {
    await router.push(path);
  }
  close();
};

const handleLogout = (close: () => void): void => {
  close();
  emit('logout');
};
</script>

<style scoped>
.profile-chip {
  height: 40px;
  max-width: 220px;
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  background: rgba(15, 23, 42, 0.72);
  color: #e2e8f0;
  padding: 0 9px 0 4px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.profile-chip:hover,
.profile-chip.is-open {
  background: rgba(51, 65, 85, 0.82);
  border-color: rgba(96, 165, 250, 0.5);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

.profile-chip__avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 999px;
  background: linear-gradient(130deg, rgba(59, 130, 246, 0.35), rgba(99, 102, 241, 0.35));
  color: #f8fafc;
  font-size: 10px;
  font-weight: 700;
  flex: 0 0 auto;
}

.profile-chip__identity {
  min-width: 0;
  display: grid;
  gap: 1px;
  flex: 1;
}

.profile-chip__name,
.profile-chip__subtitle {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.profile-chip__name {
  color: #f8fafc;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.2;
}

.profile-chip__subtitle {
  color: #94a3b8;
  font-size: 10px;
  line-height: 1.2;
}

.profile-chip__caret {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  flex: 0 0 auto;
}

.profile-chip__caret svg {
  width: 14px;
  height: 14px;
  fill: currentColor;
}

.account-panel {
  width: 254px;
  display: grid;
  gap: 10px;
  padding: 6px;
}

.account-panel__identity {
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr);
  align-items: center;
  gap: 10px;
  padding: 8px;
}

.account-panel__avatar {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  background: linear-gradient(130deg, rgba(59, 130, 246, 0.35), rgba(99, 102, 241, 0.35));
  color: #f8fafc;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
}

.account-panel__name,
.account-panel__email {
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account-panel__name {
  color: #f8fafc;
  font-size: 13px;
  font-weight: 700;
}

.account-panel__email {
  margin-top: 3px;
  color: #94a3b8;
  font-size: 11px;
}

.account-panel__divider {
  height: 1px;
  background: rgba(71, 85, 105, 0.55);
  margin: 0 4px;
}

.account-panel__actions {
  display: grid;
  gap: 2px;
}

.account-action {
  width: 100%;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #e2e8f0;
  font-size: 13px;
  text-align: left;
  padding: 9px 10px;
  transition: background-color 0.2s ease, transform 0.2s ease;
}

.account-action:hover {
  background: rgba(51, 65, 85, 0.72);
}

.account-action.is-active {
  background: rgba(59, 130, 246, 0.2);
  color: #dbeafe;
}

.account-action:active {
  transform: translateY(1px);
}

.account-action--danger {
  color: #fda4af;
}

:deep(.base-dropdown__menu) {
  padding: 6px;
  border-radius: 14px;
  border-color: rgba(100, 116, 139, 0.72);
  box-shadow: 0 16px 34px rgba(2, 6, 23, 0.56);
}

@media (max-width: 860px) {
  .profile-chip {
    max-width: 180px;
  }
}

@media (max-width: 560px) {
  .profile-chip {
    max-width: 150px;
  }
}
</style>
