import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { ChatApiService } from './chat-api.service';
import { ApiClientService } from '../../api/services/api-client.service';
import { APP_CONFIG, AppEnvironment } from '../tokens/app-config.token';
import { environment } from '../../../environments/environment.development';

describe('ChatApiService', () => {
  let service: ChatApiService;
  let httpMock: HttpTestingController;

  const appConfig = environment as unknown as AppEnvironment;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        ApiClientService,
        ChatApiService,
        { provide: APP_CONFIG, useValue: appConfig },
      ],
    });

    service = TestBed.inject(ChatApiService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('builds correct list conversations url', () => {
    service.listConversations().subscribe();
    const req = httpMock.expectOne(`${appConfig.apiBaseUrl}/v1/chat/conversations`);
    expect(req.request.method).toBe('GET');
    req.flush({ success: true, message: 'ok', data: [] });
  });

  it('sendMessage calls correct endpoint', () => {
    service.sendMessage(10, { body: 'hello', type: 'text' }).subscribe();
    const req = httpMock.expectOne(`${appConfig.apiBaseUrl}/v1/chat/conversations/10/messages`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.body).toBe('hello');
    req.flush({ success: true, message: 'ok', data: { id: 1, conversation_id: 10 } });
  });

  it('registerDevice calls correct endpoint', () => {
    service.registerDevice({ device_key: 'abc', device_type: 'browser' }).subscribe();
    const req = httpMock.expectOne(`${appConfig.apiBaseUrl}/v1/chat/devices`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.device_key).toBe('abc');
    req.flush({ success: true, message: 'ok', data: {} });
  });

  it('uploadAttachment calls correct endpoint', () => {
    const file = new File(['x'], 'demo.txt', { type: 'text/plain' });
    service.uploadAttachment(55, file).subscribe();
    const req = httpMock.expectOne(`${appConfig.apiBaseUrl}/v1/chat/messages/55/attachments`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body instanceof FormData).toBe(true);
    req.flush({ success: true, message: 'ok', data: { id: 1, message_id: 55 } });
  });

  it('listConversationParticipants calls correct endpoint', () => {
    service.listConversationParticipants(44).subscribe();
    const req = httpMock.expectOne(`${appConfig.apiBaseUrl}/v1/chat/conversations/44/participants`);
    expect(req.request.method).toBe('GET');
    req.flush({ success: true, message: 'ok', data: [] });
  });
});
