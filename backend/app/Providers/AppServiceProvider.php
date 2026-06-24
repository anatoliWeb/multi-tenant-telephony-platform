<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\SystemTranslation;
use App\Services\Rbac\PermissionCacheService;
use App\Services\Tenancy\TenantContext;
use App\Observers\PersonalAccessTokenObserver;
use App\Observers\SystemTranslationObserver;
use App\Observers\UserObserver;
use App\Policies\ConversationPolicy;
use App\Support\TestingDatabaseGuard;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->runningInConsole()) {
            $defaultConnection = (string) config('database.default');
            $activeDatabase = (string) config("database.connections.{$defaultConnection}.database");
            app(TestingDatabaseGuard::class)->assertSafe(
                app()->environment(),
                $activeDatabase,
                'console-bootstrap'
            );
        }

        RateLimiter::for('chat-external-api', function (Request $request): Limit {
            $enabled = (bool) config('chat.external_api.rate_limit.enabled', true);
            if (! $enabled) {
                return Limit::none();
            }

            $maxAttempts = max(1, (int) config('chat.external_api.rate_limit.max_attempts', 60));
            $decaySeconds = max(1, (int) config('chat.external_api.rate_limit.decay_seconds', 60));

            $user = $request->user();
            $tokenId = $user?->currentAccessToken()?->getKey();
            $routeEndpoint = $request->route('endpoint');
            $endpointIdentifier = is_object($routeEndpoint)
                ? (string) data_get($routeEndpoint, 'uuid', data_get($routeEndpoint, 'id', ''))
                : (string) ($routeEndpoint ?? '');
            $key = $tokenId
                ? 'chat-ext-token:'.$tokenId
                : ($user
                    ? 'chat-ext-user:'.$user->getAuthIdentifier()
                    : 'chat-ext-webhook:'.($endpointIdentifier !== '' ? $endpointIdentifier : $request->ip()).':'.$request->ip());

            return Limit::perSecond($maxAttempts, $decaySeconds)->by($key);
        });

        RateLimiter::for('chat-webhook-management', function (Request $request): Limit {
            $maxAttempts = max(1, (int) config('chat.webhooks.endpoint_management_rate_limit.max_attempts', 30));
            $decaySeconds = max(1, (int) config('chat.webhooks.endpoint_management_rate_limit.decay_seconds', 60));
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $key = 'chat-webhooks:'.$userId.'|'.$request->ip();

            return Limit::perSecond($maxAttempts, $decaySeconds)->by($key);
        });

        RateLimiter::for('chat-message-send', function (Request $request): Limit {
            $enabled = (bool) config('chat.message_sending_rate_limit.enabled', true);
            if (! $enabled) {
                return Limit::none();
            }

            $maxAttempts = max(1, (int) config('chat.message_sending_rate_limit.max_attempts', 30));
            $decaySeconds = max(1, (int) config('chat.message_sending_rate_limit.decay_seconds', 60));

            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $conversationId = $request->route('conversation');
            $conversationKey = is_object($conversationId)
                ? (string) ($conversationId->id ?? 'none')
                : (string) ($conversationId ?? 'none');
            $ip = (string) ($request->ip() ?? 'unknown');
            $key = 'chat-send:'.$userId.'|conv:'.$conversationKey.'|ip:'.$ip;

            return Limit::perSecond($maxAttempts, $decaySeconds)->by($key);
        });

        RateLimiter::for('auth-login', function (Request $request): Limit {
            $enabled = (bool) config('security.rate_limits.enabled', true);
            if (! $enabled) {
                return Limit::none();
            }

            $maxAttempts = max(1, (int) config('security.rate_limits.auth_login.max_attempts', 5));
            $decaySeconds = max(1, (int) config('security.rate_limits.auth_login.decay_seconds', 60));

            $email = mb_strtolower((string) $request->input('email', ''));
            $ip = (string) ($request->ip() ?? 'unknown');
            $key = $email !== '' ? 'auth-login:'.$email.'|ip:'.$ip : 'auth-login-ip:'.$ip;

            return Limit::perSecond($maxAttempts, $decaySeconds)->by($key);
        });

        RateLimiter::for('api-docs', function (Request $request): Limit {
            $enabled = (bool) config('security.rate_limits.enabled', true);
            if (! $enabled) {
                return Limit::none();
            }

            $maxAttempts = max(1, (int) config('security.rate_limits.api_docs.max_attempts', 60));
            $decaySeconds = max(1, (int) config('security.rate_limits.api_docs.decay_seconds', 60));

            $user = $request->user();
            $key = $user
                ? 'api-docs-user:'.$user->getAuthIdentifier()
                : 'api-docs-ip:'.(string) ($request->ip() ?? 'unknown');

            return Limit::perSecond($maxAttempts, $decaySeconds)->by($key);
        });

        RateLimiter::for('chat-typing', function (Request $request): Limit {
            $enabled = (bool) config('security.rate_limits.enabled', true);
            if (! $enabled) {
                return Limit::none();
            }

            $maxAttempts = max(1, (int) config('security.rate_limits.chat_typing.max_attempts', 120));
            $decaySeconds = max(1, (int) config('security.rate_limits.chat_typing.decay_seconds', 60));
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $conversationId = $request->route('conversation');
            $conversationKey = is_object($conversationId)
                ? (string) ($conversationId->id ?? 'none')
                : (string) ($conversationId ?? 'none');
            $ip = (string) ($request->ip() ?? 'unknown');
            $key = 'chat-typing:'.$userId.'|conv:'.$conversationKey.'|ip:'.$ip;

            return Limit::perSecond($maxAttempts, $decaySeconds)->by($key);
        });

        RateLimiter::for('chat-attachments', function (Request $request): Limit {
            $enabled = (bool) config('security.rate_limits.enabled', true);
            if (! $enabled) {
                return Limit::none();
            }

            $maxAttempts = max(1, (int) config('security.rate_limits.chat_attachments.max_attempts', 20));
            $decaySeconds = max(1, (int) config('security.rate_limits.chat_attachments.decay_seconds', 60));
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $messageId = $request->route('message');
            $messageKey = is_object($messageId)
                ? (string) ($messageId->id ?? 'none')
                : (string) ($messageId ?? 'none');
            $ip = (string) ($request->ip() ?? 'unknown');
            $key = 'chat-attachments:'.$userId.'|msg:'.$messageKey.'|ip:'.$ip;

            return Limit::perSecond($maxAttempts, $decaySeconds)->by($key);
        });

        if (class_exists(Scramble::class)) {
            Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
                $openApi->components->addSecurityScheme(
                    'BearerAuth',
                    SecurityScheme::http('bearer', 'token')
                        ->as('BearerAuth')
                        ->setDescription('Bearer token auth for protected API routes.')
                );
                $openApi->components->addSecurityScheme(
                    'ExternalChatToken',
                    SecurityScheme::http('bearer', 'token')
                        ->as('ExternalChatToken')
                        ->setDescription('External chat API token with configured scopes.')
                );
                $openApi->components->addSecurityScheme(
                    'SanctumSession',
                    SecurityScheme::apiKey('cookie', 'laravel_session')
                        ->as('SanctumSession')
                        ->setDescription('Laravel session cookie for Sanctum session-auth flows.')
                );
                $openApi->components->addSecurityScheme(
                    'WebhookSignature',
                    SecurityScheme::apiKey('header', 'X-Chat-Signature')
                        ->as('WebhookSignature')
                        ->setDescription('Incoming webhook HMAC signature header.')
                );
                $openApi->components->addSecurityScheme(
                    'WebhookTimestamp',
                    SecurityScheme::apiKey('header', 'X-Chat-Timestamp')
                        ->as('WebhookTimestamp')
                        ->setDescription('Incoming webhook timestamp header for replay/tolerance checks.')
                );

                $paginationMeta = (new ObjectType)
                    ->addProperty('current_page', new IntegerType)
                    ->addProperty('last_page', new IntegerType)
                    ->addProperty('per_page', new IntegerType)
                    ->addProperty('total', new IntegerType)
                    ->setRequired(['current_page', 'last_page', 'per_page', 'total']);

                $apiSuccess = (new ObjectType)
                    ->addProperty('success', (new BooleanType)->const(true))
                    ->addProperty('message', new StringType)
                    ->addProperty('data', new MixedType)
                    ->addProperty('meta', (new ObjectType)->additionalProperties(new MixedType))
                    ->setRequired(['success', 'message', 'data']);

                $apiError = (new ObjectType)
                    ->addProperty('success', (new BooleanType)->const(false))
                    ->addProperty('message', new StringType)
                    ->addProperty('errors', (new ObjectType)->additionalProperties(new MixedType))
                    ->setRequired(['success', 'message', 'errors']);

                $validationError = (new ObjectType)
                    ->addProperty('success', (new BooleanType)->const(false))
                    ->addProperty('message', (new StringType)->example('Validation failed'))
                    ->addProperty(
                        'errors',
                        (new ObjectType)->additionalProperties(
                            (new ArrayType)->setItems(new StringType)
                        )
                    )
                    ->setRequired(['success', 'message', 'errors']);

                $paginatedResponse = (new ObjectType)
                    ->addProperty('success', (new BooleanType)->const(true))
                    ->addProperty('message', new StringType)
                    ->addProperty('data', (new ArrayType)->setItems(new MixedType))
                    ->addProperty('meta', $paginationMeta)
                    ->setRequired(['success', 'message', 'data', 'meta']);

                $userSchema = (new ObjectType)
                    ->addProperty('id', new IntegerType)
                    ->addProperty('name', new StringType)
                    ->addProperty('email', new StringType)
                    ->addProperty('roles', (new ArrayType)->setItems(new StringType))
                    ->setRequired(['id', 'name']);

                $roleSchema = (new ObjectType)
                    ->addProperty('id', new IntegerType)
                    ->addProperty('name', new StringType)
                    ->addProperty('scope', new StringType)
                    ->addProperty('scope_reference', new StringType)
                    ->addProperty('tenant_id', new StringType)
                    ->addProperty('is_system', new BooleanType)
                    ->addProperty('is_protected', new BooleanType)
                    ->addProperty('label', new StringType)
                    ->addProperty('description', new StringType)
                    ->setRequired(['id', 'name']);

                $permissionSchema = (new ObjectType)
                    ->addProperty('id', new IntegerType)
                    ->addProperty('name', new StringType)
                    ->addProperty('scope', new StringType)
                    ->addProperty('scope_reference', new StringType)
                    ->addProperty('label', new StringType)
                    ->addProperty('description', new StringType)
                    ->setRequired(['id', 'name']);

                $chatAttachmentSchema = (new ObjectType)
                    ->addProperty('id', new IntegerType)
                    ->addProperty('message_id', new IntegerType)
                    ->addProperty('original_name', new StringType)
                    ->addProperty('mime_type', new StringType)
                    ->addProperty('size', new IntegerType)
                    ->addProperty('status', new StringType)
                    ->addProperty('created_at', new StringType)
                    ->setRequired(['id', 'message_id', 'original_name', 'mime_type', 'size', 'status']);

                $chatMessageSchema = (new ObjectType)
                    ->addProperty('id', new IntegerType)
                    ->addProperty('conversation_id', new IntegerType)
                    ->addProperty('sender_id', new IntegerType)
                    ->addProperty('type', new StringType)
                    ->addProperty('body', new StringType)
                    ->addProperty('status', new StringType)
                    ->addProperty('created_at', new StringType)
                    ->addProperty('updated_at', new StringType)
                    ->addProperty('attachments', (new ArrayType)->setItems($chatAttachmentSchema))
                    ->setRequired(['id', 'conversation_id', 'sender_id', 'type', 'status']);

                $chatParticipantSchema = (new ObjectType)
                    ->addProperty('conversation_id', new IntegerType)
                    ->addProperty('user_id', new IntegerType)
                    ->addProperty('role', new StringType)
                    ->addProperty('status', new StringType)
                    ->addProperty('access_state', new StringType)
                    ->addProperty('can_send', new BooleanType)
                    ->addProperty('can_attach', new BooleanType)
                    ->addProperty('joined_at', new StringType)
                    ->setRequired(['conversation_id', 'user_id', 'role', 'status', 'access_state']);

                $chatConversationSchema = (new ObjectType)
                    ->addProperty('id', new IntegerType)
                    ->addProperty('uuid', new StringType)
                    ->addProperty('type', new StringType)
                    ->addProperty('visibility', new StringType)
                    ->addProperty('title', new StringType)
                    ->addProperty('status', new StringType)
                    ->addProperty('source', new StringType)
                    ->addProperty('created_at', new StringType)
                    ->addProperty('updated_at', new StringType)
                    ->setRequired(['id', 'uuid', 'type', 'visibility', 'status']);

                $chatDeviceReadSchema = (new ObjectType)
                    ->addProperty('user_id', new IntegerType)
                    ->addProperty('device_type', new StringType)
                    ->addProperty('read_at', new StringType)
                    ->setRequired(['user_id', 'read_at']);

                $chatReadStateSchema = (new ObjectType)
                    ->addProperty('message_id', new IntegerType)
                    ->addProperty('conversation_id', new IntegerType)
                    ->addProperty('user_id', new IntegerType)
                    ->addProperty('read_at', new StringType)
                    ->setRequired(['message_id', 'conversation_id', 'user_id', 'read_at']);

                $chatWebhookEndpointSchema = (new ObjectType)
                    ->addProperty('id', new IntegerType)
                    ->addProperty('uuid', new StringType)
                    ->addProperty('name', new StringType)
                    ->addProperty('url', new StringType)
                    ->addProperty('is_active', new BooleanType)
                    ->addProperty('status', new StringType)
                    ->addProperty('events', (new ArrayType)->setItems(new StringType))
                    ->addProperty('scopes', (new ArrayType)->setItems(new StringType))
                    ->addProperty('created_at', new StringType)
                    ->addProperty('updated_at', new StringType)
                    ->setRequired(['id', 'uuid', 'name', 'url', 'is_active', 'status']);

                $chatWebhookDeliverySummarySchema = (new ObjectType)
                    ->addProperty('id', new IntegerType)
                    ->addProperty('event_type', new StringType)
                    ->addProperty('status', new StringType)
                    ->addProperty('attempts', new IntegerType)
                    ->addProperty('max_attempts', new IntegerType)
                    ->addProperty('last_status_code', new IntegerType)
                    ->addProperty('error_summary', new StringType)
                    ->addProperty('next_retry_at', new StringType)
                    ->addProperty('sent_at', new StringType)
                    ->addProperty('failed_at', new StringType)
                    ->setRequired(['id', 'event_type', 'status', 'attempts', 'max_attempts']);

                $externalMessageRequestSchema = (new ObjectType)
                    ->addProperty('conversation_id', new IntegerType)
                    ->addProperty('external_provider', new StringType)
                    ->addProperty('external_message_id', new StringType)
                    ->addProperty('body', new StringType)
                    ->addProperty('type', new StringType)
                    ->addProperty('sent_at', new StringType)
                    ->addProperty('idempotency_key', new StringType)
                    ->setRequired(['conversation_id', 'external_provider', 'external_message_id', 'body']);

                $incomingWebhookRequestSchema = (new ObjectType)
                    ->addProperty('event', new StringType)
                    ->addProperty('conversation_id', new IntegerType)
                    ->addProperty('external_provider', new StringType)
                    ->addProperty('external_message_id', new StringType)
                    ->addProperty('body', new StringType)
                    ->addProperty('type', new StringType)
                    ->addProperty('sent_at', new StringType)
                    ->addProperty('idempotency_key', new StringType)
                    ->setRequired(['event', 'conversation_id', 'external_provider', 'external_message_id', 'body']);

                $metaBootstrapResponse = (new ObjectType)
                    ->addProperty('current_user', $userSchema)
                    ->addProperty('current_user_permissions', (new ArrayType)->setItems(new StringType))
                    ->addProperty('platform_permissions', (new ArrayType)->setItems(new StringType))
                    ->addProperty('tenant_permissions', (new ArrayType)->setItems(new StringType))
                    ->setRequired(['current_user', 'current_user_permissions', 'platform_permissions', 'tenant_permissions']);

                $metaRbacResponse = (new ObjectType)
                    ->addProperty('roles', (new ArrayType)->setItems($roleSchema))
                    ->addProperty('permissions', (new ArrayType)->setItems($permissionSchema))
                    ->addProperty('role_permissions', (new ObjectType)->additionalProperties((new ArrayType)->setItems(new StringType)))
                    ->setRequired(['roles', 'permissions', 'role_permissions']);

                $openApi->components->addSchema('PaginationMeta', Schema::fromType($paginationMeta));
                $openApi->components->addSchema('ApiSuccessResponse', Schema::fromType($apiSuccess));
                $openApi->components->addSchema('ApiErrorResponse', Schema::fromType($apiError));
                $openApi->components->addSchema('ValidationErrorResponse', Schema::fromType($validationError));
                $openApi->components->addSchema('PaginatedResponse', Schema::fromType($paginatedResponse));
                $openApi->components->addSchema('User', Schema::fromType($userSchema));
                $openApi->components->addSchema('Role', Schema::fromType($roleSchema));
                $openApi->components->addSchema('Permission', Schema::fromType($permissionSchema));
                $openApi->components->addSchema('ChatConversation', Schema::fromType($chatConversationSchema));
                $openApi->components->addSchema('ChatMessage', Schema::fromType($chatMessageSchema));
                $openApi->components->addSchema('ChatAttachment', Schema::fromType($chatAttachmentSchema));
                $openApi->components->addSchema('ChatParticipant', Schema::fromType($chatParticipantSchema));
                $openApi->components->addSchema('ChatDeviceRead', Schema::fromType($chatDeviceReadSchema));
                $openApi->components->addSchema('ChatReadState', Schema::fromType($chatReadStateSchema));
                $openApi->components->addSchema('ChatWebhookEndpoint', Schema::fromType($chatWebhookEndpointSchema));
                $openApi->components->addSchema('ChatWebhookDeliverySummary', Schema::fromType($chatWebhookDeliverySummarySchema));
                $openApi->components->addSchema('ExternalMessageRequest', Schema::fromType($externalMessageRequestSchema));
                $openApi->components->addSchema('IncomingWebhookRequest', Schema::fromType($incomingWebhookRequestSchema));
                $openApi->components->addSchema('MetaBootstrapResponse', Schema::fromType($metaBootstrapResponse));
                $openApi->components->addSchema('MetaRbacResponse', Schema::fromType($metaRbacResponse));
            });

            Scramble::configure()
                ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo): void {
                    $route = $routeInfo->route;
                    $middleware = $route->gatherMiddleware();
                    $uri = '/'.ltrim($route->uri(), '/');
                    $addQueryParameter = static function (string $name, string $description, mixed $example = null) use ($operation): void {
                        foreach ($operation->parameters as $parameter) {
                            if ($parameter instanceof Parameter && $parameter->in === 'query' && $parameter->name === $name) {
                                return;
                            }
                        }

                        $parameter = Parameter::make($name, 'query')->description($description);
                        if ($example !== null) {
                            $parameter->example($example);
                        }

                        $operation->addParameters([$parameter]);
                    };

                    if (in_array('auth:sanctum', $middleware, true)) {
                        $operation->addSecurity(new SecurityRequirement(['BearerAuth' => []]));
                        $operation->addSecurity(new SecurityRequirement(['SanctumSession' => []]));
                    }

                    if (collect($middleware)->contains(fn (string $item): bool => str_starts_with($item, 'external.chat.scope:'))) {
                        $operation->addSecurity(new SecurityRequirement(['ExternalChatToken' => []]));
                    }

                    if (str_starts_with($uri, '/api/v1/chat/external/webhooks/')) {
                        $operation->addSecurity(new SecurityRequirement([
                            'WebhookSignature' => [],
                            'WebhookTimestamp' => [],
                        ]));
                    }

                    if ($uri === '/api/v1/chat/conversations' && strtolower($operation->method) === 'get') {
                        $addQueryParameter('page', 'Pagination page number.', 1);
                        $addQueryParameter('per_page', 'Items per page (1..100).', 20);
                        $addQueryParameter('search', 'Search term for conversation title/body context.', 'support');
                        $addQueryParameter('type', 'Conversation type filter (direct, group, support, external, system).', 'group');
                        $addQueryParameter('visibility', 'Conversation visibility filter (private, public).', 'private');
                        $addQueryParameter('status', 'Conversation status filter.', 'active');
                        $addQueryParameter('source', 'Conversation source filter.', 'internal');
                        $addQueryParameter('unread', 'Unread-only filter.', true);
                    }

                    if ($uri === '/api/v1/chat/conversations/{conversation}/messages' && strtolower($operation->method) === 'get') {
                        $addQueryParameter('page', 'Pagination page number.', 1);
                        $addQueryParameter('per_page', 'Items per page (1..100).', 50);
                        $addQueryParameter('before_id', 'Load messages with id lower than provided value.', 1000);
                    }

                    if ($uri === '/api/v1/users' && strtolower($operation->method) === 'get') {
                        $addQueryParameter('page', 'Pagination page number.', 1);
                        $addQueryParameter('per_page', 'Items per page.', 15);
                        $addQueryParameter('search', 'Search by name/email.', 'admin');
                        $addQueryParameter('sort', 'Sort field.', 'name');
                        $addQueryParameter('direction', 'Sort direction.', 'asc');
                    }
                });
        }

        Broadcast::routes([
            'middleware' => ['auth:sanctum'],
        ]);
        require base_path('routes/channels.php');

        User::observe(UserObserver::class);
        PersonalAccessToken::observe(PersonalAccessTokenObserver::class);

        /*
        |--------------------------------------------------------------------------
        | Test-only synchronous activity fallback
        |--------------------------------------------------------------------------
        |
        | WHY:
        | Some feature tests assert immediate DB activity rows without queue worker.
        | We keep production observer/queue flow unchanged and add a test-only
        | direct write fallback to stabilize deterministic test behavior.
        */
        if (app()->runningUnitTests() || defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) {
            User::created(function (User $user): void {
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'user_created',
                    'description' => 'User created',
                    'meta' => [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ],
                ]);
            });

            User::updated(function (User $user): void {
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'user_updated',
                    'description' => 'User updated',
                    'meta' => [
                        'user_id' => $user->id,
                        'changed' => array_keys($user->getChanges()),
                    ],
                ]);
            });

            User::deleted(function (User $user): void {
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'user_deleted',
                    'description' => 'User deleted',
                    'meta' => [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ],
                ]);
            });

            PersonalAccessToken::created(function (PersonalAccessToken $token): void {
                if (PersonalAccessTokenObserver::shouldSkipCreated()) {
                    return;
                }

                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'token_created',
                    'description' => 'API token created',
                    'meta' => [
                        'token_id' => $token->id,
                        'token_name' => $token->name,
                        'tokenable_id' => $token->tokenable_id,
                        'tokenable_type' => $token->tokenable_type,
                    ],
                ]);
            });

            PersonalAccessToken::deleted(function (PersonalAccessToken $token): void {
                if (PersonalAccessTokenObserver::shouldSkipDeleted()) {
                    return;
                }

                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'token_deleted',
                    'description' => 'API token deleted',
                    'meta' => [
                        'token_id' => $token->id,
                        'token_name' => $token->name,
                        'tokenable_id' => $token->tokenable_id,
                        'tokenable_type' => $token->tokenable_type,
                    ],
                ]);
            });
        }

        Gate::before(function (User $user, string $ability) {
            $platformPermissions = app(PermissionCacheService::class)->getPlatformPermissionsForUser($user);

            return in_array($ability, $platformPermissions, true) ? true : null;
        });

        Gate::define('viewApiDocs', function (User $user): bool {
            return in_array('api.docs.view', app(PermissionCacheService::class)->getPlatformPermissionsForUser($user), true);
        });

        Gate::define('viewFullApiDocs', function (User $user): bool {
            $permissions = app(PermissionCacheService::class)->getPlatformPermissionsForUser($user);

            return in_array('api.docs.view.full', $permissions, true)
                || $user->roles()->where('name', 'admin')->where('scope', 'platform')->exists();
        });

        Gate::policy(Conversation::class, ConversationPolicy::class);

        /*
        |--------------------------------------------------------------------------
        | Translation cache synchronization
        |--------------------------------------------------------------------------
        */

        SystemTranslation::observe(
            SystemTranslationObserver::class
        );
    }
}
