import { Injectable } from '@angular/core';
import { CanActivate, Router, UrlTree } from '@angular/router';
import { AuthStateService } from '../services/auth-state.service';

@Injectable()
export class GuestGuard implements CanActivate {
  constructor(
    private readonly authState: AuthStateService,
    private readonly router: Router,
  ) {}

  canActivate(): boolean | UrlTree {
    if (!this.authState.isAuthenticated) {
      return true;
    }

    return this.router.createUrlTree(['/dashboard']);
  }
}

