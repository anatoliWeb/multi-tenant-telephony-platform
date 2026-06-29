import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TenantGuard } from '../../core/guards/tenant.guard';
import { PermissionGuard } from '../../rbac/guards/permission.guard';
import { PhoneNumbersShellComponent } from './pages/phone-numbers-shell/phone-numbers-shell.component';

const routes: Routes = [{
  path: '',
  component: PhoneNumbersShellComponent,
  canActivate: [TenantGuard, PermissionGuard],
  data: {
    permissions: ['phone_numbers.view'],
  },
}];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class PhoneNumbersRoutingModule {}
