import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TenantGuard } from '../../core/guards/tenant.guard';
import { PermissionGuard } from '../../rbac/guards/permission.guard';
import { ChatShellComponent } from './pages/chat-shell/chat-shell.component';

const routes: Routes = [{
  path: '',
  component: ChatShellComponent,
  canActivate: [TenantGuard, PermissionGuard],
  data: {
    permissions: ['chat.view', 'chat.conversations.view'],
  },
}];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ChatRoutingModule {}
