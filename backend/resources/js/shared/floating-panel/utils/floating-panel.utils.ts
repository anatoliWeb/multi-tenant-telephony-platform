import type { FloatingPanelItem, FloatingPanelPosition } from '../types/floating-panel.types';

const VIEWPORT_GAP = 10;

/**
 * Floating panels are contextual overlays, not menus and not workflow dialogs.
 * Positioning must stay attached to trigger context while remaining viewport-safe.
 */
export const computeFloatingPanelPosition = (
  panel: FloatingPanelItem,
  panelWidth: number,
  panelHeight: number,
): FloatingPanelPosition => {
  if (panel.position) {
    return {
      x: clamp(panel.position.x, VIEWPORT_GAP, Math.max(window.innerWidth - panelWidth - VIEWPORT_GAP, VIEWPORT_GAP)),
      y: clamp(panel.position.y, VIEWPORT_GAP, Math.max(window.innerHeight - panelHeight - VIEWPORT_GAP, VIEWPORT_GAP)),
    };
  }

  const trigger = panel.trigger;
  if (!trigger) {
    return {
      x: Math.max((window.innerWidth - panelWidth) / 2, VIEWPORT_GAP),
      y: Math.max((window.innerHeight - panelHeight) / 2, VIEWPORT_GAP),
    };
  }

  const rect = trigger.getBoundingClientRect();
  const offset = panel.offset ?? 8;
  const placement = panel.placement ?? 'bottom-end';

  let x = rect.left;
  let y = rect.bottom + offset;

  if (placement.endsWith('end')) {
    x = rect.right - panelWidth;
  }

  if (placement.startsWith('top')) {
    y = rect.top - panelHeight - offset;
  }

  if (placement.startsWith('right')) {
    x = rect.right + offset;
    y = rect.top;
  }

  if (placement.startsWith('left')) {
    x = rect.left - panelWidth - offset;
    y = rect.top;
  }

  const fitsBelow = rect.bottom + offset + panelHeight <= window.innerHeight - VIEWPORT_GAP;
  const fitsAbove = rect.top - offset - panelHeight >= VIEWPORT_GAP;

  if (placement.startsWith('bottom') && !fitsBelow && fitsAbove) {
    y = rect.top - panelHeight - offset;
  }

  if (placement.startsWith('top') && !fitsAbove && fitsBelow) {
    y = rect.bottom + offset;
  }

  return {
    x: clamp(x, VIEWPORT_GAP, Math.max(window.innerWidth - panelWidth - VIEWPORT_GAP, VIEWPORT_GAP)),
    y: clamp(y, VIEWPORT_GAP, Math.max(window.innerHeight - panelHeight - VIEWPORT_GAP, VIEWPORT_GAP)),
  };
};

const clamp = (value: number, min: number, max: number): number => {
  return Math.min(Math.max(value, min), max);
};
