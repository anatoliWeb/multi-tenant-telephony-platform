import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SettingsHomeComponent } from './pages/settings-home/settings-home.component';
import { TenantGuard } from '../../core/guards/tenant.guard';
import { PermissionGuard } from '../../rbac/guards/permission.guard';

const routes: Routes = [{
  path: '',
  component: SettingsHomeComponent,
  canActivate: [TenantGuard, PermissionGuard],
  data: {
    permission: 'settings.view',
  },
}];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class SettingsRoutingModule {}
