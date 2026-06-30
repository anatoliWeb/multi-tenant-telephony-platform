import { CommonModule } from '@angular/common';
import { Component, ElementRef, EventEmitter, Input, OnChanges, OnDestroy, Output, SimpleChanges, ViewChild } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { SharedModule } from '../../../shared/shared.module';
import { TenantContextService } from '../../../core/services/tenant-context.service';
import { ExtensionsStateService } from '../../extensions/services/extensions-state.service';
import { SipClientService } from '../services/sip-client.service';

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

  readonly profile$;
  readonly callState$;
  readonly registrationState$;
  readonly microphonePermission$;
  readonly muted$;
  readonly error$;

  constructor(
    private readonly sipClient: SipClientService,
    public readonly tenantContext: TenantContextService,
    private readonly extensionsState: ExtensionsStateService,
  ) {
    this.profile$ = this.sipClient.profile$;
    this.callState$ = this.sipClient.callState$;
    this.registrationState$ = this.sipClient.registrationState$;
    this.microphonePermission$ = this.sipClient.microphonePermission$;
    this.muted$ = this.sipClient.muted$;
    this.error$ = this.sipClient.error$;
  }

  get profile() {
    return this.sipClient.profile;
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
      const extension = await this.resolveDefaultExtension();

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

  async register(): Promise<void> {
    await this.sipClient.register();
  }

  async checkMicrophonePermission(): Promise<void> {
    await this.sipClient.checkMicrophonePermission();
  }

  async placeCall(): Promise<void> {
    await this.sipClient.call(this.destination);
  }

  async hangup(): Promise<void> {
    await this.sipClient.hangup();
  }

  toggleMute(): void {
    this.sipClient.toggleMute();
  }

  requestClose(): void {
    this.sipClient.resetForTenantChange();
    this.close.emit();
  }

  async onDestinationChange(value: string): Promise<void> {
    this.destination = value;
    this.sipClient.setDestination(value);
  }

  trackExtension(_index: number, extension: { id: number }): number {
    return extension.id;
  }

  private async resolveDefaultExtension(): Promise<{ id: number; number: string; label?: string | null } | null> {
    const extensions = await firstValueFrom(this.extensionsState.extensions$);

    return extensions.find((extension) => extension.status === 'active') ?? extensions[0] ?? null;
  }
}
