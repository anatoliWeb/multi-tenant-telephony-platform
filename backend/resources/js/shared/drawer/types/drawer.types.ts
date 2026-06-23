import type { Component } from 'vue';

export type DrawerSize = 'sm' | 'md' | 'lg' | 'xl' | 'fullscreen';
export type DrawerPosition = 'right' | 'left' | 'bottom';

export interface DrawerAction {
  label: string;
  kind?: 'primary' | 'secondary' | 'danger';
  disabled?: boolean;
  closeOnClick?: boolean;
  onClick?: (ctx: { close: () => void; id: string }) => void | Promise<void>;
}

export interface DrawerOptions {
  component: Component;
  props?: Record<string, unknown>;
  title?: string;
  subtitle?: string;
  size?: DrawerSize;
  position?: DrawerPosition;
  loading?: boolean;
  closeOnBackdrop?: boolean;
  closeOnEsc?: boolean;
  stickyHeader?: boolean;
  stickyFooter?: boolean;
  actions?: DrawerAction[];
}

export interface DrawerItem extends DrawerOptions {
  id: string;
}
