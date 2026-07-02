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

  const setMediaDevicesMock = (value: any): void => {
    Object.defineProperty(navigator, 'mediaDevices', {
      value,
      configurable: true,
      writable: true,
    });
  };

  beforeEach(() => {
    setMediaDevicesMock({
      enumerateDevices: vi.fn().mockResolvedValue([]),
      getUserMedia: vi.fn().mockResolvedValue({
        getTracks: () => [],
      }),
    });
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
    )).toBe('SIP WebSocket connection failed before registration. Check local FreeSWITCH WS/WSS port mapping and browser TLS trust.');

    expect((service as any).toErrorMessage(
      new Error('WebSocket closed ws://localhost:5066 code: 1006'),
      'fallback',
      wsProfile,
    )).toBe('SIP WebSocket connection failed before registration. Check local FreeSWITCH WS port mapping.');
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
    (service as any).transportStateSubject.next('registered');
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

  it('declines an incoming call and clears the ringing state', async () => {
    const reject = vi.fn().mockResolvedValue(undefined);
    (service as any).incomingInvitation = {
      reject,
    };
    (service as any).incomingCallSubject.next(true);
    (service as any).callStateSubject.next('ringing');

    await service.rejectIncomingCall();

    expect(reject).toHaveBeenCalled();
    expect(service.callState).toBe('ended');
    expect((service as any).incomingCallSubject.value).toBe(false);
    expect((service as any).incomingInvitation).toBeNull();
  });

  it('hangs up an active call with bye and clears media cleanup state', async () => {
    const bye = vi.fn().mockResolvedValue(undefined);
    const clearIntervalSpy = vi.spyOn(globalThis, 'clearInterval');
    const senderTrack = { kind: 'audio', enabled: true, id: 'local-audio-track', muted: false, readyState: 'live' } as MediaStreamTrack;
    const sender = { track: senderTrack } as RTCRtpSender;

    (service as any).activeSession = {
      state: 'Established',
      bye,
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [sender],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');
    (service as any).mediaStatsIntervalId = setInterval(() => undefined, 1000);

    await service.hangup();

    expect(bye).toHaveBeenCalled();
    expect(clearIntervalSpy).toHaveBeenCalled();
    expect(service.callState).toBe('ended');
    expect((service as any).activeSession).toBeNull();
    clearIntervalSpy.mockRestore();
  });

  it('safely ignores hangup requests without an active session', async () => {
    (service as any).callStateSubject.next('idle');
    (service as any).activeSession = null;
    (service as any).incomingInvitation = null;

    await service.hangup();

    expect(service.callState).toBe('idle');
    expect((service as any).activeSession).toBeNull();
  });

  it('uses the outgoing cancel path when hangup is pressed during dialing', async () => {
    const cancel = vi.fn().mockResolvedValue(undefined);
    (service as any).activeSession = {
      state: 'Establishing',
      cancel,
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('dialing');

    await service.hangup();

    expect(cancel).toHaveBeenCalled();
    expect(service.callState).toBe('ended');
    expect((service as any).activeSession).toBeNull();
  });

  it('toggles local microphone tracks without stopping them', () => {
    const senderTrack = { kind: 'audio', enabled: true, id: 'local-audio-track', muted: false, readyState: 'live' } as MediaStreamTrack;
    const sender = { track: senderTrack } as RTCRtpSender;

    (service as any).activeSession = {
      state: 'Established',
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [sender],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');

    service.toggleMute();

    expect(senderTrack.enabled).toBe(false);
    expect(service.muted).toBe(true);

    service.toggleMute();

    expect(senderTrack.enabled).toBe(true);
    expect(service.muted).toBe(false);
  });

  it('keeps mute disabled when no local audio track exists', () => {
    (service as any).activeSession = {
      state: 'established',
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');

    expect(service.canToggleMute()).toBe(false);
  });

  it('marks a local hold placeholder and restores the active state on resume', async () => {
    (service as any).activeSession = {
      state: 'Established',
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');

    await service.holdCall();

    expect(service.callState).toBe('held');
    expect(service.locallyHeld).toBe(true);
    expect(service.canResume()).toBe(true);
    expect(service.canHold()).toBe(false);

    await service.resumeCall();

    expect(service.callState).toBe('active');
    expect(service.locallyHeld).toBe(false);
    expect(service.canHold()).toBe(true);
  });

  it('resets local hold state when hangup tears down the session', async () => {
    const bye = vi.fn().mockResolvedValue(undefined);
    (service as any).activeSession = {
      state: 'Established',
      bye,
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('held');
    (service as any).localHoldSubject.next(true);

    await service.hangup();

    expect(service.callState).toBe('ended');
    expect(service.locallyHeld).toBe(false);
  });

  it('retries registration after a transport disconnect and restores the registered state', async () => {
    vi.useFakeTimers();
    try {
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
      (service as any).transportStateSubject.next('registered');

      const reconnect = vi.fn().mockResolvedValue(undefined);
      const register = vi.fn().mockResolvedValue(undefined);
      (service as any).userAgent = {
        reconnect,
        isConnected: vi.fn().mockReturnValue(false),
      };
      (service as any).registerer = {
        register,
      };

      (service as any).handleTransportDisconnect(new Error('WebSocket closed wss://localhost:7443 code: 1006'));

      expect(service.transportState).toBe('reconnecting');
      expect(service.registrationState).toBe('disconnected');

      await vi.advanceTimersByTimeAsync(1000);

      expect(reconnect).toHaveBeenCalled();
      expect(register).toHaveBeenCalled();
      expect(service.transportState).toBe('registered');
      expect(service.registrationState).toBe('registered');
      expect((service as any).errorSubject.value).toBeNull();
    } finally {
      vi.useRealTimers();
    }
  });

  it('moves to failed after retry limit and allows a manual registration retry', async () => {
    vi.useFakeTimers();
    try {
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
      (service as any).transportStateSubject.next('registered');

      const reconnect = vi.fn().mockRejectedValue(new Error('WebSocket closed'));
      const register = vi.fn().mockResolvedValue(undefined);
      (service as any).userAgent = {
        reconnect,
        isConnected: vi.fn().mockReturnValue(false),
      };
      (service as any).registerer = {
        register,
      };
      (service as any).reconnectMaxAttempts = 1;

      (service as any).handleTransportDisconnect(new Error('WebSocket closed wss://localhost:7443 code: 1006'));
      await vi.advanceTimersByTimeAsync(5000);

      expect(service.transportState).toBe('failed');
      expect(service.registrationState).toBe('failed');
      expect(service.canRegister()).toBe(true);

      reconnect.mockResolvedValue(undefined);
      register.mockResolvedValue(undefined);
      await service.register();

      expect(register).toHaveBeenCalled();
      expect(service.transportState).toBe('registered');
      expect(service.registrationState).toBe('registered');
    } finally {
      vi.useRealTimers();
    }
  });

  it('does not reconnect after tenant cleanup clears the SIP transport', async () => {
    vi.useFakeTimers();
    try {
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
      (service as any).transportStateSubject.next('registered');

      const reconnect = vi.fn().mockResolvedValue(undefined);
      const register = vi.fn().mockResolvedValue(undefined);
      (service as any).userAgent = {
        reconnect,
        isConnected: vi.fn().mockReturnValue(false),
      };
      (service as any).registerer = {
        register,
      };

      (service as any).handleTransportDisconnect(new Error('WebSocket closed wss://localhost:7443 code: 1006'));
      service.resetForTenantChange();
      await vi.advanceTimersByTimeAsync(5000);

      expect(reconnect).not.toHaveBeenCalled();
      expect(register).not.toHaveBeenCalled();
      expect(service.transportState).toBe('disconnected');
      expect(service.registrationState).toBe('disconnected');
    } finally {
      vi.useRealTimers();
    }
  });

  it('does not retry transport recovery when credentials are missing', async () => {
    vi.useFakeTimers();
    try {
      callControlApiMock.getSipProfile.mockReturnValue(of({
        success: true,
        message: 'ok',
        data: {
          ...baseProfile,
          credentials_available: false,
          registration_enabled: false,
          local_demo_mode: false,
          registration: {
            enabled: false,
            state: 'disabled',
            reason: 'SIP credentials are not enabled for this environment.',
          },
        },
      }));

      await service.loadProfile(42);
      (service as any).transportStateSubject.next('registered');
      (service as any).registrationStateSubject.next('registered');
      (service as any).authorizationPassword = null;

      const reconnect = vi.fn().mockResolvedValue(undefined);
      const register = vi.fn().mockResolvedValue(undefined);
      (service as any).userAgent = {
        reconnect,
        isConnected: vi.fn().mockReturnValue(false),
      };
      (service as any).registerer = {
        register,
      };

      (service as any).handleTransportDisconnect(new Error('WebSocket closed wss://localhost:7443 code: 1006'));
      await vi.advanceTimersByTimeAsync(5000);

      expect(reconnect).not.toHaveBeenCalled();
      expect(register).not.toHaveBeenCalled();
      expect(service.transportState).toBe('disconnected');
      expect(service.registrationState).toBe('disconnected');
    } finally {
      vi.useRealTimers();
    }
  });

  it('uses insertDTMF when the sender supports DTMF insertion', async () => {
    const insertDTMF = vi.fn();
    const senderTrack = { kind: 'audio', enabled: true, id: 'local-audio-track', muted: false, readyState: 'live' } as MediaStreamTrack;
    (service as any).activeSession = {
      state: 'Established',
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [{
            track: senderTrack,
            dtmf: {
              canInsertDTMF: true,
              insertDTMF,
            },
          }],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');

    await service.sendDtmf('5');

    expect(insertDTMF).toHaveBeenCalledWith('5');
  });

  it('falls back to SIP INFO DTMF when WebRTC insertion is unavailable', async () => {
    const info = vi.fn().mockResolvedValue(undefined);
    (service as any).activeSession = {
      state: 'Established',
      info,
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [{
            track: {
              kind: 'audio',
              enabled: true,
              id: 'local-audio-track',
              muted: false,
              readyState: 'live',
            } as MediaStreamTrack,
            dtmf: {
              canInsertDTMF: false,
              insertDTMF: vi.fn(),
            },
          }],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');

    await service.sendDtmf('1');

    expect(info).toHaveBeenCalledWith(expect.objectContaining({
      requestOptions: expect.objectContaining({
        body: expect.objectContaining({
          contentType: 'application/dtmf-relay',
          content: 'Signal=1\r\nDuration=160',
        }),
      }),
    }));
  });

  it('does not crash when SIP INFO fallback is unavailable', async () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    (service as any).activeSession = {
      state: 'Established',
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [{
            track: {
              kind: 'audio',
              enabled: true,
              id: 'local-audio-track',
              muted: false,
              readyState: 'live',
            } as MediaStreamTrack,
            dtmf: {
              canInsertDTMF: false,
            },
          }],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');

    await expect(service.sendDtmf('#')).resolves.toBeUndefined();
    expect(service.callState).toBe('active');
    expect(warnSpy).toHaveBeenCalledWith('DTMF transport unavailable in this browser/session', expect.objectContaining({
      digit: '#',
    }));
    warnSpy.mockRestore();
  });

  it('sends a guarded REFER when the active session supports transfer', async () => {
    const onAccept = vi.fn();
    const onNotify = vi.fn();
    const refer = vi.fn().mockImplementation(async (_target, options) => {
      options?.requestDelegate?.onAccept?.({ statusCode: 202, reasonPhrase: 'Accepted' });
      options?.onNotify?.({ request: { body: 'SIP/2.0 200 OK' } });
      onAccept();
      onNotify();
      return {};
    });
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
    (service as any).activeSession = {
      state: 'Established',
      refer,
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');
    service.setTransferTarget('1002');

    await service.transfer();

    expect(refer).toHaveBeenCalled();
    expect(onAccept).toHaveBeenCalled();
    expect(onNotify).toHaveBeenCalled();
    expect((refer.mock.calls[0][0] as { toString: () => string }).toString()).toBe('sip:1002@localhost');
    expect(service.transferSuccess).toBe(true);
    expect(service.transferInProgress).toBe(false);
    expect(service.transferMessage).toContain('Transfer completed');
    expect(service.transferError).toBeNull();
  });

  it('shows a transfer fallback message when REFER is unavailable', async () => {
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
    (service as any).activeSession = {
      state: 'Established',
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');

    await service.transfer('1002');

    expect(service.transferError).toBe('Transfer is not supported by this session/browser yet.');
    expect(service.transferInProgress).toBe(false);
  });

  it('rejects invalid transfer targets without crashing', async () => {
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
    (service as any).activeSession = {
      state: 'Established',
      refer: vi.fn(),
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');

    await service.transfer('foo/bar');

    expect(service.transferError).toBe('Transfer target is not a valid SIP URI or extension.');
  });

  it('does not send a duplicate transfer while transferInProgress is already true', async () => {
    const refer = vi.fn();
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
    (service as any).activeSession = {
      state: 'Established',
      refer,
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');
    (service as any).transferInProgressSubject.next(true);

    await service.transfer('1002');

    expect(refer).not.toHaveBeenCalled();
  });

  it('keeps transfer logs free of SIP credential values', async () => {
    const debugSpy = vi.spyOn(console, 'debug').mockImplementation(() => undefined);
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
    (service as any).activeSession = {
      state: 'Established',
      refer: vi.fn().mockResolvedValue(undefined),
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');

    await service.transfer('1002');

    const loggedText = debugSpy.mock.calls.map((call) => JSON.stringify(call)).join('\n');
    expect(loggedText).not.toContain('change_me_local_demo_only');
    debugSpy.mockRestore();
  });

  it('resets transfer state when the call ends', async () => {
    (service as any).transferTargetSubject.next('1002');
    (service as any).transferInProgressSubject.next(true);
    (service as any).transferMessageSubject.next('Transfer request sent.');
    (service as any).transferErrorSubject.next('Transfer failed.');
    (service as any).transferSuccessSubject.next(true);

    (service as any).destroySession();

    expect(service.transferTarget).toBe('');
    expect(service.transferInProgress).toBe(false);
    expect(service.transferMessage).toBeNull();
    expect(service.transferError).toBeNull();
    expect(service.transferSuccess).toBe(false);
  });

  it('discovers audio input devices after prompting for microphone permission when labels are hidden', async () => {
    const stop = vi.fn();
    const enumerateDevices = vi.fn()
      .mockResolvedValueOnce([
        { kind: 'audioinput', deviceId: 'default', label: '' },
        { kind: 'audioinput', deviceId: 'usb-1', label: '' },
        { kind: 'videoinput', deviceId: 'cam-1', label: '' },
      ])
      .mockResolvedValueOnce([
        { kind: 'audioinput', deviceId: 'default', label: 'Default Microphone' },
        { kind: 'audioinput', deviceId: 'usb-1', label: 'USB Microphone' },
      ]);
    const getUserMedia = vi.fn().mockResolvedValue({ getTracks: () => [{ stop }] });
    setMediaDevicesMock({
      enumerateDevices,
      getUserMedia,
    });

    await service.refreshAudioInputDevices();

    expect(enumerateDevices).toHaveBeenCalledTimes(2);
    expect(getUserMedia).toHaveBeenCalledWith({ audio: true, video: false });
    expect(service.availableAudioInputDevices).toEqual([
      { device_id: 'default', label: 'Default Microphone', is_default: true },
      { device_id: 'usb-1', label: 'USB Microphone', is_default: false },
    ]);
    expect(service.audioInputDevicesLoading).toBe(false);
  });

  it('handles missing mediaDevices support without crashing', async () => {
    setMediaDevicesMock(undefined);

    await service.refreshAudioInputDevices();

    expect(service.availableAudioInputDevices).toEqual([]);
    expect(service.audioInputDevicesError).toBe('This browser does not support microphone device discovery.');
  });

  it('uses the selected microphone device in getUserMedia constraints', async () => {
    const stop = vi.fn();
    const getUserMedia = vi.fn().mockResolvedValue({ getTracks: () => [{ stop }] });
    setMediaDevicesMock({
      getUserMedia,
    });

    (service as any).selectedAudioInputDeviceIdSubject.next('usb-mic-123');

    await service.checkMicrophonePermission();

    expect(getUserMedia).toHaveBeenCalledWith({
      audio: {
        deviceId: {
          exact: 'usb-mic-123',
        },
      },
      video: false,
    });
  });

  it('falls back to the default microphone constraints when no device is selected', async () => {
    const stop = vi.fn();
    const getUserMedia = vi.fn().mockResolvedValue({ getTracks: () => [{ stop }] });
    setMediaDevicesMock({
      getUserMedia,
    });

    await service.checkMicrophonePermission();

    expect(getUserMedia).toHaveBeenCalledWith({ audio: true, video: false });
  });

  it('surfaces a permission denied error while keeping the device UI stable', async () => {
    const enumerateDevices = vi.fn().mockResolvedValue([
      { kind: 'audioinput', deviceId: 'default', label: '' },
    ]);
    const getUserMedia = vi.fn().mockRejectedValue(new Error('Permission denied'));
    setMediaDevicesMock({
      enumerateDevices,
      getUserMedia,
    });

    await service.refreshAudioInputDevices();

    expect(service.availableAudioInputDevices).toEqual([]);
    expect(service.audioInputDevicesError).toBe('Microphone permission was denied. Allow microphone access and refresh devices.');
  });

  it('rejects invalid DTMF digits without crashing', async () => {
    const insertDTMF = vi.fn();
    (service as any).activeSession = {
      state: 'Established',
      sessionDescriptionHandler: {
        peerConnection: {
          getSenders: () => [{
            track: {
              kind: 'audio',
              enabled: true,
              id: 'local-audio-track',
              muted: false,
              readyState: 'live',
            } as MediaStreamTrack,
            dtmf: {
              canInsertDTMF: true,
              insertDTMF,
            },
          }],
          getReceivers: () => [],
        },
      },
    };
    (service as any).callStateSubject.next('active');

    await service.sendDtmf('A');

    expect(insertDTMF).not.toHaveBeenCalled();
  });

  it('detects Opera as a partially supported browser and surfaces a warning', () => {
    const diagnostics = (service as any).buildBrowserCapabilityDiagnostics({
      browserName: 'Opera',
      userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Opera/99.0 OPR/99.0',
      hasMediaDevices: true,
      hasGetUserMedia: true,
      hasPeerConnection: true,
      audioAutoplaySupported: false,
    });

    expect(diagnostics.is_opera).toBe(true);
    expect(diagnostics.warning_message).toContain('Opera is not a primary supported browser for the local softphone.');
    expect(diagnostics.warning_message).toContain('Audio autoplay appears blocked in this browser.');
  });

  it('prepares remote audio elements for browser playback', () => {
    const audio = document.createElement('audio');
    const mediaAudio = audio as HTMLMediaElement & { playsInline?: boolean };

    (service as any).prepareRemoteAudioElement(audio);

    expect(audio.autoplay).toBe(true);
    expect(mediaAudio.playsInline).toBe(true);
    expect(audio.controls).toBe(true);
    expect(audio.muted).toBe(false);
    expect(audio.volume).toBe(1);
  });

  it('counts ICE candidates in the outgoing SDP', () => {
    expect((service as any).countIceCandidates([
      'v=0',
      'a=candidate:1 1 UDP 2130706431 192.0.2.1 54400 typ host',
      'a=candidate:2 1 UDP 2130706430 192.0.2.2 54401 typ host',
      'a=ice-options:trickle',
      'a=candidate:3 1 UDP 2130706429 192.0.2.3 54402 typ host',
    ].join('\r\n'))).toBe(3);
  });

  it('maps autoplay blocked playback failures to browser interaction guidance', () => {
    expect((service as any).toMediaErrorMessage(new Error('NotAllowedError: play() failed because the user didn\'t interact with the document first.'))).toBe(
      'Browser autoplay blocked remote audio. Click the page once, then retry the call.',
    );
  });

  it('maps unsupported browser media failures to WebRTC browser guidance', () => {
    expect((service as any).toMediaErrorMessage(new Error('RTCPeerConnection is not supported in this browser.'))).toBe(
      'This browser does not fully support the WebRTC audio APIs required for the softphone. Use Chrome or Edge for the local demo.',
    );
  });

  it('maps microphone permission errors to a clearer guidance message', () => {
    expect((service as any).toMediaErrorMessage(new Error('Permission denied to access microphone.'))).toBe(
      'Microphone permission was denied. Allow microphone access in the browser and try again.',
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
