import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-loading-overlay',
  templateUrl: './loading-overlay.component.html',
  styleUrls: ['./loading-overlay.component.scss'],
  standalone: false,
})
export class LoadingOverlayComponent {
  @Input() visible = false;
  @Input() messageKey = 'common.states.loading';
}
