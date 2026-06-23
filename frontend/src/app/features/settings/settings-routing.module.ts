import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SettingsHomeComponent } from './pages/settings-home/settings-home.component';
import { PermissionGuard } from '../../rbac/guards/permission.guard';

const routes: Routes = [{
  path: '',
  component: SettingsHomeComponent,
  canActivate: [PermissionGuard],
  data: {
    permission: 'settings.view',
  },
}];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class SettingsRoutingModule {}
