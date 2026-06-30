import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CallQueuesRoutingModule } from './call-queues-routing.module';
import { CallQueuesShellComponent } from './pages/call-queues-shell/call-queues-shell.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, CallQueuesRoutingModule, CallQueuesShellComponent],
})
export class CallQueuesModule {}
