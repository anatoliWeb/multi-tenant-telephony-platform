import { Component } from '@angular/core';
import { AuthStateService } from '../../../../core/services/auth-state.service';

@Component({
  selector: 'app-profile-home',
  templateUrl: './profile-home.component.html',
  styleUrls: ['./profile-home.component.scss'],
  standalone: false,
})
export class ProfileHomeComponent {
  constructor(public readonly authState: AuthStateService) {}
}
