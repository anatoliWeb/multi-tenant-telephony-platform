import { NgModule } from '@angular/core';
import { ChatRoutingModule } from './chat-routing.module';
import { ChatShellComponent } from './pages/chat-shell/chat-shell.component';

@NgModule({
  imports: [ChatRoutingModule, ChatShellComponent],
})
export class ChatModule {}
