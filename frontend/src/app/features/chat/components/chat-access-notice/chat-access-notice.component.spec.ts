import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ChatAccessNoticeComponent } from './chat-access-notice.component';

describe('ChatAccessNoticeComponent', () => {
  let fixture: ComponentFixture<ChatAccessNoticeComponent>;
  let component: ChatAccessNoticeComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ChatAccessNoticeComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(ChatAccessNoticeComponent);
    component = fixture.componentInstance;
  });

  it('read_only notice renders', () => {
    component.conversation = { id: 1, current_user_access: { user_id: 1, access_state: 'read_only' } };
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Read-only conversation');
  });

  it('blocked show_notice renders', () => {
    component.conversation = {
      id: 1,
      current_user_access: { user_id: 1, access_state: 'blocked', block_display_mode: 'show_notice' },
    };
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('You cannot access this conversation');
  });

  it('show_read_only_history notice renders', () => {
    component.conversation = {
      id: 1,
      current_user_access: { user_id: 1, access_state: 'blocked', block_display_mode: 'show_read_only_history' },
    };
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('You can only view previous message history');
  });

  it('hidden state notice renders', () => {
    component.conversation = {
      id: 1,
      current_user_access: { user_id: 1, access_state: 'hidden' },
    };
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('This conversation is not available');
  });

  it('blocked reason and raw metadata are not rendered', () => {
    component.conversation = {
      id: 1,
      current_user_access: {
        user_id: 1,
        access_state: 'blocked',
        block_display_mode: 'show_notice',
      } as any,
      description: 'meta',
    };
    fixture.detectChanges();
    const content = fixture.nativeElement.textContent as string;
    expect(content).not.toContain('blocked_reason');
    expect(content).not.toContain('metadata');
  });
});
