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
} from 'sip.js';
import { CallControlApiService } from './call-control-api.service';
import type {
  MicrophonePermissionState,
  SipCallState,
  SipProfile,
  SipRegistrationState,
} from '../models/call-control.model';

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

  private userAgent: UserAgent | null = null;
  private registerer: Registerer | null = null;
  private activeSession: Session | null = null;
  private incomingInvitation: Invitation | null = null;
  private remoteAudioElement: HTMLAudioElement | null = null;
  private authorizationPassword: string | null = null;
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

    this.errorSubject.next(null);
    this.callStateSubject.next('dialing');

    // The outgoing Inviter owns the call attempt until it either reaches an
    // established dialog, gets rejected, or is torn down by hangup/reset.
    const inviter = new Inviter(this.userAgent, target, {
      sessionDescriptionHandlerOptions: {
        constraints: {
          audio: true,
          video: false,
        },
      },
    });

    this.activeSession = inviter;
    this.attachSessionHandlers(inviter);

    try {
      await inviter.invite({
        requestDelegate: {
          onTrying: () => {
            this.callStateSubject.next('dialing');
          },
          onProgress: () => {
            this.callStateSubject.next('ringing');
          },
          onReject: () => {
            this.callStateSubject.next('failed');
            this.errorSubject.next('Call failed.');
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
        await this.rejectIncomingCall();
        return;
      }

      this.callStateSubject.next('ended');
      return;
    }

    try {
      if (this.activeSession.state === SessionState.Established && typeof (this.activeSession as Session & { bye?: () => Promise<void> }).bye === 'function') {
        await (this.activeSession as Session & { bye: () => Promise<void> }).bye();
      }
    } finally {
      this.callStateSubject.next('ended');
      this.destroySession();
    }
  }

  toggleMute(): void {
    const muted = !this.mutedSubject.value;
    this.mutedSubject.next(muted);

    if (!this.activeSession) {
      return;
    }

    const senderSet = (this.activeSession as Session & {
      sessionDescriptionHandler?: { peerConnection?: RTCPeerConnection };
    }).sessionDescriptionHandler?.peerConnection?.getSenders?.() ?? [];
    senderSet.forEach((sender) => {
      if (sender.track?.kind === 'audio') {
        sender.track.enabled = !muted;
      }
    });
  }

  bindRemoteAudio(element: HTMLAudioElement | null): void {
    this.remoteAudioElement = element;
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

    const options = {
      uri,
      displayName: profile.display_name,
      authorizationUsername: profile.authorization_username,
      authorizationPassword: this.authorizationPassword,
      transportOptions: {
        server: profile.websocket_url,
      },
      sessionDescriptionHandlerFactoryOptions: {
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
        this.errorSubject.next(this.registrationFailureMessage);
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

    try {
      await this.incomingInvitation.accept({
        sessionDescriptionHandlerOptions: {
          constraints: {
            audio: true,
            video: false,
          },
        },
      });
    } catch (error) {
      this.callStateSubject.next('failed');
      this.errorSubject.next(this.toErrorMessage(error, 'Call failed.'));
      this.destroySession();
    }
  }

  async rejectIncomingCall(): Promise<void> {
    if (!this.incomingInvitation) {
      return;
    }

    try {
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
        if (this.incomingInvitation === session) {
          this.incomingInvitation = null;
          this.incomingCallSubject.next(false);
        }

        this.callStateSubject.next('active');
        this.registrationStateSubject.next('registered');
        this.bindSessionAudio(session);
      }

      if (state === SessionState.Terminated) {
        this.callStateSubject.next('ended');
        this.destroySession();
      }
    });
  }

  private bindSessionAudio(session: Session): void {
    if (!this.remoteAudioElement) {
      return;
    }

    const stream = (session as Session & {
      sessionDescriptionHandler?: { remoteMediaStream?: MediaStream };
    }).sessionDescriptionHandler?.remoteMediaStream;

    if (!stream) {
      return;
    }

    // The remote audio element must be rebound per call so we do not leave a
    // dangling stream reference behind after tenant switches or hangup.
    this.remoteAudioElement.srcObject = stream;
    void this.remoteAudioElement.play().catch(() => undefined);
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
    }
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

    if (destination.includes('@')) {
      return `sip:${destination}`;
    }

    return `sip:${destination}@${domain}`;
  }

  private destroySession(): void {
    if (typeof MediaStream !== 'undefined' && this.remoteAudioElement?.srcObject instanceof MediaStream) {
      // Stop any live media tracks so a tenant switch does not leave the
      // browser capturing microphone or speaker resources in the background.
      this.remoteAudioElement.srcObject.getTracks().forEach((track) => track.stop());
    }

    if (this.remoteAudioElement) {
      this.remoteAudioElement.srcObject = null;
    }

    this.incomingInvitation = null;
    this.incomingCallSubject.next(false);
    this.activeSession = null;
    this.mutedSubject.next(false);
  }

  private toErrorMessage(error: unknown, fallback: string): string {
    if (error instanceof Error && error.message.trim()) {
      return error.message;
    }

    return fallback;
  }
}
