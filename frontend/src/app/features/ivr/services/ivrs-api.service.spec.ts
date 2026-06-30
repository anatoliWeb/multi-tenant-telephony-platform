import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { IvrsApiService } from './ivrs-api.service';
import { ApiClientService } from '../../../api/services/api-client.service';
import { APP_CONFIG, AppEnvironment } from '../../../core/tokens/app-config.token';
import { environment } from '../../../../environments/environment.development';

describe('IvrsApiService', () => {
  let service: IvrsApiService;
  let httpMock: HttpTestingController;

  const appConfig = environment as unknown as AppEnvironment;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        ApiClientService,
        IvrsApiService,
        { provide: APP_CONFIG, useValue: appConfig },
      ],
    });

    service = TestBed.inject(IvrsApiService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('builds the ivr list url with filters', () => {
    service.listIvrMenus({ search: 'main', status: 'active', page: 2, per_page: 25 }).subscribe();

    const req = httpMock.expectOne((request) =>
      request.url === `${appConfig.apiBaseUrl}/v1/ivr-menus`
      && request.params.get('search') === 'main'
      && request.params.get('status') === 'active'
      && request.params.get('page') === '2'
      && request.params.get('per_page') === '25',
    );

    expect(req.request.method).toBe('GET');
    req.flush({ success: true, message: 'ok', data: [] });
  });

  it('builds the option and route test endpoints', () => {
    service.createOption(10, {
      digit: '1',
      label: 'Sales',
      destination_type: 'call_queue',
      destination_id: 42,
      priority: 1,
      is_active: true,
    }).subscribe();

    const createReq = httpMock.expectOne(`${appConfig.apiBaseUrl}/v1/ivr-menus/10/options`);
    expect(createReq.request.method).toBe('POST');
    expect(createReq.request.body.destination_id).toBe(42);
    createReq.flush({ success: true, message: 'ok', data: { id: 1 } });

    service.testRoute(10, { input_type: 'digit', digit: '1' }).subscribe();
    const testReq = httpMock.expectOne(`${appConfig.apiBaseUrl}/v1/ivr-menus/10/test-route`);
    expect(testReq.request.method).toBe('POST');
    expect(testReq.request.body.digit).toBe('1');
    testReq.flush({ success: true, message: 'ok', data: { resolved_at: new Date().toISOString() } });
  });
});
