import { ComponentFixture, TestBed } from '@angular/core/testing';
import { describe, expect, it } from 'vitest';
import { AppComponent } from './app.component';
import { AppModule } from './app.module';
import { EN_TRANSLATIONS } from './i18n/translations/en';
import { UK_TRANSLATIONS } from './i18n/translations/uk';
import { DE_TRANSLATIONS } from './i18n/translations/de';

describe('Frontend UI polish contracts', () => {
  it('renders app shell without debug/secret/token text', async () => {
    await TestBed.configureTestingModule({
      imports: [AppModule],
    }).compileComponents();

    const fixture: ComponentFixture<AppComponent> = TestBed.createComponent(AppComponent);
    fixture.detectChanges();

    const text = (fixture.nativeElement.textContent ?? '').toLowerCase();
    expect(text).not.toContain('debug');
    expect(text).not.toContain('token=');
    expect(text).not.toContain('secret=');
  });

  it('includes dashboard i18n keys used by final UI cleanup labels', () => {
    const requiredKeys = [
      'dashboard.fields.users',
      'dashboard.fields.tokens',
      'dashboard.fields.recentActivity',
      'dashboard.fields.refreshed',
      'dashboard.realtime.activityEvents',
      'dashboard.realtime.onlineUsers',
      'dashboard.realtime.dashboardPresence',
      'ringGroups.title',
      'ringGroups.fields.members',
      'ringGroups.memberModal.subtitle',
    ];

    for (const key of requiredKeys) {
      expect(EN_TRANSLATIONS[key]).toBeTruthy();
      expect(UK_TRANSLATIONS[key]).toBeTruthy();
      expect(DE_TRANSLATIONS[key]).toBeTruthy();
    }
  });
});
