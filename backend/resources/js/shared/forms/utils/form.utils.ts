export const formUtils = {
  cloneModel<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T;
  },

  isEqual(left: unknown, right: unknown): boolean {
    return JSON.stringify(left) === JSON.stringify(right);
  },
};
