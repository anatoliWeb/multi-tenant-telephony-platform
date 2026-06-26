import { NgModule } from '@angular/core';
import { SharedModule } from '../../shared/shared.module';
import { PhoneNumbersRoutingModule } from './phone-numbers-routing.module';

@NgModule({
  imports: [SharedModule, PhoneNumbersRoutingModule],
})
export class PhoneNumbersModule {}
