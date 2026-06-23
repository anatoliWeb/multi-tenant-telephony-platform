import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { NotificationsHomeComponent } from './pages/notifications-home/notifications-home.component';
import { PermissionGuard } from '../../rbac/guards/permission.guard';

const routes: Routes = [{
  path: '',
  component: NotificationsHomeComponent,
  canActivate: [PermissionGuard],
  data: {
    permissions: ['notifications.view'],
  },
}];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class NotificationsRoutingModule {}
