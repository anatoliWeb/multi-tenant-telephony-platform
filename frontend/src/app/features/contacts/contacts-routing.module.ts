import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PermissionGuard } from '../../rbac/guards/permission.guard';
import { ContactsShellComponent } from './pages/contacts-shell/contacts-shell.component';

const routes: Routes = [{
  path: '',
  component: ContactsShellComponent,
  canActivate: [PermissionGuard],
  data: {
    permissions: ['contacts.view'],
  },
}];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ContactsRoutingModule {}
