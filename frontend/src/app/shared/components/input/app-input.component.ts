import { Component, EventEmitter, Input, Output } from '@angular/core';

@Component({
  selector: 'app-input',
  templateUrl: './app-input.component.html',
  styleUrls: ['./app-input.component.scss'],
  standalone: false,
})
export class AppInputComponent {
  @Input() value = '';
  @Input() label = '';
  @Input() placeholder = '';
  @Input() type: 'text' | 'email' | 'password' = 'text';
  @Input() disabled = false;
  @Output() valueChange = new EventEmitter<string>();

  emitValue(event: Event): void {
    this.valueChange.emit((event.target as HTMLInputElement).value);
  }
}
