import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TenantGuard } from '../../core/guards/tenant.guard';
import { PermissionGuard } from '../../rbac/guards/permission.guard';
import { ExtensionsShellComponent } from './pages/extensions-shell/extensions-shell.component';

const routes: Routes = [{
  path: '',
  component: ExtensionsShellComponent,
  canActivate: [TenantGuard, PermissionGuard],
  data: {
    permissions: ['extensions.view'],
  },
}];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ExtensionsRoutingModule {}
