import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PermissionGuard } from '../../rbac/guards/permission.guard';
import { TenantGuard } from '../../core/guards/tenant.guard';
import { IvrShellComponent } from './pages/ivr-shell/ivr-shell.component';

const routes: Routes = [{
  path: '',
  component: IvrShellComponent,
  canActivate: [TenantGuard, PermissionGuard],
  data: {
    permissions: ['ivr.view'],
  },
}];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class IvrRoutingModule {}
