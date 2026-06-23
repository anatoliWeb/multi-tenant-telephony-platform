import { InjectionToken } from '@angular/core';
import { environment } from '../../../environments/environment';

export type AppEnvironment = typeof environment;

export const APP_CONFIG = new InjectionToken<AppEnvironment>('APP_CONFIG', {
  providedIn: 'root',
  factory: () => environment,
});

