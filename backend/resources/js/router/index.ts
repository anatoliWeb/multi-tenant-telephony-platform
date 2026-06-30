import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';

import AdminLayout from '../layouts/AdminLayout.vue';
import AuthLayout from '../layouts/AuthLayout.vue';
import LoginView from '../modules/auth/views/LoginView.vue';
import DashboardPage from '../modules/dashboard/pages/DashboardPage.vue';
import VueDemoPage from '../modules/dashboard/pages/VueDemoPage.vue';
import DemoUI from '../modules/demo/views/DemoUI.vue';
import ModulePlaceholderPage from '../modules/shared/pages/ModulePlaceholderPage.vue';
import UsersPage from '../modules/users/pages/UsersPage.vue';
import RolesPage from '../modules/roles/pages/RolesPage.vue';
import PermissionsPage from '../modules/permissions/pages/PermissionsPage.vue';
import TokensPage from '../modules/tokens/pages/TokensPage.vue';
import ActivityPage from '../modules/activity/pages/ActivityPage.vue';
import SettingsPage from '../modules/settings/pages/SettingsPage.vue';
import ProfilePage from '../modules/profile/pages/ProfilePage.vue';
import BillingPage from '../modules/billing/pages/BillingPage.vue';
import TranslationsPage from '../modules/translations/pages/TranslationsPage.vue';
import NotificationsPage from '../modules/notifications/pages/NotificationsPage.vue';
import ChatAdminMonitoringPage from '../modules/chat-admin/pages/ChatAdminMonitoringPage.vue';
import TenantsPage from '../modules/tenant-support/pages/TenantsPage.vue';
import ContactsSupportPage from '../modules/tenant-support/pages/ContactsSupportPage.vue';
import ExtensionsSupportPage from '../modules/tenant-support/pages/ExtensionsSupportPage.vue';
import RingGroupsSupportPage from '../modules/tenant-support/pages/RingGroupsSupportPage.vue';
import PhoneNumbersSupportPage from '../modules/tenant-support/pages/PhoneNumbersSupportPage.vue';
import CallLogsSupportPage from '../modules/tenant-support/pages/CallLogsSupportPage.vue';
import NotFoundView from '../shared/components/NotFoundView.vue';

/**
 * Router architecture notes:
 * - Layout routes provide stable UI shells for route groups.
 * - Feature views live under `modules/*` to keep domain boundaries explicit.
 * - Guards and permission checks will be introduced later without changing
 *   route ownership structure.
 */
const routes: RouteRecordRaw[] = [
  {
    path: '/',
    component: AdminLayout,
    meta: {
      requiresAuth: true,
    },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: DashboardPage,
        meta: {
          title: 'Dashboard',
          subtitle: 'Operational overview',
        },
      },
      {
        path: 'dashboard',
        name: 'dashboard-page',
        component: DashboardPage,
        meta: {
          title: 'Dashboard',
          subtitle: 'Operational overview',
        },
      },
      {
        path: 'users',
        name: 'users',
        component: UsersPage,
        meta: {
          title: 'Users',
          subtitle: 'User management module',
          permission: 'users.view',
        },
      },
      {
        path: 'roles',
        name: 'roles',
        component: RolesPage,
        meta: {
          title: 'Roles',
          subtitle: 'Role management module',
          permission: 'roles.view',
        },
      },
      {
        path: 'permissions',
        name: 'permissions',
        component: PermissionsPage,
        meta: {
          title: 'Permissions',
          subtitle: 'Permissions module',
          permission: 'permissions.view',
        },
      },
      {
        path: 'tokens',
        name: 'tokens',
        component: TokensPage,
        meta: {
          title: 'Tokens',
          subtitle: 'API token management module',
          permission: 'tokens.view',
        },
      },
      {
        path: 'activity',
        name: 'activity',
        component: ActivityPage,
        meta: {
          title: 'Activity',
          subtitle: 'Audit log and monitoring module',
          permission: 'activity.view',
        },
      },
      {
        path: 'settings',
        name: 'settings',
        component: SettingsPage,
        meta: {
          title: 'Settings',
          subtitle: 'Platform configuration module',
          permission: 'settings.view',
        },
      },
      {
        path: 'profile',
        name: 'profile',
        component: ProfilePage,
        meta: {
          title: 'Profile',
          subtitle: 'Account center',
        },
      },
      {
        path: 'billing',
        name: 'billing',
        component: BillingPage,
        meta: {
          title: 'Billing',
          subtitle: 'Subscription and usage',
          permission: 'billing.view',
        },
      },
      {
        path: 'translations',
        name: 'translations',
        component: TranslationsPage,
        meta: {
          title: 'Translations',
          subtitle: 'Runtime localization management',
          permission: 'translations.view',
        },
      },
      {
        path: 'notifications',
        name: 'notifications',
        component: NotificationsPage,
        meta: {
          title: 'Notifications',
          subtitle: 'System notification center',
          permission: 'notifications.view',
        },
      },
      {
        path: 'chat',
        name: 'chat-admin-monitoring',
        component: ChatAdminMonitoringPage,
        meta: {
          title: 'Chat Monitoring',
          subtitle: 'Admin conversation monitoring',
          permissions: ['chat.admin.view', 'chat.admin.view_metadata'],
        },
      },
      {
        path: 'tenants',
        name: 'tenants',
        component: TenantsPage,
        meta: {
          title: 'Tenants',
          subtitle: 'Tenant support selector',
          permission: 'tenants.view',
        },
      },
      {
        path: 'contacts',
        name: 'contacts',
        component: ContactsSupportPage,
        meta: {
          title: 'Contacts',
          subtitle: 'Tenant support contacts',
          permission: 'contacts.view',
        },
      },
      {
        path: 'extensions',
        name: 'extensions',
        component: ExtensionsSupportPage,
        meta: {
          title: 'Extensions',
          subtitle: 'Tenant support extensions',
          permission: 'extensions.view',
        },
      },
      {
        path: 'ring-groups',
        name: 'ring-groups',
        component: RingGroupsSupportPage,
        meta: {
          title: 'Ring Groups',
          subtitle: 'Tenant support ring groups',
          permission: 'ring_groups.view',
        },
      },
      {
        path: 'phone-numbers',
        name: 'phone-numbers',
        component: PhoneNumbersSupportPage,
        meta: {
          title: 'Phone Numbers',
          subtitle: 'Tenant support phone numbers',
          permission: 'phone_numbers.view',
        },
      },
      {
        path: 'call-logs',
        name: 'call-logs',
        component: CallLogsSupportPage,
        meta: {
          title: 'Call Logs',
          subtitle: 'Tenant support call logs',
          permission: 'call_logs.view',
        },
      },
      {
        path: 'demo-ui',
        name: 'demo-ui',
        component: DemoUI,
      },
      {
        path: 'vue-demo',
        name: 'vue-demo',
        component: VueDemoPage,
      },
    ],
  },
  {
    path: '/login',
    component: AuthLayout,
    meta: {
      guestOnly: true,
    },
    children: [
      {
        path: '',
        name: 'login',
        component: LoginView,
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: NotFoundView,
  },
];

const router = createRouter({
  // Vue admin SPA now owns /admin/* directly as the primary admin shell.
  // Legacy Blade admin routes are isolated server-side under /admin/legacy/*
  // to keep coexistence explicit without polluting SPA URLs.
  history: createWebHistory('/admin'),
  routes,
});

export default router;
