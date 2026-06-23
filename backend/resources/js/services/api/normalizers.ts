import type { BackendResponse, ValidationErrors } from '../../types/api.types';
import type { NormalizedApiError } from '../../types/response.types';

/**
 * Response normalization utilities.
 *
 * WHY:
 * Backend envelope parsing must be handled in one place so feature code
 * remains clean and consistent. Components should consume normalized payloads,
 * not raw transport details.
 */
export const extractData = <TData>(response: BackendResponse<TData>): TData | undefined => {
  return response.data;
};

export const extractMessage = (response: BackendResponse<unknown>): string => {
  return response.message ?? '';
};

export const extractValidationErrors = (error: NormalizedApiError): ValidationErrors => {
  if (error.code !== 'validation' || !error.errors || typeof error.errors !== 'object') {
    return {};
  }

  return error.errors as ValidationErrors;
};

