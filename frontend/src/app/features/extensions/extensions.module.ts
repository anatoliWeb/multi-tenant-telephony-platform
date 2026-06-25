import { NgModule } from '@angular/core';
import { SharedModule } from '../../shared/shared.module';
import { ExtensionsRoutingModule } from './extensions-routing.module';

@NgModule({
  imports: [SharedModule, ExtensionsRoutingModule],
})
export class ExtensionsModule {}
