import { CommonModule } from '@angular/common';
import { NgModule } from '@angular/core';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { AppButtonComponent } from './components/button/app-button.component';
import { AppInputComponent } from './components/input/app-input.component';
import { LoadingStateComponent } from './components/loading-state/loading-state.component';
import { UiCardComponent } from './components/card/ui-card.component';
import { TablePlaceholderComponent } from './components/table-placeholder/table-placeholder.component';
import { ModalPlaceholderComponent } from './components/modal-placeholder/modal-placeholder.component';
import { FiltersBarComponent } from './components/filters-bar/filters-bar.component';
import { SearchInputComponent } from './components/search-input/search-input.component';
import { SelectFilterComponent } from './components/select-filter/select-filter.component';
import { PaginationComponent } from './components/pagination/pagination.component';
import { EmptyStateComponent } from './components/empty-state/empty-state.component';
import { DataTableComponent } from './components/data-table/data-table.component';
import { ModalShellComponent } from './components/modal-shell/modal-shell.component';
import { LoadingOverlayComponent } from './components/loading-overlay/loading-overlay.component';
import { LoadingSpinnerComponent } from './components/loading-spinner/loading-spinner.component';
import { SkeletonCardComponent } from './components/skeleton-card/skeleton-card.component';
import { SkeletonTableComponent } from './components/skeleton-table/skeleton-table.component';
import { EmptyValuePipe } from './pipes/empty-value.pipe';
import { TPipe } from './pipes/t.pipe';

@NgModule({
  declarations: [
    AppButtonComponent,
    AppInputComponent,
    LoadingStateComponent,
    UiCardComponent,
    TablePlaceholderComponent,
    ModalPlaceholderComponent,
    FiltersBarComponent,
    SearchInputComponent,
    SelectFilterComponent,
    PaginationComponent,
    EmptyStateComponent,
    DataTableComponent,
    ModalShellComponent,
    LoadingOverlayComponent,
    LoadingSpinnerComponent,
    SkeletonCardComponent,
    SkeletonTableComponent,
    EmptyValuePipe,
    TPipe,
  ],
  imports: [CommonModule, FormsModule, ReactiveFormsModule],
  exports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    AppButtonComponent,
    AppInputComponent,
    LoadingStateComponent,
    UiCardComponent,
    TablePlaceholderComponent,
    ModalPlaceholderComponent,
    FiltersBarComponent,
    SearchInputComponent,
    SelectFilterComponent,
    PaginationComponent,
    EmptyStateComponent,
    DataTableComponent,
    ModalShellComponent,
    LoadingOverlayComponent,
    LoadingSpinnerComponent,
    SkeletonCardComponent,
    SkeletonTableComponent,
    EmptyValuePipe,
    TPipe,
  ],
})
export class SharedModule {}
