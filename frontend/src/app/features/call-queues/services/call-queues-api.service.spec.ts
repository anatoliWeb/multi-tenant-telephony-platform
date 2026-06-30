import { TestBed } from '@angular/core/testing';
import { CallQueuesApiService } from './call-queues-api.service';
import { ApiClientService } from '../../../api/services/api-client.service';

describe('CallQueuesApiService', () => {
  const apiClientMock = {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
    TestBed.configureTestingModule({
      providers: [
        CallQueuesApiService,
        { provide: ApiClientService, useValue: apiClientMock },
      ],
    });
  });

  it('requests queue endpoints', () => {
    const service = TestBed.inject(CallQueuesApiService);
    service.listCallQueues({});
    service.options();
    service.testRoute(1);
    expect(apiClientMock.get).toHaveBeenCalled();
    expect(apiClientMock.post).toHaveBeenCalled();
  });
});
