import { ChangeDetectorRef, OnDestroy, Pipe, PipeTransform } from '@angular/core';
import { Subscription } from 'rxjs';
import { LocaleService } from '../../i18n/services/locale.service';
import { RuntimeTranslationService } from '../../i18n/services/runtime-translation.service';
import { TranslationFacadeService } from '../../i18n/services/translation-facade.service';

@Pipe({
  name: 't',
  pure: false,
  standalone: false,
})
export class TPipe implements PipeTransform, OnDestroy {
  private readonly localeSub: Subscription;
  private readonly runtimeSub: Subscription;

  constructor(
    private readonly translations: TranslationFacadeService,
    private readonly localeService: LocaleService,
    private readonly runtimeTranslations: RuntimeTranslationService,
    private readonly cdr: ChangeDetectorRef,
  ) {
    // Keep pipe reactive to runtime locale switches.
    this.localeSub = this.localeService.locale$.subscribe(() => this.cdr.markForCheck());
    this.runtimeSub = this.runtimeTranslations.revision$.subscribe(() => this.cdr.markForCheck());
  }

  transform(key: string, fallback?: string): string {
    return this.translations.t(key, fallback);
  }

  ngOnDestroy(): void {
    this.localeSub.unsubscribe();
    this.runtimeSub.unsubscribe();
  }
}
