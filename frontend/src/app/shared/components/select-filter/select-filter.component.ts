import { Component, EventEmitter, Input, Output } from '@angular/core';

export interface SelectFilterOption {
  value: string;
  labelKey?: string;
  label?: string;
}

@Component({
  selector: 'app-select-filter',
  templateUrl: './select-filter.component.html',
  styleUrls: ['./select-filter.component.scss'],
  standalone: false,
})
export class SelectFilterComponent {
  @Input() labelKey = '';
  @Input() value = '';
  @Input() allLabelKey = 'common.filters.all';
  @Input() options: SelectFilterOption[] = [];
  @Output() valueChange = new EventEmitter<string>();

  onChange(event: Event): void {
    this.valueChange.emit((event.target as HTMLSelectElement).value);
  }
}
