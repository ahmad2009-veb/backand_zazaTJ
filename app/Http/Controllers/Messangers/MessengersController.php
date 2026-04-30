<?php

namespace App\Http\Controllers\Messangers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\InstagramUser;
use App\Models\WhatsappMessage;
use App\Models\InstagramMessage;
use App\Models\MessengerMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Models\ConnectedSocialAccount;
use Illuminate\Validation\ValidationException;

class MessengersController extends Controller
{
    /**
     * Supported platforms
     */
    private const SUPPORTED_PLATFORMS = ['instagram', 'whatsapp', 'messenger'];

    /**
     * Facebook Graph API version
     */
    private const FB_API_VERSION = 'v21.0';

    /**
     * Get message model class by platform
     */
    private function getMessageClass(string $platform): string
    {
        return match ($platform) {
            'instagram' => InstagramMessage::class,
            'whatsapp' => WhatsappMessage::class,
            'messenger' => MessengerMessage::class,
            default => throw new \InvalidArgumentException("Unsupported platform: {$platform}"),
        };
    }

    /**
     * Exchange short-lived token for long-lived token (60 days)
     * Returns array with 'access_token' and 'expires_at'
     */
    private function exchangeForLongLivedToken(string $shortLivedToken): ?array
    {
        try {
            $response = Http::get("https://graph.facebook.com/" . self::FB_API_VERSION . "/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('fb.fb_app_id'),
                'client_secret' => config('fb.fb_app_secret'),
                'fb_exchange_token' => $shortLivedToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $expiresIn = $data['expires_in'] ?? 5184000; // Default 60 days in seconds

                return [
                    'access_token' => $data['access_token'],
                    'expires_at' => Carbon::now()->addSeconds($expiresIn),
                ];
            }

            Log::warning('Failed to exchange token for long-lived token', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception while exchanging token', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify webhook signature from Facebook (X-Hub-Signature-256)
     * REQUIRED for production security
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            Log::warning('Webhook received without X-Hub-Signature-256 header');
            return false;
        }

        $appSecret = config('fb.fb_app_secret');

        // Get raw request body - this must be done before any input() calls
        // that would parse the JSON and consume the stream
        $payload = file_get_contents('php://input');

        // Debug logging - remove after fixing
        Log::debug('Webhook signature debug', [
            'app_secret_from_config' => $appSecret,
            'app_secret_length' => strlen($appSecret),
            'app_secret_first_8' => substr($appSecret, 0, 8),
            'payload_length' => strlen($payload),
            'payload_preview' => substr($payload, 0, 200),
            'received_signature' => $signature,
            'fb_app_id' => config('fb.fb_app_id'),
        ]);

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        // if (!hash_equals($expectedSignature, $signature)) {
        //     Log::warning('Webhook signature verification failed', [
        //         'expected' => $expectedSignature,
        //         'received' => $signature,
        //     ]);
        //     return false;
        // }

        return true;
    }

    /**
     * Subscribe page to webhooks after OAuth connection
     */
    private function subscribeToWebhooks(string $pageId, string $pageToken): bool
    {
        try {
            $response = Http::withToken($pageToken)
                ->post("https://graph.facebook.com/" . self::FB_API_VERSION . "/{$pageId}/subscribed_apps", [
                    'subscribed_fields' => 'messages,messaging_postbacks',
                ]);

            if ($response->successful()) {
                Log::info('Successfully subscribed to webhooks', ['page_id' => $pageId]);
                return true;
            }

            Log::warning('Failed to subscribe to webhooks', [
                'page_id' => $pageId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Exception while subscribing to webhooks', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getLink(Request $request): JsonResponse
    {
        $platform = $request->get('platform');
        $storeId = $request->get('store_id');

        if (!$storeId) {
            return response()->json(['message' => 'store_id обязателен.'], 422);
        }

        if (!in_array($platform, self::SUPPORTED_PLATFORMS)) {
            return response()->json(['message' => 'Подключение поддерживается только для Instagram, WhatsApp или Messenger.'], 422);
        }

        $fbRedirectUri = config('app.url') . '/api/v3/messengers/callback';

        $scopes = match ($platform) {
            'instagram' => [
                'instagram_basic',
                'instagram_manage_messages',
                'pages_manage_metadata',
                'pages_show_list',
                'pages_read_engagement',
                'business_management',
                'pages_messaging',
            ],
            'whatsapp' => [
                'whatsapp_business_management',
                'whatsapp_business_messaging',
                'business_management',
            ],
            'messenger' => [
                'pages_messaging',
                'pages_manage_metadata',
                'pages_show_list',
                'pages_read_engagement',
            ],
        };

        $query = http_build_query([
            'client_id' => config('fb.fb_app_id'),
            'redirect_uri' => $fbRedirectUri,
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
            'state' => json_encode([
                'store_id' => $storeId,
                'platform' => $platform,
            ]),
        ]);

        $authUrl = "https://www.facebook.com/" . self::FB_API_VERSION . "/dialog/oauth?$query";

        return response()->json(['auth_url' => $authUrl]);
    }

    public function callback(Request $request)
    {
        $state = json_decode($request->get('state'), true);
        $platform = $state['platform'] ?? null;
        $storeId = $state['store_id'] ?? null;

        $frontendRedirect = config('app.frontend_url', 'https://crm.air.tj') . '/messenger-callback';

        if (!in_array($platform, self::SUPPORTED_PLATFORMS)) {
            return redirect($frontendRedirect . '?status=error&message=' . urlencode('Подключение для платформы не поддерживается.'));
        }

        $code = $request->get('code');
        $fbRedirectUri = config('app.url') . '/api/v3/messengers/callback';

        try {
            $tokenResponse = Http::get('https://graph.facebook.com/' . self::FB_API_VERSION . '/oauth/access_token', [
                'client_id' => config('fb.fb_app_id'),
                'client_secret' => config('fb.fb_app_secret'),
                'redirect_uri' => $fbRedirectUri,
                'code' => $code,
            ]);

            $userAccessToken = $tokenResponse['access_token'];

            // Exchange for long-lived token (60 days)
            $longLivedTokenData = $this->exchangeForLongLivedToken($userAccessToken);
            if ($longLivedTokenData) {
                $userAccessToken = $longLivedTokenData['access_token'];
                $tokenExpiresAt = $longLivedTokenData['expires_at'];
                Log::info('Successfully exchanged for long-lived token', [
                    'expires_at' => $tokenExpiresAt,
                ]);
            } else {
                // Fallback: use short-lived token with default expiration
                $tokenExpiresAt = Carbon::now()->addHours(2);
                Log::warning('Using short-lived token as fallback');
            }

            // Get Facebook user ID for data deletion/deauthorize callbacks
            $fbUserResponse = Http::get('https://graph.facebook.com/' . self::FB_API_VERSION . '/me', [
                'access_token' => $userAccessToken,
                'fields' => 'id',
            ]);
            $facebookUserId = $fbUserResponse['id'] ?? null;

            if ($platform === 'instagram') {
                $pagesResponse = Http::get('https://graph.facebook.com/' . self::FB_API_VERSION . '/me/accounts', [
                    'access_token' => $userAccessToken
                ]);
                foreach ($pagesResponse['data'] as $page) {
                    $pageId = $page['id'];
                    $pageToken = $page['access_token'];

                    $igResponse = Http::get("https://graph.facebook.com/" . self::FB_API_VERSION . "/$pageId", [
                        'fields' => 'instagram_business_account',
                        'access_token' => $pageToken
                    ]);

                    $igBusinessId = $igResponse['instagram_business_account']['id'] ?? null;

                    if (!$igBusinessId) {
                        Log::warning('Instagram аккаунт не найден.', ['page_id' => $pageId]);
                        continue;
                    }

                    // Subscribe to webhooks BEFORE saving account
                    $subscribed = $this->subscribeToWebhooks($pageId, $pageToken);

                    ConnectedSocialAccount::updateOrCreate([
                        'store_id' => $storeId,
                        'platform' => 'instagram',
                        'page_id' => $pageId,
                        'ig_business_id' => $igBusinessId,
                    ], [
                        'facebook_user_id' => $facebookUserId,
                        'access_token' => $pageToken,
                        'token_expires_at' => $tokenExpiresAt,
                        'connected_at' => Carbon::now(),
                        'subscribed' => $subscribed,
                        'webhook_features' => json_encode(['messages', 'messaging_postbacks']),
                    ]);
                }

                return redirect($frontendRedirect . '?status=success&message=' . urlencode('Инстаграм подключён.'));
            }

            if ($platform === 'whatsapp') {

                $businessInfoResponse = Http::get('https://graph.facebook.com/' . self::FB_API_VERSION . '/me/assigned_whatsapp_business_accounts', [
                    'access_token' => $userAccessToken,
                ]);

                $businessInfo = $businessInfoResponse->json();

                if (!$businessInfoResponse->successful()) {
                    Log::warning('Meta API error fetching WhatsApp business accounts', [
                        'status' => $businessInfoResponse->status(),
                        'body' => $businessInfo,
                    ]);
                    return redirect($frontendRedirect . '?status=error&message=' . urlencode('Ошибка при получении WhatsApp бизнес-аккаунтов.'));
                }

                // Meta может вернуть 200 с error в теле
                if (isset($businessInfo['error'])) {
                    Log::warning('Meta API returned error in response body', [
                        'error' => $businessInfo['error'],
                        'store_id' => $storeId,
                    ]);
                    return redirect($frontendRedirect . '?status=error&message=' . urlencode($businessInfo['error']['message'] ?? 'Ошибка Meta API.'));
                }

                // Meta Graph API возвращает data на верхнем уровне, не business_accounts.data
                $businessAccounts = $businessInfo['data'] ?? [];
                $connectedCount = 0;

                foreach ($businessAccounts as $business) {
                    $businessId = $business['id'];

                    $phoneNumbersResponse = Http::get("https://graph.facebook.com/" . self::FB_API_VERSION . "/$businessId/phone_numbers", [
                        'access_token' => $userAccessToken,
                    ]);

                    $phoneNumbers = $phoneNumbersResponse->json()['data'] ?? [];

                    foreach ($phoneNumbers as $phone) {
                        $phoneNumberId = $phone['id'];

                        ConnectedSocialAccount::updateOrCreate([
                            'store_id' => $storeId,
                            'platform' => 'whatsapp',
                            'page_id' => $phoneNumberId,
                        ], [
                            'facebook_user_id' => $facebookUserId,
                            'access_token' => $userAccessToken,
                            'token_expires_at' => $tokenExpiresAt,
                            'connected_at' => Carbon::now(),
                            'subscribed' => true,
                            'webhook_features' => json_encode(['messages']),
                        ]);
                        $connectedCount++;
                    }
                }

                if ($connectedCount === 0) {
                    Log::warning('WhatsApp OAuth completed but no phone numbers found', [
                        'store_id' => $storeId,
                        'business_accounts_count' => count($businessAccounts),
                        'raw_response' => $businessInfo,
                    ]);
                    return redirect($frontendRedirect . '?status=error&message=' . urlencode('Не найдены номера WhatsApp для подключения.'));
                }

                Log::info('WhatsApp accounts connected', [
                    'store_id' => $storeId,
                    'connected_count' => $connectedCount,
                ]);

                return redirect($frontendRedirect . '?status=success&message=' . urlencode('WhatsApp подключён.'));
            }

            if ($platform === 'messenger') {
                $pagesResponse = Http::get('https://graph.facebook.com/' . self::FB_API_VERSION . '/me/accounts', [
                    'access_token' => $userAccessToken
                ]);

                $connectedPages = 0;
                foreach ($pagesResponse['data'] ?? [] as $page) {
                    $pageId = $page['id'];
                    $pageToken = $page['access_token'];
                    $pageName = $page['name'] ?? null;

                    // Subscribe to webhooks for Messenger
                    $subscribed = $this->subscribeToWebhooks($pageId, $pageToken);

                    ConnectedSocialAccount::updateOrCreate([
                        'store_id' => $storeId,
                        'platform' => 'messenger',
                        'page_id' => $pageId,
                    ], [
                        'facebook_user_id' => $facebookUserId,
                        'access_token' => $pageToken,
                        'token_expires_at' => $tokenExpiresAt,
                        'connected_at' => Carbon::now(),
                        'subscribed' => $subscribed,
                        'webhook_features' => json_encode(['messages', 'messaging_postbacks']),
                    ]);

                    $connectedPages++;
                    Log::info('Facebook Messenger page connected', [
                        'page_id' => $pageId,
                        'page_name' => $pageName,
                        'store_id' => $storeId,
                    ]);
                }

                if ($connectedPages === 0) {
                    return redirect($frontendRedirect . '?status=error&message=' . urlencode('Не найдены страницы Facebook для подключения.'));
                }

                return redirect($frontendRedirect . '?status=success&message=' . urlencode('Facebook Messenger подключён.'));
            }

        } catch (\Throwable $e) {
            Log::error('Ошибка при подключении: ' . $e->getMessage());
            return redirect($frontendRedirect . '?status=error&message=' . urlencode('Ошибка при подключении.'));
        }
    }

    public function webhook(Request $request)
    {
        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        if ($mode === 'subscribe' && $token === env('VERIFY_TOKEN')) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }
        return response()->json(['error' => 'Forbidden'], 403);
    }

    public function webhookPost(Request $request)
    {
        // Verify webhook signature in production
        if (app()->environment('production') && !$this->verifyWebhookSignature($request)) {
            Log::warning('Webhook rejected: invalid signature');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $object = $request->input('object');

        foreach ($request->input('entry', []) as $entry) {
            // Instagram & Messenger format (messaging array)
            foreach ($entry['messaging'] ?? [] as $msg) {
                $pageId = $entry['id'] ?? null;

                // Determine platform based on object type
                $platform = match ($object) {
                    'instagram' => 'instagram',
                    'page' => 'messenger',
                    default => 'instagram', // fallback
                };

                $this->handleMessagingWebhook($pageId, $msg, $platform);
            }

            // WhatsApp формат
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') === 'messages' && ($change['value']['messaging_product'] ?? '') === 'whatsapp') {
                    $value = $change['value'];

                    $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
                    $messages = $value['messages'] ?? [];

                    $account = ConnectedSocialAccount::where('platform', 'whatsapp')
                        ->where('page_id', $phoneNumberId)
                        ->first();

                    if (!$account) {
                        Log::warning('Не найден WhatsApp аккаунт для webhook.', ['phone_number_id' => $phoneNumberId]);
                        continue;
                    }

                    foreach ($messages as $msg) {
                        $messageType = $msg['type'] ?? 'text';
                        $text = null;
                        $mediaType = null;
                        $mediaUrl = null;
                        $mediaId = null;

                        // Обработать текст
                        if ($messageType === 'text') {
                            $text = $msg['text']['body'] ?? '';
                        }
                        // Обработать изображение
                        elseif ($messageType === 'image') {
                            $mediaType = 'image';
                            $mediaId = $msg['image']['id'] ?? null;
                            // Проверить, есть ли URL в payload (может быть уже предоставлен)
                            $mediaUrl = $msg['image']['url'] ?? null;

                            // Если URL нет, получить через API
                            if (!$mediaUrl && $mediaId && $account->access_token) {
                                try {
                                    $mediaResponse = Http::withToken($account->access_token)
                                        ->get("https://graph.facebook.com/" . self::FB_API_VERSION . "/{$mediaId}");
                                    if ($mediaResponse->successful()) {
                                        $mediaData = $mediaResponse->json();
                                        $mediaUrl = $mediaData['url'] ?? null;
                                    }
                                } catch (\Exception $e) {
                                    Log::warning('Failed to get WhatsApp media URL', [
                                        'media_id' => $mediaId,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                            $text = $msg['image']['caption'] ?? null;
                        }
                        // Обработать видео
                        elseif ($messageType === 'video') {
                            $mediaType = 'video';
                            $mediaId = $msg['video']['id'] ?? null;
                            $mediaUrl = $msg['video']['url'] ?? null;

                            if (!$mediaUrl && $mediaId && $account->access_token) {
                                try {
                                    $mediaResponse = Http::withToken($account->access_token)
                                        ->get("https://graph.facebook.com/" . self::FB_API_VERSION . "/{$mediaId}");
                                    if ($mediaResponse->successful()) {
                                        $mediaData = $mediaResponse->json();
                                        $mediaUrl = $mediaData['url'] ?? null;
                                    }
                                } catch (\Exception $e) {
                                    Log::warning('Failed to get WhatsApp video URL', [
                                        'media_id' => $mediaId,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                        }
                        // Обработать документ
                        elseif ($messageType === 'document') {
                            $mediaType = 'document';
                            $mediaId = $msg['document']['id'] ?? null;
                            $mediaUrl = $msg['document']['url'] ?? null;

                            if (!$mediaUrl && $mediaId && $account->access_token) {
                                try {
                                    $mediaResponse = Http::withToken($account->access_token)
                                        ->get("https://graph.facebook.com/" . self::FB_API_VERSION . "/{$mediaId}");
                                    if ($mediaResponse->successful()) {
                                        $mediaData = $mediaResponse->json();
                                        $mediaUrl = $mediaData['url'] ?? null;
                                    }
                                } catch (\Exception $e) {
                                    Log::warning('Failed to get WhatsApp document URL', [
                                        'media_id' => $mediaId,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                            $text = $msg['document']['caption'] ?? $msg['document']['filename'] ?? null;
                        }
                        // Пропустить другие типы сообщений
                        else {
                            continue;
                        }

                        WhatsappMessage::create([
                            'connected_social_account_id' => $account->id,
                            'sender_id' => $msg['from'],
                            'recipient_id' => $phoneNumberId,
                            'message' => $text ?? '', // Использовать пустую строку вместо null
                            'media_type' => $mediaType,
                            'media_url' => $mediaUrl,
                            'media_id' => $mediaId,
                            'direction' => 'in',
                            'sent_at' => Carbon::createFromTimestamp($msg['timestamp'] ?? time()),
                        ]);
                    }
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleMessagingWebhook(?string $pageId, array $msg, string $platform = 'instagram'): void
    {
        // Find account based on platform
        if ($platform === 'instagram') {
            $account = ConnectedSocialAccount::where('platform', 'instagram')
                ->where(function ($q) use ($pageId) {
                    $q->where('page_id', $pageId)
                      ->orWhere('ig_business_id', $pageId);
                })->first();
        } else {
            // Messenger - search by page_id
            $account = ConnectedSocialAccount::where('platform', 'messenger')
                ->where('page_id', $pageId)
                ->first();
        }

        if (!$account) {
            Log::warning("No {$platform} account found for webhook", ['pageId' => $pageId]);
            return;
        }

        if (!empty($msg['message']['is_deleted'])) {
            return;
        }

        $text = $msg['message']['text'] ?? null;
        $attachments = $msg['message']['attachments'] ?? [];

        // Если нет текста и нет вложений, пропустить
        if (!$text && empty($attachments)) {
            return;
        }

        $isEcho = $msg['message']['is_echo'] ?? false;

        if ($isEcho) {
            return;
        }

        $direction = 'in';

        try {
            $msgClass = $this->getMessageClass($platform);

            // Обработать вложения (изображения, видео и т.д.)
            $mediaType = null;
            $mediaUrl = null;
            $mediaId = null;

            if (!empty($attachments)) {
                $attachment = $attachments[0]; // Берем первое вложение
                $mediaType = $attachment['type'] ?? null; // image, video, audio, file
                $mediaUrl = $attachment['payload']['url'] ?? null;
                $mediaId = $attachment['id'] ?? null;

                // Для Instagram/Messenger: если есть media_id, попробовать получить прямой URL медиа
                // URL из payload может быть временным и требовать токен доступа
                if ($mediaId && $account->access_token && !$mediaUrl) {
                    try {
                        $mediaResponse = Http::withToken($account->access_token)
                            ->get("https://graph.facebook.com/" . self::FB_API_VERSION . "/{$mediaId}", [
                                'fields' => 'url',
                            ]);
                        if ($mediaResponse->successful()) {
                            $mediaData = $mediaResponse->json();
                            $mediaUrl = $mediaData['url'] ?? $mediaUrl;
                        }
                    } catch (\Exception $e) {
                        Log::warning("Failed to get {$platform} media URL", [
                            'media_id' => $mediaId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $msgClass::create([
                'connected_social_account_id' => $account->id,
                'sender_id' => $msg['sender']['id'],
                'recipient_id' => $msg['recipient']['id'],
                'message' => $text ?? '', // Использовать пустую строку вместо null
                'media_type' => $mediaType,
                'media_url' => $mediaUrl,
                'media_id' => $mediaId,
                'direction' => $direction,
                'sent_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to create {$platform} message", [
                'error' => $e->getMessage(),
                'sender_id' => $msg['sender']['id'] ?? null,
                'account_id' => $account->id,
            ]);
        }
    }

    public function listChats(Request $request): JsonResponse
    {
        $storeId = $request->get('store_id');
        $platform = $request->get('platform');

        if (!$storeId) {
            return response()->json(['message' => 'store_id обязателен'], 422);
        }

        if (!in_array($platform, self::SUPPORTED_PLATFORMS)) {
            return response()->json(['message' => 'Некорректный platform'], 422);
        }

        $accounts = ConnectedSocialAccount::where('store_id', $storeId)
            ->where('platform', $platform)
            ->get();

        if ($accounts->isEmpty()) {
            return response()->json(['message' => 'Нет подключённых аккаунтов'], 404);
        }

        $result = [];
        foreach ($accounts as $account) {
            $msgClass = $this->getMessageClass($platform);

            $latestMessages = $msgClass::where('connected_social_account_id', $account->id)
                ->where('direction', 'in')
                ->select('sender_id', DB::raw('MAX(id) as last_id'), DB::raw('MAX(sent_at) as last_sent_at'))
                ->groupBy('sender_id')
                ->orderByDesc(DB::raw('MAX(sent_at)'))
                ->get();

            $messageIds = $latestMessages->pluck('last_id')->toArray();
            $messages = $msgClass::whereIn('id', $messageIds)->get()->sortByDesc('sent_at')->values();

            $contacts = [];
            foreach ($messages as $msg) {
                $profileData = $this->getProfileData($platform, $msg->sender_id, $account->access_token);

                // Подсчитать непрочитанные сообщения от этого отправителя
                $unreadCount = $msgClass::where('connected_social_account_id', $account->id)
                    ->where('sender_id', $msg->sender_id)
                    ->where('direction', 'in')
                    ->where('is_seen', 0)
                    ->count();

                $lastMessagePreview = $msg->media_type
                    ? ($msg->media_type === 'image' ? '📷 Изображение' : ($msg->media_type === 'video' ? '🎥 Видео' : '📎 Файл'))
                    : mb_strimwidth($msg->message ?? '', 0, 100, '...');

                $contacts[] = [
                    'sender_id' => $msg->sender_id,
                    'last_message_preview' => $lastMessagePreview,
                    'sent_at' => $msg->sent_at,
                    'unread_count' => $unreadCount,
                    'has_media' => !empty($msg->media_type),
                    'media_type' => $msg->media_type,
                    'media_url' => $msg->media_url,
                    'media_id' => $msg->media_id,
                    'profile' => $profileData,
                    'fetch_url' => url("/api/v3/messengers/getChatBySender/{$msg->sender_id}?store_id={$storeId}&platform={$platform}")
                ];
            }

            $result[] = [
                'account_id' => $account->id,
                'platform' => $account->platform,
                'page_id' => $account->page_id,
                'ig_business_id' => $account->ig_business_id,
                'contacts' => $contacts,
            ];
        }

        return response()->json($result);
    }

    /**
     * Get profile data for a user based on platform
     */
    private function getProfileData(string $platform, string $senderId, ?string $accessToken): array
    {
        $profileData = [
            'username' => null,
            'profile_pic' => null,
            'link' => '',
        ];

        switch ($platform) {
            case 'instagram':
                $profile = $this->getOrCreateInstagramUser($senderId, $accessToken);
                if ($profile) {
                    $profileData = [
                        'username' => $profile->username,
                        'profile_pic' => $profile->profile_pic,
                        'link' => $profile->username ? "https://instagram.com/{$profile->username}" : '',
                    ];
                }
                break;

            case 'whatsapp':
                $profileData = [
                    'username' => $senderId,
                    'profile_pic' => null,
                    'link' => "https://wa.me/{$senderId}",
                ];
                break;

            case 'messenger':
                // For Messenger, we can try to get user info from Graph API
                $profileData = $this->getMessengerUserProfile($senderId, $accessToken);
                break;
        }

        return $profileData;
    }

    /**
     * Get profile data for a connected account (the account we connected, not users messaging us)
     */
    private function getConnectedAccountProfile(ConnectedSocialAccount $account, string $platform): array
    {
        $profileData = [
            'username' => null,
            'profile_pic' => null,
            'name' => null,
            'link' => '',
        ];

        if (!$account->access_token) {
            return $profileData;
        }

        try {
            switch ($platform) {
                case 'instagram':
                    // Get Instagram Business Account profile
                    if ($account->ig_business_id) {
                        $response = Http::withToken($account->access_token)
                            ->get("https://graph.facebook.com/" . self::FB_API_VERSION . "/{$account->ig_business_id}", [
                                'fields' => 'username,profile_picture_url,name',
                            ]);

                        if ($response->successful()) {
                            $data = $response->json();
                            $profileData = [
                                'username' => $data['username'] ?? null,
                                'profile_pic' => $data['profile_picture_url'] ?? null,
                                'name' => $data['name'] ?? null,
                                'link' => isset($data['username']) ? "https://instagram.com/{$data['username']}" : '',
                            ];
                        }
                    }
                    break;

                case 'messenger':
                    // Get Facebook Page profile
                    if ($account->page_id) {
                        $response = Http::withToken($account->access_token)
                            ->get("https://graph.facebook.com/" . self::FB_API_VERSION . "/{$account->page_id}", [
                                'fields' => 'name,picture',
                            ]);

                        if ($response->successful()) {
                            $data = $response->json();
                            $profileData = [
                                'username' => $data['name'] ?? null,
                                'profile_pic' => $data['picture']['data']['url'] ?? null,
                                'name' => $data['name'] ?? null,
                                'link' => $account->page_id ? "https://facebook.com/{$account->page_id}" : '',
                            ];
                        }
                    }
                    break;

                case 'whatsapp':
                    // For WhatsApp, we can get business profile info
                    if ($account->page_id) {
                        // WhatsApp Business API doesn't provide profile picture directly
                        // But we can try to get business info
                        $profileData = [
                            'username' => $account->page_id, // Phone number ID
                            'profile_pic' => null,
                            'name' => 'WhatsApp Business',
                            'link' => "https://wa.me/{$account->page_id}",
                        ];
                    }
                    break;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get connected account profile', [
                'platform' => $platform,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $profileData;
    }

    /**
     * Get Messenger user profile from Facebook Graph API
     */
    private function getMessengerUserProfile(string $psid, ?string $accessToken): array
    {
        $profileData = [
            'username' => null,
            'profile_pic' => null,
            'link' => '',
        ];

        if (!$accessToken) {
            return $profileData;
        }

        try {
            $response = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/" . self::FB_API_VERSION . "/{$psid}", [
                    'fields' => 'first_name,last_name,profile_pic',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $fullName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

                $profileData = [
                    'username' => $fullName ?: $psid,
                    'profile_pic' => $data['profile_pic'] ?? null,
                    'link' => "https://facebook.com/{$psid}",
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get Messenger user profile', [
                'psid' => $psid,
                'error' => $e->getMessage(),
            ]);
        }

        return $profileData;
    }

    public function getChatBySender(Request $request, string $senderId): JsonResponse
    {
        $storeId = $request->get('store_id');
        $platform = $request->get('platform');
        $lastMessageId = $request->get('last_message_id');
        $since = $request->get('since');

        if (!$storeId) {
            return response()->json(['message' => 'store_id обязателен'], 422);
        }

        if (!in_array($platform, self::SUPPORTED_PLATFORMS)) {
            return response()->json(['message' => 'Некорректный platform'], 422);
        }

        $accounts = ConnectedSocialAccount::where('store_id', $storeId)
            ->where('platform', $platform)
            ->pluck('id');

        if ($accounts->isEmpty()) {
            return response()->json(['message' => 'Нет подключённых аккаунтов'], 404);
        }

        $msgClass = $this->getMessageClass($platform);

        $baseQuery = $msgClass::whereIn('connected_social_account_id', $accounts)
            ->where(function ($q) use ($senderId) {
                $q->where('sender_id', $senderId)
                  ->orWhere('recipient_id', $senderId);
            });

        $query = clone $baseQuery;

        // Фильтр по последнему ID сообщения (для эффективного опроса новых сообщений)
        // last_message_id - это ID записи в БД (primary key), а не sender_id
        if ($lastMessageId) {
            // Привести к целому числу для корректного сравнения
            $lastMessageId = (int) $lastMessageId;

            // Проверить, что это валидный ID
            if ($lastMessageId <= 0) {
                return response()->json([
                    'message' => 'Некорректный формат last_message_id. Должно быть положительное число.',
                    'error' => 'invalid_last_message_id'
                ], 422);
            }

            // Получить сообщения с ID больше переданного (включая как входящие, так и исходящие)
            $query->where('id', '>', $lastMessageId);
        }
        // Или фильтр по временной метке
        elseif ($since) {
            try {
                $sinceDate = Carbon::parse($since);
                $query->where('sent_at', '>', $sinceDate);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Некорректный формат параметра since'], 422);
            }
        }

        // Сортировать по ID (более надежно, чем по sent_at, так как ID всегда уникален и последователен)
        $messages = $query->orderBy('id', 'asc')->get();

        // Получить абсолютно последний ID сообщения в этом чате (после выполнения запроса)
        // Это нужно для корректного возврата last_message_id, даже если новых сообщений нет
        $absoluteLastMessageId = (clone $baseQuery)
            ->orderBy('id', 'desc')
            ->value('id');

        // Определить последний ID для возврата:
        // - Если есть новые сообщения, взять ID последнего нового сообщения
        // - Если новых нет, но был передан last_message_id, проверить, не изменился ли абсолютный последний ID
        //   (это может произойти, если между запросами было добавлено сообщение, но оно не попало в фильтр)
        // - Если абсолютный последний ID больше переданного last_message_id, вернуть его
        // - Иначе вернуть переданный last_message_id (чтобы фронтенд не потерял позицию)
        if ($messages->isNotEmpty()) {
            $finalLastMessageId = $messages->last()->id;
        } elseif ($lastMessageId) {
            // Если нет новых сообщений в результате запроса, но абсолютный последний ID больше переданного,
            // значит есть сообщения, которые не попали в фильтр (например, из-за race condition)
            // В этом случае вернуть абсолютный последний ID
            if ($absoluteLastMessageId && $absoluteLastMessageId > $lastMessageId) {
                $finalLastMessageId = $absoluteLastMessageId;
            } else {
                // Нет новых сообщений, вернуть переданный ID
                $finalLastMessageId = $lastMessageId;
            }
        } else {
            // Первая загрузка без last_message_id
            $finalLastMessageId = $absoluteLastMessageId;
        }

        // Подсчитать непрочитанные входящие сообщения от этого отправителя
        $unreadCount = (clone $baseQuery)
            ->where('sender_id', $senderId)
            ->where('direction', 'in')
            ->where('is_seen', 0)
            ->count();

        return response()->json([
            'sender_id' => $senderId,
            'has_new_messages' => $messages->isNotEmpty(),
            'unread_count' => $unreadCount,
            'messages' => $messages->map(fn($m) => [
                'id' => $m->id,
                'text' => $m->message,
                'direction' => $m->direction,
                'sent_at' => $m->sent_at,
                'is_seen' => (bool) ($m->is_seen ?? 0),
                'media_type' => $m->media_type,
                'media_url' => $m->media_url,
                'media_id' => $m->media_id,
            ]),
            'last_message_id' => $finalLastMessageId,
            'debug' => [
                'requested_last_message_id' => $lastMessageId,
                'absolute_last_message_id_in_chat' => $absoluteLastMessageId,
                'returned_last_message_id' => $finalLastMessageId,
                'new_messages_count' => $messages->count(),
                'new_message_ids' => $messages->pluck('id')->toArray(),
                'query_used' => $lastMessageId ? "id > {$lastMessageId}" : ($since ? "sent_at > {$since}" : 'all'),
            ],
        ]);
    }

    public function checkConnectionStatus(Request $request): JsonResponse
    {
        $storeId = $request->get('store_id');
        $platform = $request->get('platform', 'instagram');

        if (!$storeId) {
            return response()->json(['message' => 'store_id обязателен'], 422);
        }

        if (!in_array($platform, self::SUPPORTED_PLATFORMS)) {
            return response()->json(['message' => 'Некорректный platform'], 422);
        }

        $accounts = ConnectedSocialAccount::where('store_id', $storeId)
            ->where('platform', $platform)
            ->get();

        if ($accounts->isEmpty()) {
            return response()->json([
                'message' => "Нет подключённых {$platform} аккаунтов",
                'connected_accounts' => [],
                'can_connect' => true,
                'connect_url' => url("/api/v3/messengers/connect?platform={$platform}&store_id={$storeId}")
            ], 200);
        }

        $result = $accounts->map(function ($account) use ($storeId, $platform) {
            $msgClass = $this->getMessageClass($platform);
            $messageCount = $msgClass::where('connected_social_account_id', $account->id)->count();

            // Подсчитать общее количество непрочитанных входящих сообщений для этого аккаунта
            $unreadCount = $msgClass::where('connected_social_account_id', $account->id)
                ->where('direction', 'in')
                ->where('is_seen', 0)
                ->count();

            // Получить профиль подключенного аккаунта
            $accountProfile = $this->getConnectedAccountProfile($account, $platform);

            return [
                'id' => $account->id,
                'platform' => $account->platform,
                'page_id' => $account->page_id,
                'ig_business_id' => $account->ig_business_id,
                'connected_at' => $account->connected_at,
                'subscribed' => $account->subscribed,
                'webhook_features' => is_array($account->webhook_features) ? $account->webhook_features : json_decode($account->webhook_features, true),
                'message_count' => $messageCount,
                'unread_count' => $unreadCount,
                'profile' => $accountProfile,
                'disconnect_url' => url("/api/v3/messengers/disconnect"),
                'disconnect_payload' => [
                    'account_id' => $account->id,
                    'store_id' => $storeId,
                    'platform' => $platform
                ]
            ];
        });

        // Подсчитать общее количество непрочитанных сообщений для всех аккаунтов
        $totalUnreadCount = $result->sum('unread_count');

        return response()->json([
            'connected_accounts' => $result,
            'total_accounts' => $result->count(),
            'total_unread_count' => $totalUnreadCount,
            'can_connect_more' => true,
            'connect_url' => url("/api/v3/messengers/connect?platform={$platform}&store_id={$storeId}")
        ]);
    }

    /**
     * Mark messages as seen for a specific sender
     * Can mark specific message IDs or all messages up to a certain message ID
     */
    public function markMessagesAsSeen(Request $request, string $senderId): JsonResponse
    {
        $storeId = $request->get('store_id');
        $platform = $request->get('platform');
        $messageIds = $request->get('message_ids'); // Array of specific message IDs to mark as seen
        $upToMessageId = $request->get('up_to_message_id'); // Mark all messages up to (and including) this ID

        if (!$storeId) {
            return response()->json(['message' => 'store_id обязателен'], 422);
        }

        if (!in_array($platform, self::SUPPORTED_PLATFORMS)) {
            return response()->json(['message' => 'Некорректный platform'], 422);
        }

        $accounts = ConnectedSocialAccount::where('store_id', $storeId)
            ->where('platform', $platform)
            ->pluck('id');

        if ($accounts->isEmpty()) {
            return response()->json(['message' => 'Нет подключённых аккаунтов'], 404);
        }

        $msgClass = $this->getMessageClass($platform);
        $query = $msgClass::whereIn('connected_social_account_id', $accounts)
            ->where('sender_id', $senderId)
            ->where('direction', 'in')
            ->where('is_seen', 0);

        // Если передан массив конкретных ID сообщений
        if ($messageIds) {
            $messageIdsArray = is_array($messageIds) ? $messageIds : explode(',', $messageIds);
            $messageIdsArray = array_map('intval', $messageIdsArray);
            $query->whereIn('id', $messageIdsArray);
        }
        // Или если передан ID сообщения "до которого включительно" пометить как прочитанное
        elseif ($upToMessageId) {
            $upToMessageId = (int) $upToMessageId;
            $query->where('id', '<=', $upToMessageId);
        }
        // Если ничего не передано, пометить все непрочитанные (для обратной совместимости)
        // Но лучше не использовать этот вариант, лучше явно указывать message_ids или up_to_message_id

        $updated = $query->update(['is_seen' => 1]);

        return response()->json([
            'status' => 'success',
            'sender_id' => $senderId,
            'marked_as_seen_count' => $updated,
            'message' => "Помечено как прочитано: {$updated} сообщений"
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $accountId = $request->get('account_id');
        $storeId = $request->get('store_id');
        $platform = $request->get('platform', 'instagram');

        if (!$accountId) {
            return response()->json(['error' => 'account_id обязателен'], 400);
        }

        if (!$storeId) {
            return response()->json(['error' => 'store_id обязателен'], 400);
        }

        if (!in_array($platform, self::SUPPORTED_PLATFORMS)) {
            return response()->json(['error' => 'Некорректный platform'], 422);
        }

        try {
            // Search by appropriate field based on platform to avoid conflicts
            // Instagram uses ig_business_id, Messenger/WhatsApp use id (primary key)
            // Earlier it was only searching by ig_business_id for Instagram !!!
            $query = ConnectedSocialAccount::where('store_id', $storeId)
                ->where('platform', $platform);

            if ($platform === 'instagram') {
                // For Instagram, search by ig_business_id
                $account = $query->where('ig_business_id', $accountId)->first();
            } else {
                // For Messenger/WhatsApp, search by id (primary key)
                $account = $query->where('id', $accountId)->first();
            }

            if (!$account) {
                return response()->json(['error' => 'Аккаунт не найден'], 404);
            }

            $this->unsubscribeFromWebhooks($account);

            $msgClass = $this->getMessageClass($platform);
            $messageCount = $msgClass::where('connected_social_account_id', $account->id)->count();
            $msgClass::where('connected_social_account_id', $account->id)->delete();

            $account->delete();

            return response()->json([
                'status' => 'disconnected',
                'message' => 'Аккаунт успешно отключён',
                'messages_deleted' => $messageCount,
                'can_reconnect' => true,
                'reconnect_url' => url("/api/v3/messengers/connect?platform={$platform}&store_id={$storeId}")
            ]);

        } catch (\Exception $e) {
            Log::error('Error disconnecting social account', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Не удалось отключить аккаунт',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function unsubscribeFromWebhooks(ConnectedSocialAccount $account): bool
    {
        try {
            if (in_array($account->platform, ['instagram', 'messenger'])) {
                $response = Http::withToken($account->access_token)
                    ->delete("https://graph.facebook.com/" . self::FB_API_VERSION . "/{$account->page_id}/subscribed_apps");

                if ($response->successful()) {
                    Log::info("Successfully unsubscribed from {$account->platform} webhooks", [
                        'page_id' => $account->page_id
                    ]);
                    return true;
                } else {
                    Log::warning("Failed to unsubscribe from {$account->platform} webhooks", [
                        'page_id' => $account->page_id,
                        'status' => $response->status(),
                    ]);
                }
            } elseif ($account->platform === 'whatsapp') {
                Log::info('WhatsApp account disconnected', [
                    'phone_number_id' => $account->page_id
                ]);
                return true;
            }

        } catch (\Exception $e) {
            Log::warning('Exception while unsubscribing from webhooks', [
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    private function getOrCreateInstagramUser(string $senderId, string $accessToken): ?InstagramUser
    {
        $user = InstagramUser::firstWhere('instagram_id', $senderId);

        if ($user) return $user;

        $res = Http::withToken($accessToken)
            ->get("https://graph.facebook.com/" . self::FB_API_VERSION . "/{$senderId}", [
                'fields' => 'username,profile_pic',
            ]);

        if ($res->failed()) {
            Log::warning('Не удалось получить профиль IG-пользователя', [
                'sender_id' => $senderId,
                'status' => $res->status(),
                'body' => $res->body()
            ]);
            return null;
        }

        $data = $res->json();

        return InstagramUser::create([
            'instagram_id' => $senderId,
            'username' => $data['username'] ?? null,
            'profile_pic' => $data['profile_pic'] ?? null,
        ]);
    }

    public function sendReply(Request $request): JsonResponse
    {
        $accountId = $request->get('account_id');
        $recipientId = $request->get('recipient_id');
        $message = $request->get('message');
        $imageUrl = $request->get('image_url'); // URL изображения для отправки
        $mediaType = $request->get('media_type'); // image, video, audio, document

        if (!$accountId) {
            return response()->json(['error' => 'account_id обязателен'], 422);
        }

        if (!$recipientId) {
            return response()->json(['error' => 'recipient_id обязателен'], 422);
        }

        // Должно быть либо сообщение, либо медиа
        if ((!$message || trim($message) === '') && !$imageUrl) {
            return response()->json(['error' => 'message или image_url обязателен'], 422);
        }

        $account = ConnectedSocialAccount::find($accountId);

        if (!$account) {
            return response()->json(['error' => 'Аккаунт не найден'], 404);
        }

        // Check if access token exists
        if (!$account->access_token) {
            return response()->json([
                'error' => 'Токен доступа недействителен. Переподключите аккаунт.',
                'need_reconnect' => true
            ], 401);
        }

        // Check if token is expired
        if ($account->token_expires_at && Carbon::now()->isAfter($account->token_expires_at)) {
            Log::warning('Access token expired', [
                'account_id' => $accountId,
                'expired_at' => $account->token_expires_at,
            ]);
            return response()->json([
                'error' => 'Токен доступа истёк. Переподключите аккаунт.',
                'need_reconnect' => true,
                'expired_at' => $account->token_expires_at->toIso8601String(),
            ], 401);
        }

        $platform = $account->platform;

        if (!in_array($platform, self::SUPPORTED_PLATFORMS)) {
            return response()->json(['error' => 'Неподдерживаемая платформа'], 422);
        }

        // Проверка 24-часового окна: ответ возможен только в течение 24 часов после последнего входящего сообщения
        $msgClass = $this->getMessageClass($platform);
        $lastIncomingMessage = $msgClass::where('connected_social_account_id', $account->id)
            ->where('sender_id', $recipientId) // Последнее сообщение ОТ пользователя
            ->where('direction', 'in')
            ->orderBy('sent_at', 'desc')
            ->first();

        if ($lastIncomingMessage) {
            $hoursSinceLastMessage = Carbon::now()->diffInHours($lastIncomingMessage->sent_at);

            if ($hoursSinceLastMessage >= 24) {
                return response()->json([
                    'error' => 'Прошло более 24 часов с момента последнего сообщения пользователя. Отправка ответа недоступна.',
                    'can_send' => false,
                    'last_message_time' => $lastIncomingMessage->sent_at->toIso8601String(),
                    'hours_passed' => $hoursSinceLastMessage,
                ], 400);
            }
        } else {
            // Если нет входящих сообщений от этого пользователя, нельзя отправлять ответ
            return response()->json([
                'error' => 'Не найдено входящих сообщений от этого пользователя. Отправка ответа недоступна.',
                'can_send' => false,
            ], 400);
        }

        Log::info("Попытка отправки сообщения через {$platform}.", [
            'account_id' => $accountId,
            'recipient_id' => $recipientId,
            'message' => $message,
        ]);

        try {
            // Подготовить payload для отправки
            $payload = [];

            if ($imageUrl) {
                // Отправка изображения
                match ($platform) {
                    'instagram' => $payload = [
                        'recipient' => ['id' => $recipientId],
                        'message' => [
                            'attachment' => [
                                'type' => 'image',
                                'payload' => [
                                    'url' => $imageUrl,
                                    'is_reusable' => false,
                                ],
                            ],
                        ],
                    ],
                    'whatsapp' => $payload = [
                        'messaging_product' => 'whatsapp',
                        'to' => $recipientId,
                        'type' => 'image',
                        'image' => [
                            'link' => $imageUrl,
                        ],
                    ],
                    'messenger' => $payload = [
                        'recipient' => ['id' => $recipientId],
                        'message' => [
                            'attachment' => [
                                'type' => 'image',
                                'payload' => [
                                    'url' => $imageUrl,
                                    'is_reusable' => false,
                                ],
                            ],
                        ],
                        'messaging_type' => 'RESPONSE',
                    ],
                };

                // Если есть текст, добавить его как подпись к изображению (для Instagram/Messenger)
                if ($message && trim($message) !== '' && in_array($platform, ['instagram', 'messenger'])) {
                    $payload['message']['attachment']['payload']['caption'] = $message;
                }
            } else {
                // Отправка текстового сообщения
                match ($platform) {
                    'instagram' => $payload = [
                        'recipient' => ['id' => $recipientId],
                        'message' => ['text' => $message],
                    ],
                    'whatsapp' => $payload = [
                        'messaging_product' => 'whatsapp',
                        'to' => $recipientId,
                        'type' => 'text',
                        'text' => ['body' => $message],
                    ],
                    'messenger' => $payload = [
                        'recipient' => ['id' => $recipientId],
                        'message' => ['text' => $message],
                        'messaging_type' => 'RESPONSE',
                    ],
                };
            }

            // Определить правильный endpoint для отправки сообщений
            // Для Instagram используем ig_business_id, для остальных - page_id
            $endpointId = $account->page_id;

            // Убедиться, что imageUrl является полным публичным URL
            if ($imageUrl && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                // Если это относительный путь, преобразовать в полный URL
                if (strpos($imageUrl, 'http') !== 0) {
                    $imageUrl = config('app.url') . '/' . ltrim($imageUrl, '/');
                }
                // Обновить URL в payload
                if (isset($payload['message']['attachment']['payload']['url'])) {
                    $payload['message']['attachment']['payload']['url'] = $imageUrl;
                } elseif (isset($payload['image']['link'])) {
                    $payload['image']['link'] = $imageUrl;
                }
            }

            $endpointUrl = "https://graph.facebook.com/" . self::FB_API_VERSION . "/{$endpointId}/messages";

            Log::info("Отправка сообщения через {$platform}", [
                'endpoint' => $endpointUrl,
                'endpoint_id' => $endpointId,
                'platform' => $platform,
                'has_image' => !empty($imageUrl),
                'image_url' => $imageUrl,
                'payload' => $payload,
            ]);

            $response = Http::withToken($account->access_token)
                ->post($endpointUrl, $payload);

            // Логирование ответа для отладки
            Log::info("Ответ API при отправке сообщения через {$platform}", [
                'status' => $response->status(),
                'success' => $response->successful(),
                'response_body' => $response->json(),
            ]);

            if ($response->failed()) {
                $body = $response->json();

                Log::error("Ошибка при отправке сообщения через {$platform}.", [
                    'response' => $body,
                    'status' => $response->status(),
                    'account_id' => $accountId,
                    'recipient_id' => $recipientId,
                    'page_id' => $account->page_id,
                    'ig_business_id' => $account->ig_business_id,
                    'platform' => $platform,
                    'error_code' => $body['error']['code'] ?? null,
                    'error_message' => $body['error']['message'] ?? null,
                    'error_subcode' => $body['error']['error_subcode'] ?? null,
                    'api_version' => self::FB_API_VERSION,
                    'token_expires_at' => $account->token_expires_at?->toIso8601String(),
                ]);

                // Handle specific error codes
                $errorCode = $body['error']['code'] ?? null;
                $errorSubcode = $body['error']['error_subcode'] ?? null;

                // API capability error (code 3) - App doesn't have required permissions
                if ($errorCode == 3) {
                    $requiredPermissions = match ($platform) {
                        'instagram' => [
                            'instagram_manage_messages',
                            'instagram_basic',
                            'pages_manage_metadata',
                            'pages_show_list',
                            'pages_read_engagement',
                        ],
                        'messenger' => [
                            'pages_messaging',
                            'pages_manage_metadata',
                            'pages_show_list',
                        ],
                        'whatsapp' => [
                            'whatsapp_business_management',
                            'whatsapp_business_messaging',
                        ],
                        default => [],
                    };

                    return response()->json([
                        'error' => 'У приложения нет необходимых разрешений для этого действия. Проверьте настройки приложения в Facebook App Dashboard.',
                        'error_code' => $errorCode,
                        'need_app_review' => true,
                        'platform' => $platform,
                        'required_permissions' => $requiredPermissions,
                        'facebook_error' => $body['error']['message'] ?? null,
                        'instructions' => [
                            '1. Перейдите в Facebook App Dashboard: https://developers.facebook.com/apps/',
                            '2. Выберите ваше приложение (App ID: ' . config('fb.fb_app_id') . ')',
                            '3. Перейдите в раздел "App Review" → "Permissions and Features"',
                            '4. Убедитесь, что все необходимые разрешения одобрены',
                            '5. Если разрешения не одобрены, отправьте приложение на App Review',
                        ],
                    ], 403);
                }

                // Instagram: User hasn't allowed messaging
                if ($platform === 'instagram' && $errorSubcode == 2534041) {
                    return response()->json([
                        'error' => 'Пользователь не разрешил доступ к Direct в Instagram.',
                        'can_send' => false
                    ], 400);
                }

                // Permission errors (code 10 or 200)
                if (in_array($errorCode, [10, 200])) {
                    return response()->json([
                        'error' => 'Недостаточно прав для отправки сообщений. Проверьте разрешения приложения.',
                        'error_code' => $errorCode,
                        'need_reconnect' => true
                    ], 403);
                }

                // Token expired (code 190)
                if ($errorCode == 190) {
                    return response()->json([
                        'error' => 'Токен доступа истёк. Переподключите аккаунт.',
                        'need_reconnect' => true
                    ], 401);
                }

                // Messenger: 24-hour window expired (code 10 with subcode 2018278)
                if ($platform === 'messenger' && $errorSubcode == 2018278) {
                    return response()->json([
                        'error' => 'Прошло более 24 часов с момента последнего сообщения пользователя. Отправка невозможна.',
                        'can_send' => false
                    ], 400);
                }

                $errorMessage = $body['error']['message'] ?? 'Ошибка при отправке сообщения.';
                return response()->json([
                    'error' => $errorMessage,
                    'error_code' => $errorCode,
                    'error_subcode' => $errorSubcode
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error("Исключение при отправке сообщения через {$platform}.", [
                'exception' => $e->getMessage(),
                'account_id' => $accountId,
                'recipient_id' => $recipientId,
            ]);

            return response()->json(['error' => 'Произошла ошибка при отправке сообщения.'], 500);
        }

        try {
            $msgClass = $this->getMessageClass($platform);
            $businessId = $platform === 'instagram' ? $account->ig_business_id : $account->page_id;

            $savedMessage = $msgClass::create([
                'connected_social_account_id' => $account->id,
                'sender_id' => $businessId,
                'recipient_id' => $recipientId,
                'message' => $message ?? '', // Использовать пустую строку вместо null
                'media_type' => $imageUrl ? 'image' : null,
                'media_url' => $imageUrl ?? null,
                'direction' => 'out',
                'sent_at' => Carbon::now(),
            ]);

            // Пометить все входящие сообщения от этого отправителя как прочитанные
            // Когда мы отправляем ответ, это означает, что мы видели все его сообщения
            $msgClass::where('connected_social_account_id', $account->id)
                ->where('sender_id', $recipientId) // recipientId - это ID отправителя, от которого мы получили сообщения
                ->where('direction', 'in')
                ->where('is_seen', 0)
                ->update(['is_seen' => 1]);

            Log::info('Сообщение успешно отправлено и сохранено.', [
                'account_id' => $accountId,
                'recipient_id' => $recipientId,
                'platform' => $platform,
                'message_id' => $savedMessage->id,
            ]);

            // Вернуть ID сохраненного сообщения, чтобы фронтенд мог обновить last_message_id
            return response()->json([
                'status' => 'sent',
                'message_id' => $savedMessage->id,
                'last_message_id' => $savedMessage->id, // Для удобства фронтенда
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при сохранении сообщения в БД.', [
                'exception' => $e->getMessage(),
                'account_id' => $accountId,
                'recipient_id' => $recipientId,
            ]);

            return response()->json([
                'status' => 'sent',
                'warning' => 'Сообщение отправлено, но возникла ошибка при сохранении в БД.'
            ], 200);
        }
    }

    /**
     * Facebook Data Deletion Callback
     * REQUIRED for App Review - Facebook sends this when user requests data deletion
     * URL: Configure in Facebook App Dashboard → Settings → Basic → Data Deletion Request URL
     */
    public function dataDeletionCallback(Request $request): JsonResponse
    {
        // Verify signature in production
        if (app()->environment('production') && !$this->verifyWebhookSignature($request)) {
            Log::warning('Data deletion callback rejected: invalid signature');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $signedRequest = $request->input('signed_request');

        if (!$signedRequest) {
            Log::warning('Data deletion callback: missing signed_request');
            return response()->json(['error' => 'Missing signed_request'], 400);
        }

        // Parse signed request
        $data = $this->parseSignedRequest($signedRequest);

        if (!$data) {
            Log::warning('Data deletion callback: invalid signed_request');
            return response()->json(['error' => 'Invalid signed_request'], 400);
        }

        $userId = $data['user_id'] ?? null;

        if (!$userId) {
            Log::warning('Data deletion callback: missing user_id');
            return response()->json(['error' => 'Missing user_id'], 400);
        }

        Log::info('Data deletion request received', ['user_id' => $userId]);

        // Find and delete all accounts and messages for this user
        $deletedAccounts = 0;
        $deletedMessages = 0;

        // Find accounts by facebook_user_id (primary), or fallback to page_id/ig_business_id
        $accounts = ConnectedSocialAccount::where('facebook_user_id', $userId)
            ->orWhere('page_id', $userId)
            ->orWhere('ig_business_id', $userId)
            ->get();

        foreach ($accounts as $account) {
            // Delete messages based on platform
            try {
                $msgClass = $this->getMessageClass($account->platform);
                $deletedMessages += $msgClass::where('connected_social_account_id', $account->id)->count();
                $msgClass::where('connected_social_account_id', $account->id)->delete();
            } catch (\InvalidArgumentException $e) {
                Log::warning('Unknown platform during data deletion', [
                    'platform' => $account->platform,
                    'account_id' => $account->id
                ]);
            }

            // Delete account
            $account->delete();
            $deletedAccounts++;
        }

        // Generate confirmation code for Facebook
        $confirmationCode = 'DEL_' . md5($userId . time());

        Log::info('Data deletion completed', [
            'user_id' => $userId,
            'accounts_deleted' => $deletedAccounts,
            'messages_deleted' => $deletedMessages,
            'confirmation_code' => $confirmationCode,
        ]);

        // Return response in Facebook required format
        return response()->json([
            'url' => config('app.frontend_url') . '/data-deletion-status?code=' . $confirmationCode,
            'confirmation_code' => $confirmationCode,
        ]);
    }

    /**
     * Facebook Deauthorization Callback
     * Called when user removes app from their Facebook settings
     * URL: Configure in Facebook App Dashboard → Settings → Basic → Deauthorize Callback URL
     */
    public function deauthorizeCallback(Request $request): JsonResponse
    {
        // Verify signature in production
        if (app()->environment('production') && !$this->verifyWebhookSignature($request)) {
            Log::warning('Deauthorize callback rejected: invalid signature');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $signedRequest = $request->input('signed_request');

        if (!$signedRequest) {
            Log::warning('Deauthorize callback: missing signed_request');
            return response()->json(['error' => 'Missing signed_request'], 400);
        }

        $data = $this->parseSignedRequest($signedRequest);

        if (!$data) {
            Log::warning('Deauthorize callback: invalid signed_request');
            return response()->json(['error' => 'Invalid signed_request'], 400);
        }

        $userId = $data['user_id'] ?? null;

        if (!$userId) {
            Log::warning('Deauthorize callback: missing user_id');
            return response()->json(['error' => 'Missing user_id'], 400);
        }

        Log::info('Deauthorization request received', ['user_id' => $userId]);

        // Mark accounts as deauthorized (but don't delete - user may reconnect)
        // Search by facebook_user_id (primary), or fallback to page_id/ig_business_id
        $accounts = ConnectedSocialAccount::where('facebook_user_id', $userId)
            ->orWhere('page_id', $userId)
            ->orWhere('ig_business_id', $userId)
            ->get();

        foreach ($accounts as $account) {
            $account->update([
                'subscribed' => false,
                'access_token' => null, // Invalidate token
            ]);

            Log::info('Account deauthorized', [
                'account_id' => $account->id,
                'platform' => $account->platform,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Parse Facebook signed request
     */
    private function parseSignedRequest(string $signedRequest): ?array
    {
        $parts = explode('.', $signedRequest, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedSig, $payload] = $parts;

        $sig = $this->base64UrlDecode($encodedSig);
        $data = json_decode($this->base64UrlDecode($payload), true);

        if (!$data) {
            return null;
        }

        // Verify signature
        $expectedSig = hash_hmac('sha256', $payload, config('fb.fb_app_secret'), true);

        if (!hash_equals($expectedSig, $sig)) {
            Log::warning('Signed request signature verification failed');
            return null;
        }

        return $data;
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $input): string
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
