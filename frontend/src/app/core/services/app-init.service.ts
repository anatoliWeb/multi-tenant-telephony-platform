import { Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AuthRuntimeService } from '../../auth/services/auth-runtime.service';
import { LocaleService } from '../../i18n/services/locale.service';
import { RuntimeTranslationService } from '../../i18n/services/runtime-translation.service';
import { SettingsPreloadService } from '../../settings/services/settings-preload.service';
import { AppLoadingService } from './app-loading.service';

@Injectable({ providedIn: 'root' })
export class AppInitService {
  private initialized = false;
  private initPromise: Promise<void> | null = null;

  constructor(
    private readonly authRuntime: AuthRuntimeService,
    private readonly localeService: LocaleService,
    private readonly translationService: RuntimeTranslationService,
    private readonly settingsPreload: SettingsPreloadService,
    private readonly appLoading: AppLoadingService,
  ) {}

  async initialize(): Promise<void> {
    if (this.initialized) {
      return;
    }
    if (this.initPromise) {
      return this.initPromise;
    }

    const locale = this.localeService.currentLocale;
    this.appLoading.show('common.bootstrap.initializing', 'bootstrap');
    this.initPromise = Promise.all([
      firstValueFrom(this.translationService.preload(locale)),
      firstValueFrom(this.settingsPreload.preload()),
      this.authRuntime.hydrateAuth(),
    ]).then(() => {
      this.initialized = true;
    }).finally(() => {
      this.initPromise = null;
      this.appLoading.hide();
    });

    return this.initPromise;
  }
}
