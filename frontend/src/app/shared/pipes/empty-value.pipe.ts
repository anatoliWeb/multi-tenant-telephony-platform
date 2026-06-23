import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
  name: 'emptyValue',
  standalone: false,
})
export class EmptyValuePipe implements PipeTransform {
  transform(value: string | null | undefined, fallback = '—'): string {
    if (!value || value.trim().length === 0) {
      return fallback;
    }

    return value;
  }
}

