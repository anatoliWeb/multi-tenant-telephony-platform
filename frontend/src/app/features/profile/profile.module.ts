import { NgModule } from '@angular/core';
import { SharedModule } from '../../shared/shared.module';
import { ProfileRoutingModule } from './profile-routing.module';
import { ProfileHomeComponent } from './pages/profile-home/profile-home.component';

@NgModule({
  declarations: [ProfileHomeComponent],
  imports: [SharedModule, ProfileRoutingModule],
})
export class ProfileModule {}

