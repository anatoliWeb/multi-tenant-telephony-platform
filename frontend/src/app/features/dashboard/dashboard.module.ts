import { NgModule } from '@angular/core';
import { SharedModule } from '../../shared/shared.module';
import { DashboardHomeComponent } from './pages/dashboard-home/dashboard-home.component';
import { DashboardRoutingModule } from './dashboard-routing.module';
import { DashboardWidgetCardComponent } from './components/dashboard-widget-card.component';
import { WidgetHeaderComponent } from './components/widget-header.component';
import { WidgetLoadingStateComponent } from './components/widget-loading-state.component';

@NgModule({
  declarations: [
    DashboardHomeComponent,
    DashboardWidgetCardComponent,
    WidgetHeaderComponent,
    WidgetLoadingStateComponent,
  ],
  imports: [SharedModule, DashboardRoutingModule],
})
export class DashboardModule {}
