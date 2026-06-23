import { Component } from '@angular/core';
import { AppLoadingService } from './core/services/app-loading.service';
import { AuthStateService } from './core/services/auth-state.service';

@Component({
  selector: 'app-root',
  standalone: false,
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss'],
})
export class AppComponent {
  constructor(
    public readonly authState: AuthStateService,
    public readonly appLoading: AppLoadingService,
  ) {}
}
