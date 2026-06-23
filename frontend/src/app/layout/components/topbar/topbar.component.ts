import { Component, EventEmitter, Input, Output } from '@angular/core';

@Component({
  selector: 'app-topbar',
  templateUrl: './topbar.component.html',
  styleUrls: ['./topbar.component.scss'],
  standalone: false,
})
export class TopbarComponent {
  @Input() currentLocale = 'en';
  @Input() userName = '';
  @Input() locales: readonly string[] = ['en', 'uk', 'de'];
  @Output() localeChange = new EventEmitter<string>();
  @Output() logout = new EventEmitter<void>();

  onLocaleChange(event: Event): void {
    this.localeChange.emit((event.target as HTMLSelectElement).value);
  }
}
