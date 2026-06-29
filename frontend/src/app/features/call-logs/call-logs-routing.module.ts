import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TenantGuard } from '../../core/guards/tenant.guard';
import { PermissionGuard } from '../../rbac/guards/permission.guard';

const routes: Routes = [
  {
    path: '',
    loadComponent: () => import('./pages/call-logs-shell/call-logs-shell.component').then((m) => m.CallLogsShellComponent),
    canActivate: [TenantGuard, PermissionGuard],
    data: { permission: 'call_logs.view' },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class CallLogsRoutingModule {}
