import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from './core/guards/auth.guard';
import { DashboardShellComponent } from './layout/components/dashboard-shell/dashboard-shell.component';

const routes: Routes = [
  {
    path: 'login',
    loadChildren: () => import('./features/auth/auth.module').then((m) => m.AuthModule),
  },
  {
    path: '',
    component: DashboardShellComponent,
    canActivate: [AuthGuard],
    children: [
      {
        path: '',
        pathMatch: 'full',
        redirectTo: 'dashboard',
      },
      {
        path: 'dashboard',
        loadChildren: () => import('./features/dashboard/dashboard.module').then((m) => m.DashboardModule),
      },
      {
        path: 'profile',
        loadChildren: () => import('./features/profile/profile.module').then((m) => m.ProfileModule),
      },
      {
        path: 'settings',
        loadChildren: () => import('./features/settings/settings.module').then((m) => m.SettingsModule),
      },
      {
        path: 'notifications',
        loadChildren: () => import('./features/notifications/notifications.module').then((m) => m.NotificationsModule),
      },
      {
        path: 'chat',
        loadChildren: () => import('./features/chat/chat.module').then((m) => m.ChatModule),
      },
      {
        path: 'contacts',
        loadChildren: () => import('./features/contacts/contacts.module').then((m) => m.ContactsModule),
      },
    ],
  },
  {
    path: '**',
    redirectTo: 'dashboard',
  },
];

@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule],
})
export class AppRoutingModule {}
