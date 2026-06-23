import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { GuestGuard } from '../../core/guards/guest.guard';
import { LoginPageComponent } from './pages/login/login-page.component';

const routes: Routes = [
  {
    path: '',
    canActivate: [GuestGuard],
    component: LoginPageComponent,
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class AuthRoutingModule {}

