import { NgModule } from '@angular/core';
import { SharedModule } from '../../shared/shared.module';
import { NotificationsRoutingModule } from './notifications-routing.module';
import { NotificationsHomeComponent } from './pages/notifications-home/notifications-home.component';

@NgModule({
  declarations: [NotificationsHomeComponent],
  imports: [SharedModule, NotificationsRoutingModule],
})
export class NotificationsModule {}

