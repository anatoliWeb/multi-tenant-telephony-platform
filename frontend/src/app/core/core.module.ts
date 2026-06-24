import { APP_INITIALIZER, NgModule } from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { AppInitService } from './services/app-init.service';
import { authSessionInterceptor } from './interceptors/auth-session.interceptor';
import { tenantContextInterceptor } from './interceptors/tenant-context.interceptor';
import { localeInterceptor } from './interceptors/locale.interceptor';
import { errorInterceptor } from './interceptors/error.interceptor';
import { AuthGuard } from './guards/auth.guard';
import { GuestGuard } from './guards/guest.guard';
import { PermissionGuard } from '../rbac/guards/permission.guard';

const initializeApp = (appInit: AppInitService) => {
  return () => appInit.initialize();
};

@NgModule({
  providers: [
    AuthGuard,
    GuestGuard,
    PermissionGuard,
    {
      provide: APP_INITIALIZER,
      useFactory: initializeApp,
      deps: [AppInitService],
      multi: true,
    },
    provideHttpClient(
      withInterceptors([
        authSessionInterceptor,
        tenantContextInterceptor,
        localeInterceptor,
        errorInterceptor,
      ]),
    ),
  ],
})
export class CoreModule {}
