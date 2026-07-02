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

  it('maps websocket close errors to local transport guidance', () => {
    const wsProfile = {
      ...baseProfile,
      websocket_url: 'ws://localhost:5066',
    };

    const wssProfile = {
      ...baseProfile,
      websocket_url: 'wss://localhost:7443',
    };

    expect((service as any).toErrorMessage(
      new Error('WebSocket closed wss://localhost:7443 code: 1006'),
      'fallback',
      wssProfile,
    )).toBe('SIP WebSocket closed before registration. Check local FreeSWITCH WS/WSS port mapping and browser TLS trust.');

    expect((service as any).toErrorMessage(
      new Error('WebSocket closed ws://localhost:5066 code: 1006'),
      'fallback',
      wsProfile,
    )).toBe('SIP WebSocket closed before registration. Check local FreeSWITCH WS port mapping.');
  });

  it('maps forbidden SIP auth errors to domain and password guidance', () => {
    expect((service as any).toErrorMessage(
      new Error('SIP/2.0 403 Forbidden'),
      'fallback',
      baseProfile,
    )).toBe('SIP registration was rejected by FreeSWITCH. Check the local demo password, realm, and directory domain.');
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

  it('normalizes extension destinations into local SIP URIs', () => {
    expect((service as any).resolveSipTarget('1002', 'localhost')).toBe('sip:1002@localhost');
    expect((service as any).resolveSipTarget('sip:2002@localhost', 'localhost')).toBe('sip:2002@localhost');
  });

  it('blocks self calls with a friendly local demo message', async () => {
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

    (service as any).registrationStateSubject.next('registered');
    (service as any).userAgent = {};

    await service.call('2001');

    expect(service.callState).toBe('failed');
    expect((service as any).errorSubject.value).toBe('Choose a different registered extension for this local demo call.');
  });

  it('maps user-not-registered call rejections to browser-session guidance', () => {
    expect((service as any).toErrorMessage(
      new Error('USER_NOT_REGISTERED'),
      'fallback',
      baseProfile,
    )).toBe('The destination extension is not registered. Open another browser session and register it first.');
  });

  it('maps media negotiation failures to local WebRTC bridge guidance', () => {
    expect((service as any).toErrorMessage(
      new Error('488 Not Acceptable Here'),
      'fallback',
      baseProfile,
    )).toBe('The call was rejected during media negotiation. Check WebRTC codec, ICE/DTLS, and the local FreeSWITCH demo bridge.');
  });

  it('maps incompatible destination failures to bridge target guidance', () => {
    expect((service as any).toErrorMessage(
      new Error('INCOMPATIBLE_DESTINATION'),
      'fallback',
      baseProfile,
    )).toBe('FreeSWITCH could not bridge the local WebRTC call. Check the demo dialplan bridge target and media compatibility.');
  });

  it('maps autoplay blocked playback failures to browser interaction guidance', () => {
    expect((service as any).toMediaErrorMessage(new Error('NotAllowedError: play() failed because the user didn\'t interact with the document first.'))).toBe(
      'Browser autoplay blocked remote audio. Click the page once, then retry the call.',
    );
  });

  it('resets media diagnostics on tenant cleanup', async () => {
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
    (service as any).mediaDiagnosticsSubject.next({
      remote_audio_attached: true,
      remote_audio_track_count: 1,
      remote_audio_playing: true,
      peer_connection_state: 'connected',
      ice_connection_state: 'connected',
      last_media_error: 'example',
    });

    service.resetForTenantChange();

    expect(service.mediaDiagnostics).toEqual({
      remote_audio_attached: false,
      remote_audio_track_count: 0,
      remote_audio_playing: false,
      peer_connection_state: 'unknown',
      ice_connection_state: 'unknown',
      last_media_error: null,
    });
  });

  it('does not write SIP credentials to browser storage', async () => {
    const setItemSpy = vi.spyOn(Storage.prototype, 'setItem');
    const removeItemSpy = vi.spyOn(Storage.prototype, 'removeItem');
    const clearSpy = vi.spyOn(Storage.prototype, 'clear');

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

    expect(setItemSpy).not.toHaveBeenCalled();
    expect(removeItemSpy).not.toHaveBeenCalled();
    expect(clearSpy).not.toHaveBeenCalled();
  });
});
