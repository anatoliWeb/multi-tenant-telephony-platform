import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-modal-placeholder',
  templateUrl: './modal-placeholder.component.html',
  styleUrls: ['./modal-placeholder.component.scss'],
  standalone: false,
})
export class ModalPlaceholderComponent {
  @Input() title = '';
  @Input() description = '';
}
