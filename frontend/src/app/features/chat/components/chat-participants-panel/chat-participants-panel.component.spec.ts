import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ChatParticipantsPanelComponent } from './chat-participants-panel.component';

describe('ChatParticipantsPanelComponent', () => {
  let fixture: ComponentFixture<ChatParticipantsPanelComponent>;
  let component: ChatParticipantsPanelComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ChatParticipantsPanelComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(ChatParticipantsPanelComponent);
    component = fixture.componentInstance;
  });

  it('panel renders participant names', () => {
    component.participants = [
      { user_id: 10, name: 'Alice', role: 'owner', status: 'active', access_state: 'full' },
      { user_id: 11, user: { name: 'Bob' }, role: 'member', status: 'invited', access_state: 'read_only' },
    ];
    component.onlineUsers = [{ id: 10, name: 'Alice', role: 'owner', device_type: 'browser' }];
    fixture.detectChanges();

    const items = fixture.nativeElement.querySelectorAll('[data-testid="participant-item"]');
    expect(items.length).toBe(2);
    expect(fixture.nativeElement.textContent).toContain('Alice');
    expect(fixture.nativeElement.textContent).toContain('Bob');
    expect(fixture.nativeElement.textContent).toContain('Online now');
    expect(fixture.nativeElement.textContent).toContain('online');
    expect(fixture.nativeElement.textContent).toContain('offline');
  });

  it('panel renders role/status/access badges', () => {
    component.participants = [{ user_id: 10, role: 'admin', status: 'blocked', access_state: 'blocked' }];
    fixture.detectChanges();
    const content = fixture.nativeElement.textContent as string;
    expect(content).toContain('admin');
    expect(content).toContain('blocked');
  });

  it('panel renders loading state', () => {
    component.loading = true;
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Loading participants...');
  });

  it('panel renders error state', () => {
    component.loading = false;
    component.loading = false;
    component.error = 'Failed to load participants.';
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Failed to load participants.');
  });

  it('panel renders empty state', () => {
    component.loading = false;
    component.error = null;
    component.participants = [];
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('No participants yet.');
  });

  it('panel renders online users section with fallback', () => {
    component.participants = [{ user_id: 88, role: 'member', status: 'active', access_state: 'full' }];
    component.onlineUsers = [];
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Nobody online');
  });

  it('does not render blocked_reason/raw metadata/email/device sensitive fields', () => {
    component.onlineUsers = [{
      id: 10,
      name: 'Safe Name',
      role: 'member',
      device_type: 'browser',
      email: 'sensitive@example.com',
      device_key: 'chatdev_secret',
      user_agent: 'UA',
      ip_address: '127.0.0.1',
      metadata: { secret: true },
    } as any];
    component.participants = [{
      user_id: 10,
      role: 'member',
      status: 'blocked',
      access_state: 'blocked',
      user: { name: 'Safe Name', email: 'sensitive@example.com' } as any,
      blocked_reason: 'sensitive',
      metadata: { secret: true },
      user_agent: 'UA',
      ip_address: '127.0.0.1',
    } as any];
    fixture.detectChanges();
    const content = fixture.nativeElement.textContent as string;
    expect(content).not.toContain('sensitive@example.com');
    expect(content).not.toContain('sensitive');
    expect(content).not.toContain('metadata');
    expect(content).not.toContain('127.0.0.1');
    expect(content).not.toContain('UA');
    expect(content).not.toContain('chatdev_secret');
  });

  it('participant is online when nested user.id matches presence user id', () => {
    component.participants = [{
      user_id: 999,
      user: { id: 77, name: 'Nested User' },
      role: 'member',
      status: 'active',
      access_state: 'full',
    } as any];
    component.onlineUsers = [{ id: 77, name: 'Nested User' }];
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('online');
  });
});
