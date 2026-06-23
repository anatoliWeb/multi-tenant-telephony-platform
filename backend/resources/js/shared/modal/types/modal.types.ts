import type { Component } from 'vue';

export type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | 'fullscreen';

export interface ModalAction {
  label: string;
  kind?: 'primary' | 'secondary' | 'danger';
  disabled?: boolean;
  closeOnClick?: boolean;
  onClick?: (ctx: { close: () => void; id: string }) => void | Promise<void>;
}

export interface ModalOptions {
  component: Component;
  props?: Record<string, unknown>;
  title?: string;
  subtitle?: string;
  size?: ModalSize;
  loading?: boolean;
  closeOnBackdrop?: boolean;
  closeOnEsc?: boolean;
  stickyHeader?: boolean;
  stickyFooter?: boolean;
  actions?: ModalAction[];
}

export interface ModalItem extends ModalOptions {
  id: string;
}
