import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-table-placeholder',
  templateUrl: './table-placeholder.component.html',
  styleUrls: ['./table-placeholder.component.scss'],
  standalone: false,
})
export class TablePlaceholderComponent {
  @Input() title = '';
  @Input() subtitle = '';
}
