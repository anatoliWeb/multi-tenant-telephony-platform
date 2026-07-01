import { BehaviorSubject } from 'rxjs';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { vi } from 'vitest';
import { SoftphoneModalComponent } from './softphone-modal.component';
import { SipClientService } from '../services/sip-client.service';
import { ExtensionsStateService } from '../../extensions/services/extensions-state.service';
import { TenantContextService } from '../../../core/services/tenant-context.service';

describe('SoftphoneModalComponent', () => {
  let fixture: ComponentFixture<SoftphoneModalComponent>;
  let component: SoftphoneModalComponent;

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
    setDestination: vi.fn(),
  };

  beforeEach(async () => {
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
    expect(fixture.nativeElement.textContent).toContain('2001 and 2002');
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

    const buttons = fixture.debugElement.queryAll(By.css('.softphone__actions button'));
    expect(buttons[1].nativeElement.disabled).toBe(true);
    expect(buttons[2].nativeElement.disabled).toBe(true);
  });

  it('keeps the call action disabled before registration', async () => {
    component.open = true;
    await component.prepareProfile();
    fixture.detectChanges();

    const callButton = () => fixture.debugElement.queryAll(By.css('.softphone__actions button'))[2].nativeElement as HTMLButtonElement;
    expect(callButton().disabled).toBe(true);
  });

  it('shows answer and reject actions when an incoming call is pending', async () => {
    sipClientMock.incomingCall$.next(true);

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
