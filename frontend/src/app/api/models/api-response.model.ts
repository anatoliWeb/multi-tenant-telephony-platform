export interface ApiResponse<TData = unknown, TErrors = unknown> {
  success: boolean;
  message: string;
  data?: TData;
  errors?: TErrors;
  meta?: Record<string, unknown>;
}

export interface NormalizedApiError {
  status: number;
  code: 'unauthorized' | 'forbidden' | 'validation' | 'server' | 'network' | 'unknown';
  message: string;
  errors: unknown;
}

