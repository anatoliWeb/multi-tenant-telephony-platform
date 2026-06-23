import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-widget-header',
  templateUrl: './widget-header.component.html',
  styleUrls: ['./widget-header.component.scss'],
  standalone: false,
})
export class WidgetHeaderComponent {
  @Input({ required: true }) title!: string;
  @Input() subtitle = '';
}
