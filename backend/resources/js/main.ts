import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import { initializeApplication } from './app/index';
import router from './router';
import { commandPaletteStore } from './shared/command-palette';
import { i18n, getStoredLocale } from './shared/i18n';
import { useTranslationStore } from './stores/translation.store'
import { useBootstrapStore } from './stores/bootstrap.store';
import { useGlobalLoadingStore } from './stores/global-loading.store';
import { useAuthStore } from './stores/auth.store';
import { registerAdminPermissionGuard } from './router/permission-guard';
import '../scss/app.scss';

initializeApplication();
commandPaletteStore.init(router);
commandPaletteStore.registerNavigation({
  id: 'nav-dashboard',
  title: 'Dashboard',
  subtitle: 'Open operational overview',
  icon: '◉',
  keywords: ['home', 'overview', 'stats'],
  group: 'Navigation',
  to: '/dashboard',
});
commandPaletteStore.registerNavigation({
  id: 'nav-users',
  title: 'Users',
  subtitle: 'Open users management',
  icon: '◍',
  keywords: ['accounts', 'members'],
  group: 'Navigation',
  to: '/users',
});
commandPaletteStore.registerNavigation({
  id: 'nav-roles',
  title: 'Roles',
  subtitle: 'Open roles management',
  icon: '◌',
  keywords: ['rbac', 'access'],
  group: 'Navigation',
  to: '/roles',
});
commandPaletteStore.registerNavigation({
  id: 'nav-permissions',
  title: 'Permissions',
  subtitle: 'Open permissions management',
  icon: '◎',
  keywords: ['rbac', 'policy'],
  group: 'Navigation',
  to: '/permissions',
});
commandPaletteStore.registerNavigation({
  id: 'nav-tokens',
  title: 'Tokens',
  subtitle: 'Open API token management',
  icon: '◐',
  keywords: ['api', 'keys', 'scopes'],
  group: 'Navigation',
  to: '/tokens',
});
commandPaletteStore.registerNavigation({
  id: 'nav-activity',
  title: 'Activity',
  subtitle: 'Open audit activity stream',
  icon: '◑',
  keywords: ['logs', 'audit'],
  group: 'Navigation',
  to: '/activity',
});
commandPaletteStore.registerNavigation({
  id: 'nav-settings',
  title: 'Settings',
  subtitle: 'Open platform settings',
  icon: '◒',
  keywords: ['config', 'system'],
  group: 'Navigation',
  to: '/settings',
});
commandPaletteStore.registerNavigation({
  id: 'nav-billing',
  title: 'Billing',
  subtitle: 'Open subscription settings',
  icon: '◓',
  keywords: ['plan', 'invoice'],
  group: 'Navigation',
  to: '/billing',
});
commandPaletteStore.registerNavigation({
  id: 'nav-profile',
  title: 'Profile',
  subtitle: 'Open account profile',
  icon: '◔',
  keywords: ['account', 'me'],
  group: 'Navigation',
  to: '/profile',
});

const bootstrap = async (): Promise<void> => {

    const pinia = createPinia()

    const app = createApp(App)

    app.use(pinia)
    app.use(i18n)
    app.use(router)

    /*
    |--------------------------------------------------------------------------
    | Runtime translation preload
    |--------------------------------------------------------------------------
    */

    const translationStore = useTranslationStore(
        pinia
    )
    const bootstrapStore = useBootstrapStore(
        pinia
    )
    const globalLoadingStore = useGlobalLoadingStore(
        pinia
    )
    const authStore = useAuthStore(
        pinia
    )

    let routeLoadingToken: number | null = null

    registerAdminPermissionGuard(router, authStore)

    router.beforeEach(async (to) => {
        routeLoadingToken = globalLoadingStore.begin(
            'Loading page...',
            'route',
            450,
        )
    })

    router.afterEach(async () => {
        if (routeLoadingToken !== null) {
            await globalLoadingStore.end(routeLoadingToken)
            routeLoadingToken = null
        }
    })

    router.onError(async () => {
        if (routeLoadingToken !== null) {
            await globalLoadingStore.end(routeLoadingToken)
            routeLoadingToken = null
        }
    })

    /*
    |----------------------------------------------------------------------
    | Mount-first bootstrap strategy
    |----------------------------------------------------------------------
    |
    | We mount the app before async preload so bootstrap loader can render.
    | Async startup tasks then run under centralized bootstrap lifecycle.
    |
    */
    bootstrapStore.startBoot()
    const bootstrapLoadingToken = globalLoadingStore.begin(
        'Initializing application...',
        'bootstrap',
        550,
    )
    app.mount('#app')

    try {
        try {
            await translationStore.loadTranslations(
                getStoredLocale()
            )
        } catch (error) {
            if (import.meta.env.DEV) {
                console.warn('[bootstrap] translation preload failed; continuing with static locale bundle', {
                    error,
                })
            }
        }

        bootstrapStore.finishBoot()
        await globalLoadingStore.end(bootstrapLoadingToken)
    } catch (error) {
        bootstrapStore.failBoot(error)
        await globalLoadingStore.end(bootstrapLoadingToken)
    }
}

bootstrap().catch((error) => {
    console.error(
        'Application bootstrap failed',
        error
    )
})
