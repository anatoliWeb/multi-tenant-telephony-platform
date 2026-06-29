import { NgModule } from '@angular/core';
import { SharedModule } from '../../shared/shared.module';
import { CallLogsRoutingModule } from './call-logs-routing.module';

@NgModule({
  imports: [SharedModule, CallLogsRoutingModule],
})
export class CallLogsModule {}
