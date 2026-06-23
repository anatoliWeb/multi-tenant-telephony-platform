import { Component, EventEmitter, Input, Output } from '@angular/core';

@Component({
  selector: 'app-search-input',
  templateUrl: './search-input.component.html',
  styleUrls: ['./search-input.component.scss'],
  standalone: false,
})
export class SearchInputComponent {
  @Input() labelKey = 'common.labels.search';
  @Input() placeholderKey = 'common.labels.search';
  @Input() value = '';
  @Output() valueChange = new EventEmitter<string>();
}
