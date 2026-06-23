/**
 * Authentication-related frontend types placeholder.
 *
 * Explicit auth contracts keep auth module stable when backend payloads evolve.
 */
export interface AuthUser {
  id: number;
  name: string;
  email: string;
}

