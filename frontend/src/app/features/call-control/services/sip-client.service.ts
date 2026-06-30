import { Injectable } from '@angular/core';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import { Inviter, Registerer, Session, SessionState, UserAgent } from 'sip.js';
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
  private readonly destinationSubject = new BehaviorSubject<string>('');
  private readonly errorSubject = new BehaviorSubject<string | null>(null);

  private userAgent: UserAgent | null = null;
  private registerer: Registerer | null = null;
  private activeSession: Session | null = null;
  private remoteAudioElement: HTMLAudioElement | null = null;

  readonly profile$ = this.profileSubject.asObservable();
  readonly callState$ = this.callStateSubject.asObservable();
  readonly registrationState$ = this.registrationStateSubject.asObservable();
  readonly microphonePermission$ = this.microphonePermissionSubject.asObservable();
  readonly muted$ = this.mutedSubject.asObservable();
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
    this.destinationSubject.next('');

    try {
      const response = await firstValueFrom(this.callControlApi.getSipProfile(extensionId));
      const profile = response.data ?? null;

      if (!profile) {
        throw new Error('SIP profile unavailable.');
      }

      this.profileSubject.next(profile);
      this.callStateSubject.next('ready');
    } catch (error) {
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

    if (!profile.registration.enabled) {
      this.registrationStateSubject.next('failed');
      this.callStateSubject.next('registration_failed');
      this.errorSubject.next(profile.registration.reason ?? 'Registration is not enabled for this softphone foundation.');
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
      await this.registerer?.register();
      this.registrationStateSubject.next('registered');
      this.callStateSubject.next('registered');
    } catch (error) {
      this.registrationStateSubject.next('failed');
      this.callStateSubject.next('registration_failed');
      this.errorSubject.next(this.toErrorMessage(error, 'Registration failed.'));
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

    const target = UserAgent.makeURI(
      normalizedDestination.includes('@') ? normalizedDestination : `sip:${normalizedDestination}@${profile.domain}`,
    );

    if (!target) {
      this.callStateSubject.next('failed');
      this.errorSubject.next('Destination is not a valid SIP target.');
      return;
    }

    this.errorSubject.next(null);
    this.callStateSubject.next('dialing');

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
      await inviter.invite();
    } catch (error) {
      this.callStateSubject.next('failed');
      this.errorSubject.next(this.toErrorMessage(error, 'Call failed.'));
      this.destroySession();
    }
  }

  async hangup(): Promise<void> {
    if (!this.activeSession) {
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

  setDestination(destination: string): void {
    this.destinationSubject.next(destination);
  }

  /**
   * Tenant switches must tear down the SIP client so stale audio streams and
   * cached user-agent state do not leak across tenant boundaries.
   */
  resetForTenantChange(): void {
    this.destroyTransport();
    this.profileSubject.next(null);
    this.callStateSubject.next('idle');
    this.registrationStateSubject.next('disconnected');
    this.microphonePermissionSubject.next('unknown');
    this.mutedSubject.next(false);
    this.destinationSubject.next('');
    this.errorSubject.next(null);
  }

  private async ensureTransport(profile: SipProfile): Promise<void> {
    if (this.userAgent && this.registerer) {
      return;
    }

    const uri = UserAgent.makeURI(profile.sip_uri);

    if (!uri) {
      throw new Error('SIP URI is invalid.');
    }

    const options = {
      uri,
      displayName: profile.display_name,
      authorizationUsername: profile.authorization_username,
      authorizationPassword: profile.authorization_password ? String(profile.authorization_password) : undefined,
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

    this.userAgent = new UserAgent(options);
    this.registerer = new Registerer(this.userAgent);
    await this.userAgent.start();
  }

  private attachSessionHandlers(session: Session): void {
    session.stateChange.addListener((state) => {
      if (state === SessionState.Established) {
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
      void this.registerer.unregister().catch(() => undefined);
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

  private destroySession(): void {
    if (typeof MediaStream !== 'undefined' && this.remoteAudioElement?.srcObject instanceof MediaStream) {
      this.remoteAudioElement.srcObject.getTracks().forEach((track) => track.stop());
    }

    if (this.remoteAudioElement) {
      this.remoteAudioElement.srcObject = null;
    }

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
