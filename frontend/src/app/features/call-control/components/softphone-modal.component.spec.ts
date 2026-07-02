import { BehaviorSubject } from 'rxjs';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { vi } from 'vitest';
import { SoftphoneModalComponent } from './softphone-modal.component';
import { SipClientService } from '../services/sip-client.service';
import { ExtensionsStateService } from '../../extensions/services/extensions-state.service';
import { TenantContextService } from '../../../core/services/tenant-context.service';
import type { SipBrowserDiagnostics } from '../models/call-control.model';

describe('SoftphoneModalComponent', () => {
  let fixture: ComponentFixture<SoftphoneModalComponent>;
  let component: SoftphoneModalComponent;

  const actionLabels = (): string[] =>
    fixture.debugElement.queryAll(By.css('.softphone__actions button'))
      .map((button) => (button.nativeElement.textContent ?? '').trim());

  const extensions$ = new BehaviorSubject([
    {
      id: 42,
      number: '2001',
      label: 'Primary Desk',
      status: 'active',
    },
    {
      id: 43,
      number: '2002',
      label: 'Backup Desk',
      status: 'active',
    },
  ]);

  const tenantContextMock = {
    hasTenant: vi.fn(() => true),
    activeTenant$: new BehaviorSubject({ id: 'tenant-a', name: 'Tenant A' }),
  };

  const extensionsStateMock = {
    extensions$,
    init: vi.fn().mockResolvedValue(undefined),
  };

  let destinationValue = '';
  let hasLocalAudioTrackValue = false;

  const sipClientMock = {
    profile$: new BehaviorSubject<any>({
      extension_id: 42,
      extension_number: '2001',
      display_name: 'Primary Desk',
      sip_uri: 'sip:2001@localhost',
      authorization_username: '2001',
      password: 'change_me_local_demo_only',
      websocket_url: 'wss://localhost:7443',
      domain: 'localhost',
      provider: 'freeswitch',
      expires_seconds: 300,
      credentials_available: true,
      registration_enabled: true,
      local_demo_mode: true,
      registration: {
        enabled: true,
        state: 'available',
        reason: 'Local demo SIP credentials are enabled for this development environment.',
      },
      capabilities: {
        outbound_call: true,
        inbound_call: false,
        hold: false,
        mute: true,
      },
      tenant_id: 'tenant-a',
    }),
    callState$: new BehaviorSubject('ready'),
    registrationState$: new BehaviorSubject('disconnected'),
    microphonePermission$: new BehaviorSubject('unknown'),
    muted$: new BehaviorSubject(false),
    incomingCall$: new BehaviorSubject(false),
    error$: new BehaviorSubject<string | null>(null),
    mediaDiagnostics$: new BehaviorSubject({
      remote_audio_attached: false,
      remote_audio_track_count: 0,
      remote_audio_playing: false,
      peer_connection_state: 'unknown',
      ice_connection_state: 'unknown',
      last_media_error: null,
    }),
    browserDiagnostics$: new BehaviorSubject<SipBrowserDiagnostics>({
      browser_name: 'Chrome',
      is_opera: false,
      has_media_devices: true,
      has_get_user_media: true,
      has_peer_connection: true,
      audio_autoplay_supported: true,
      warning_message: null,
    }),
    canRegister: vi.fn(() => {
      const profile = sipClientMock.profile$.value;
      return Boolean(
        profile?.registration_enabled
          && profile.credentials_available
          && profile.password
          && sipClientMock.registrationState$.value !== 'connecting'
          && sipClientMock.registrationState$.value !== 'registered',
      );
    }),
    canPlaceCall: vi.fn(() => {
      const profile = sipClientMock.profile$.value;
      return Boolean(
        profile?.capabilities?.outbound_call
          && sipClientMock.registrationState$.value === 'registered'
          && destinationValue.trim()
          && !['dialing', 'ringing', 'active'].includes(sipClientMock.callState$.value as string),
      );
    }),
    canAnswerIncomingCall: vi.fn(() => sipClientMock.incomingCall$.value && sipClientMock.callState$.value === 'ringing'),
    canRejectIncomingCall: vi.fn(() => sipClientMock.incomingCall$.value && sipClientMock.callState$.value === 'ringing'),
    canHangup: vi.fn(() => ['dialing', 'ringing', 'active'].includes(sipClientMock.callState$.value as string)),
    canToggleMute: vi.fn(() => Boolean(
      sipClientMock.profile$.value?.capabilities?.mute
        && sipClientMock.callState$.value === 'active'
        && hasLocalAudioTrackValue,
    )),
    loadProfile: vi.fn().mockResolvedValue(undefined),
    bindRemoteAudio: vi.fn(),
    resetForTenantChange: vi.fn(),
    register: vi.fn().mockResolvedValue(undefined),
    checkMicrophonePermission: vi.fn().mockResolvedValue(undefined),
    call: vi.fn().mockResolvedValue(undefined),
    hangup: vi.fn().mockResolvedValue(undefined),
    answerIncomingCall: vi.fn().mockResolvedValue(undefined),
    rejectIncomingCall: vi.fn().mockResolvedValue(undefined),
    toggleMute: vi.fn(),
    setDestination: vi.fn((value: string) => {
      destinationValue = value;
    }),
  };

  const defaultBrowserDiagnostics = {
    browser_name: 'Chrome',
    is_opera: false,
    has_media_devices: true,
    has_get_user_media: true,
    has_peer_connection: true,
    audio_autoplay_supported: true,
    warning_message: null,
  };

  beforeEach(async () => {
    destinationValue = '';
    hasLocalAudioTrackValue = false;
    sipClientMock.browserDiagnostics$.next(defaultBrowserDiagnostics);
    sipClientMock.error$.next(null);
    await TestBed.configureTestingModule({
      imports: [SoftphoneModalComponent],
      providers: [
        { provide: SipClientService, useValue: sipClientMock },
        { provide: ExtensionsStateService, useValue: extensionsStateMock },
        { provide: TenantContextService, useValue: tenantContextMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(SoftphoneModalComponent);
    component = fixture.componentInstance;
  });

  it('loads a default extension profile when opened', async () => {
    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    expect(extensionsStateMock.init).toHaveBeenCalled();
    expect(sipClientMock.loadProfile).toHaveBeenCalledWith(42);
    expect(component.selectedExtensionId).toBe(42);
    expect(fixture.nativeElement.textContent).toContain('2001');
    expect(fixture.nativeElement.textContent).toContain('1001/1002');
    expect(fixture.nativeElement.textContent).not.toContain('callControl.title');
  });

  it('keeps the remote audio element unmuted and shows media diagnostics', async () => {
    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    const audioElement = fixture.nativeElement.querySelector('audio') as HTMLAudioElement;
    expect(audioElement.muted).toBe(false);
    expect(audioElement.autoplay).toBe(true);
    expect(audioElement.controls).toBe(true);
    expect(fixture.nativeElement.textContent).toContain('Media diagnostics');
  });

  it('shows a browser warning when support is questionable', async () => {
    sipClientMock.browserDiagnostics$.next({
      browser_name: 'Opera',
      is_opera: true,
      has_media_devices: true,
      has_get_user_media: true,
      has_peer_connection: true,
      audio_autoplay_supported: false,
      warning_message: 'Opera is not a primary supported browser for the local softphone. Chrome or Edge is recommended for reliable local demo calling.',
    });

    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Browser compatibility');
    expect(fixture.nativeElement.textContent).toContain('Opera is not a primary supported browser for the local softphone.');
  });

  it('keeps the supported browser path unaffected', async () => {
    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Chrome and Edge are the primary supported browsers for the local demo.');
    expect(fixture.nativeElement.textContent).not.toContain('Opera is not a primary supported browser for the local softphone.');
  });

  it('loads the selected extension when the picker changes', async () => {
    component.open = true;
    await component.prepareProfile();
    await component.onExtensionChange('43');
    fixture.detectChanges();

    expect(component.selectedExtensionId).toBe(43);
    expect(sipClientMock.loadProfile).toHaveBeenCalledWith(43);
  });

  it('disables register and call actions when credentials are unavailable', async () => {
    sipClientMock.profile$.next({
      extension_id: 42,
      extension_number: '2001',
      display_name: 'Primary Desk',
      sip_uri: 'sip:2001@localhost',
      authorization_username: '2001',
      password: null,
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
    });

    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    const buttons = actionLabels();
    expect(buttons).not.toContain('Register');
    expect(buttons).not.toContain('Call');
  });

  it('keeps the call action hidden before registration', async () => {
    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    expect(actionLabels()).not.toContain('Call');
  });

  it('shows register and call actions only when state allows them', async () => {
    sipClientMock.registrationState$.next('registered');
    sipClientMock.profile$.next({
      ...sipClientMock.profile$.value,
      registration_enabled: true,
      credentials_available: true,
      password: 'change_me_local_demo_only',
    });
    component.open = true;
    await component.prepareProfile();
    await component.onDestinationChange('1002');
    fixture.detectChanges();

    expect(component.canRegister()).toBe(false);
    expect(component.canPlaceCall()).toBe(true);
    expect(actionLabels()).toContain('Call');
  });

  it('shows hangup and mute controls only during an active call', async () => {
    sipClientMock.registrationState$.next('registered');
    sipClientMock.callState$.next('active');
    hasLocalAudioTrackValue = true;
    sipClientMock.profile$.next({
      ...sipClientMock.profile$.value,
      registration_enabled: true,
      credentials_available: true,
      password: 'change_me_local_demo_only',
    });

    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    expect(component.canHangup()).toBe(true);
    expect(component.canToggleMute()).toBe(true);
    expect(actionLabels()).toContain('Hang up');
    expect(actionLabels().some((label) => label === 'Mute' || label === 'Unmute')).toBe(true);
  });

  it('shows answer and reject actions when an incoming call is pending', async () => {
    sipClientMock.incomingCall$.next(true);
    sipClientMock.callState$.next('ringing');

    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    const buttons = fixture.debugElement.queryAll(By.css('.softphone__actions button'));
    expect(buttons.some((button) => button.nativeElement.textContent.includes('Answer'))).toBe(true);
    expect(buttons.some((button) => button.nativeElement.textContent.includes('Reject'))).toBe(true);
  });

  it('cleans up state when closed', () => {
    component.requestClose();

    expect(sipClientMock.resetForTenantChange).toHaveBeenCalled();
  });
});
