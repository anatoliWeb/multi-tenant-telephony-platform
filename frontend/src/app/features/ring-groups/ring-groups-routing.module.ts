import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PermissionGuard } from '../../rbac/guards/permission.guard';
import { TenantGuard } from '../../core/guards/tenant.guard';
import { RingGroupsShellComponent } from './pages/ring-groups-shell/ring-groups-shell.component';

const routes: Routes = [{
  path: '',
  component: RingGroupsShellComponent,
  canActivate: [TenantGuard, PermissionGuard],
  data: {
    permissions: ['ring_groups.view'],
  },
}];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class RingGroupsRoutingModule {}
