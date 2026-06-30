import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { RingGroupsApiService } from './ring-groups-api.service';
import { ApiClientService } from '../../../api/services/api-client.service';
import { APP_CONFIG, AppEnvironment } from '../../../core/tokens/app-config.token';
import { environment } from '../../../../environments/environment.development';

describe('RingGroupsApiService', () => {
  let service: RingGroupsApiService;
  let httpMock: HttpTestingController;

  const appConfig = environment as unknown as AppEnvironment;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        ApiClientService,
        RingGroupsApiService,
        { provide: APP_CONFIG, useValue: appConfig },
      ],
    });

    service = TestBed.inject(RingGroupsApiService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('builds the ring groups list url with tenant filters', () => {
    service.listRingGroups({ search: 'sales', status: 'active', strategy: 'sequential', page: 2, per_page: 25 }).subscribe();

    const req = httpMock.expectOne((request) =>
      request.url === `${appConfig.apiBaseUrl}/v1/ring-groups`
      && request.params.get('search') === 'sales'
      && request.params.get('status') === 'active'
      && request.params.get('strategy') === 'sequential'
      && request.params.get('page') === '2'
      && request.params.get('per_page') === '25',
    );

    expect(req.request.method).toBe('GET');
    req.flush({ success: true, message: 'ok', data: [] });
  });

  it('builds the member management endpoints', () => {
    service.createMember(10, {
      member_type: 'extension',
      extension_id: 99,
      priority: 1,
      delay_seconds: 0,
      timeout_seconds: 20,
      is_active: true,
    }).subscribe();

    const createReq = httpMock.expectOne(`${appConfig.apiBaseUrl}/v1/ring-groups/10/members`);
    expect(createReq.request.method).toBe('POST');
    expect(createReq.request.body.extension_id).toBe(99);
    createReq.flush({ success: true, message: 'ok', data: { id: 1 } });

    service.testRoute(10).subscribe();
    const testReq = httpMock.expectOne(`${appConfig.apiBaseUrl}/v1/ring-groups/10/test-route`);
    expect(testReq.request.method).toBe('POST');
    testReq.flush({ success: true, message: 'ok', data: { active_member_count: 0, members: [] } });
  });
});
