import type { CommandPaletteItem } from '../types/command-palette.types';

export const normalizeQuery = (value: string): string => value.trim().toLowerCase();

export const matchCommandItem = (item: CommandPaletteItem, query: string): boolean => {
  if (!query) return true;

  const haystack = [
    item.title,
    item.subtitle ?? '',
    item.group ?? '',
    ...(item.keywords ?? []),
  ]
    .join(' ')
    .toLowerCase();

  return haystack.includes(query);
};

export const groupItems = (items: CommandPaletteItem[]): Record<string, CommandPaletteItem[]> => {
  return items.reduce<Record<string, CommandPaletteItem[]>>((acc, item) => {
    const group = item.group ?? 'General';
    if (!acc[group]) {
      acc[group] = [];
    }
    acc[group].push(item);
    return acc;
  }, {});
};

export const isEditableTarget = (target: EventTarget | null): boolean => {
  const element = target as HTMLElement | null;
  if (!element) return false;

  if (element.closest('[data-command-palette]')) return false;

  const tag = element.tagName.toLowerCase();
  return tag === 'input' || tag === 'textarea' || element.isContentEditable;
};
