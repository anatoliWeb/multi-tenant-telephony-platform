import { Component } from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthRuntimeService } from '../../../../auth/services/auth-runtime.service';
import { TranslationFacadeService } from '../../../../i18n/services/translation-facade.service';

@Component({
  selector: 'app-login-page',
  templateUrl: './login-page.component.html',
  styleUrls: ['./login-page.component.scss'],
  standalone: false,
})
export class LoginPageComponent {
  isLoading = false;
  errorMessage = '';
  readonly form;

  constructor(
    private readonly fb: FormBuilder,
    private readonly router: Router,
    private readonly route: ActivatedRoute,
    private readonly authRuntime: AuthRuntimeService,
    private readonly t: TranslationFacadeService,
  ) {
    this.form = this.fb.nonNullable.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required]],
      remember: [false],
    });
  }

  submit(): void {
    if (this.form.invalid || this.isLoading) {
      this.form.markAllAsTouched();
      return;
    }

    this.isLoading = true;
    this.errorMessage = '';

    const value = this.form.getRawValue();
    this.authRuntime
      .login({
        email: value.email,
        password: value.password,
        remember: value.remember,
      })
      .then(() => {
        const redirectPath = this.route.snapshot.queryParamMap.get('redirect');
        const safeRedirect = redirectPath && redirectPath.startsWith('/') ? redirectPath : '/dashboard';
        void this.router.navigateByUrl(safeRedirect);
      })
      .catch(() => {
        this.errorMessage = this.t.t('auth.login.invalidCredentials');
      })
      .finally(() => {
        this.isLoading = false;
      });
  }
}
