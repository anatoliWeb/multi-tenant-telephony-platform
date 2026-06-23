import { NgModule } from '@angular/core';
import { SharedModule } from '../../shared/shared.module';
import { SettingsRoutingModule } from './settings-routing.module';
import { SettingsHomeComponent } from './pages/settings-home/settings-home.component';
import { SettingsUpsertModalComponent } from './components/settings-upsert-modal.component';

@NgModule({
  declarations: [
    SettingsHomeComponent,
    SettingsUpsertModalComponent,
  ],
  imports: [SharedModule, SettingsRoutingModule],
})
export class SettingsModule {}
