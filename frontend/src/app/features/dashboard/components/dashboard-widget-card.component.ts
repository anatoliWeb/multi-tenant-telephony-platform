import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-dashboard-widget-card',
  templateUrl: './dashboard-widget-card.component.html',
  styleUrls: ['./dashboard-widget-card.component.scss'],
  standalone: false,
})
export class DashboardWidgetCardComponent {
  @Input() loading = false;
}
