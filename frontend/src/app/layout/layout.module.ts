import { NgModule } from '@angular/core';
import { RouterModule } from '@angular/router';
import { SharedModule } from '../shared/shared.module';
import { ContentWrapperComponent } from './components/content-wrapper/content-wrapper.component';
import { DashboardShellComponent } from './components/dashboard-shell/dashboard-shell.component';
import { FooterComponent } from './components/footer/footer.component';
import { NotificationHostComponent } from './components/notification-host/notification-host.component';
import { SidebarComponent } from './components/sidebar/sidebar.component';
import { TopbarComponent } from './components/topbar/topbar.component';

@NgModule({
  declarations: [
    DashboardShellComponent,
    SidebarComponent,
    TopbarComponent,
    ContentWrapperComponent,
    FooterComponent,
    NotificationHostComponent,
  ],
  imports: [RouterModule, SharedModule],
  exports: [DashboardShellComponent],
})
export class LayoutModule {}
