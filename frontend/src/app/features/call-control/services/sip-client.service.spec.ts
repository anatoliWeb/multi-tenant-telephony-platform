import { of } from 'rxjs';
import { vi } from 'vitest';
import { CallControlApiService } from './call-control-api.service';
import { SipClientService } from './sip-client.service';

describe('SipClientService', () => {
  const baseProfile = {
    extension_id: 42,
    extension_number: '2001',
    display_name: 'Primary Desk',
    sip_uri: 'sip:2001@localhost',
    authorization_username: '2001',
    websocket_url: 'wss://localhost:7443',
    domain: 'localhost',
    provider: 'freeswitch',
    expires_seconds: 300,
    credentials_available: false,
    registration_enabled: false,
    local_demo_mode: false,
    registration: {
      enabled: false,
      state: 'disabled',
      reason: 'SIP credentials are not enabled for this environment.',
    },
    capabilities: {
      outbound_call: true,
      inbound_call: false,
      hold: false,
      mute: true,
    },
    tenant_id: 'tenant-a',
  };

  let callControlApiMock: {
    getSipProfile: ReturnType<typeof vi.fn>;
  };
  let service: SipClientService;

  beforeEach(() => {
    callControlApiMock = {
      getSipProfile: vi.fn(),
    };

    service = new SipClientService(callControlApiMock as unknown as CallControlApiService);
  });

  it('keeps passwords out of the public profile stream', async () => {
    callControlApiMock.getSipProfile.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: {
        ...baseProfile,
        credentials_available: true,
        registration_enabled: true,
        local_demo_mode: true,
        password: 'change_me_local_demo_only',
        registration: {
          enabled: true,
          state: 'available',
          reason: 'Local demo SIP credentials are enabled for this development environment.',
        },
      },
    }));

    await service.loadProfile(42);

    expect(service.profile?.password).toBeNull();
    expect((service as any).authorizationPassword).toBe('change_me_local_demo_only');
  });

  it('rejects registration when credentials are unavailable', async () => {
    callControlApiMock.getSipProfile.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: baseProfile,
    }));

    await service.loadProfile(42);
    await service.register();

    expect(service.registrationState).toBe('failed');
    expect(service.callState).toBe('registration_failed');
  });

  it('attempts local demo registration and clears the password when the browser transport fails', async () => {
    callControlApiMock.getSipProfile.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: {
        ...baseProfile,
        credentials_available: true,
        registration_enabled: true,
        local_demo_mode: true,
        password: 'change_me_local_demo_only',
        registration: {
          enabled: true,
          state: 'available',
          reason: 'Local demo SIP credentials are enabled for this development environment.',
        },
      },
    }));

    await service.loadProfile(42);
    await service.register();

    expect(service.registrationState).toBe('failed');
    expect(service.callState).toBe('registration_failed');
    expect(service.profile?.password).toBeNull();
    expect((service as any).authorizationPassword).toBeNull();
  });

  it('clears the in-memory password on tenant reset', async () => {
    callControlApiMock.getSipProfile.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: {
        ...baseProfile,
        credentials_available: true,
        registration_enabled: true,
        local_demo_mode: true,
        password: 'change_me_local_demo_only',
        registration: {
          enabled: true,
          state: 'available',
          reason: 'Local demo SIP credentials are enabled for this development environment.',
        },
      },
    }));

    await service.loadProfile(42);
    service.resetForTenantChange();

    expect(service.profile).toBeNull();
    expect((service as any).authorizationPassword).toBeNull();
    expect(service.registrationState).toBe('disconnected');
  });
});
