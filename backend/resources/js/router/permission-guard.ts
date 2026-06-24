import type { Router } from 'vue-router';

type RouteLike = {
  path: string
  fullPath: string
  matched: Array<{ meta?: Record<string, unknown> }>
};

type GuardResult = true | false | { path: string; query?: Record<string, string> };

type AuthGuardLike = {
  hydrateSession: () => Promise<boolean>
  hasPlatformPermission: (permission: string) => boolean
  hasAnyPlatformPermission: (permissions: string[]) => boolean
};

export const evaluateAdminRouteAccess = async (to: RouteLike, auth: AuthGuardLike): Promise<GuardResult> => {
  const requiresAuth = to.matched.some((record) => Boolean(record.meta?.requiresAuth));
  const guestOnly = to.matched.some((record) => Boolean(record.meta?.guestOnly));
  const requiredPermission = to.matched
    .map((record) => record.meta?.permission)
    .find((permission) => typeof permission === 'string') as string | undefined;
  const requiredPermissions = to.matched
    .flatMap((record) => {
      const permissionsMeta = record.meta?.permissions;

      return Array.isArray(permissionsMeta)
        ? permissionsMeta.filter((permission): permission is string => typeof permission === 'string')
        : [];
    });

  const hasSession = await auth.hydrateSession();

  if (requiresAuth && !hasSession) {
    return { path: '/login', query: { redirect: to.fullPath } };
  }

  if (guestOnly && hasSession) {
    return { path: '/dashboard' };
  }

  if (requiresAuth && hasSession) {
    const isAllowedBySinglePermission = requiredPermission
      ? auth.hasPlatformPermission(requiredPermission)
      : true;
    const isAllowedByAnyPermission = requiredPermissions.length > 0
      ? auth.hasAnyPlatformPermission(requiredPermissions)
      : true;

    if (!isAllowedBySinglePermission || !isAllowedByAnyPermission) {
      return to.path === '/dashboard'
        ? false
        : { path: '/dashboard' };
    }
  }

  return true;
};

export const registerAdminPermissionGuard = (
  router: Pick<Router, 'beforeEach'>,
  auth: AuthGuardLike,
): void => {
  router.beforeEach(async (to) => evaluateAdminRouteAccess(to as RouteLike, auth));
};
