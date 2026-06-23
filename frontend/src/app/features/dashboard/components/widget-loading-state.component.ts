import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-widget-loading-state',
  templateUrl: './widget-loading-state.component.html',
  styleUrls: ['./widget-loading-state.component.scss'],
  standalone: false,
})
export class WidgetLoadingStateComponent {
  @Input() label = '';
}
