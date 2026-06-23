import type { AuthUser } from '../../core/models/auth-user.model';

export interface SessionAuthPayload {
  token?: string | null;
  user: AuthUser | null;
  permissions: string[];
  roles: string[];
}
