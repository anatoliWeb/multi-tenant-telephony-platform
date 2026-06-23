import { Component, EventEmitter, Input, Output } from '@angular/core';

@Component({
  selector: 'app-pagination',
  templateUrl: './pagination.component.html',
  styleUrls: ['./pagination.component.scss'],
  standalone: false,
})
export class PaginationComponent {
  @Input() currentPage = 1;
  @Input() lastPage = 1;
  @Input() perPage = 15;
  @Input() total = 0;
  @Output() pageChange = new EventEmitter<number>();

  get startRow(): number {
    return this.total === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1;
  }

  get endRow(): number {
    return Math.min(this.currentPage * this.perPage, this.total);
  }

  go(page: number): void {
    this.pageChange.emit(page);
  }
}
