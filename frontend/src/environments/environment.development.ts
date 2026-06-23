export const environment = {
  production: false,
  appName: 'Multi-Tenant Telephony Platform (Dev)',
  apiBaseUrl: 'http://localhost:8080/api',
  defaultLocale: 'en',
  enabledLocales: ['en', 'uk', 'de'],
  featureFlags: {
    notifications: true,
    realtimeWidgets: true,
    betaProfile: true,
  },
  realtime: {
    // Dev defaults for local Docker/Desktop setup.
    enabled: true,
    provider: 'reverb',
    appKey: 'app-key',
    wsHost: 'localhost',
    wsPort: 6001,
    forceTLS: false,
    usePrivateChannel: true,
    broadcastingAuthUrl: 'http://localhost:8080/broadcasting/auth',
  },
} as const;
