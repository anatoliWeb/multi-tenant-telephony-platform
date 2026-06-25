import { NgModule } from '@angular/core';
import { SharedModule } from '../../shared/shared.module';
import { ContactsRoutingModule } from './contacts-routing.module';

@NgModule({
  imports: [SharedModule, ContactsRoutingModule],
})
export class ContactsModule {}
