import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PermissionGuard } from '../../rbac/guards/permission.guard';
import { TenantGuard } from '../../core/guards/tenant.guard';
import { CallQueuesShellComponent } from './pages/call-queues-shell/call-queues-shell.component';

const routes: Routes = [{
  path: '',
  component: CallQueuesShellComponent,
  canActivate: [TenantGuard, PermissionGuard],
  data: {
    permissions: ['call_queues.view'],
  },
}];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class CallQueuesRoutingModule {}
