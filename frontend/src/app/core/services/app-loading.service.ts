import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

export type GlobalLoadingType = 'bootstrap' | 'locale' | 'page' | 'submit' | 'background';

export interface GlobalLoadingState {
  active: boolean;
  messageKey: string;
  type: GlobalLoadingType;
}

@Injectable({ providedIn: 'root' })
export class AppLoadingService {
  private pendingCount = 0;
  private readonly stateSubject = new BehaviorSubject<GlobalLoadingState>({
    active: false,
    messageKey: 'common.states.loading',
    type: 'background',
  });

  readonly state$ = this.stateSubject.asObservable();

  show(messageKey: string, type: GlobalLoadingType = 'background'): void {
    this.pendingCount += 1;
    this.stateSubject.next({ active: true, messageKey, type });
  }

  hide(): void {
    this.pendingCount = Math.max(0, this.pendingCount - 1);
    if (this.pendingCount === 0) {
      this.stateSubject.next({ active: false, messageKey: 'common.states.loading', type: 'background' });
    }
  }
}
