import { CommonModule } from '@angular/common';
import { Component, ElementRef, EventEmitter, Input, OnChanges, OnDestroy, Output, SimpleChanges, ViewChild } from '@angular/core';
import { firstValueFrom, Observable } from 'rxjs';
import { SharedModule } from '../../../shared/shared.module';
import { TenantContextService } from '../../../core/services/tenant-context.service';
import { ExtensionsStateService } from '../../extensions/services/extensions-state.service';
import { SipClientService } from '../services/sip-client.service';
import type { SipAudioInputDevice, SipBrowserDiagnostics, SipMediaDiagnostics } from '../models/call-control.model';

@Component({
  selector: 'app-softphone-modal',
  templateUrl: './softphone-modal.component.html',
  styleUrls: ['./softphone-modal.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule],
})
export class SoftphoneModalComponent implements OnChanges, OnDestroy {
  @Input() open = false;
  @Output() close = new EventEmitter<void>();
  @ViewChild('remoteAudio')
  set remoteAudioRef(element: ElementRef<HTMLAudioElement> | undefined) {
    this.sipClient.bindRemoteAudio(element?.nativeElement ?? null);
  }

  destination = '';
  selectedExtensionId: number | null = null;
  loadingExtension = false;
  readonly dtmfKeypadRows = [
    ['1', '2', '3'],
    ['4', '5', '6'],
    ['7', '8', '9'],
    ['*', '0', '#'],
  ];

  readonly extensions$;
  readonly profile$;
  readonly callState$;
  readonly registrationState$;
  readonly transportState$;
  readonly microphonePermission$;
  readonly audioInputDevices$;
  readonly selectedAudioInputDeviceId$;
  readonly audioInputDevicesLoading$;
  readonly audioInputDevicesError$;
  readonly muted$;
  readonly incomingCall$;
  readonly error$;
  readonly mediaDiagnostics$: Observable<SipMediaDiagnostics>;
  readonly browserDiagnostics$: Observable<SipBrowserDiagnostics>;

  constructor(
    private readonly sipClient: SipClientService,
    public readonly tenantContext: TenantContextService,
    private readonly extensionsState: ExtensionsStateService,
  ) {
    this.profile$ = this.sipClient.profile$;
    this.callState$ = this.sipClient.callState$;
    this.registrationState$ = this.sipClient.registrationState$;
    this.transportState$ = this.sipClient.transportState$;
    this.microphonePermission$ = this.sipClient.microphonePermission$;
    this.audioInputDevices$ = this.sipClient.audioInputDevices$;
    this.selectedAudioInputDeviceId$ = this.sipClient.selectedAudioInputDeviceId$;
    this.audioInputDevicesLoading$ = this.sipClient.audioInputDevicesLoading$;
    this.audioInputDevicesError$ = this.sipClient.audioInputDevicesError$;
    this.muted$ = this.sipClient.muted$;
    this.incomingCall$ = this.sipClient.incomingCall$;
    this.error$ = this.sipClient.error$;
    this.mediaDiagnostics$ = this.sipClient.mediaDiagnostics$;
    this.browserDiagnostics$ = this.sipClient.browserDiagnostics$;
    this.extensions$ = this.extensionsState.extensions$;
  }

  get profile() {
    return this.sipClient.profile;
  }

  canRegister(): boolean {
    return this.sipClient.canRegister();
  }

  canReconnect(): boolean {
    return this.sipClient.transportState === 'failed' || this.sipClient.transportState === 'disconnected';
  }

  canPlaceCall(): boolean {
    return this.sipClient.canPlaceCall();
  }

  canAnswerIncomingCall(): boolean {
    return this.sipClient.canAnswerIncomingCall();
  }

  canRejectIncomingCall(): boolean {
    return this.sipClient.canRejectIncomingCall();
  }

  canHangup(): boolean {
    return this.sipClient.canHangup();
  }

  canHold(): boolean {
    return this.sipClient.canHold();
  }

  canResume(): boolean {
    return this.sipClient.canResume();
  }

  canToggleMute(): boolean {
    return this.sipClient.canToggleMute();
  }

  canSendDtmf(): boolean {
    return this.sipClient.canSendDtmf();
  }

  canChangeAudioInputDevice(): boolean {
    return this.sipClient.canChangeAudioInputDevice();
  }

  async ngOnChanges(changes: SimpleChanges): Promise<void> {
    if (!changes['open'] || !this.open) {
      return;
    }

    await this.prepareProfile();
  }

  ngOnDestroy(): void {
    this.sipClient.resetForTenantChange();
  }

  async prepareProfile(): Promise<void> {
    if (!this.tenantContext.hasTenant()) {
      this.sipClient.resetForTenantChange();
      return;
    }

    this.loadingExtension = true;

    try {
      await this.extensionsState.init();
      const extension = await this.resolveSelectedExtension();

      if (!extension) {
        this.sipClient.resetForTenantChange();
        return;
      }

      this.selectedExtensionId = extension.id;
      await this.sipClient.loadProfile(extension.id);
      this.sipClient.bindRemoteAudio(this.remoteAudioRef?.nativeElement ?? null);
    } finally {
      this.loadingExtension = false;
    }
  }

  async onExtensionChange(value: string): Promise<void> {
    const extensionId = Number(value);

    if (!Number.isFinite(extensionId) || extensionId <= 0) {
      this.selectedExtensionId = null;
      this.sipClient.resetForTenantChange();
      return;
    }

    this.selectedExtensionId = extensionId;
    await this.sipClient.loadProfile(extensionId);
  }

  async register(): Promise<void> {
    await this.sipClient.register();
  }

  async checkMicrophonePermission(): Promise<void> {
    await this.sipClient.checkMicrophonePermission();
  }

  async refreshAudioInputDevices(): Promise<void> {
    await this.sipClient.refreshAudioInputDevices();
  }

  async placeCall(): Promise<void> {
    await this.sipClient.call(this.destination);
  }

  async hangup(): Promise<void> {
    await this.sipClient.hangup();
  }

  async holdCall(): Promise<void> {
    await this.sipClient.holdCall();
  }

  async resumeCall(): Promise<void> {
    await this.sipClient.resumeCall();
  }

  async answerIncomingCall(): Promise<void> {
    await this.sipClient.answerIncomingCall();
  }

  async rejectIncomingCall(): Promise<void> {
    await this.sipClient.rejectIncomingCall();
  }

  toggleMute(): void {
    this.sipClient.toggleMute();
  }

  async sendDtmf(digit: string): Promise<void> {
    await this.sipClient.sendDtmf(digit);
  }

  requestClose(): void {
    this.sipClient.resetForTenantChange();
    this.close.emit();
  }

  async onDestinationChange(value: string): Promise<void> {
    this.destination = value;
    this.sipClient.setDestination(value);
  }

  async onAudioInputDeviceChange(value: string): Promise<void> {
    if (!value) {
      this.sipClient.setSelectedAudioInputDevice(null);
      return;
    }

    this.sipClient.setSelectedAudioInputDevice(value);
  }

  trackExtension(_index: number, extension: { id: number }): number {
    return extension.id;
  }

  trackAudioInputDevice(_index: number, device: SipAudioInputDevice): string {
    return device.device_id;
  }

  private async resolveSelectedExtension(): Promise<{ id: number; number: string; label?: string | null } | null> {
    const extensions = await firstValueFrom(this.extensionsState.extensions$);

    if (this.selectedExtensionId) {
      const selected = extensions.find((extension) => extension.id === this.selectedExtensionId);
      if (selected) {
        return selected;
      }
    }

    return extensions.find((extension) => extension.status === 'active') ?? extensions[0] ?? null;
  }
}
