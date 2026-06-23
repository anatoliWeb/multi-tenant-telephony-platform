import type { Component } from 'vue';

export type FloatingPanelSize = 'sm' | 'md' | 'lg';
export type FloatingPanelPlacement =
  | 'bottom-start'
  | 'bottom-end'
  | 'top-start'
  | 'top-end'
  | 'right-start'
  | 'left-start';

export interface FloatingPanelPosition {
  x: number;
  y: number;
}

export interface FloatingPanelOptions {
  component: Component;
  props?: Record<string, unknown>;
  title?: string;
  subtitle?: string;
  size?: FloatingPanelSize;
  loading?: boolean;
  empty?: boolean;
  emptyText?: string;
  showHeader?: boolean;
  showFooter?: boolean;
  closable?: boolean;
  persistent?: boolean;
  trigger?: HTMLElement | null;
  placement?: FloatingPanelPlacement;
  offset?: number;
  position?: FloatingPanelPosition;
}

export interface FloatingPanelItem extends FloatingPanelOptions {
  id: string;
}
