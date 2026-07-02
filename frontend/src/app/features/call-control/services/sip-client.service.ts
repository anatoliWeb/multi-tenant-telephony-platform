import { Injectable } from '@angular/core';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import {
  Invitation,
  Inviter,
  Registerer,
  RegistererState,
  Session,
  SessionState,
  UserAgent,
  UserAgentDelegate,
  TransportState,
  Web,
} from 'sip.js';
import { CallControlApiService } from './call-control-api.service';
import type {
  SipAudioInputDevice,
  SipBrowserDiagnostics,
  MicrophonePermissionState,
  SipMediaDiagnostics,
  SipCallState,
  SipProfile,
  SipRegistrationState,
  SipTransportState,
} from '../models/call-control.model';

type SipSessionDescriptionHandler = {
  peerConnection?: RTCPeerConnection;
  peerConnectionDelegate?: {
    ontrack?: (event: RTCTrackEvent) => void;
    onconnectionstatechange?: (event: Event) => void;
    oniceconnectionstatechange?: (event: Event) => void;
    onicegatheringstatechange?: (event: Event) => void;
  };
  remoteMediaStream?: MediaStream;
  refer?: (
    referTo: ReturnType<typeof UserAgent.makeURI> | Session,
    options?: {
      onNotify?: (notification: {
        accept?: () => Promise<void>;
        request: { body: string };
      }) => void;
      requestDelegate?: {
        onAccept?: (response: { statusCode?: number; reasonPhrase?: string }) => void;
        onProgress?: (response: { statusCode?: number; reasonPhrase?: string }) => void;
        onReject?: (response: { statusCode?: number; reasonPhrase?: string }) => void;
      };
      requestOptions?: {
        extraHeaders?: string[];
      };
    },
  ) => Promise<unknown>;
  getDescription?: (
    options?: {
      constraints?: MediaStreamConstraints;
      iceGatheringTimeout?: number;
    },
    modifiers?: Array<(description: RTCSessionDescriptionInit) => Promise<RTCSessionDescriptionInit>>,
  ) => Promise<{ body: string; contentType: string }>;
  waitForIceGatheringComplete?: (restart?: boolean, timeout?: number) => Promise<void>;
};

@Injectable({ providedIn: 'root' })
export class SipClientService {
  private readonly profileSubject = new BehaviorSubject<SipProfile | null>(null);
  private readonly callStateSubject = new BehaviorSubject<SipCallState>('idle');
  private readonly registrationStateSubject = new BehaviorSubject<SipRegistrationState>('disconnected');
  private readonly transportStateSubject = new BehaviorSubject<SipTransportState>('disconnected');
  private readonly microphonePermissionSubject = new BehaviorSubject<MicrophonePermissionState>('unknown');
  private readonly mutedSubject = new BehaviorSubject<boolean>(false);
  private readonly incomingCallSubject = new BehaviorSubject<boolean>(false);
  private readonly localHoldSubject = new BehaviorSubject<boolean>(false);
  private readonly audioInputDevicesSubject = new BehaviorSubject<SipAudioInputDevice[]>([]);
  private readonly selectedAudioInputDeviceIdSubject = new BehaviorSubject<string | null>(null);
  private readonly audioInputDevicesLoadingSubject = new BehaviorSubject<boolean>(false);
  private readonly audioInputDevicesErrorSubject = new BehaviorSubject<string | null>(null);
  private readonly destinationSubject = new BehaviorSubject<string>('');
  private readonly transferTargetSubject = new BehaviorSubject<string>('');
  private readonly transferInProgressSubject = new BehaviorSubject<boolean>(false);
  private readonly transferErrorSubject = new BehaviorSubject<string | null>(null);
  private readonly transferMessageSubject = new BehaviorSubject<string | null>(null);
  private readonly transferSuccessSubject = new BehaviorSubject<boolean>(false);
  private readonly errorSubject = new BehaviorSubject<string | null>(null);
  private readonly lastMediaErrorSubject = new BehaviorSubject<string | null>(null);
  private readonly mediaDiagnosticsSubject = new BehaviorSubject<SipMediaDiagnostics>({
    remote_audio_attached: false,
    remote_audio_track_count: 0,
    remote_audio_playing: false,
    peer_connection_state: 'unknown',
    ice_connection_state: 'unknown',
    last_media_error: null,
  });
  private readonly browserDiagnosticsSubject = new BehaviorSubject<SipBrowserDiagnostics>({
    browser_name: 'unknown',
    is_opera: false,
    has_media_devices: false,
    has_get_user_media: false,
    has_peer_connection: typeof RTCPeerConnection !== 'undefined',
    audio_autoplay_supported: 'unknown',
    warning_message: null,
  });

  private userAgent: UserAgent | null = null;
  private registerer: Registerer | null = null;
  private activeSession: Session | null = null;
  private incomingInvitation: Invitation | null = null;
  private remoteAudioElement: HTMLAudioElement | null = null;
  private remoteMediaStream: MediaStream | null = null;
  private mediaStatsIntervalId: ReturnType<typeof setInterval> | null = null;
  private mediaStatsPollingInFlight = false;
  private reconnectTimerId: ReturnType<typeof setTimeout> | null = null;
  private reconnectAttemptCount = 0;
  private reconnectInFlight = false;
  private reconnectSuppressed = false;
  private readonly reconnectBaseDelayMs = 1000;
  private readonly reconnectMaxAttempts = 3;
  private authorizationPassword: string | null = null;
  private readonly iceGatheringTimeoutMs = 5000;
  private readonly registrationFailureMessage =
    'SIP registration failed. Check local FreeSWITCH WebSocket/TLS configuration.';

  readonly profile$ = this.profileSubject.asObservable();
  readonly callState$ = this.callStateSubject.asObservable();
  readonly registrationState$ = this.registrationStateSubject.asObservable();
  readonly transportState$ = this.transportStateSubject.asObservable();
  readonly microphonePermission$ = this.microphonePermissionSubject.asObservable();
  readonly muted$ = this.mutedSubject.asObservable();
  readonly incomingCall$ = this.incomingCallSubject.asObservable();
  readonly audioInputDevices$ = this.audioInputDevicesSubject.asObservable();
  readonly selectedAudioInputDeviceId$ = this.selectedAudioInputDeviceIdSubject.asObservable();
  readonly audioInputDevicesLoading$ = this.audioInputDevicesLoadingSubject.asObservable();
  readonly audioInputDevicesError$ = this.audioInputDevicesErrorSubject.asObservable();
  readonly destination$ = this.destinationSubject.asObservable();
  readonly transferTarget$ = this.transferTargetSubject.asObservable();
  readonly transferInProgress$ = this.transferInProgressSubject.asObservable();
  readonly transferError$ = this.transferErrorSubject.asObservable();
  readonly transferMessage$ = this.transferMessageSubject.asObservable();
  readonly transferSuccess$ = this.transferSuccessSubject.asObservable();
  readonly error$ = this.errorSubject.asObservable();
  readonly mediaDiagnostics$ = this.mediaDiagnosticsSubject.asObservable();
  readonly browserDiagnostics$ = this.browserDiagnosticsSubject.asObservable();

  constructor(private readonly callControlApi: CallControlApiService) {}

  get profile(): SipProfile | null {
    return this.profileSubject.value;
  }

  get callState(): SipCallState {
    return this.callStateSubject.value;
  }

  get registrationState(): SipRegistrationState {
    return this.registrationStateSubject.value;
  }

  get transportState(): SipTransportState {
    return this.transportStateSubject.value;
  }

  get microphonePermission(): MicrophonePermissionState {
    return this.microphonePermissionSubject.value;
  }

  get muted(): boolean {
    return this.mutedSubject.value;
  }

  get locallyHeld(): boolean {
    return this.localHoldSubject.value;
  }

  get selectedAudioInputDeviceId(): string | null {
    return this.selectedAudioInputDeviceIdSubject.value;
  }

  get availableAudioInputDevices(): SipAudioInputDevice[] {
    return this.audioInputDevicesSubject.value;
  }

  get audioInputDevicesLoading(): boolean {
    return this.audioInputDevicesLoadingSubject.value;
  }

  get audioInputDevicesError(): string | null {
    return this.audioInputDevicesErrorSubject.value;
  }

  get destination(): string {
    return this.destinationSubject.value;
  }

  get transferTarget(): string {
    return this.transferTargetSubject.value;
  }

  get transferInProgress(): boolean {
    return this.transferInProgressSubject.value;
  }

  get transferError(): string | null {
    return this.transferErrorSubject.value;
  }

  get transferMessage(): string | null {
    return this.transferMessageSubject.value;
  }

  get transferSuccess(): boolean {
    return this.transferSuccessSubject.value;
  }

  get mediaDiagnostics(): SipMediaDiagnostics {
    return this.mediaDiagnosticsSubject.value;
  }

  get browserDiagnostics(): SipBrowserDiagnostics {
    return this.browserDiagnosticsSubject.value;
  }

  canRegister(): boolean {
    const profile = this.profileSubject.value;

    return Boolean(
      profile?.registration_enabled
        && profile.credentials_available
        && this.authorizationPassword
        && this.registrationStateSubject.value !== 'connecting'
        && this.transportStateSubject.value !== 'connecting'
        && this.transportStateSubject.value !== 'reconnecting'
        && this.transportStateSubject.value !== 'unregistering'
        && this.registrationStateSubject.value !== 'registered',
    );
  }

  canPlaceCall(): boolean {
    const profile = this.profileSubject.value;
    const destination = this.destinationSubject.value.trim();

    return Boolean(
      profile?.capabilities?.outbound_call
        && this.registrationStateSubject.value === 'registered'
        && this.transportStateSubject.value === 'registered'
        && destination
        && !this.isCallInProgress(),
    );
  }

  canHold(): boolean {
    return this.callStateSubject.value === 'active' && !this.localHoldSubject.value && Boolean(this.activeSession);
  }

  canResume(): boolean {
    return this.callStateSubject.value === 'held' && this.localHoldSubject.value && Boolean(this.activeSession);
  }

  canAnswerIncomingCall(): boolean {
    return this.incomingCallSubject.value && this.callStateSubject.value === 'ringing';
  }

  canRejectIncomingCall(): boolean {
    return this.canAnswerIncomingCall();
  }

  canHangup(): boolean {
    return ['dialing', 'ringing', 'active', 'held'].includes(this.callStateSubject.value);
  }

  canToggleMute(): boolean {
    const profile = this.profileSubject.value;

    return Boolean(
      profile?.capabilities?.mute
        && this.callStateSubject.value === 'active'
        && this.hasLocalAudioSenderTrack(),
    );
  }

  canSendDtmf(): boolean {
    return this.callStateSubject.value === 'active' && Boolean(this.activeSession);
  }

  canChangeAudioInputDevice(): boolean {
    return !this.isCallInProgress();
  }

  canTransfer(): boolean {
    return Boolean(
      this.activeSession
        && this.callStateSubject.value === 'active'
    );
  }

  /**
   * SIP credentials stay in memory only. The browser should never persist
   * them in storage or global app state because the softphone is tenant-scoped
   * and must disappear cleanly on tenant change or logout.
   */
  async loadProfile(extensionId: number): Promise<void> {
    this.destroyTransport({ suppressReconnect: true });
    this.errorSubject.next(null);
    this.callStateSubject.next('checking_permissions');
    this.registrationStateSubject.next('disconnected');
    this.transportStateSubject.next('disconnected');
    this.microphonePermissionSubject.next('unknown');
    this.mutedSubject.next(false);
    this.localHoldSubject.next(false);
    this.selectedAudioInputDeviceIdSubject.next(null);
    this.audioInputDevicesSubject.next([]);
    this.audioInputDevicesLoadingSubject.next(false);
    this.audioInputDevicesErrorSubject.next(null);
    this.incomingCallSubject.next(false);
    this.destinationSubject.next('');
    this.resetTransferState();
    this.resetMediaDiagnostics();
    void this.refreshBrowserCapabilities();

    try {
      const response = await firstValueFrom(this.callControlApi.getSipProfile(extensionId));
      const profile = response.data ?? null;

      if (!profile) {
        throw new Error('SIP profile unavailable.');
      }

      // SIP secrets stay in transient service memory only. The public profile
      // stream is sanitized so Angular templates and devtools snapshots do not
      // retain passwords after the profile is loaded.
      this.authorizationPassword = profile.credentials_available && profile.registration_enabled && profile.local_demo_mode
        ? profile.password ?? null
        : null;
      this.profileSubject.next(this.sanitizeProfile(profile));
      this.reconnectSuppressed = false;
      this.callStateSubject.next('ready');
      void this.refreshAudioInputDevices();
    } catch (error) {
      this.authorizationPassword = null;
      this.profileSubject.next(null);
      this.callStateSubject.next('failed');
      this.errorSubject.next(this.toErrorMessage(error, 'SIP profile unavailable.'));
    }
  }

  async checkMicrophonePermission(): Promise<void> {
    this.microphonePermissionSubject.next('checking');

    if (!navigator.mediaDevices?.getUserMedia) {
      this.microphonePermissionSubject.next('unsupported');
      this.errorSubject.next('This browser does not support the WebRTC audio APIs required for the softphone. Use Chrome or Edge for the local demo.');
      return;
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia(this.buildMicrophoneConstraints());
      stream.getTracks().forEach((track) => track.stop());

      if (navigator.permissions?.query) {
        try {
          const result = await navigator.permissions.query({ name: 'microphone' as PermissionName });
          if (result.state === 'granted' || result.state === 'denied' || result.state === 'prompt') {
            this.microphonePermissionSubject.next(result.state);
            return;
          }
        } catch {
          // Browsers disagree on microphone permission introspection, so we
          // fall back to an actual media probe below.
        }
      }
      this.microphonePermissionSubject.next('granted');
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);

      this.microphonePermissionSubject.next('denied');

      if (/notfound|overconstrained/i.test(message)) {
        this.errorSubject.next('The selected microphone is not available. Choose another microphone and try again.');
        console.warn('[SIP/WebRTC] selected microphone not found during permission check', {
          selectedDeviceId: this.selectedAudioInputDeviceIdSubject.value ? this.maskDeviceId(this.selectedAudioInputDeviceIdSubject.value) : 'default',
        });
        return;
      }

      this.errorSubject.next('Microphone permission was denied. Allow microphone access in the browser and try again.');
    }
  }

  async refreshAudioInputDevices(): Promise<void> {
    if (!navigator.mediaDevices?.enumerateDevices) {
      this.audioInputDevicesSubject.next([]);
      this.audioInputDevicesErrorSubject.next('This browser does not support microphone device discovery.');
      console.warn('[SIP/WebRTC] audio input device discovery unavailable');
      return;
    }

    this.audioInputDevicesLoadingSubject.next(true);
    this.audioInputDevicesErrorSubject.next(null);

    try {
      const devices = await this.discoverAudioInputDevices();
      this.audioInputDevicesSubject.next(devices);

      const selectedAudioInputDeviceId = this.selectedAudioInputDeviceIdSubject.value;
      if (selectedAudioInputDeviceId && !devices.some((device) => device.device_id === selectedAudioInputDeviceId)) {
        console.warn('[SIP/WebRTC] selected microphone is no longer available; reverting to default input', {
          selectedDeviceId: this.maskDeviceId(selectedAudioInputDeviceId),
        });
        this.selectedAudioInputDeviceIdSubject.next(null);
        this.errorSubject.next('Selected microphone is no longer available. Using the default microphone.');
      }

      console.debug('[SIP/WebRTC] audio input devices refreshed', {
        availableAudioInputCount: devices.length,
      });
    } catch (error) {
      const message = error instanceof Error && error.message.trim()
        ? error.message.trim()
        : 'Failed to load audio input devices.';
      this.audioInputDevicesSubject.next([]);
      this.audioInputDevicesErrorSubject.next(message);
      this.errorSubject.next(message);
      console.warn('[SIP/WebRTC] audio input device discovery failed', {
        error: message,
      });
    } finally {
      this.audioInputDevicesLoadingSubject.next(false);
    }
  }

  setSelectedAudioInputDevice(deviceId: string | null): void {
    const normalizedDeviceId = deviceId?.trim() || null;
    this.selectedAudioInputDeviceIdSubject.next(normalizedDeviceId);

    console.debug('[SIP/WebRTC] selected microphone updated', {
      selectedDeviceId: normalizedDeviceId ? this.maskDeviceId(normalizedDeviceId) : 'default',
      selectionMode: normalizedDeviceId ? 'selected' : 'default',
    });
  }

  async register(): Promise<void> {
    const profile = this.profileSubject.value;

    if (!profile) {
      this.registrationStateSubject.next('failed');
      this.errorSubject.next('SIP profile unavailable.');
      return;
    }

    if (!profile.registration_enabled || !profile.credentials_available || !this.authorizationPassword) {
      this.registrationStateSubject.next('failed');
      this.callStateSubject.next('registration_failed');
      this.errorSubject.next(profile.registration.reason ?? 'SIP credentials are not enabled for this environment.');
      this.clearCredentials();
      return;
    }

    if (!profile.authorization_username) {
      this.registrationStateSubject.next('failed');
      this.callStateSubject.next('registration_failed');
      this.errorSubject.next('SIP credentials are incomplete.');
      return;
    }

    this.clearReconnectLoop();
    this.reconnectSuppressed = false;
    this.registrationStateSubject.next('connecting');
    this.transportStateSubject.next('connecting');
    this.callStateSubject.next('registering');
    this.errorSubject.next(null);

    try {
      await this.ensureTransport(profile);
      await this.registerer?.register({
        requestDelegate: {
          onReject: () => {
            this.registrationStateSubject.next('failed');
            this.transportStateSubject.next('failed');
            this.callStateSubject.next('registration_failed');
            this.errorSubject.next(this.registrationFailureMessage);
            this.clearCredentials();
            this.clearReconnectLoop();
          },
        },
      });
      this.registrationStateSubject.next('registered');
      this.transportStateSubject.next('registered');
      this.callStateSubject.next('registered');
      this.clearReconnectLoop();
    } catch (error) {
      this.destroyTransport({ suppressReconnect: true });
      this.registrationStateSubject.next('failed');
      this.transportStateSubject.next('failed');
      this.callStateSubject.next('registration_failed');
      this.errorSubject.next(this.toErrorMessage(error, this.registrationFailureMessage));
      this.clearCredentials();
    }
  }

  async call(destination: string): Promise<void> {
    const profile = this.profileSubject.value;
    const normalizedDestination = destination.trim();

    if (!profile) {
      this.callStateSubject.next('failed');
      this.errorSubject.next('SIP profile unavailable.');
      return;
    }

    if (this.registrationStateSubject.value !== 'registered' || this.transportStateSubject.value !== 'registered') {
      this.callStateSubject.next('failed');
      this.errorSubject.next('Register before placing a call.');
      return;
    }

    if (!normalizedDestination) {
      this.callStateSubject.next('failed');
      this.errorSubject.next('Destination is required.');
      return;
    }

    if (!this.userAgent) {
      this.callStateSubject.next('failed');
      this.errorSubject.next('Register before placing a call.');
      return;
    }

    const target = UserAgent.makeURI(this.resolveSipTarget(normalizedDestination, profile.domain));

    if (!target) {
      this.callStateSubject.next('failed');
      this.errorSubject.next('Destination is not a valid SIP target.');
      return;
    }

    if (this.isSelfCallTarget(target, profile.extension_number)) {
      this.callStateSubject.next('failed');
      this.errorSubject.next('Choose a different registered extension for this local demo call.');
      return;
    }

    this.errorSubject.next(null);
    this.callStateSubject.next('dialing');
    this.localHoldSubject.next(false);
    console.debug('[SIP/WebRTC] caller preparing outgoing INVITE', {
      destination: normalizedDestination,
      target: target.toString(),
      iceGatheringTimeoutMs: this.iceGatheringTimeoutMs,
    });

    // The outgoing Inviter owns the call attempt until it either reaches an
    // established dialog, gets rejected, or is torn down by hangup/reset.
    const inviter = new Inviter(this.userAgent, target, {
      sessionDescriptionHandlerOptions: {
        constraints: this.buildMediaConstraints(),
        iceGatheringTimeout: this.iceGatheringTimeoutMs,
      } as any,
    });

    this.activeSession = inviter;
    this.attachSessionHandlers(inviter);
    this.attachSessionMediaHandlers(inviter);

    try {
      await inviter.invite({
        // Force the initial offer path to use the full ICE timeout and the
        // browser-audio-only constraints even if invite defaults are partial.
        sessionDescriptionHandlerOptions: {
          constraints: this.buildMediaConstraints(),
          iceGatheringTimeout: this.iceGatheringTimeoutMs,
        } as any,
        requestDelegate: {
          onTrying: () => {
            this.callStateSubject.next('dialing');
          },
          onProgress: () => {
            this.callStateSubject.next('ringing');
          },
          onReject: (response: unknown) => {
            this.callStateSubject.next('failed');
            this.errorSubject.next(this.toErrorMessage(this.toSipError(response), 'Call failed.'));
            this.destroySession();
          },
        },
      });
    } catch (error) {
      this.callStateSubject.next('failed');
      this.errorSubject.next(this.toErrorMessage(error, 'Call failed.'));
      this.destroySession();
    }
  }

  async hangup(): Promise<void> {
    if (!this.activeSession) {
      if (this.incomingInvitation) {
        console.debug('[SIP/WebRTC] hangup routed to incoming decline because no active session exists');
        await this.rejectIncomingCall();
        return;
      }

      console.debug('[SIP/WebRTC] hangup skipped because no active session exists');
      return;
    }

    try {
      const session = this.activeSession as Session & {
        bye?: () => Promise<void>;
        cancel?: () => Promise<void> | void;
        dispose?: () => Promise<void> | void;
        terminate?: () => Promise<void> | void;
      };

      console.debug('[SIP/WebRTC] hangup requested', {
        callState: this.callStateSubject.value,
        sessionState: this.activeSession.state,
      });

      if (session.state === SessionState.Established && typeof session.bye === 'function') {
        await session.bye();
      } else if (typeof session.cancel === 'function') {
        await session.cancel();
      } else if (typeof session.terminate === 'function') {
        await session.terminate();
      } else if (typeof session.dispose === 'function') {
        await session.dispose();
      }
    } finally {
      console.debug('[SIP/WebRTC] hangup cleanup completed', {
        callState: this.callStateSubject.value,
        hasActiveSession: Boolean(this.activeSession),
      });
      this.callStateSubject.next('ended');
      this.destroySession();
    }
  }

  async holdCall(): Promise<void> {
    if (!this.activeSession || this.callStateSubject.value !== 'active' || this.localHoldSubject.value) {
      console.debug('[SIP/WebRTC] local hold skipped', {
        hasActiveSession: Boolean(this.activeSession),
        callState: this.callStateSubject.value,
        locallyHeld: this.localHoldSubject.value,
      });
      return;
    }

    console.debug('[SIP/WebRTC] local hold placeholder activated', {
      callState: this.callStateSubject.value,
      sessionState: this.activeSession.state,
    });

    this.localHoldSubject.next(true);
    this.callStateSubject.next('held');
  }

  async resumeCall(): Promise<void> {
    if (!this.activeSession || !this.localHoldSubject.value || this.callStateSubject.value !== 'held') {
      console.debug('[SIP/WebRTC] local resume skipped', {
        hasActiveSession: Boolean(this.activeSession),
        callState: this.callStateSubject.value,
        locallyHeld: this.localHoldSubject.value,
      });
      return;
    }

    console.debug('[SIP/WebRTC] local resume placeholder activated', {
      callState: this.callStateSubject.value,
      sessionState: this.activeSession.state,
    });

    this.localHoldSubject.next(false);
    this.callStateSubject.next('active');
  }

  async sendDtmf(digit: string): Promise<void> {
    const normalizedDigit = digit.trim();

    if (!/^[0-9*#]$/.test(normalizedDigit)) {
      console.warn('[SIP/WebRTC] DTMF rejected because the digit is invalid', {
        digit: normalizedDigit,
      });
      return;
    }

    if (!this.activeSession || this.callStateSubject.value !== 'active') {
      console.debug('[SIP/WebRTC] DTMF skipped because there is no active call', {
        digit: normalizedDigit,
        hasActiveSession: Boolean(this.activeSession),
        callState: this.callStateSubject.value,
      });
      return;
    }

    const peerConnection = (this.activeSession.sessionDescriptionHandler as Session['sessionDescriptionHandler'] & {
      peerConnection?: RTCPeerConnection;
    }).peerConnection;
    const senders = peerConnection?.getSenders?.() ?? [];
    const senderDiagnostics = senders.map((sender) => ({
      trackKind: sender.track?.kind ?? null,
      trackReadyState: sender.track?.readyState ?? null,
      hasDtmf: Boolean((sender as RTCRtpSender & { dtmf?: RTCDTMFSender | null }).dtmf),
      canInsertDTMF: (sender as RTCRtpSender & { dtmf?: RTCDTMFSender | null }).dtmf?.canInsertDTMF ?? null,
    }));

    console.debug('[SIP/WebRTC] DTMF sender diagnostics', {
      digit: normalizedDigit,
      callState: this.callStateSubject.value,
      senderDiagnostics,
    });

    const dtmfSender = senders.find((sender) => {
      const senderWithDtmf = sender as RTCRtpSender & { dtmf?: RTCDTMFSender | null };
      return sender.track?.kind === 'audio' && Boolean(senderWithDtmf.dtmf?.canInsertDTMF);
    }) as RTCRtpSender & { dtmf?: RTCDTMFSender | null } | undefined;

    if (dtmfSender?.dtmf?.canInsertDTMF) {
      try {
        dtmfSender.dtmf.insertDTMF(normalizedDigit);
        console.debug('[SIP/WebRTC] DTMF digit sent through RTCDTMFSender', {
          digit: normalizedDigit,
        });
        return;
      } catch (error) {
        console.warn('[SIP/WebRTC] RTCDTMFSender insertDTMF failed, falling back to SIP INFO', {
          digit: normalizedDigit,
          error: error instanceof Error ? error.message : String(error),
        });
      }
    } else {
      console.debug('[SIP/WebRTC] RTCDTMFSender unavailable or cannot insert DTMF, checking SIP INFO fallback', {
        digit: normalizedDigit,
      });
    }

    const session = this.activeSession as Session & {
      info?: (options?: {
        requestOptions?: {
          body?: {
            contentDisposition: string;
            contentType: string;
            content: string;
          };
        };
      }) => Promise<unknown>;
    };

    if (typeof session.info === 'function') {
      try {
        console.debug('[SIP/WebRTC] sending DTMF digit via SIP INFO fallback', {
          digit: normalizedDigit,
        });

        await session.info({
          requestOptions: {
            body: {
              contentDisposition: 'render',
              contentType: 'application/dtmf-relay',
              content: `Signal=${normalizedDigit}\r\nDuration=160`,
            },
          },
        });

        console.debug('[SIP/WebRTC] DTMF digit sent via SIP INFO fallback', {
          digit: normalizedDigit,
        });
        return;
      } catch (error) {
        console.warn('[SIP/WebRTC] SIP INFO DTMF fallback failed', {
          digit: normalizedDigit,
          error: error instanceof Error ? error.message : String(error),
        });
      }
    }

    console.warn('DTMF transport unavailable in this browser/session', {
      digit: normalizedDigit,
    });
  }

  async transfer(targetInput?: string): Promise<void> {
    const profile = this.profileSubject.value;
    const normalizedTargetInput = (targetInput ?? this.transferTargetSubject.value).trim();
    this.transferTargetSubject.next(normalizedTargetInput);
    this.transferErrorSubject.next(null);
    this.transferMessageSubject.next(null);
    this.transferSuccessSubject.next(false);

    if (!this.activeSession || this.callStateSubject.value !== 'active') {
      console.debug('[SIP/WebRTC] transfer skipped because there is no active established call', {
        callState: this.callStateSubject.value,
        hasActiveSession: Boolean(this.activeSession),
      });
      this.transferErrorSubject.next('Transfer is available only during an active established call.');
      return;
    }

    if (this.transferInProgressSubject.value) {
      console.debug('[SIP/WebRTC] transfer skipped because another transfer is already in progress');
      return;
    }

    if (!normalizedTargetInput) {
      this.transferErrorSubject.next('Transfer target is required.');
      return;
    }

    const referTo = this.buildTransferTarget(normalizedTargetInput, profile?.domain ?? '');
    if (!referTo) {
      this.transferErrorSubject.next('Transfer target is not a valid SIP URI or extension.');
      return;
    }

    if (this.activeSession.state !== SessionState.Established) {
      this.transferErrorSubject.next('Transfer is available only after the call is established.');
      return;
    }

    const session = this.activeSession as Session & SipSessionDescriptionHandler;
    if (typeof session.refer !== 'function') {
      this.transferErrorSubject.next('Transfer is not supported by this session/browser yet.');
      console.warn('[SIP/WebRTC] transfer unsupported because session.refer is unavailable', {
        target: referTo.toString(),
      });
      return;
    }

    this.transferInProgressSubject.next(true);
    this.transferMessageSubject.next('Transfer request sent. Waiting for remote SIP progress.');
    console.debug('[SIP/WebRTC] transfer request started', {
      target: referTo.toString(),
      inputMode: normalizedTargetInput.startsWith('sip:') || normalizedTargetInput.includes('@') ? 'uri' : 'extension',
      callState: this.callStateSubject.value,
    });

    try {
      await session.refer(referTo, {
        requestDelegate: {
          onAccept: (response) => {
            const responseMessage = response.message as { statusCode?: number; reasonPhrase?: string } | undefined;
            console.debug('[SIP/WebRTC] transfer REFER accepted', {
              statusCode: responseMessage?.statusCode ?? null,
              reasonPhrase: responseMessage?.reasonPhrase ?? null,
              target: referTo.toString(),
            });
            this.transferMessageSubject.next('Transfer request accepted. Waiting for transfer progress.');
          },
          onProgress: (response) => {
            const responseMessage = response.message as { statusCode?: number; reasonPhrase?: string } | undefined;
            console.debug('[SIP/WebRTC] transfer REFER progress', {
              statusCode: responseMessage?.statusCode ?? null,
              reasonPhrase: responseMessage?.reasonPhrase ?? null,
              target: referTo.toString(),
            });
            this.transferMessageSubject.next('Transfer request is in progress.');
          },
          onReject: (response) => {
            const responseMessage = response.message as { statusCode?: number; reasonPhrase?: string } | undefined;
            const message = this.formatReferResponseMessage(responseMessage?.statusCode, responseMessage?.reasonPhrase, 'Transfer was rejected.');
            console.warn('[SIP/WebRTC] transfer REFER rejected', {
              statusCode: responseMessage?.statusCode ?? null,
              reasonPhrase: responseMessage?.reasonPhrase ?? null,
              target: referTo.toString(),
            });
            this.transferErrorSubject.next(message);
            this.transferMessageSubject.next(null);
            this.transferSuccessSubject.next(false);
            this.transferInProgressSubject.next(false);
          },
        },
        onNotify: (notification) => {
          const notifyResult = this.parseReferNotify(notification.request.body ?? '');
          console.debug('[SIP/WebRTC] transfer NOTIFY received', {
            target: referTo.toString(),
            notifyResult,
          });
          void notification.accept?.().catch(() => undefined);

          if (notifyResult.isFinal && notifyResult.statusCode !== null) {
            if (notifyResult.statusCode >= 200 && notifyResult.statusCode < 300) {
              this.transferSuccessSubject.next(true);
              this.transferMessageSubject.next(notifyResult.reasonPhrase ? `Transfer completed: ${notifyResult.reasonPhrase}` : 'Transfer completed.');
              this.transferErrorSubject.next(null);
            } else {
              this.transferSuccessSubject.next(false);
              this.transferErrorSubject.next(this.formatReferResponseMessage(
                notifyResult.statusCode,
                notifyResult.reasonPhrase,
                'Transfer failed.',
              ));
              this.transferMessageSubject.next(null);
            }
            this.transferInProgressSubject.next(false);
          }
        },
      });

      console.debug('[SIP/WebRTC] transfer REFER request dispatched', {
        target: referTo.toString(),
      });
    } catch (error) {
      const message = this.toErrorMessage(error, 'Transfer is not supported by this session/browser yet.');
      this.transferErrorSubject.next(message);
      this.transferMessageSubject.next(null);
      this.transferSuccessSubject.next(false);
      this.transferInProgressSubject.next(false);
      console.warn('[SIP/WebRTC] transfer request failed', {
        target: referTo.toString(),
        error: message,
      });
    }
  }

  toggleMute(): void {
    if (!this.activeSession || this.callStateSubject.value !== 'active') {
      console.debug('[SIP/WebRTC] mute toggle skipped', {
        hasActiveSession: Boolean(this.activeSession),
        callState: this.callStateSubject.value,
      });
      return;
    }

    const senderSet = (this.activeSession as Session & {
      sessionDescriptionHandler?: { peerConnection?: RTCPeerConnection };
    }).sessionDescriptionHandler?.peerConnection?.getSenders?.() ?? [];
    const audioSenders = senderSet.filter((sender) => sender.track?.kind === 'audio');

    console.debug('[SIP/WebRTC] local audio mute request', {
      callState: this.callStateSubject.value,
      audioSenderCount: audioSenders.length,
      currentMuted: this.mutedSubject.value,
      nextMuted: !this.mutedSubject.value,
    });

    if (audioSenders.length === 0) {
      console.debug('[SIP/WebRTC] mute toggle skipped because no local audio sender tracks are available');
      return;
    }

    const muted = !this.mutedSubject.value;
    audioSenders.forEach((sender) => {
      if (sender.track?.kind === 'audio') {
        sender.track.enabled = !muted;
      }
    });

    this.mutedSubject.next(muted);

    console.debug('[SIP/WebRTC] local audio mute state updated', {
      muted,
      enabledTrackCount: audioSenders.filter((sender) => sender.track?.enabled).length,
      disabledTrackCount: audioSenders.filter((sender) => sender.track ? !sender.track.enabled : false).length,
    });
  }

  bindRemoteAudio(element: HTMLAudioElement | null): void {
    console.debug('[SIP/WebRTC] remote audio element', {
      action: element ? 'found' : 'cleared',
      hasSession: Boolean(this.activeSession),
      hasRemoteStream: Boolean(this.remoteMediaStream),
    });

    if (this.remoteAudioElement === element) {
      if (this.remoteAudioElement) {
        this.prepareRemoteAudioElement(this.remoteAudioElement);
        void this.bindSessionAudio(this.activeSession);
      }

      return;
    }

    this.remoteAudioElement = element;

    if (this.remoteAudioElement) {
      this.prepareRemoteAudioElement(this.remoteAudioElement);
      void this.bindSessionAudio(this.activeSession);
      return;
    }

    this.remoteMediaStream = null;
    this.mediaDiagnosticsSubject.next({
      ...this.mediaDiagnosticsSubject.value,
      remote_audio_attached: false,
      remote_audio_playing: false,
    });
  }

  /**
   * Browser storage must stay empty. Demo SIP credentials are local-only and
   * live in memory just long enough for the current tenant session.
   */
  hasPersistedCredentials(): boolean {
    try {
      return Boolean(localStorage.getItem('sip_password') || sessionStorage.getItem('sip_password'));
    } catch {
      return false;
    }
  }

  setDestination(destination: string): void {
    this.destinationSubject.next(destination);
  }

  setTransferTarget(target: string): void {
    const normalizedTarget = target.trim();
    this.transferTargetSubject.next(normalizedTarget);

    if (!normalizedTarget) {
      this.transferErrorSubject.next(null);
      this.transferMessageSubject.next(null);
      this.transferSuccessSubject.next(false);
    }
  }

  /**
   * Tenant switches must tear down the SIP client so stale audio streams and
   * cached user-agent state do not leak across tenant boundaries.
   */
  resetForTenantChange(): void {
    this.destroyTransport({ suppressReconnect: true });
    this.clearCredentials();
    this.profileSubject.next(null);
    this.callStateSubject.next('idle');
    this.registrationStateSubject.next('disconnected');
    this.transportStateSubject.next('disconnected');
    this.microphonePermissionSubject.next('unknown');
    this.mutedSubject.next(false);
    this.destinationSubject.next('');
    this.resetTransferState();
    this.incomingCallSubject.next(false);
    this.errorSubject.next(null);
    this.resetMediaDiagnostics();
  }

  private async ensureTransport(profile: SipProfile): Promise<void> {
    if (this.userAgent && this.registerer) {
      return;
    }

    if (!this.authorizationPassword) {
      throw new Error('SIP credentials are not enabled for this environment.');
    }

    const uri = UserAgent.makeURI(profile.sip_uri);

    if (!uri) {
      throw new Error('SIP URI is invalid.');
    }

    // The browser SIP URI and WSS transport must be built from browser-
    // reachable values. The FreeSWITCH directory lookup domain can be a
    // different Docker runtime IP and must never be used here.
    const options = {
      uri,
      displayName: profile.display_name,
      authorizationUsername: profile.authorization_username,
      authorizationPassword: this.authorizationPassword,
      transportOptions: {
        server: profile.websocket_url,
      },
      sessionDescriptionHandlerFactory: this.createSessionDescriptionHandlerFactory(),
      sessionDescriptionHandlerFactoryOptions: {
        iceGatheringTimeout: this.iceGatheringTimeoutMs,
        constraints: this.buildMediaConstraints(),
      },
    } as any;

    // Browser SIP.js transport only works when the local WSS/TLS endpoint is
    // reachable and the browser trusts the certificate chain.
    this.userAgent = new UserAgent(options);
    this.userAgent.delegate = this.createUserAgentDelegate();
    // Registerer lifecycle is tied to the UserAgent so a tenant change or
    // disconnect can tear both down together without leaking stale state.
    this.registerer = new Registerer(this.userAgent);
    this.registerer.stateChange.addListener((state) => this.handleRegistererStateChange(state));
    this.attachTransportListeners(this.userAgent);
    await this.userAgent.start();
  }

  private createSessionDescriptionHandlerFactory(): Web.SessionDescriptionHandlerFactory {
    const defaultFactory = Web.defaultSessionDescriptionHandlerFactory();

    return ((session: Session, options: unknown) => {
      const sessionDescriptionHandler = defaultFactory(session, options as any) as any as
        Session['sessionDescriptionHandler'] &
        SipSessionDescriptionHandler;
      const originalGetDescription = sessionDescriptionHandler.getDescription?.bind(sessionDescriptionHandler);
      const directionLabel = session instanceof Invitation ? 'answer' : 'outgoing';

      if (originalGetDescription) {
        sessionDescriptionHandler.getDescription = async (handlerOptions: any, modifiers?: Array<(description: RTCSessionDescriptionInit) => Promise<RTCSessionDescriptionInit>>) => {
          const peerConnection = sessionDescriptionHandler.peerConnection;
          const effectiveOptions = this.mergeIceGatheringOptions(handlerOptions, options);

          console.debug(`[SIP/WebRTC] ${directionLabel} SDP before originalGetDescription`, {
            iceGatheringState: peerConnection?.iceGatheringState ?? 'unknown',
            iceGatheringTimeoutMs: effectiveOptions.iceGatheringTimeout,
            handlerOptions,
          });

          const description = await originalGetDescription(effectiveOptions as any, modifiers);
          const localDescriptionCandidateCount = this.countIceCandidates(peerConnection?.localDescription?.sdp ?? '');
          const candidateCount = this.countIceCandidates(description.body);

          console.debug(
            `[SIP/WebRTC] ${directionLabel} SDP after originalGetDescription`,
            {
              iceGatheringState: peerConnection?.iceGatheringState ?? 'unknown',
              candidateCount,
              localDescriptionCandidateCount,
              hasCandidates: candidateCount > 0,
              sdpLength: description.body.length,
            },
          );

          if (candidateCount === 0) {
            console.warn(`[SIP/WebRTC] ${directionLabel} SDP completed without ICE candidates`, {
              localDescriptionCandidateCount,
            });

            const waitedSdp = await this.waitForIceCandidates(peerConnection, effectiveOptions.iceGatheringTimeout, directionLabel);
            const waitedCandidateCount = this.countIceCandidates(waitedSdp);

            console.debug(`[SIP/WebRTC] ${directionLabel} SDP after explicit wait`, {
              iceGatheringState: peerConnection?.iceGatheringState ?? 'unknown',
              waitedCandidateCount,
            });

            if (waitedCandidateCount > 0) {
              return {
                body: waitedSdp,
                contentType: description.contentType,
              };
            }
          }

          return description;
        };
      }

      return sessionDescriptionHandler;
    }) as Web.SessionDescriptionHandlerFactory;
  }

  private createUserAgentDelegate(): UserAgentDelegate {
    return {
      onInvite: (invitation) => this.handleIncomingInvitation(invitation),
      onConnect: () => {
        console.debug('[SIP/WebRTC] SIP transport connected');
        if (this.transportStateSubject.value === 'connecting' || this.transportStateSubject.value === 'reconnecting') {
          this.transportStateSubject.next('connecting');
        }
      },
      onDisconnect: (error) => this.handleTransportDisconnect(error),
    };
  }

  private handleRegistererStateChange(state: RegistererState): void {
    if (state === RegistererState.Registered) {
      this.registrationStateSubject.next('registered');
      if (this.transportStateSubject.value !== 'reconnecting') {
        this.transportStateSubject.next('registered');
      }
      return;
    }

    if (state === RegistererState.Unregistered || state === RegistererState.Terminated) {
      if (this.registrationStateSubject.value !== 'failed' && this.transportStateSubject.value !== 'reconnecting') {
        this.registrationStateSubject.next('disconnected');
      }
    }
  }

  private attachTransportListeners(userAgent: UserAgent): void {
    const transport = userAgent.transport as UserAgent['transport'] & {
      stateChange?: { addListener: (listener: (state: TransportState) => void) => void };
    };

    transport.stateChange?.addListener((state) => {
      console.debug('[SIP/WebRTC] SIP transport state change', {
        transportState: state,
        reconnectState: this.transportStateSubject.value,
      });

      if (state === TransportState.Connecting) {
        if (this.transportStateSubject.value !== 'reconnecting') {
          this.transportStateSubject.next('connecting');
        }
        return;
      }

      if (state === TransportState.Connected) {
        if (this.transportStateSubject.value === 'connecting') {
          this.transportStateSubject.next('registered');
        }
        return;
      }

      if (state === TransportState.Disconnected) {
        this.handleTransportDisconnect();
      }
    });
  }

  private handleTransportDisconnect(error?: Error): void {
    const profile = this.profileSubject.value;
    const hasCredentials = Boolean(profile?.registration_enabled && profile.credentials_available && this.authorizationPassword);
    const isManualShutdown = this.reconnectSuppressed || !profile || !hasCredentials;
    const disconnectMessage = error
      ? this.toErrorMessage(error, 'SIP transport disconnected.', profile)
      : 'SIP transport disconnected.';

    console.warn('[SIP/WebRTC] SIP transport disconnected', {
      hasError: Boolean(error),
      disconnectMessage,
      reconnectSuppressed: this.reconnectSuppressed,
      registrationState: this.registrationStateSubject.value,
      transportState: this.transportStateSubject.value,
      callState: this.callStateSubject.value,
      hasCredentials,
    });

    this.stopMediaStatsDiagnostics();
    this.destroySession();

    if (isManualShutdown) {
      this.clearReconnectLoop();
      this.transportStateSubject.next('disconnected');
      this.registrationStateSubject.next('disconnected');
      this.errorSubject.next(null);
      return;
    }

    this.registrationStateSubject.next('disconnected');
    this.transportStateSubject.next('reconnecting');
    this.callStateSubject.next('registration_failed');
    this.errorSubject.next('SIP transport disconnected. Reconnecting...');
    this.scheduleReconnectAttempt();
  }

  private scheduleReconnectAttempt(): void {
    if (this.reconnectInFlight || this.reconnectTimerId !== null) {
      return;
    }

    const attemptNumber = this.reconnectAttemptCount + 1;
    if (attemptNumber > this.reconnectMaxAttempts) {
      this.failReconnectLoop('SIP transport reconnect failed after retry limit.');
      return;
    }

    const delayMs = Math.min(this.reconnectBaseDelayMs * (2 ** Math.max(0, attemptNumber - 1)), 4000);
    console.debug('[SIP/WebRTC] scheduling SIP reconnect attempt', {
      attemptNumber,
      delayMs,
      transportState: this.transportStateSubject.value,
    });

    this.reconnectTimerId = window.setTimeout(() => {
      this.reconnectTimerId = null;
      void this.attemptReconnect();
    }, delayMs);
  }

  private async attemptReconnect(): Promise<void> {
    if (this.reconnectInFlight || this.reconnectSuppressed) {
      return;
    }

    const profile = this.profileSubject.value;
    if (!profile || !profile.registration_enabled || !profile.credentials_available || !this.authorizationPassword) {
      this.failReconnectLoop('SIP reconnect skipped because credentials are unavailable.');
      return;
    }

    this.reconnectInFlight = true;
    this.reconnectAttemptCount += 1;
    console.debug('[SIP/WebRTC] attempting SIP reconnect', {
      attemptNumber: this.reconnectAttemptCount,
      transportState: this.transportStateSubject.value,
      registrationState: this.registrationStateSubject.value,
    });

    try {
      if (!this.userAgent || !this.registerer) {
        await this.ensureTransport(profile);
      } else if (!this.userAgent.isConnected()) {
        await this.userAgent.reconnect();
      }

      await this.registerer?.register();
      const completedAttempt = this.reconnectAttemptCount;
      this.registrationStateSubject.next('registered');
      this.transportStateSubject.next('registered');
      this.callStateSubject.next('registered');
      this.errorSubject.next(null);
      this.reconnectAttemptCount = 0;
      this.clearReconnectLoop();
      console.debug('[SIP/WebRTC] SIP reconnect succeeded', {
        attemptNumber: completedAttempt,
      });
    } catch (error) {
      console.warn('[SIP/WebRTC] SIP reconnect attempt failed', {
        attemptNumber: this.reconnectAttemptCount,
        error: this.toErrorMessage(error, 'SIP reconnect attempt failed.', profile),
      });

      if (this.reconnectAttemptCount >= this.reconnectMaxAttempts) {
        this.failReconnectLoop('SIP transport reconnect failed after retry limit.');
        return;
      }

      this.transportStateSubject.next('reconnecting');
      this.registrationStateSubject.next('disconnected');
      this.scheduleReconnectAttempt();
    } finally {
      this.reconnectInFlight = false;
    }
  }

  private clearReconnectLoop(): void {
    if (this.reconnectTimerId !== null) {
      clearTimeout(this.reconnectTimerId);
      this.reconnectTimerId = null;
    }

    this.reconnectAttemptCount = 0;
    this.reconnectInFlight = false;
  }

  private failReconnectLoop(message: string): void {
    this.clearReconnectLoop();
    this.transportStateSubject.next('failed');
    this.registrationStateSubject.next('failed');
    this.callStateSubject.next('registration_failed');
    this.errorSubject.next(message);
    console.warn('[SIP/WebRTC] SIP reconnect failed', {
      message,
      transportState: this.transportStateSubject.value,
      registrationState: this.registrationStateSubject.value,
      callState: this.callStateSubject.value,
    });
  }

  private handleIncomingInvitation(invitation: Invitation): void {
    if (this.activeSession || this.incomingInvitation) {
      void invitation.reject({
        statusCode: 486,
        reasonPhrase: 'Busy Here',
      }).catch(() => undefined);
      return;
    }

    // Incoming calls stay in a lightweight ringing state until the user
    // explicitly answers or rejects them from the current browser session.
    this.destroySession();
    this.incomingInvitation = invitation;
    this.incomingCallSubject.next(true);
    this.callStateSubject.next('ringing');
    this.errorSubject.next(null);

    invitation.stateChange.addListener((state) => {
      if (state === SessionState.Terminated && this.incomingInvitation === invitation) {
        this.destroySession();
        this.callStateSubject.next('ended');
      }
    });
  }

  async answerIncomingCall(): Promise<void> {
    if (!this.incomingInvitation) {
      return;
    }

    this.activeSession = this.incomingInvitation;
    // Accepting the invitation promotes the incoming call into the same
    // session lifecycle as an outgoing call so cleanup stays centralized.
    this.attachSessionHandlers(this.incomingInvitation);
    this.attachSessionMediaHandlers(this.incomingInvitation);

    try {
      const acceptOptions = {
        sessionDescriptionHandlerOptions: {
          constraints: this.buildMediaConstraints(),
          iceGatheringTimeout: this.iceGatheringTimeoutMs,
        } as any,
      };

      console.debug('[SIP/WebRTC] callee preparing incoming answer', {
        iceGatheringTimeoutMs: this.iceGatheringTimeoutMs,
        acceptOptions,
      });

      await this.incomingInvitation.accept(acceptOptions);
    } catch (error) {
      this.callStateSubject.next('failed');
      this.errorSubject.next(this.toErrorMessage(error, 'Call failed.'));
      this.destroySession();
    }
  }

  async rejectIncomingCall(): Promise<void> {
    if (!this.incomingInvitation) {
      console.debug('[SIP/WebRTC] decline skipped because no incoming invitation exists');
      return;
    }

    try {
      console.debug('[SIP/WebRTC] declining incoming call', {
        callState: this.callStateSubject.value,
      });
      await this.incomingInvitation.reject();
    } finally {
      this.destroySession();
      this.callStateSubject.next('ended');
    }
  }

  private attachSessionHandlers(session: Session): void {
    // Session lifecycle events drive the visible call state so the UI can show
    // dialing, ringing, active, and terminated transitions without guessing.
    session.stateChange.addListener((state) => {
      if (state === SessionState.Establishing) {
        this.callStateSubject.next('dialing');
      }

      if (state === SessionState.Established) {
        const peerConnection = (session.sessionDescriptionHandler as Session['sessionDescriptionHandler'] & {
          peerConnection?: RTCPeerConnection;
        } | undefined)?.peerConnection;
        const receiverAudioTracks = peerConnection?.getReceivers?.().filter((receiver) => receiver.track?.kind === 'audio') ?? [];

        console.debug('[SIP/WebRTC] session established', {
          receiverAudioTrackCount: receiverAudioTracks.length,
          receiverAudioTrackStates: receiverAudioTracks.map((receiver) => ({
            id: receiver.track?.id ?? null,
            kind: receiver.track?.kind ?? null,
            readyState: receiver.track?.readyState ?? null,
          })),
        });

        if (this.incomingInvitation === session) {
          this.incomingInvitation = null;
          this.incomingCallSubject.next(false);
        }

        this.callStateSubject.next('active');
        this.registrationStateSubject.next('registered');
        this.startMediaStatsDiagnostics(session);
        void this.bindSessionAudio(session);
      }

      if (state === SessionState.Terminated) {
        console.debug('[SIP/WebRTC] session terminated', {
          hasRemoteStream: Boolean(this.remoteMediaStream),
          hasAudioElement: Boolean(this.remoteAudioElement),
        });
        this.stopMediaStatsDiagnostics();
        this.callStateSubject.next('ended');
        this.destroySession();
      }
    });
  }

  private async bindSessionAudio(session: Session | null): Promise<void> {
    if (!session) {
      this.mediaDiagnosticsSubject.next({
        ...this.mediaDiagnosticsSubject.value,
        remote_audio_attached: false,
        remote_audio_track_count: 0,
        remote_audio_playing: false,
      });
      return;
    }

    const audioElement = this.remoteAudioElement;
    const handlerStream = (session as Session & {
      sessionDescriptionHandler?: { remoteMediaStream?: MediaStream };
    }).sessionDescriptionHandler?.remoteMediaStream;
    const stream = this.remoteMediaStream ?? handlerStream ?? null;

    console.debug('[SIP/WebRTC] binding remote audio', {
      hasAudioElement: Boolean(audioElement),
      hasRemoteMediaStream: Boolean(this.remoteMediaStream),
      handlerStreamId: handlerStream?.id ?? null,
      trackedStreamId: stream?.id ?? null,
      remoteAudioTrackCount: stream?.getAudioTracks().length ?? 0,
      receiverAudioTrackCount: (session as Session & {
        sessionDescriptionHandler?: { peerConnection?: RTCPeerConnection };
      }).sessionDescriptionHandler?.peerConnection?.getReceivers?.().filter((receiver) => receiver.track?.kind === 'audio').length ?? 0,
    });

    if (!audioElement || !stream) {
      const lastMediaError = !audioElement
        ? 'Remote audio element is not attached yet.'
        : 'Remote audio stream is not available yet.';
      this.mediaDiagnosticsSubject.next({
        ...this.mediaDiagnosticsSubject.value,
        remote_audio_attached: Boolean(audioElement && stream),
        remote_audio_track_count: stream?.getTracks().length ?? 0,
        remote_audio_playing: false,
        last_media_error: lastMediaError,
      });

      this.lastMediaErrorSubject.next(lastMediaError);

      return;
    }

    // The browser must play the remote stream through the single reusable
    // audio element so we can observe autoplay blocks and cleanup one source.
    this.prepareRemoteAudioElement(audioElement);
    audioElement.srcObject = stream;

    console.debug('[SIP/WebRTC] remote audio element srcObject assigned', {
      streamId: stream.id,
      remoteTrackCount: stream.getAudioTracks().length,
      paused: audioElement.paused,
      muted: audioElement.muted,
      volume: audioElement.volume,
      autoplay: audioElement.autoplay,
      playsInline: (audioElement as HTMLMediaElement & { playsInline?: boolean }).playsInline ?? false,
    });

    const trackCount = stream.getTracks().length;
    this.mediaDiagnosticsSubject.next({
      ...this.mediaDiagnosticsSubject.value,
      remote_audio_attached: true,
      remote_audio_track_count: trackCount,
      remote_audio_playing: false,
      last_media_error: trackCount > 0 ? null : 'Remote audio track has not arrived yet.',
    });

    if (trackCount === 0) {
      this.lastMediaErrorSubject.next('Remote audio track has not arrived yet.');
      return;
    }

    try {
      const playResult = audioElement.play();
      if (playResult) {
        await playResult;
      }

      console.debug('[SIP/WebRTC] remote audio element play succeeded', {
        paused: audioElement.paused,
        muted: audioElement.muted,
        volume: audioElement.volume,
        trackCount,
        streamId: stream.id,
      });

      if (this.remoteAudioElement === audioElement && audioElement.srcObject === stream) {
        this.mediaDiagnosticsSubject.next({
          ...this.mediaDiagnosticsSubject.value,
          remote_audio_attached: true,
          remote_audio_track_count: trackCount,
          remote_audio_playing: true,
          last_media_error: null,
        });
        this.lastMediaErrorSubject.next(null);
      }
    } catch (error) {
      const mediaError = this.toMediaErrorMessage(error);
      console.debug('[SIP/WebRTC] remote audio element play failed', {
        paused: audioElement.paused,
        muted: audioElement.muted,
        volume: audioElement.volume,
        trackCount,
        streamId: stream.id,
        error: mediaError,
      });
      if (this.remoteAudioElement === audioElement && audioElement.srcObject === stream) {
        this.mediaDiagnosticsSubject.next({
          ...this.mediaDiagnosticsSubject.value,
          remote_audio_attached: true,
          remote_audio_track_count: trackCount,
          remote_audio_playing: false,
          last_media_error: mediaError,
        });
      }
      this.lastMediaErrorSubject.next(mediaError);
      this.errorSubject.next(mediaError);
    }
  }

  private destroyTransport(options: { suppressReconnect?: boolean } = {}): void {
    if (options.suppressReconnect) {
      this.reconnectSuppressed = true;
    }

    this.clearReconnectLoop();
    this.destroySession();

    if (this.registerer) {
      const disposePromise = this.registerer.dispose?.();
      void disposePromise?.catch(() => undefined);
      this.registerer = null;
    }

    if (this.userAgent) {
      const stopPromise = this.userAgent.stop?.();
      void stopPromise?.catch(() => undefined);
      this.userAgent = null;
    }

    if (this.remoteAudioElement) {
      this.remoteAudioElement.srcObject = null;
      this.prepareRemoteAudioElement(this.remoteAudioElement);
    }

    this.transportStateSubject.next('disconnected');
    this.resetTransferState();
    this.resetMediaDiagnostics();
  }

  private clearCredentials(): void {
    this.authorizationPassword = null;
  }

  private sanitizeProfile(profile: SipProfile): SipProfile {
    return {
      ...profile,
      password: null,
    };
  }

  private resolveSipTarget(destination: string, domain: string): string {
    if (destination.startsWith('sip:')) {
      return destination;
    }

    // The call target follows the browser-facing SIP domain from the loaded
    // profile so local demo dialing stays reachable from the browser session.
    if (destination.includes('@')) {
      return `sip:${destination}`;
    }

    return `sip:${destination}@${domain}`;
  }

  private destroySession(): void {
    this.stopMediaStatsDiagnostics();

    if (typeof MediaStream !== 'undefined' && this.remoteAudioElement?.srcObject instanceof MediaStream) {
      // Stop any live media tracks so a tenant switch does not leave the
      // browser capturing microphone or speaker resources in the background.
      this.remoteAudioElement.srcObject.getTracks().forEach((track) => track.stop());
    }

    if (this.remoteAudioElement) {
      this.remoteAudioElement.srcObject = null;
      this.prepareRemoteAudioElement(this.remoteAudioElement);
    }

    this.remoteMediaStream = null;
    this.incomingInvitation = null;
    this.incomingCallSubject.next(false);
    this.activeSession = null;
    this.mutedSubject.next(false);
    this.localHoldSubject.next(false);
    this.resetTransferState();
    this.mediaDiagnosticsSubject.next({
      ...this.mediaDiagnosticsSubject.value,
      remote_audio_attached: false,
      remote_audio_track_count: 0,
      remote_audio_playing: false,
    });
  }

  private toErrorMessage(error: unknown, fallback: string): string;

  private toErrorMessage(error: unknown, fallback: string, profile: SipProfile | null): string;

  private toErrorMessage(error: unknown, fallback: string, profile: SipProfile | null = this.profileSubject.value): string {
    if (error instanceof Error && error.message.trim()) {
      const message = error.message.trim();

      if (this.isWebSocketTransportError(message)) {
        if (profile?.websocket_url?.startsWith('ws://')) {
          return 'SIP WebSocket connection failed before registration. Check local FreeSWITCH WS port mapping.';
        }

        return 'SIP WebSocket connection failed before registration. Check local FreeSWITCH WS/WSS port mapping and browser TLS trust.';
      }

      if (this.isSipAuthRejectedError(message)) {
        return 'SIP registration was rejected by FreeSWITCH. Check the local demo password, realm, and directory domain.';
      }

      if (this.isBrowserSupportError(message)) {
        return 'This browser does not fully support the WebRTC audio APIs required for the softphone. Use Chrome or Edge for the local demo.';
      }

      if (this.isMediaNegotiationError(message)) {
        return 'The call was rejected during media negotiation. Check WebRTC codec, ICE/DTLS, and the local FreeSWITCH demo bridge.';
      }

      if (this.isBridgeIncompatibleDestinationError(message)) {
        return 'FreeSWITCH could not bridge the local WebRTC call. Check the demo dialplan bridge target and media compatibility.';
      }

      if (this.isUserNotRegisteredError(message)) {
        return 'The destination extension is not registered. Open another browser session and register it first.';
      }

      return message;
    }

    return fallback;
  }

  private isWebSocketTransportError(message: string): boolean {
    return /websocket closed/i.test(message) || /\b1006\b/.test(message);
  }

  private isSipAuthRejectedError(message: string): boolean {
    return /\b403\b/.test(message) || /forbidden/i.test(message) || /digest.*rejected/i.test(message);
  }

  private isUserNotRegisteredError(message: string): boolean {
    return /USER_NOT_REGISTERED/i.test(message) || /\b480\b/.test(message) || /\b481\b/.test(message);
  }

  private isMediaNegotiationError(message: string): boolean {
    return /\b488\b/.test(message) || /not acceptable here/i.test(message);
  }

  private isBridgeIncompatibleDestinationError(message: string): boolean {
    return /INCOMPATIBLE_DESTINATION/i.test(message);
  }

  private isAutoplayBlockedError(message: string): boolean {
    return /notallowederror/i.test(message) || /autoplay/i.test(message) || /user gesture/i.test(message) || /play\(\)/i.test(message);
  }

  private isBrowserSupportError(message: string): boolean {
    return /not supported/i.test(message)
      || /rtcpconnection/i.test(message)
      || /rtcpeerconnection/i.test(message)
      || /getusermedia/i.test(message)
      || /webrtc/i.test(message) && /unavailable|missing|unsupported/i.test(message);
  }

  private isRemoteMediaConnectionError(message: string): boolean {
    return /\bICE\b/i.test(message) || /\bDTLS\b/i.test(message) || /\bRTP\b/i.test(message) || /\bSDP\b/i.test(message);
  }

  private isCallInProgress(): boolean {
    return ['dialing', 'ringing', 'active', 'held'].includes(this.callStateSubject.value);
  }

  private resetTransferState(): void {
    this.transferTargetSubject.next('');
    this.transferInProgressSubject.next(false);
    this.transferErrorSubject.next(null);
    this.transferMessageSubject.next(null);
    this.transferSuccessSubject.next(false);
  }

  private buildTransferTarget(targetInput: string, domain: string): ReturnType<typeof UserAgent.makeURI> | null {
    const normalizedInput = targetInput.trim();
    if (!normalizedInput) {
      return null;
    }

    const isTransferUri = normalizedInput.startsWith('sip:');
    const isExtension = /^\d+$/.test(normalizedInput);
    const isBareSipUser = /^[^@\s/]+@[^@\s/]+$/.test(normalizedInput);

    if (!isTransferUri && !isExtension && !isBareSipUser) {
      return null;
    }

    return UserAgent.makeURI(this.resolveSipTarget(normalizedInput, domain));
  }

  private parseReferNotify(body: string): { statusCode: number | null; reasonPhrase: string | null; isFinal: boolean } {
    const statusLine = body.match(/^SIP\/2\.0\s+(\d{3})\s*(.*)$/im);
    const statusCode = statusLine?.[1] ? Number(statusLine[1]) : null;
    const reasonPhrase = statusLine?.[2]?.trim() || null;

    return {
      statusCode,
      reasonPhrase,
      isFinal: statusCode !== null && statusCode >= 200,
    };
  }

  private formatReferResponseMessage(statusCode?: number | null, reasonPhrase?: string | null, fallback = 'Transfer failed.'): string {
    const parts = [statusCode, reasonPhrase?.trim()].filter(Boolean);
    return parts.length > 0 ? `Transfer failed: ${parts.join(' ')}` : fallback;
  }

  private hasLocalAudioSenderTrack(): boolean {
    const peerConnection = (this.activeSession?.sessionDescriptionHandler as Session['sessionDescriptionHandler'] & {
      peerConnection?: RTCPeerConnection;
    } | undefined)?.peerConnection;

    return Boolean(peerConnection?.getSenders?.().some((sender) => sender.track?.kind === 'audio'));
  }

  private isSelfCallTarget(target: ReturnType<typeof UserAgent.makeURI>, currentExtensionNumber: string): boolean {
    const targetUser = target?.user?.trim() ?? '';
    const current = currentExtensionNumber.trim();

    return targetUser !== '' && current !== '' && targetUser === current;
  }

  private toMediaErrorMessage(error: unknown): string {
    const message = error instanceof Error ? error.message.trim() : '';

    if (message && this.isBrowserSupportError(message)) {
      return 'This browser does not fully support the WebRTC audio APIs required for the softphone. Use Chrome or Edge for the local demo.';
    }

    if (message && this.isAutoplayBlockedError(message)) {
      return 'Browser autoplay blocked remote audio. Click the page once, then retry the call.';
    }

    if (message && /permission/i.test(message)) {
      return 'Microphone permission was denied. Allow microphone access in the browser and try again.';
    }

    if (message && this.isRemoteMediaConnectionError(message)) {
      return 'Remote media transport failed. Check FreeSWITCH RTP, ICE, DTLS, and SDP direction.';
    }

    return message || 'Remote audio playback failed.';
  }

  private attachSessionMediaHandlers(session: Session): void {
    const sessionDelegate = session.delegate ?? {};
    session.delegate = {
      ...sessionDelegate,
      onSessionDescriptionHandler: (sessionDescriptionHandler, provisional) => {
        sessionDelegate.onSessionDescriptionHandler?.(sessionDescriptionHandler, provisional);
        this.attachPeerConnectionDiagnostics(sessionDescriptionHandler);
      },
    };

    if (session.sessionDescriptionHandler) {
      this.attachPeerConnectionDiagnostics(session.sessionDescriptionHandler);
    }
  }

  private async refreshBrowserCapabilities(): Promise<void> {
    const browserName = this.detectBrowserName();
    const baseDiagnostics = this.buildBrowserCapabilityDiagnostics({
      browserName,
      audioAutoplaySupported: 'unknown',
    });

    this.browserDiagnosticsSubject.next(baseDiagnostics);
    console.debug('[SIP/WebRTC] browser capability snapshot', baseDiagnostics);

    const audioAutoplaySupported = await this.probeAudioAutoplay();
    const nextDiagnostics = this.buildBrowserCapabilityDiagnostics({
      browserName,
      audioAutoplaySupported,
    });

    this.browserDiagnosticsSubject.next(nextDiagnostics);
    console.debug('[SIP/WebRTC] browser autoplay probe result', {
      browserName,
      audioAutoplaySupported,
      warningMessage: nextDiagnostics.warning_message,
    });
  }

  private buildBrowserCapabilityDiagnostics(overrides: {
    browserName?: string;
    userAgent?: string;
    hasMediaDevices?: boolean;
    hasGetUserMedia?: boolean;
    hasPeerConnection?: boolean;
    audioAutoplaySupported?: boolean | 'unknown';
  } = {}): SipBrowserDiagnostics {
    const userAgent = overrides.userAgent ?? navigator?.userAgent ?? '';
    const browserName = overrides.browserName ?? this.detectBrowserName(userAgent);
    const hasMediaDevices = overrides.hasMediaDevices ?? (typeof navigator !== 'undefined' && Boolean(navigator.mediaDevices));
    const hasGetUserMedia = overrides.hasGetUserMedia ?? Boolean(navigator?.mediaDevices?.getUserMedia);
    const hasPeerConnection = overrides.hasPeerConnection ?? (typeof RTCPeerConnection !== 'undefined');
    const isOpera = /opera|opr\//i.test(userAgent);
    const audioAutoplaySupported = overrides.audioAutoplaySupported ?? 'unknown';

    const warningParts: string[] = [];

    if (!hasMediaDevices || !hasGetUserMedia || !hasPeerConnection) {
      warningParts.push('This browser does not fully support the WebRTC audio APIs required for the softphone.');
      warningParts.push('Use Chrome or Edge for the local demo.');
    } else if (isOpera) {
      warningParts.push('Opera is not a primary supported browser for the local softphone.');
      warningParts.push('Chrome or Edge is recommended for reliable local demo calling.');
    }

    if (audioAutoplaySupported === false) {
      warningParts.push('Audio autoplay appears blocked in this browser.');
      warningParts.push('Click the page once before calling, or use Chrome or Edge.');
    }

    return {
      browser_name: browserName,
      is_opera: isOpera,
      has_media_devices: hasMediaDevices,
      has_get_user_media: hasGetUserMedia,
      has_peer_connection: hasPeerConnection,
      audio_autoplay_supported: audioAutoplaySupported,
      warning_message: warningParts.length > 0 ? warningParts.join(' ') : null,
    };
  }

  private detectBrowserName(userAgent: string = navigator?.userAgent ?? ''): string {

    if (/opera|opr\//i.test(userAgent)) {
      return 'Opera';
    }

    if (/edg\//i.test(userAgent)) {
      return 'Edge';
    }

    if (/chrome\//i.test(userAgent)) {
      return 'Chrome';
    }

    if (/firefox\//i.test(userAgent)) {
      return 'Firefox';
    }

    if (/safari\//i.test(userAgent)) {
      return 'Safari';
    }

    return 'unknown';
  }

  private async probeAudioAutoplay(): Promise<boolean | 'unknown'> {
    if (typeof document === 'undefined' || typeof HTMLAudioElement === 'undefined') {
      return 'unknown';
    }

    const audioElement = document.createElement('audio');
    const mediaElement = audioElement as HTMLMediaElement & { playsInline?: boolean };
    audioElement.muted = true;
    audioElement.autoplay = true;
    mediaElement.playsInline = true;
    audioElement.src =
      'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAIA+AAACABAAZGF0YQAAAAA=';

    try {
      const playResult = audioElement.play();
      if (playResult) {
        await playResult;
      }

      audioElement.pause();
      audioElement.src = '';
      return true;
    } catch {
      audioElement.pause();
      audioElement.src = '';
      return false;
    }
  }

  private attachPeerConnectionDiagnostics(sessionDescriptionHandler: NonNullable<Session['sessionDescriptionHandler']>): void {
    const handler = sessionDescriptionHandler as NonNullable<Session['sessionDescriptionHandler']> & SipSessionDescriptionHandler;
    const peerConnection = handler.peerConnection;

    if (!peerConnection) {
      return;
    }

    const existingDelegate = handler.peerConnectionDelegate ?? {};
    handler.peerConnectionDelegate = {
      ...existingDelegate,
      ontrack: (event: RTCTrackEvent) => {
        existingDelegate.ontrack?.(event);
        const track = event.track;
        const streamIds = event.streams.map((stream) => stream.id);

        console.debug('[SIP/WebRTC] peerConnection.ontrack fired', {
          trackKind: track?.kind ?? null,
          trackId: track?.id ?? null,
          trackReadyState: track?.readyState ?? null,
          streamIds,
        });

        if (!this.remoteMediaStream) {
          this.remoteMediaStream = new MediaStream();
        }

        if (track && !this.remoteMediaStream.getTracks().some((existingTrack) => existingTrack.id === track.id)) {
          this.remoteMediaStream.addTrack(track);
        }

        console.debug('[SIP/WebRTC] remote media stream updated from ontrack', {
          streamId: this.remoteMediaStream.id,
          remoteAudioTrackCount: this.remoteMediaStream.getAudioTracks().length,
          remoteTrackCount: this.remoteMediaStream.getTracks().length,
        });

        const activeHandler = this.activeSession?.sessionDescriptionHandler as
          | (NonNullable<Session['sessionDescriptionHandler']> & SipSessionDescriptionHandler)
          | undefined;
        this.mediaDiagnosticsSubject.next({
          ...this.mediaDiagnosticsSubject.value,
          remote_audio_track_count: this.activeSession
            ? (this.remoteMediaStream?.getTracks().length ?? activeHandler?.remoteMediaStream?.getTracks().length ?? 0)
            : this.mediaDiagnosticsSubject.value.remote_audio_track_count,
        });
        if (this.remoteAudioElement) {
          this.prepareRemoteAudioElement(this.remoteAudioElement);
        }
        void this.bindSessionAudio(this.activeSession);
      },
      onconnectionstatechange: (event: Event) => {
        existingDelegate.onconnectionstatechange?.(event);
        this.updateConnectionDiagnostics(peerConnection.connectionState, peerConnection.iceConnectionState);
      },
      oniceconnectionstatechange: (event: Event) => {
        existingDelegate.oniceconnectionstatechange?.(event);
        this.updateConnectionDiagnostics(peerConnection.connectionState, peerConnection.iceConnectionState);
      },
    };

    this.updateConnectionDiagnostics(peerConnection.connectionState, peerConnection.iceConnectionState);
  }

  private updateConnectionDiagnostics(connectionState: RTCPeerConnectionState | 'unknown', iceConnectionState: RTCIceConnectionState | 'unknown'): void {
    const nextDiagnostics = {
      ...this.mediaDiagnosticsSubject.value,
      peer_connection_state: connectionState,
      ice_connection_state: iceConnectionState,
    };

    if (connectionState === 'failed' || iceConnectionState === 'failed') {
      nextDiagnostics.last_media_error = 'Peer connection failed. Check FreeSWITCH RTP, ICE, DTLS, and browser network logs.';
      this.lastMediaErrorSubject.next(nextDiagnostics.last_media_error);
      this.errorSubject.next(nextDiagnostics.last_media_error);
    }

    this.mediaDiagnosticsSubject.next(nextDiagnostics);
  }

  private startMediaStatsDiagnostics(session: Session): void {
    this.stopMediaStatsDiagnostics();

    void this.logMediaStats(session, 'established');
    this.mediaStatsIntervalId = setInterval(() => {
      void this.logMediaStats(session, 'poll');
    }, 2000);
  }

  private stopMediaStatsDiagnostics(): void {
    if (this.mediaStatsIntervalId) {
      clearInterval(this.mediaStatsIntervalId);
      this.mediaStatsIntervalId = null;
    }

    this.mediaStatsPollingInFlight = false;
  }

  private async logMediaStats(session: Session, reason: 'established' | 'poll'): Promise<void> {
    const sessionDescriptionHandler = session.sessionDescriptionHandler as
      | (NonNullable<Session['sessionDescriptionHandler']> & SipSessionDescriptionHandler)
      | undefined;
    const peerConnection = sessionDescriptionHandler?.peerConnection;

    if (!peerConnection) {
      console.debug('[SIP/WebRTC] media stats unavailable', { reason, hasPeerConnection: false });
      return;
    }

    if (this.mediaStatsPollingInFlight) {
      return;
    }

    this.mediaStatsPollingInFlight = true;

    try {
      const stats = await peerConnection.getStats();
      let inboundAudioBytesReceived: number | null = null;
      let inboundAudioPacketsReceived: number | null = null;
      let outboundAudioBytesSent: number | null = null;
      let outboundAudioPacketsSent: number | null = null;
      let remoteInboundAudioBytesReceived: number | null = null;
      let remoteInboundAudioPacketsReceived: number | null = null;
      let selectedCandidatePairState: string | null = null;
      let selectedCandidatePairNominated: boolean | null = null;
      let selectedCandidatePairCurrentRoundTripTime: number | null = null;
      let selectedCandidatePairLocalCandidateType: string | null = null;
      let selectedCandidatePairRemoteCandidateType: string | null = null;

      stats.forEach((stat) => {
        if (stat.type === 'inbound-rtp' && stat.kind === 'audio') {
          inboundAudioBytesReceived = stat.bytesReceived ?? inboundAudioBytesReceived;
          inboundAudioPacketsReceived = stat.packetsReceived ?? inboundAudioPacketsReceived;
        }

        if (stat.type === 'outbound-rtp' && stat.kind === 'audio') {
          outboundAudioBytesSent = stat.bytesSent ?? outboundAudioBytesSent;
          outboundAudioPacketsSent = stat.packetsSent ?? outboundAudioPacketsSent;
        }

        if (stat.type === 'remote-inbound-rtp' && stat.kind === 'audio') {
          remoteInboundAudioBytesReceived = stat.bytesReceived ?? remoteInboundAudioBytesReceived;
          remoteInboundAudioPacketsReceived = stat.packetsReceived ?? remoteInboundAudioPacketsReceived;
        }

        if (stat.type === 'candidate-pair' && (stat.selected || stat.nominated || stat.state === 'succeeded')) {
          selectedCandidatePairState = stat.state ?? selectedCandidatePairState;
          selectedCandidatePairNominated = stat.nominated ?? selectedCandidatePairNominated;
          selectedCandidatePairCurrentRoundTripTime = stat.currentRoundTripTime ?? selectedCandidatePairCurrentRoundTripTime;
          selectedCandidatePairLocalCandidateType = stat.localCandidateType ?? selectedCandidatePairLocalCandidateType;
          selectedCandidatePairRemoteCandidateType = stat.remoteCandidateType ?? selectedCandidatePairRemoteCandidateType;
        }
      });

      const senderAudioTracks = peerConnection.getSenders().filter((sender) => sender.track?.kind === 'audio').map((sender) => ({
        id: sender.track?.id ?? null,
        readyState: sender.track?.readyState ?? null,
        enabled: sender.track?.enabled ?? null,
        muted: sender.track?.muted ?? null,
      }));
      const receiverAudioTracks = peerConnection.getReceivers().filter((receiver) => receiver.track?.kind === 'audio').map((receiver) => ({
        id: receiver.track?.id ?? null,
        readyState: receiver.track?.readyState ?? null,
        enabled: receiver.track?.enabled ?? null,
        muted: receiver.track?.muted ?? null,
      }));

      const audioElement = this.remoteAudioElement;
      const audioElementTrackCount = audioElement?.srcObject instanceof MediaStream
        ? audioElement.srcObject.getAudioTracks().length
        : 0;

      console.debug('[SIP/WebRTC] media stats snapshot', {
        reason,
        inboundAudioBytesReceived,
        inboundAudioPacketsReceived,
        outboundAudioBytesSent,
        outboundAudioPacketsSent,
        remoteInboundAudioBytesReceived,
        remoteInboundAudioPacketsReceived,
        selectedCandidatePairState,
        selectedCandidatePairNominated,
        selectedCandidatePairCurrentRoundTripTime,
        selectedCandidatePairLocalCandidateType,
        selectedCandidatePairRemoteCandidateType,
        audioTrackState: {
          senders: senderAudioTracks,
          receivers: receiverAudioTracks,
        },
        audioElementState: {
          hasElement: Boolean(audioElement),
          paused: audioElement?.paused ?? null,
          muted: audioElement?.muted ?? null,
          volume: audioElement?.volume ?? null,
          srcObjectTrackCount: audioElementTrackCount,
        },
      });
    } catch (error) {
      console.debug('[SIP/WebRTC] media stats collection failed', {
        reason,
        error: error instanceof Error ? error.message : String(error),
      });
    } finally {
      this.mediaStatsPollingInFlight = false;
    }
  }

  private prepareRemoteAudioElement(element: HTMLAudioElement): void {
    const mediaElement = element as HTMLMediaElement & { playsInline?: boolean };
    element.autoplay = true;
    mediaElement.playsInline = true;
    element.controls = true;
    element.muted = false;
    element.volume = 1;
  }

  private resetMediaDiagnostics(): void {
    this.mediaDiagnosticsSubject.next({
      remote_audio_attached: false,
      remote_audio_track_count: 0,
      remote_audio_playing: false,
      peer_connection_state: 'unknown',
      ice_connection_state: 'unknown',
      last_media_error: null,
    });
    this.lastMediaErrorSubject.next(null);
  }

  private countIceCandidates(sdp: string): number {
    return (sdp.match(/^a=candidate:/gm) ?? []).length;
  }

  private mergeIceGatheringOptions(
    handlerOptions: {
      constraints?: MediaStreamConstraints;
      iceGatheringTimeout?: number;
    } | undefined,
    options: unknown,
  ): {
    constraints: MediaStreamConstraints;
    iceGatheringTimeout: number;
  } {
    const baseOptions = options as { iceGatheringTimeout?: number; constraints?: MediaStreamConstraints } | undefined;

    return {
      constraints: handlerOptions?.constraints ?? baseOptions?.constraints ?? {
        audio: true,
        video: false,
      },
      iceGatheringTimeout:
        handlerOptions?.iceGatheringTimeout ??
        baseOptions?.iceGatheringTimeout ??
        this.iceGatheringTimeoutMs,
    };
  }

  private async waitForIceCandidates(
    peerConnection: RTCPeerConnection | undefined,
    timeoutMs: number,
    directionLabel: string,
  ): Promise<string> {
    if (!peerConnection) {
      return '';
    }

    const logState = (label: string): void => {
      console.debug(`[SIP/WebRTC] ${directionLabel} ice gathering transition`, {
        label,
        iceGatheringState: peerConnection.iceGatheringState,
      });
    };

    logState('start');

    const initialCandidateCount = this.countIceCandidates(peerConnection.localDescription?.sdp ?? '');
    if (initialCandidateCount > 0) {
      logState('initial-candidates-present');
      return peerConnection.localDescription?.sdp ?? '';
    }

    await new Promise<void>((resolve) => {
      let settled = false;
      const cleanup = (): void => {
        peerConnection.removeEventListener('icegatheringstatechange', onGatheringStateChange);
        peerConnection.removeEventListener('icecandidate', onIceCandidate);
        if (timerId !== undefined) {
          clearTimeout(timerId);
        }
      };

      const finish = (label: string): void => {
        if (settled) {
          return;
        }

        settled = true;
        logState(label);
        cleanup();
        resolve();
      };

      const onGatheringStateChange = (): void => {
        logState('icegatheringstatechange');
        if (peerConnection.iceGatheringState === 'complete') {
          finish('complete');
        }
      };

      const onIceCandidate = (): void => {
        const candidateCount = this.countIceCandidates(peerConnection.localDescription?.sdp ?? '');
        console.debug(`[SIP/WebRTC] ${directionLabel} icecandidate event`, {
          iceGatheringState: peerConnection.iceGatheringState,
          candidateCount,
        });

        if (candidateCount > 0) {
          finish('candidate-present');
        }
      };

      peerConnection.addEventListener('icegatheringstatechange', onGatheringStateChange);
      peerConnection.addEventListener('icecandidate', onIceCandidate);

      const timerId = window.setTimeout(() => finish('timeout'), timeoutMs);
    });

    const finalSdp = peerConnection.localDescription?.sdp ?? '';
    console.debug(`[SIP/WebRTC] ${directionLabel} wait complete`, {
      iceGatheringState: peerConnection.iceGatheringState,
      candidateCount: this.countIceCandidates(finalSdp),
    });

    return finalSdp;
  }

  private buildMediaConstraints(): MediaStreamConstraints {
    return {
      audio: this.buildAudioConstraint(),
      video: false,
    };
  }

  private buildMicrophoneConstraints(): MediaStreamConstraints {
    return this.buildMediaConstraints();
  }

  private buildAudioConstraint(): boolean | MediaTrackConstraints {
    const selectedAudioInputDeviceId = this.selectedAudioInputDeviceIdSubject.value?.trim();

    if (selectedAudioInputDeviceId) {
      return {
        deviceId: {
          exact: selectedAudioInputDeviceId,
        },
      };
    }

    return true;
  }

  private async discoverAudioInputDevices(): Promise<SipAudioInputDevice[]> {
    const devices = await navigator.mediaDevices.enumerateDevices();
    let audioInputs = devices.filter((device) => device.kind === 'audioinput');

    if (audioInputs.length === 0) {
      return [];
    }

    const labelsHidden = audioInputs.every((device) => !device.label?.trim());
    if (labelsHidden && navigator.mediaDevices.getUserMedia) {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          audio: true,
          video: false,
        });
        stream.getTracks().forEach((track) => track.stop());
        const refreshed = await navigator.mediaDevices.enumerateDevices();
        audioInputs = refreshed.filter((device) => device.kind === 'audioinput');
      } catch (error) {
        const message = error instanceof Error ? error.message : String(error);

        if (/notfound|overconstrained/i.test(message)) {
          throw new Error('The selected microphone is not available.');
        }

        if (/denied|permission/i.test(message)) {
          throw new Error('Microphone permission was denied. Allow microphone access and refresh devices.');
        }
      }
    }

    const normalizedDevices = audioInputs.map((device, index) => {
      const isDefault = device.deviceId === 'default' || index === 0;
      const label = device.label?.trim() || (isDefault ? 'Default microphone' : `Microphone ${index + 1}`);

      return {
        device_id: device.deviceId,
        label,
        is_default: isDefault,
      };
    });

    console.debug('[SIP/WebRTC] audio input devices discovered', {
      availableAudioInputCount: normalizedDevices.length,
      devices: normalizedDevices.map((device) => ({
        label: device.label,
        deviceId: this.maskDeviceId(device.device_id),
        isDefault: device.is_default,
      })),
    });

    return normalizedDevices;
  }

  private maskDeviceId(deviceId: string): string {
    if (!deviceId) {
      return 'unknown';
    }

    return deviceId.length <= 6 ? deviceId : `...${deviceId.slice(-6)}`;
  }

  private toSipError(response: unknown): Error {
    if (response && typeof response === 'object') {
      const message = (response as { message?: { reasonPhrase?: string; statusCode?: number } }).message;
      const reasonPhrase = message?.reasonPhrase?.trim();
      const statusCode = message?.statusCode;

      if (reasonPhrase || statusCode) {
        return new Error([statusCode, reasonPhrase].filter(Boolean).join(' ').trim());
      }
    }

    return new Error('Call failed.');
  }
}
