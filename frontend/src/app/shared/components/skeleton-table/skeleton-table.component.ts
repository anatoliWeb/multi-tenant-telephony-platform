import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-skeleton-table',
  templateUrl: './skeleton-table.component.html',
  styleUrls: ['./skeleton-table.component.scss'],
  standalone: false,
})
export class SkeletonTableComponent {
  @Input() rows = 5;
}
