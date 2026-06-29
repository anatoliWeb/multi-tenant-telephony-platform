import { firstValueFrom, throwError, of } from 'rxjs';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { SettingsPreloadService } from './settings-preload.service';

describe('SettingsPreloadService', () => {
  const apiClient = {
    get: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('loads the frontend preload payload from the stable /api/v1 route', async () => {
    apiClient.get.mockReturnValue(of({
      data: {
        settings: {
          'branding.app_name': 'Telephony Platform',
        },
      },
    }));

    const service = new SettingsPreloadService(apiClient as any);
    const payload = await firstValueFrom(service.preload());

    expect(apiClient.get).toHaveBeenCalledWith('/v1/settings/preload');
    expect(payload.settings['branding.app_name']).toBe('Telephony Platform');
  });

  it('falls back to an empty settings object when preload fails', async () => {
    apiClient.get.mockReturnValue(throwError(() => new Error('unauthorized')));

    const service = new SettingsPreloadService(apiClient as any);
    const payload = await firstValueFrom(service.preload());

    expect(payload).toEqual({ settings: {} });
  });
});
