import { Component, EventEmitter, Input, Output } from '@angular/core';

@Component({
  selector: 'app-modal-shell',
  templateUrl: './modal-shell.component.html',
  styleUrls: ['./modal-shell.component.scss'],
  standalone: false,
})
export class ModalShellComponent {
  @Input() open = false;
  @Output() close = new EventEmitter<void>();
}
