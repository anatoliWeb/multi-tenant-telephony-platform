import { api } from '../services/api/client';
import { extractData, extractMessage, extractValidationErrors } from '../services/api/normalizers';

/**
 * API composable facade.
 *
 * WHY:
 * Views should consume concise helpers that expose normalized data/message/error
 * behavior, rather than repeating envelope parsing logic.
 */
export const useApi = () => {
  return {
    api,
    extractData,
    extractMessage,
    extractValidationErrors,
  };
};
