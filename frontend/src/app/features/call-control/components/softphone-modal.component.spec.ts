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

  const headerActionLabels = (): string[] =>
    fixture.debugElement.queryAll(By.css('.softphone__header-actions button'))
      .map((button) => (button.nativeElement.textContent ?? '').trim());

  const minimizedActionLabels = (): string[] =>
    fixture.debugElement.queryAll(By.css('.softphone__minimized-actions button'))
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
  let locallyHeldValue = false;
  let selectedAudioInputDeviceIdValue: string | null = null;
  let transportStateValue: 'disconnected' | 'connecting' | 'registered' | 'reconnecting' | 'failed' | 'unregistering' = 'disconnected';

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
    transportState$: new BehaviorSubject('disconnected'),
    microphonePermission$: new BehaviorSubject('unknown'),
    muted$: new BehaviorSubject(false),
    incomingCall$: new BehaviorSubject(false),
    error$: new BehaviorSubject<string | null>(null),
    audioInputDevices$: new BehaviorSubject([
      { device_id: 'default', label: 'Default Microphone', is_default: true },
      { device_id: 'usb-mic', label: 'USB Microphone', is_default: false },
    ]),
    selectedAudioInputDeviceId$: new BehaviorSubject<string | null>(null),
    audioInputDevicesLoading$: new BehaviorSubject(false),
    audioInputDevicesError$: new BehaviorSubject<string | null>(null),
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
          && transportStateValue !== 'connecting'
          && transportStateValue !== 'reconnecting'
          && transportStateValue !== 'unregistering'
          && sipClientMock.registrationState$.value !== 'registered',
      );
    }),
    canPlaceCall: vi.fn(() => {
      const profile = sipClientMock.profile$.value;
      return Boolean(
        profile?.capabilities?.outbound_call
          && sipClientMock.registrationState$.value === 'registered'
          && transportStateValue === 'registered'
          && destinationValue.trim()
          && !['dialing', 'ringing', 'active'].includes(sipClientMock.callState$.value as string),
      );
    }),
    canAnswerIncomingCall: vi.fn(() => sipClientMock.incomingCall$.value && sipClientMock.callState$.value === 'ringing'),
    canRejectIncomingCall: vi.fn(() => sipClientMock.incomingCall$.value && sipClientMock.callState$.value === 'ringing'),
    canHangup: vi.fn(() => ['dialing', 'ringing', 'active', 'held'].includes(sipClientMock.callState$.value as string)),
    canHold: vi.fn(() => sipClientMock.callState$.value === 'active' && !locallyHeldValue),
    canResume: vi.fn(() => sipClientMock.callState$.value === 'held' && locallyHeldValue),
    canToggleMute: vi.fn(() => Boolean(
      sipClientMock.profile$.value?.capabilities?.mute
        && sipClientMock.callState$.value === 'active'
        && hasLocalAudioTrackValue,
    )),
    canSendDtmf: vi.fn(() => sipClientMock.callState$.value === 'active'),
    canChangeAudioInputDevice: vi.fn(() => !['dialing', 'ringing', 'active', 'held'].includes(sipClientMock.callState$.value as string)),
    loadProfile: vi.fn().mockResolvedValue(undefined),
    bindRemoteAudio: vi.fn(),
    resetForTenantChange: vi.fn(),
    register: vi.fn().mockResolvedValue(undefined),
    checkMicrophonePermission: vi.fn().mockResolvedValue(undefined),
    refreshAudioInputDevices: vi.fn().mockResolvedValue(undefined),
    transportState: 'disconnected',
    call: vi.fn().mockResolvedValue(undefined),
    hangup: vi.fn().mockResolvedValue(undefined),
    answerIncomingCall: vi.fn().mockResolvedValue(undefined),
    rejectIncomingCall: vi.fn().mockResolvedValue(undefined),
    holdCall: vi.fn().mockImplementation(async () => {
      locallyHeldValue = true;
      sipClientMock.callState$.next('held');
    }),
    resumeCall: vi.fn().mockImplementation(async () => {
      locallyHeldValue = false;
      sipClientMock.callState$.next('active');
    }),
    toggleMute: vi.fn(),
    sendDtmf: vi.fn().mockResolvedValue(undefined),
    setSelectedAudioInputDevice: vi.fn((value: string | null) => {
      selectedAudioInputDeviceIdValue = value;
      sipClientMock.selectedAudioInputDeviceId$.next(value);
    }),
    setDestination: vi.fn((value: string) => {
      destinationValue = value;
    }),
    get callState() {
      return sipClientMock.callState$.value;
    },
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
    locallyHeldValue = false;
    selectedAudioInputDeviceIdValue = null;
    transportStateValue = 'disconnected';
    sipClientMock.browserDiagnostics$.next(defaultBrowserDiagnostics);
    sipClientMock.error$.next(null);
    sipClientMock.audioInputDevicesError$.next(null);
    sipClientMock.audioInputDevicesLoading$.next(false);
    sipClientMock.selectedAudioInputDeviceId$.next(null);
    sipClientMock.transportState$.next('disconnected');
    sipClientMock.transportState = 'disconnected';
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

  it('shows microphone device selection controls with a default fallback', async () => {
    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Microphone devices');
    expect(fixture.nativeElement.textContent).toContain('Default Microphone');
    expect(fixture.nativeElement.textContent).toContain('USB Microphone');
    expect(fixture.nativeElement.textContent).toContain('Refresh devices');
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
    sipClientMock.transportState$.next('registered');
    sipClientMock.transportState = 'registered';
    transportStateValue = 'registered';
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

  it('minimizes the softphone without ending the active call', async () => {
    sipClientMock.registrationState$.next('registered');
    sipClientMock.transportState$.next('registered');
    sipClientMock.transportState = 'registered';
    transportStateValue = 'registered';
    sipClientMock.callState$.next('active');
    hasLocalAudioTrackValue = true;

    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    const minimizeButton = fixture.debugElement.queryAll(By.css('.softphone__header-actions button'))
      .find((button) => (button.nativeElement.textContent ?? '').trim() === 'Minimize');

    expect(minimizeButton).toBeTruthy();
    expect(headerActionLabels()).toContain('Minimize');

    minimizeButton?.nativeElement.click();
    fixture.detectChanges();

    expect(component.isMinimized).toBe(true);
    expect(fixture.nativeElement.textContent).toContain('Minimizing does not end the call. Restore to see the full control panel.');
    expect(fixture.nativeElement.textContent).toContain('active');
    expect(minimizedActionLabels()).toContain('Hang up');
    expect(minimizedActionLabels()).toContain('Mute');
  });

  it('restores the expanded modal from minimized mode', async () => {
    sipClientMock.registrationState$.next('registered');
    sipClientMock.transportState$.next('registered');
    sipClientMock.transportState = 'registered';
    transportStateValue = 'registered';
    sipClientMock.callState$.next('registered');

    component.open = true;
    await component.prepareProfile();
    component.minimize();
    fixture.detectChanges();

    const restoreButton = fixture.debugElement.queryAll(By.css('.softphone__minimized-card button'))
      .find((button) => (button.nativeElement.textContent ?? '').trim() === 'Restore');

    expect(restoreButton).toBeTruthy();
    restoreButton?.nativeElement.click();
    fixture.detectChanges();

    expect(component.isMinimized).toBe(false);
    expect(fixture.nativeElement.textContent).toContain('Media and call controls');
  });

  it('keeps an incoming ringing call visible while minimized', async () => {
    sipClientMock.incomingCall$.next(true);
    sipClientMock.callState$.next('ringing');

    component.open = true;
    await component.prepareProfile();
    component.minimize();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('ringing');
    expect(minimizedActionLabels()).toContain('Answer');
    expect(minimizedActionLabels()).toContain('Reject');
  });

  it('hangs up from minimized mode without altering the call-control cleanup flow', async () => {
    const hangupSpy = vi.spyOn(sipClientMock, 'hangup').mockResolvedValue(undefined);
    sipClientMock.registrationState$.next('registered');
    sipClientMock.transportState$.next('registered');
    sipClientMock.transportState = 'registered';
    transportStateValue = 'registered';
    sipClientMock.callState$.next('active');

    component.open = true;
    await component.prepareProfile();
    component.minimize();
    fixture.detectChanges();

    const hangupButton = fixture.debugElement.queryAll(By.css('.softphone__minimized-actions button'))
      .find((button) => (button.nativeElement.textContent ?? '').trim() === 'Hang up');

    expect(hangupButton).toBeTruthy();
    hangupButton?.nativeElement.click();

    expect(hangupSpy).toHaveBeenCalled();
    hangupSpy.mockRestore();
  });

  it('toggles mute and unmute from minimized mode', async () => {
    const toggleMuteSpy = vi.spyOn(sipClientMock, 'toggleMute');
    sipClientMock.registrationState$.next('registered');
    sipClientMock.transportState$.next('registered');
    sipClientMock.transportState = 'registered';
    transportStateValue = 'registered';
    sipClientMock.callState$.next('active');
    hasLocalAudioTrackValue = true;

    component.open = true;
    await component.prepareProfile();
    component.minimize();
    fixture.detectChanges();

    const muteButton = fixture.debugElement.queryAll(By.css('.softphone__minimized-actions button'))
      .find((button) => ['Mute', 'Unmute'].includes((button.nativeElement.textContent ?? '').trim()));

    expect(muteButton).toBeTruthy();
    muteButton?.nativeElement.click();

    expect(toggleMuteSpy).toHaveBeenCalled();
    toggleMuteSpy.mockRestore();
  });

  it('shows the reconnecting state label in minimized mode', async () => {
    sipClientMock.registrationState$.next('registered');
    sipClientMock.transportState$.next('reconnecting');
    sipClientMock.transportState = 'reconnecting';
    transportStateValue = 'reconnecting';
    sipClientMock.callState$.next('registered');

    component.open = true;
    await component.prepareProfile();
    component.minimize();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('reconnecting');
  });

  it('resets minimized state when cleanup is triggered', async () => {
    component.open = true;
    await component.prepareProfile();
    component.minimize();
    fixture.detectChanges();

    expect(component.isMinimized).toBe(true);

    component.requestClose();

    expect(component.isMinimized).toBe(false);
    expect(sipClientMock.resetForTenantChange).toHaveBeenCalled();
  });

  it('exposes accessibility labels for minimized mode controls', async () => {
    sipClientMock.registrationState$.next('registered');
    sipClientMock.transportState$.next('registered');
    sipClientMock.transportState = 'registered';
    transportStateValue = 'registered';
    sipClientMock.callState$.next('active');
    hasLocalAudioTrackValue = true;

    component.open = true;
    await component.prepareProfile();
    component.minimize();
    fixture.detectChanges();

    const ariaLabels = fixture.debugElement.queryAll(By.css('.softphone__minimized-card button'))
      .map((button) => button.nativeElement.getAttribute('aria-label'));

    expect(ariaLabels).toContain('Restore softphone');
    expect(ariaLabels).toContain('Hang up call');
    expect(ariaLabels).toContain('Mute');
  });

  it('shows reconnecting status and disables register/call actions while transport retries', async () => {
    sipClientMock.registrationState$.next('registered');
    sipClientMock.transportState$.next('reconnecting');
    sipClientMock.transportState = 'reconnecting';
    transportStateValue = 'reconnecting';

    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('SIP transport was disconnected and the softphone is retrying registration automatically.');
    expect(component.canRegister()).toBe(false);
    expect(component.canPlaceCall()).toBe(false);
    expect(actionLabels()).not.toContain('Register');
    expect(actionLabels()).not.toContain('Call');
  });

  it('shows the retry register label after reconnect failure', async () => {
    sipClientMock.registrationState$.next('failed');
    sipClientMock.transportState$.next('failed');
    sipClientMock.transportState = 'failed';
    transportStateValue = 'failed';

    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('SIP reconnect reached the retry limit. Click Register to retry manually.');
    expect(actionLabels()).toContain('Retry register');
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

  it('shows hold and DTMF controls only during an active call', async () => {
    sipClientMock.registrationState$.next('registered');
    sipClientMock.callState$.next('active');
    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    expect(component.canHold()).toBe(true);
    expect(component.canResume()).toBe(false);
    expect(component.canSendDtmf()).toBe(true);

    const labels = actionLabels();
    expect(labels).toContain('Hold');
    expect(labels).not.toContain('Resume');

    const keypadLabels = fixture.debugElement.queryAll(By.css('.softphone__dtmf-key'))
      .map((button) => (button.nativeElement.textContent ?? '').trim());
    expect(keypadLabels).toEqual(['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#']);
  });

  it('disables microphone device switching during an active call', async () => {
    sipClientMock.registrationState$.next('registered');
    sipClientMock.callState$.next('active');

    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    const devicePanelSelect = fixture.debugElement.query(By.css('.softphone__device-panel select'));
    expect(devicePanelSelect.nativeElement.disabled).toBe(true);
    expect(fixture.nativeElement.textContent).toContain('Switching microphones during an active call is not supported yet.');
  });

  it('shows resume only when the call is locally held', async () => {
    sipClientMock.registrationState$.next('registered');
    sipClientMock.callState$.next('held');
    locallyHeldValue = true;

    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    expect(component.canHold()).toBe(false);
    expect(component.canResume()).toBe(true);
    expect(actionLabels()).toContain('Resume');
    expect(actionLabels()).not.toContain('Hold');
  });

  it('supports hold and resume button actions', async () => {
    sipClientMock.registrationState$.next('registered');
    sipClientMock.callState$.next('active');

    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    const holdButton = fixture.debugElement.queryAll(By.css('.softphone__actions button'))
      .find((button) => (button.nativeElement.textContent ?? '').trim() === 'Hold');

    expect(holdButton).toBeTruthy();
    holdButton?.nativeElement.click();
    fixture.detectChanges();

    expect(sipClientMock.holdCall).toHaveBeenCalled();
    expect(component.canResume()).toBe(true);
    expect(actionLabels()).toContain('Resume');

    const resumeButton = fixture.debugElement.queryAll(By.css('.softphone__actions button'))
      .find((button) => (button.nativeElement.textContent ?? '').trim() === 'Resume');

    expect(resumeButton).toBeTruthy();
    resumeButton?.nativeElement.click();
    fixture.detectChanges();

    expect(sipClientMock.resumeCall).toHaveBeenCalled();
    expect(component.canHold()).toBe(true);
  });

  it('supports DTMF keypad send actions during an active call', async () => {
    sipClientMock.registrationState$.next('registered');
    sipClientMock.callState$.next('active');

    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    const keypadButtons = fixture.debugElement.queryAll(By.css('.softphone__dtmf-key'));
    expect(keypadButtons.length).toBe(12);

    keypadButtons[0].nativeElement.click();
    keypadButtons[11].nativeElement.click();

    expect(sipClientMock.sendDtmf).toHaveBeenCalledWith('1');
    expect(sipClientMock.sendDtmf).toHaveBeenCalledWith('#');
  });

  it('refreshes microphone devices and updates the selected device through the dropdown', async () => {
    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    const refreshButton = fixture.debugElement.queryAll(By.css('.softphone__device-panel button'))
      .find((button) => (button.nativeElement.textContent ?? '').trim() === 'Refresh devices');
    refreshButton?.nativeElement.click();

    const selectElement = fixture.debugElement.query(By.css('.softphone__device-panel select'));
    selectElement.nativeElement.value = 'usb-mic';
    selectElement.nativeElement.dispatchEvent(new Event('change'));
    fixture.detectChanges();

    expect(sipClientMock.refreshAudioInputDevices).toHaveBeenCalled();
    expect(sipClientMock.setSelectedAudioInputDevice).toHaveBeenCalledWith('usb-mic');
    expect(selectedAudioInputDeviceIdValue).toBe('usb-mic');
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
