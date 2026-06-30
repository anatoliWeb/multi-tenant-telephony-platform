import { NgModule } from '@angular/core';
import { SharedModule } from '../../shared/shared.module';
import { RingGroupsRoutingModule } from './ring-groups-routing.module';

@NgModule({
  imports: [SharedModule, RingGroupsRoutingModule],
})
export class RingGroupsModule {}
