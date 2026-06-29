import { Injectable } from '@angular/core';
import { CanActivate, Router, UrlTree } from '@angular/router';
import { TenantContextService } from '../services/tenant-context.service';

@Injectable({ providedIn: 'root' })
export class TenantGuard implements CanActivate {
  constructor(
    private readonly tenantContext: TenantContextService,
    private readonly router: Router,
  ) {}

  canActivate(): boolean | UrlTree {
    if (this.tenantContext.hasTenant()) {
      return true;
    }

    return this.router.createUrlTree(['/dashboard']);
  }
}
