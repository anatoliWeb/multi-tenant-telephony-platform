import { Injectable } from '@angular/core';
import { of } from 'rxjs';
import type { DashboardWidgetSnapshot } from '../models/dashboard.model';

@Injectable({ providedIn: 'root' })
export class DashboardService {
  listWidgets() {
    return of<DashboardWidgetSnapshot[]>([
      { id: 'status-api', title: 'API status', value: 'Ready' },
      { id: 'status-queue', title: 'Queue status', value: 'Ready' },
    ]);
  }
}

