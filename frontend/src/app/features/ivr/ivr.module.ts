import { NgModule } from '@angular/core';
import { SharedModule } from '../../shared/shared.module';
import { IvrRoutingModule } from './ivr-routing.module';

@NgModule({
  imports: [SharedModule, IvrRoutingModule],
})
export class IvrModule {}
