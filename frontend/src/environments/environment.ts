export const environment = {
  production: true,
  appName: 'Multi-Tenant Telephony Platform',
  apiBaseUrl: '/api',
  defaultLocale: 'en',
  enabledLocales: ['en', 'uk', 'de'],
  featureFlags: {
    notifications: true,
    realtimeWidgets: false,
    betaProfile: false,
  },
  realtime: {
    // Production-safe default:
    // keep realtime disabled until deployment-specific values are configured.
    enabled: false,
    provider: 'reverb',
    appKey: '',
    wsHost: '',
    wsPort: 6001,
    forceTLS: true,
    usePrivateChannel: true,
    broadcastingAuthUrl: '/broadcasting/auth',
  },
} as const;
