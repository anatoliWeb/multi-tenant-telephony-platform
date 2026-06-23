import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-loading-state',
  templateUrl: './loading-state.component.html',
  styleUrls: ['./loading-state.component.scss'],
  standalone: false,
})
export class LoadingStateComponent {
  @Input() visible = false;
  @Input() message = '';
}
