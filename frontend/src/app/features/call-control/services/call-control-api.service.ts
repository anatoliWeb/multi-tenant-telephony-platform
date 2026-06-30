import { Injectable } from '@angular/core';
import { ApiClientService } from '../../../api/services/api-client.service';
import type { SipProfile } from '../models/call-control.model';

@Injectable({ providedIn: 'root' })
export class CallControlApiService {
  constructor(private readonly apiClient: ApiClientService) {}

  getSipProfile(extensionId: number) {
    return this.apiClient.get<SipProfile>(`/v1/extensions/${extensionId}/sip-profile`);
  }
}
