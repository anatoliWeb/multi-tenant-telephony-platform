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
  Web,
} from 'sip.js';
import { CallControlApiService } from './call-control-api.service';
import type {
  SipBrowserDiagnostics,
  MicrophonePermissionState,
  SipMediaDiagnostics,
  SipCallState,
  SipProfile,
  SipRegistrationState,
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
  private readonly microphonePermissionSubject = new BehaviorSubject<MicrophonePermissionState>('unknown');
  private readonly mutedSubject = new BehaviorSubject<boolean>(false);
  private readonly incomingCallSubject = new BehaviorSubject<boolean>(false);
  private readonly destinationSubject = new BehaviorSubject<string>('');
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
  private authorizationPassword: string | null = null;
  private readonly iceGatheringTimeoutMs = 5000;
  private readonly registrationFailureMessage =
    'SIP registration failed. Check local FreeSWITCH WebSocket/TLS configuration.';

  readonly profile$ = this.profileSubject.asObservable();
  readonly callState$ = this.callStateSubject.asObservable();
  readonly registrationState$ = this.registrationStateSubject.asObservable();
  readonly microphonePermission$ = this.microphonePermissionSubject.asObservable();
  readonly muted$ = this.mutedSubject.asObservable();
  readonly incomingCall$ = this.incomingCallSubject.asObservable();
  readonly destination$ = this.destinationSubject.asObservable();
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

  get microphonePermission(): MicrophonePermissionState {
    return this.microphonePermissionSubject.value;
  }

  get muted(): boolean {
    return this.mutedSubject.value;
  }

  get destination(): string {
    return this.destinationSubject.value;
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
        && this.registrationStateSubject.value !== 'registered',
    );
  }

  canPlaceCall(): boolean {
    const profile = this.profileSubject.value;
    const destination = this.destinationSubject.value.trim();

    return Boolean(
      profile?.capabilities?.outbound_call
        && this.registrationStateSubject.value === 'registered'
        && destination
        && !this.isCallInProgress(),
    );
  }

  canAnswerIncomingCall(): boolean {
    return this.incomingCallSubject.value && this.callStateSubject.value === 'ringing';
  }

  canRejectIncomingCall(): boolean {
    return this.canAnswerIncomingCall();
  }

  canHangup(): boolean {
    return ['dialing', 'ringing', 'active'].includes(this.callStateSubject.value);
  }

  canToggleMute(): boolean {
    const profile = this.profileSubject.value;

    return Boolean(
      profile?.capabilities?.mute
        && this.callStateSubject.value === 'active'
        && this.hasLocalAudioSenderTrack(),
    );
  }

  /**
   * SIP credentials stay in memory only. The browser should never persist
   * them in storage or global app state because the softphone is tenant-scoped
   * and must disappear cleanly on tenant change or logout.
   */
  async loadProfile(extensionId: number): Promise<void> {
    this.destroyTransport();
    this.errorSubject.next(null);
    this.callStateSubject.next('checking_permissions');
    this.registrationStateSubject.next('disconnected');
    this.microphonePermissionSubject.next('unknown');
    this.mutedSubject.next(false);
    this.incomingCallSubject.next(false);
    this.destinationSubject.next('');
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
      this.callStateSubject.next('ready');
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

      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      stream.getTracks().forEach((track) => track.stop());
      this.microphonePermissionSubject.next('granted');
    } catch {
      this.microphonePermissionSubject.next('denied');
      this.errorSubject.next('Microphone permission was denied. Allow microphone access in the browser and try again.');
    }
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

    this.registrationStateSubject.next('connecting');
    this.callStateSubject.next('registering');
    this.errorSubject.next(null);

    try {
      await this.ensureTransport(profile);
      await this.registerer?.register({
        requestDelegate: {
          onReject: () => {
            this.registrationStateSubject.next('failed');
            this.callStateSubject.next('registration_failed');
            this.errorSubject.next(this.registrationFailureMessage);
            this.clearCredentials();
          },
        },
      });
      this.registrationStateSubject.next('registered');
      this.callStateSubject.next('registered');
    } catch (error) {
      this.registrationStateSubject.next('failed');
      this.callStateSubject.next('registration_failed');
      this.errorSubject.next(this.toErrorMessage(error, this.registrationFailureMessage));
      this.clearCredentials();
      this.destroyTransport();
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

    if (this.registrationStateSubject.value !== 'registered') {
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
    console.debug('[SIP/WebRTC] caller preparing outgoing INVITE', {
      destination: normalizedDestination,
      target: target.toString(),
      iceGatheringTimeoutMs: this.iceGatheringTimeoutMs,
    });

    // The outgoing Inviter owns the call attempt until it either reaches an
    // established dialog, gets rejected, or is torn down by hangup/reset.
    const inviter = new Inviter(this.userAgent, target, {
      sessionDescriptionHandlerOptions: {
        constraints: {
          audio: true,
          video: false,
        },
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
          constraints: {
            audio: true,
            video: false,
          },
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

  /**
   * Tenant switches must tear down the SIP client so stale audio streams and
   * cached user-agent state do not leak across tenant boundaries.
   */
  resetForTenantChange(): void {
    this.destroyTransport();
    this.clearCredentials();
    this.profileSubject.next(null);
    this.callStateSubject.next('idle');
    this.registrationStateSubject.next('disconnected');
    this.microphonePermissionSubject.next('unknown');
    this.mutedSubject.next(false);
    this.destinationSubject.next('');
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
        constraints: {
          audio: true,
          video: false,
        },
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
      onDisconnect: (error) => {
        if (!error) {
          return;
        }

        // A browser transport error usually means local WSS/TLS is not trusted
        // yet, not that the SIP credentials themselves are wrong.
        this.registrationStateSubject.next('failed');
        this.callStateSubject.next('failed');
        this.errorSubject.next(this.toErrorMessage(error, this.registrationFailureMessage, this.profileSubject.value));
      },
    };
  }

  private handleRegistererStateChange(state: RegistererState): void {
    if (state === RegistererState.Registered) {
      this.registrationStateSubject.next('registered');
      return;
    }

    if (state === RegistererState.Unregistered || state === RegistererState.Terminated) {
      if (this.registrationStateSubject.value !== 'failed') {
        this.registrationStateSubject.next('disconnected');
      }
    }
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
          constraints: {
            audio: true,
            video: false,
          },
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

  private destroyTransport(): void {
    this.destroySession();

    if (this.registerer) {
      void this.registerer.dispose().catch(() => undefined);
      this.registerer = null;
    }

    if (this.userAgent) {
      void this.userAgent.stop().catch(() => undefined);
      this.userAgent = null;
    }

    if (this.remoteAudioElement) {
      this.remoteAudioElement.srcObject = null;
      this.prepareRemoteAudioElement(this.remoteAudioElement);
    }

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
    return ['dialing', 'ringing', 'active'].includes(this.callStateSubject.value);
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
