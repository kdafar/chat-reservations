<?php

namespace App\Wa\Services\WhatsApp;

use App\Wa\Http\Controllers\Api\HubController;
use App\Wa\Hub\Models\DeliveryArea;
use App\Wa\Hub\Models\HubBranch;
use App\Wa\Hub\Models\MessageTemplate;
use App\Wa\Hub\Models\Rating;
use App\Wa\Hub\Models\Vendors;
use App\Wa\Hub\Models\WhatsappSession;
use App\Wa\Services\About\AboutService;
use App\Wa\Services\Order\OrderHistoryService;
use App\Wa\Services\Restaurant\RestaurantSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Wave\Setting;

class WhatsAppMessageHandler
{
    private WhatsAppService $whatsAppService;

    private WhatsAppFlowService $whatsAppFlowService;

    private RestaurantSearchService $restaurantSearchService;

    private OrderHistoryService $orderHistoryService;

    private AboutService $aboutService;

    public function __construct(WhatsAppService $whatsAppService, WhatsAppFlowService $whatsAppFlowService, RestaurantSearchService $restaurantSearchService, OrderHistoryService $orderHistoryService, AboutService $aboutService)
    {
        $this->whatsAppService = $whatsAppService;
        $this->whatsAppFlowService = $whatsAppFlowService;
        $this->restaurantSearchService = $restaurantSearchService;
        $this->orderHistoryService = $orderHistoryService;
        $this->aboutService = $aboutService;
    }

    public function handle(array $message): void
    {
        $customerPhone = $message['from'];

        // Ensure session exists
        $session = WhatsappSession::firstOrCreate(
            ['customer_phone_number' => $customerPhone],
            ['status' => 'active', 'locale' => 'en', 'last_interacted_at' => now()]
        );

        $session->update(['last_interacted_at' => now()]);

        // 1. EXTRACT BODY EARLY (still useful for routing + language detection)
        $type = $message['type'] ?? 'text';
        $bodyRaw = '';

        if ($type === 'text') {
            $bodyRaw = trim($message['text']['body'] ?? '');
        } elseif ($type === 'interactive') {
            // Check button clicks too
            $bodyRaw = $message['interactive']['button_reply']['id']
                ?? $message['interactive']['list_reply']['id']
                ?? '';
        }

        $lowBody = mb_strtolower($bodyRaw);

        // =========================================================
        // 2. NORMAL BOT LOGIC
        // =========================================================

        // --- START: LANGUAGE DETECTION LOGIC ---
        $currentLocale = $session->locale;

        if ($type === 'text' && ! empty($bodyRaw)) {
            // Check if the message contains any Arabic characters
            if (preg_match('/[\p{Arabic}]/u', $bodyRaw)) {
                if ($currentLocale !== 'ar') {
                    $currentLocale = 'ar';
                    $session->update(['locale' => 'ar']);
                    Log::info('Switched locale to AR based on user input.', ['phone' => $customerPhone]);
                }
            } else {
                // Optional: Switch back to English
                if ($currentLocale !== 'en') {
                    $currentLocale = 'en';
                    $session->update(['locale' => 'en']);
                    Log::info('Switched locale to EN based on user input.', ['phone' => $customerPhone]);
                }
            }
        }
        // --- END: LANGUAGE DETECTION LOGIC ---

        $locale = $currentLocale;

        $this->whatsAppService->markRead($customerPhone, $message['id']);

        // Check Mode (Banner vs Flow)
        $mode = Setting::get('whatsapp.entry_mode') ?? 'flow';
        Log::info('[WA Bot] Entry mode: '.$mode, [
            'phone' => $customerPhone,
            'locale' => $locale,
        ]);

        if ($mode === 'banner') {
            $this->handleBannerPitch($session, $message, $locale);

            return;
        }

        // ---------- ORIGINAL FLOW / RESTAURANT LOGIC BELOW ----------
        switch ($message['type']) {
            case 'text':
                if (in_array($lowBody, ['thanks', 'thank you', 'شكرا', 'شكراً'], true)) {
                    $this->whatsAppService->react($session->customer_phone_number, $message['id'], '👍');

                    return;
                }

                // Pass the already extracted body
                $this->routeCommand($session, $bodyRaw, $locale);
                break;

            case 'interactive':
                if (isset($message['interactive']['nfm_reply'])) {
                    $responseJson = $message['interactive']['nfm_reply']['response_json'] ?? '{}';
                    $response = json_decode($responseJson, true) ?? [];
                    $messageId = $message['id'];

                    if (isset($response['final_payload'])) {
                        // E-COMMERCE CHECKOUT FLOW
                        $this->handleFlowCompletion($session, $message['interactive']['nfm_reply'], $locale, $messageId);
                    } elseif (isset($response['rating'])) {
                        // RATING FLOW
                        $this->handleRatingFlowSubmission($session, $response, $message['from'], $messageId);
                    }
                } else {
                    $command = $message['interactive']['list_reply']['id']
                        ?? $message['interactive']['button_reply']['id']
                        ?? '';
                    $this->routeCommand($session, $command, $locale);
                }
                break;

            case 'location':
                $this->handleLocationMessage($session, $message['location'], $locale);
                break;

            case 'order':
                $this->handleNativeOrder($session, $message['order'], $locale);
                break;
        }
    }

    private function isDetailedRequirementMessage(string $body): bool
    {
        $body = trim($body);
        if ($body === '') {
            return false;
        }

        // 1. Length check
        if (mb_strlen($body) >= 120) {
            Log::info('[WA Bot] Stopped: Message too long ('.mb_strlen($body).' chars)');

            return true;
        }

        // 2. Fetch keywords
        $settingValue = Setting::get('whatsapp.detailed_requirement_keywords');

        if (is_array($settingValue)) {
            $keywords = $settingValue;
        } elseif (is_string($settingValue)) {
            $decoded = json_decode($settingValue, true);
            $keywords = is_array($decoded) ? $decoded : [];
        } else {
            $keywords = [];
        }

        // Fallback
        if (empty($keywords)) {
            Log::warning('[WA Bot] No keywords found in DB, using fallback.');
            $keywords = ['problem', 'complaint', 'issue', 'help', 'scam', 'fraud', 'money', 'refund', 'urgent', 'police', 'support', 'مشكلة', 'شكوى', 'فلوس', 'نصب', 'احتيال', 'مساعدة', 'ضروري', 'عاجل', 'شرطة'];
        }

        $lower = mb_strtolower($body, 'UTF-8');
        $hits = 0;
        $matchedWords = [];

        foreach ($keywords as $word) {
            if (! is_string($word)) {
                continue;
            }

            if (mb_strpos($lower, mb_strtolower(trim($word), 'UTF-8')) !== false) {
                $hits++;
                $matchedWords[] = $word;
            }
        }

        // DEBUG LOG: See what matches were found
        if ($hits > 0) {
            Log::info("[WA Bot] Keyword hits: $hits. Words: ".implode(', ', $matchedWords));
        }

        // Strict Logic:
        // If "problem", "complaint", "scam", "urgent" are found -> Stop immediately (1 hit is enough for these)
        // For softer words like "need", maybe require 2 hits.

        // For now, let's make it stricter: If ANY stop word is found, stop.
        if ($hits >= 1) {
            Log::info('[WA Bot] STOPPING execution. Reason: Stop keyword found.');

            return true;
        }

        return false;
    }

    private function handleBannerPitch(WhatsappSession $session, array $message, string $locale): void
    {
        $phone = $session->customer_phone_number;
        $type = $message['type'] ?? 'text';
        $bodyRaw = $type === 'text'
            ? trim($message['text']['body'] ?? '')
            : '';

        if (empty($bodyRaw)) {
            return;
        }

        // 1. Check for complex requirements (Stops bot if true)
        if ($this->isDetailedRequirementMessage($bodyRaw)) {
            Log::info("[WA Bot] Ignored message from $phone due to Stop Keywords.");

            return; // <--- This MUST return to stop the bot
        }

        // 2. Quick reaction for thanks
        $lowBody = mb_strtolower($bodyRaw);
        if (in_array($lowBody, ['thanks', 'thank you', 'شكرا', 'شكراً', 'jazaak', 'جزاك'], true)) {
            $this->whatsAppService->react($phone, $message['id'] ?? null, '🤲');

            return;
        }

        // ---------------------------------------------------------
        // 3. DYNAMIC TEMPLATE MATCHING (From Database)
        // ---------------------------------------------------------
        // Fetch approved auto-reply templates for the current language
        $templates = MessageTemplate::query()
            ->where('is_auto_reply', true)
            ->where('status', 'APPROVED')
            ->where('language', 'LIKE', $locale.'%') // Matches 'en', 'en_US', 'ar', etc.
            ->get();

        foreach ($templates as $template) {
            $triggers = $template->triggers ?? [];

            if (empty($triggers)) {
                continue;
            }

            foreach ($triggers as $trigger) {
                // Case-insensitive check
                if (str_contains($lowBody, mb_strtolower(trim($trigger)))) {

                    // --- MATCH FOUND! Determine media type ---
                    $mediaType = null;
                    $components = $template->components ?? [];

                    foreach ($components as $comp) {
                        if (($comp['type'] ?? '') === 'HEADER') {
                            $format = $comp['format'] ?? 'TEXT';
                            if ($format === 'VIDEO') {
                                $mediaType = 'video';
                            }
                            if ($format === 'IMAGE') {
                                $mediaType = 'image';
                            }
                            if ($format === 'DOCUMENT') {
                                $mediaType = 'document';
                            }
                        }
                    }

                    // --- PREPARE DYNAMIC VARIABLES ---
                    // Extract data from the new auto_reply_data JSON column
                    $autoReplyData = $template->auto_reply_data ?? [];

                    // 1. Header Variables (e.g., {{1}})
                    $headerVars = [];
                    if (! empty($autoReplyData['header_1'])) {
                        $headerVars[] = $autoReplyData['header_1'];
                    }

                    // 2. Body Variables (sort keys body_1, body_2 to ensure correct order)
                    $bodyVars = [];
                    foreach ($autoReplyData as $key => $value) {
                        if (str_starts_with($key, 'body_')) {
                            // Extract the number: body_1 -> 1, body_10 -> 10
                            $index = (int) filter_var($key, FILTER_SANITIZE_NUMBER_INT);
                            $bodyVars[$index] = $value;
                        }
                    }
                    ksort($bodyVars); // Ensure strict 1, 2, 3 order

                    // Fallback: If no body vars in auto_reply_data, use legacy campaign_link if available
                    if (empty($bodyVars) && ! empty($template->campaign_link)) {
                        $bodyVars[] = $template->campaign_link;
                    } elseif (empty($bodyVars)) {
                        // Optional: Default fallback if absolutely nothing is set
                        $bodyVars[] = 'https://atf-kw.org/donate';
                    }

                    // Structure parameters for the service
                    // We pass a structured array so sendRichTemplate can distinguish Header vs Body vars
                    $templateParams = [
                        'header' => $headerVars,
                        'body' => array_values($bodyVars), // Re-index to 0,1,2... for clean array
                    ];

                    // Send using the new sendRichTemplate method
                    $this->whatsAppService->sendRichTemplate(
                        $phone,
                        $template->name,
                        $template->language,
                        $template->campaign_media_url, // URL via Model Accessor
                        $mediaType,
                        $templateParams // Passing structured data ['header' => [...], 'body' => [...]]
                    );

                    return; // Stop processing after first match
                }
            }
        }

        // ---------------------------------------------------------
        // 4. STANDARD FALLBACK LOGIC (Settings DB)
        // ---------------------------------------------------------

        $isGreeting = preg_match('/\b(hi|hello|hey|salam)\b/i', $bodyRaw)
            || preg_match('/السلام عليكم|هلا|مرحبا/u', $bodyRaw);

        // General Donation Keywords (if no specific campaign matched above)
        $isDonation = preg_match('/donate|zakat|sadaqah|project|cost|price|bank/i', $bodyRaw)
            || preg_match('/تبرع|زكاة|صدقة|مشروع|مشاريع|حساب|ايبان|تكلفة|سعر/u', $bodyRaw);

        $isPrivacy = preg_match('/privacy|number|where did you get/i', $bodyRaw)
            || preg_match('/من وين لكم رقمي|من وين تطلعون|من وين جبتو رقمي|خصوصية/u', $bodyRaw);

        $isAbout = preg_match('/who are you|website|link|license|location|about/i', $bodyRaw)
            || preg_match('/من انتم|منو انتو|موقع|رابط|ترخيص|مكان|عن الجمعية/u', $bodyRaw);

        // A) Privacy Reply
        if ($isPrivacy) {
            $msg = $locale === 'ar'
                ? (Setting::get('whatsapp.privacy_reply_ar') ?: "نقدر ونحترم خصوصيتك كمتبرع 🤝\n\nعادةً نتواصل مع الأرقام من خلال:\n متبرعين سابقين أو أشخاص سجلوا اهتمامهم بأنشطتنا.\n قوائم من شركاء الخير أو فعاليات سابقة.\n\nإذا ما تحب تستلم رسائل مستقبلاً، فقط اكتب *إيقاف* ونحذف رقمك فوراً.")
                : (Setting::get('whatsapp.privacy_reply_en') ?: "We deeply respect your privacy as a donor 🤝\n\nWe usually contact numbers from:\n People who have previously donated or registered with us.\n Lists from our charitable partners.\n\nIf you prefer not to receive updates, just reply with *STOP* and we will remove your number immediately.");

            $this->whatsAppService->sendTextMessage($phone, $msg);

            return;
        }

        // B) About Us Reply
        if ($isAbout) {
            $msg = $locale === 'ar'
                ? (Setting::get('whatsapp.about_reply_ar') ?: "نحن *جمعية عطف الخيرية*، مؤسسة كويتية مرخصة تسعى لمد يد العون للمحتاجين 🇰🇼\n\nنركز على:\n *مشاريع تنموية:* حفر آبار، بناء مساجد.\n *إغاثة عاجلة:* طعام، كسوة شتاء.\n *داخل الكويت:* رعاية الأسر المتعففة.\n\n📍 *المقر:* الكويت\n🌐 *موقعنا:* https://atf-kw.org/")
                : (Setting::get('whatsapp.about_reply_en') ?: "We are *Ataf Charity Society*, a licensed Kuwaiti organization dedicated to humanitarian work 🇰🇼\n\nWe focus on:\n *Sustainable Development:* Water wells, mosques.\n *Urgent Relief:* Food, winter supplies.\n *Local Support:* Helping families inside Kuwait.\n\n📍 *Based in:* Kuwait\n🌐 *Visit us:* https://atf-kw.org/");

            $this->whatsAppService->sendTextMessage($phone, $msg);

            return;
        }

        // C) General Donation Reply (Fallback if no dynamic template matched)
        if ($isDonation) {
            $msg = $locale === 'ar'
                ? (Setting::get('whatsapp.pricing_reply_ar') ?: "حالياً لدينا عدة أبواب للخير تقدر تساهم فيها 🌍\n\n💧 *سقيا الماء:* حفر الآبار.\n🍞 *إغاثة عاجلة:* سلات غذائية ودفء الشتاء.\n🇰🇼 *داخل الكويت:* إطعام الأسر المتعففة.\n\n*أفضل شيء:*\n1️⃣ اكتب نوع المشروع (مثلاً: *آبار*، *زكاة*).\n2️⃣ أو حدد المبلغ اللي ببالك.\n\nوراح نرسل لك رابط التبرع المباشر 🔗")
                : (Setting::get('whatsapp.pricing_reply_en') ?: "Right now, we have several urgent projects you can support 🌍\n\n💧 *Water & Wells:* Digging wells.\n🍞 *Relief Aid:* Food baskets and winter supplies.\n🇰🇼 *Inside Kuwait:* Helping families in need.\n\n*Best next step:*\n1️⃣ Tell me which project interests you (e.g., *Wells*, *Zakat*).\n2️⃣ Or let me know your donation budget.\n\nThen I’ll send you the direct donation link 🔗");

            $this->whatsAppService->sendTextMessage($phone, $msg);

            return;
        }

        // D) Default Greeting / Menu
        if ($isGreeting || $bodyRaw === '' || $lowBody === 'menu' || $lowBody === 'قائمة') {
            $msg = $locale === 'ar'
                ? (Setting::get('whatsapp.banner_greeting_ar') ?: "السلام عليكم ورحمة الله وبركاته 🌙\n\nأهلاً وسهلاً في *جمعية عطف الخيرية* 🇰🇼\n\nنحن جمعية مرخصة نسعى لمد يد العون عبر مشاريع إنسانية متنوعة.\n\nاكتب مثلاً:\n*كيف أتبرع بالزكاة؟*\n*عندكم مشاريع آبار؟*\n*من أنتم؟*\n\nأو اكتب *هلا* بس، ونرسلك التفاصيل.")
                : (Setting::get('whatsapp.banner_greeting_en') ?: "Hi 👋\n\nWelcome to *Ataf Charity Society* 🇰🇼\n\nWe are a licensed charity dedicated to humanitarian aid and supporting families in need.\n\nYou can ask things like:\n*How can I donate Zakat?*\n*Do you have water well projects?*\n*Who are you?*\n\nOr just type *Menu*.");

            $this->whatsAppService->sendTextMessage($phone, $msg);

            return;
        }

        // E) General Fallback
        $msg = $locale === 'ar'
            ? (Setting::get('whatsapp.fallback_reply_ar') ?: "حياكم الله 🌹\nاكتب لنا: *تبرع* أو *مشاريع* لنرسل لك قائمة أبواب الخير المتاحة.")
            : (Setting::get('whatsapp.fallback_reply_en') ?: "Welcome 🌹\nType *Donate* or *Projects* to see how you can help.");

        $this->whatsAppService->sendTextMessage($phone, $msg);
    }

    private function routeCommand(WhatsappSession $session, string $command, string $locale): void
    {
        // 1) Normalize
        $command = trim($command);
        $lower = mb_strtolower($command);

        // Commands that should always re-start the flow
        $resetCommands = ['hi', 'hello', 'start', 'menu', 'مرحبا', 'القائمة'];

        // 2) Hard reset → start full restaurant flow
        if (in_array($lower, $resetCommands, true)) {
            $this->startNewOrder($session, $locale);

            return;
        }

        // 3) About / عن / حول → existing logic
        if (preg_match('/^(about|عن|حول)(?:\s+(.*))?$/u', $lower, $m)) {
            $query = trim($m[2] ?? '');

            // 3.1 "about" or "about hub/you"
            if ($query === '' || in_array(\Illuminate\Support\Str::lower($query), ['hub', 'you', 'u', 'انت', 'انتم'], true)) {
                $card = $this->aboutService->buildHubCard($locale, 'whatsapp');
                if (! $card) {
                    $this->whatsAppService->sendTextMessage(
                        $session->customer_phone_number,
                        $locale === 'ar' ? 'لا تتوفر معلومات عن المركز حالياً.' : 'Hub profile is not available right now.'
                    );

                    return;
                }
                $this->sendCard($session->customer_phone_number, $card);

                return;
            }

            // 3.2 "about <name>" → search restaurants (your existing logic)
            $cityId = $session->delivery_city_id;
            $result = $this->restaurantSearchService->search($query, $cityId, $locale);

            if (! empty($result['exact'])) {
                $this->sendRestaurantAbout($session, $result['exact'], $locale);

                return;
            }

            $ranked = collect($result['ranked'] ?? []);
            if ($ranked->count() === 1) {
                $this->sendRestaurantAbout($session, $ranked->first()['restaurant'], $locale);

                return;
            }
            if ($ranked->count() > 1) {
                $rows = $ranked->take(5)->map(function ($r) use ($locale) {
                    /** @var \App\Hub\Models\Vendors $rest */
                    $rest = $r['restaurant'];
                    $name = method_exists($rest, 'getTranslation')
                        ? $rest->getTranslation('name', $locale)
                        : ($rest->name ?? 'Restaurant');

                    return [
                        'id' => 'about_res_'.$rest->id,
                        'title' => $name,
                        'description' => $locale === 'ar' ? 'اضغط للعرض' : 'Tap to view',
                    ];
                })->values()->all();

                $this->whatsAppService->send(
                    $session->customer_phone_number,
                    [
                        'type' => 'interactive',
                        'interactive' => [
                            'type' => 'list',
                            'header' => ['type' => 'text', 'text' => $locale === 'ar' ? 'اختر المطعم' : 'Pick a restaurant'],
                            'body' => ['text' => $locale === 'ar' ? 'وجدنا عدة نتائج.' : 'We found several matches.'],
                            'action' => [
                                'button' => $locale === 'ar' ? 'عرض' : 'View',
                                'sections' => [[
                                    'title' => $locale === 'ar' ? 'مطاعم' : 'Restaurants',
                                    'rows' => $rows,
                                ]],
                            ],
                        ],
                    ]
                );

                return;
            }

            // not found
            $this->whatsAppService->sendTextMessage(
                $session->customer_phone_number,
                $locale === 'ar'
                    ? "لم أجد مطعماً باسم *{$query}*. أرسل *القائمة* لاستكشاف العروض."
                    : "I couldn't find a restaurant named *{$query}*. Send *menu* to browse."
            );

            return;
        }

        // 4) Smart free-text intent → try to detect restaurant/cuisine and send address flow
        if ($this->handleSmartStart($session, $command, $locale)) {
            return;
        }

        // 5) Fallback: always start the main flow so user never gets “no reply”
        $this->startNewOrder($session, $locale);
    }

    private function startNewOrder(WhatsappSession $session, string $locale): void
    {
        // When starting a new flow, clear the campaign tag
        if (is_null($session->direct_intent_restaurant_id) && is_null($session->direct_intent_cuisine_id)) {
            $this->clearSessionForNewOrder($session);
        }

        $session->cartItems()->delete();
        Log::info('Cart cleared for session', ['session_id' => $session->id]);

        $session->loadMissing('customerProfile');
        $name = $session->customerProfile->full_name ?? null;

        // 1. Fetch Dynamic Welcome Message for Flow
        if ($locale === 'ar') {
            $baseMsg = Setting::get('whatsapp.flow_welcome_ar')
                ?: "حياكم الله في *جمعية عطف الخيرية*. 🇰🇼\nاضغط على الزر بالأسفل لاستعراض المشاريع.";
        } else {
            $baseMsg = Setting::get('whatsapp.flow_welcome_en')
                ?: "Welcome to *Ataf Charity*. 🇰🇼\nTap the button below to browse our projects.";
        }

        // 2. Personalize if name exists
        if ($name) {
            $msg = $locale === 'ar'
                ? "أهلاً بعودتك، {$name}! 🌹\n\n{$baseMsg}"
                : "Welcome back, {$name}! 🌹\n\n{$baseMsg}";
        } else {
            $msg = $baseMsg;
        }

        // For new customers, send the original flow.
        $this->whatsAppService->sendTextMessage($session->customer_phone_number, $msg);
        $this->whatsAppFlowService->sendInitialFlow($session->customer_phone_number, $locale);
    }

    private function handleSmartStart(WhatsappSession $session, string $text, string $locale): bool
    {
        Log::info('[SmartStart] Initiated.', [
            'session_id' => $session->id,
            'initial_intent_restaurant_id' => $session->direct_intent_restaurant_id, // UPDATED
            'initial_intent_cuisine_id' => $session->direct_intent_cuisine_id, // UPDATED
        ]);

        if ($session->cartItems()->exists()) {
            // This will delete the cart items and reset the session state
            $this->clearSessionForNewOrder($session);
        }

        $cityId = $session->delivery_city_id;
        $searchResult = $this->restaurantSearchService->search($text, $cityId, $locale);

        $exactRestaurant = $searchResult['exact'];
        $rankedRestaurants = $searchResult['ranked'];
        $cuisine = $searchResult['cuisine'];
        $confidentRestaurant = null;

        if ($exactRestaurant) {
            $confidentRestaurant = $exactRestaurant;
            Log::info('[SmartStart] Found exact restaurant by name.', ['id' => $confidentRestaurant->id]);
        } elseif ($rankedRestaurants->isNotEmpty()) {
            $topMatch = $rankedRestaurants->first();
            $secondMatch = $rankedRestaurants->get(1);
            $topScore = $topMatch['score'];
            if ($secondMatch === null || ($topScore >= $secondMatch['score'] * 1.5)) {
                $confidentRestaurant = $topMatch['restaurant'];
                Log::info('[SmartStart] Found confident keyword match.', ['id' => $confidentRestaurant->id, 'score' => $topScore]);
            }
        }

        if ($confidentRestaurant) {
            $primaryCuisine = $confidentRestaurant->cuisines()->first();
            $dataToSave = [
                'direct_intent_restaurant_id' => $confidentRestaurant->id, // UPDATED
                'direct_intent_business_type_id' => $confidentRestaurant->business_type_id,
                'direct_intent_cuisine_id' => $primaryCuisine ? $primaryCuisine->id : null, // UPDATED
            ];
            Log::info('[SmartStart] About to update session with CONFIDENT match.', $dataToSave);
            $session->update($dataToSave);
            $this->requestAddressAfterSmartStart($session, $locale);

            return true;
        }

        $ambiguousCuisineId = null;
        if ($rankedRestaurants->isNotEmpty() && ! $confidentRestaurant) {
            $topRestaurant = $rankedRestaurants->first()['restaurant'];
            $primaryCuisine = $topRestaurant->cuisines()->first();
            if ($primaryCuisine) {
                $ambiguousCuisineId = $primaryCuisine->id;
                Log::info('[SmartStart] Ambiguous result. Routing to primary cuisine.', ['id' => $ambiguousCuisineId]);
            }
        } elseif ($cuisine) {
            $ambiguousCuisineId = $cuisine->id;
            Log::info('[SmartStart] Found matching cuisine.', ['id' => $ambiguousCuisineId]);
        }

        if ($ambiguousCuisineId) {
            $dataToSave = [
                'direct_intent_restaurant_id' => null, // UPDATED
                'direct_intent_business_type_id' => null,
                'direct_intent_cuisine_id' => $ambiguousCuisineId, // UPDATED
            ];
            Log::info('[SmartStart] About to update session with AMBIGUOUS match.', $dataToSave);
            $session->update($dataToSave);
            $this->requestAddressAfterSmartStart($session, $locale);

            return true;
        }

        return false;
    }

    /**
     * ADDED: A new helper method to centralize the action after a successful smart start.
     * This method sends the address selection flow.
     */
    private function requestAddressAfterSmartStart(WhatsappSession $session, string $locale): void
    {
        $session->loadMissing('customerProfile');
        $name = $session->customerProfile->full_name ?? null;

        $msg = '';
        if ($name) {
            // UPDATED: Added an emoji for visual appeal
            $template = $locale === 'ar'
                ? 'مرحباً :name، لقد وجدت ما تبحث عنه! 🎯 أولاً، أين تود أن يتم التوصيل؟'
                : 'Hi :name, I found something for you! 🎯 First, where should we deliver?';
            $msg = str_replace(':name', $name, $template);
        } else {
            // UPDATED: Added an emoji for visual appeal
            $msg = $locale === 'ar'
                ? 'لقد وجدت ما تبحث عنه! 🎯 أولاً، أين تود أن يتم التوصيل؟'
                : 'I found something for you! 🎯 First, where should we deliver?';
        }

        $this->whatsAppService->sendTextMessage($session->customer_phone_number, $msg);
        $this->whatsAppFlowService->sendInitialFlow($session->customer_phone_number, $locale);
    }

    private function clearSessionForNewOrder(WhatsappSession $session): void
    {
        // We clear everything EXCEPT the 'direct_intent_*' fields, which we need
        // for the next step in the flow.
        $session->update([
            'selected_vendor_id' => null,
            'delivery_address' => null,
            'last_promotional_campaign_id' => null,
            'delivery_state_id' => null,
            'delivery_city_id' => null,
            'flow_street' => null,
            'flow_block_id' => null,
            'flow_house_no' => null,
            'promo_code' => null,
        ]);
        $session->cartItems()->delete();
        Log::info('Cart and session state cleared for new order.', ['session_id' => $session->id]);
    }

    private function handleAddItemToCart(WhatsappSession $session, string $retailerId, string $locale): void
    {
        if (! $session->selected_vendor_id) {
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, 'Please select a restaurant first.');

            return;
        }
        $request = new Request([
            'customer_phone' => $session->customer_phone_number,
            'restaurant_id' => $session->selected_vendor_id,
            'item_id' => $retailerId,
            'lang' => $locale,
        ]);
        $response = (new HubController)->addItemToCart($request);
        $this->whatsAppService->send($session->customer_phone_number, json_decode($response->getContent(), true));
    }

    // private function requestAddress(WhatsappSession $session, string $locale): void
    // {
    //      if ($session->cartItems->isEmpty()) {
    //          $this->whatsAppService->sendTextMessage($session->customer_phone_number, $locale === 'ar' ? 'سلتك فارغة! أضف بعض المنتجات أولاً.' : 'Your cart is empty! Please add some items first.');
    //          return;
    //      }

    //      $payload = [
    //          'type' => 'interactive',
    //          'interactive' => [
    //              'type' => 'location_request_message',
    //              'body' => ['text' => $locale === 'ar' ? 'لتوصيل طلبك، يرجى مشاركة موقعك.' : 'To deliver your order, please share your location.'],
    //              'action' => ['name' => 'send_location'],
    //          ],
    //      ];
    //      $this->whatsAppService->send($session->customer_phone_number, $payload);
    // }

    private function requestAddress(WhatsappSession $session, string $locale): void
    {
        if ($session->cartItems->isEmpty()) {
            $this->whatsAppService->sendTextMessage(
                $session->customer_phone_number,
                $locale === 'ar' ? 'سلتك فارغة! أضف بعض المنتجات أولاً.' : 'Your cart is empty! Please add some items first.'
            );

            return;
        }

        // Build the cart summary, address defaults, etc. For now just pass cart summary, you can enhance later.
        $cartSummary = [
            'cart_summary_text' => $session->cartItems->map(fn ($i) => "{$i->quantity} × {$i->item_name}")->implode("\n"),
            // Add more prefill data if needed
            'full_name' => $session->customerProfile->full_name ?? '',
            'state_id' => null,
            'city_id' => null,
            'block_id' => null,
            // etc...
        ];

        // Call the new Checkout Flow (from WhatsAppFlowService)
        $this->whatsAppFlowService->sendCheckoutFlow($session->customer_phone_number, $cartSummary, $locale);
    }

    private function handleLocationMessage(WhatsappSession $session, array $locationData, string $locale): void
    {
        $structuredAddress = $this->getAddressComponents(null, $locationData);
        if ($structuredAddress && ! empty($structuredAddress['full_address'])) {
            $session->update(['delivery_address' => $structuredAddress['full_address']]);
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, "Thanks! We've got your location. Now, please tap below to add your building and apartment details.");
        } else {
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, "Sorry, we couldn't read that location. Let's get your details manually.");
        }
        $this->initiateCheckoutFlow($session, $locale);
    }

    private function initiateCheckoutFlow(WhatsappSession $session, string $locale): void
    {
        $session->loadMissing(['cartItems', 'customerProfile']);

        if ($session->cartItems->isEmpty()) {
            $this->whatsAppService->sendTextMessage(
                $session->customer_phone_number,
                $locale === 'ar' ? 'سلتك فارغة! أضف بعض العناصر أولاً.' : 'Your cart is empty! Please add some items first.'
            );

            return;
        }

        $itemsSummary = $session->cartItems->map(fn ($i) => "{$i->quantity} × {$i->item_name}")->implode("\n");
        $saved = $session->customerProfile->addresses[0] ?? [];
        $flowData = [
            'items_summary' => $itemsSummary,
            'order_type' => null,
            'selected_address' => $saved ? 'addr_0' : null,
            'street_address' => $session->delivery_address ?? $saved['street'] ?? '',
            'building' => $saved['building'] ?? null,
            'floor' => $saved['floor'] ?? null,
            'apt_suite' => $saved['apartment'] ?? null,
            'block' => $saved['block'] ?? null,
            'landmark' => $saved['landmark'] ?? null,
            'full_name' => $session->customerProfile->full_name ?? '',
            'delivery_notes' => $session->customerProfile->notes ?? '',
        ];

        foreach ($flowData as &$v) {
            if (is_null($v)) {
                $v = '';
            }
        }
        unset($v);

        Log::debug('FLOW‑DATA', $flowData);

        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'flow',
                'header' => [
                    'type' => 'text',
                    'text' => $locale === 'ar' ? 'تفاصيل العنوان ونوع الطلب' : 'Address & Order Type',
                ],
                'body' => [
                    'text' => $locale === 'ar' ? 'راجع عنوانك واختر نوع الطلب.' : 'Please review your address and choose your order type.',
                ],
                'action' => [
                    'name' => 'flow',
                    'parameters' => [
                        'flow_message_version' => '3',
                        'flow_id' => config('services.whatsapp.checkout_flow_id'),
                        'flow_cta' => $locale === 'ar' ? 'التالي' : 'Next',
                        'flow_action' => 'navigate',
                        'flow_action_payload' => json_encode([
                            'screen' => 'CART_OVERVIEW',
                            'data' => $flowData,
                        ], JSON_UNESCAPED_UNICODE),
                        'flow_token' => (string) Str::uuid(),
                    ],
                ],
            ],
        ];

        $this->whatsAppService->send($session->customer_phone_number, $payload);
    }

    private function getAddressComponents(?string $address = null, ?array $locationData = null): ?array
    {
        $apiKey = config('services.google.maps_api_key');
        if (! $apiKey) {
            Log::error('Google Maps API Key is not configured.');

            return null;
        }

        $queryParams = ['key' => $apiKey];
        if ($locationData) {
            $queryParams['latlng'] = "{$locationData['latitude']},{$locationData['longitude']}";
        } elseif ($address) {
            $queryParams['address'] = $address;
            $queryParams['region'] = 'KW';
        } else {
            return null;
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', $queryParams);

        if ($response->failed() || empty($response->json('results'))) {
            return null;
        }

        $result = $response->json('results.0');
        $components = collect($result['address_components']);

        $getComponentValue = fn (string $type) => $components->firstWhere(fn ($c) => in_array($type, $c['types']))['long_name'] ?? null;

        return [
            'full_address' => $result['formatted_address'] ?? $address,
            'city' => $getComponentValue('locality'),
            'governorate' => $getComponentValue('administrative_area_level_1'),
        ];
    }

    private function handleFlowCompletion(WhatsappSession $session, array $flowData, string $locale, ?string $messageId): void
    {
        // ---------- 0) Idempotency on webhook ----------
        if ($messageId) {
            $cacheKey = 'processed_message_'.$messageId;
            if (Cache::has($cacheKey)) {
                Log::warning('Duplicate flow completion webhook received, ignoring.', ['message_id' => $messageId]);

                return;
            }
            Cache::put($cacheKey, true, now()->addHours(24));
        }

        Log::info('[FLOW_COMPLETE] Raw data', $flowData);

        $responseJson = $flowData['response_json'] ?? '{}';
        $responseData = json_decode($responseJson, true) ?? [];

        if (! isset($responseData['final_payload'])) {
            Log::warning('[FLOW_COMPLETE] Missing final_payload', $responseData);

            return;
        }

        $payload = $responseData['final_payload'];
        $locale = $payload['locale'] ?? $session->locale ?? $locale ?? 'en';

        $cartLines = data_get($payload, 'cart', []);
        if (empty($cartLines)) {
            $cartLines = $session->cartItems()->get()->map(function ($ci) {
                return [
                    'id' => 'item_'.$ci->item_id_from_restaurant,
                    'qty' => $ci->quantity,
                    'addons' => $ci->variations ?? [],
                ];
            })->toArray();
        }

        if (empty($cartLines)) {
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, $locale === 'ar' ? 'لا يمكن إتمام الطلب بسلة فارغة.' : 'Cannot complete order with an empty cart.');

            return;
        }

        $items = [];
        foreach ($cartLines as $line) {
            $itemIdInt = (int) preg_replace('/\D+/', '', $line['id'] ?? '');
            $qty = (int) ($line['qty'] ?? 0);
            if ($itemIdInt <= 0 || $qty <= 0) {
                continue;
            }
            $addonsPayload = [];
            foreach (($line['addons'] ?? []) as $ad) {
                $optId = null;
                if (! empty($ad['id'])) {
                    $parts = explode('-', $ad['id']);
                    $optId = (int) end($parts);
                }
                if ($optId) {
                    $addonsPayload[] = ['id' => $optId, 'quantity' => $qty];
                }
            }
            $items[] = [
                'id' => $itemIdInt,
                'quantity' => $qty,
                'addons' => $addonsPayload,
            ];
        }

        if (empty($items)) {
            Log::warning('[FLOW_COMPLETE] Items array empty after mapping', ['cartLines' => $cartLines]);
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, $locale === 'ar' ? 'حدث خطأ في عناصر الطلب.' : 'Order items could not be processed.');

            return;
        }

        // ---------- 2) Validate restaurant ----------
        // UPDATED: Changed 'restaurant_id' to 'vendor_id' to match the payload from the flow
        $restaurantId = (int) preg_replace('/\D+/', '', $payload['vendor_id'] ?? '0');
        $restaurant = Vendors::find($restaurantId);
        if (! $restaurant) {
            // Add a log to see what ID was not found
            Log::error('[FLOW_COMPLETE] Restaurant not found with ID.', ['id_from_payload' => $restaurantId]);
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, $locale === 'ar' ? 'المطعم غير موجود.' : 'Restaurant not found.');

            return;
        }

        // ---------- 3) Address / order type ----------
        $orderType = $payload['order_type'] ?? 'delivery';
        $isDelivery = $orderType === 'delivery';

        $addressType = is_array($payload['address_type'] ?? null)
            ? ($payload['address_type'][0] ?? null)
            : ($payload['address_type'] ?? null);

        if ($isDelivery && empty($payload['street'])) {
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, $locale === 'ar' ? 'تفاصيل عنوان التوصيل فارغة.' : 'Delivery address details cannot be empty.');

            return;
        }

        $cityId = (int) ($payload['city_id'] ?? $session->delivery_city_id ?? 0);
        // Note: $restaurantId is already defined above, no need to redefine.

        $deliveryFee = $isDelivery && $cityId ? $this->deliveryFeeFor($restaurantId, $cityId) : 0.0;
        $subtotal = $this->cartTotals($session);
        $discount = (float) ($payload['discount_amount'] ?? 0);
        $grandTotal = max(0, $subtotal + $deliveryFee - $discount);

        $totals = ['subtotal' => $subtotal, 'delivery_fee' => $deliveryFee, 'discount' => $discount, 'grand_total' => $grandTotal];

        $minOrder = $this->minOrderForCity($cityId, $restaurantId) ?? 0;
        if ($subtotal < $minOrder) {
            $need = number_format($minOrder - $subtotal, 3);
            $msg = $locale === 'ar' ? 'الحد الأدنى للطلب هو '.number_format($minOrder, 3)." د.ك. أضف $need د.ك." : 'Minimum order is '.number_format($minOrder, 3)." KWD. Add $need KWD more.";
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, $msg);

            return;
        }

        $deliveryAddress = implode(', ', array_filter([
            $payload['street'] ?? null,
            $payload['building'] ?? null,
            isset($payload['floor']) ? 'Floor: '.$payload['floor'] : null,
            isset($payload['house_no']) ? 'House No: '.$payload['house_no'] : null,
            $payload['landmark'] ?? null,
        ]));

        $deliveryDate = now()->addMinutes(30)->format('Y-m-d H:i');

        // ---------- 4) Send to restaurant API ----------
        try {
            $resp = Http::withHeaders([
                'Authorization' => 'Bearer '.$restaurant->api_key,
                'Accept' => 'application/json',
            ])->post(rtrim($restaurant->api_base_url, '/').'/v1/orders', [
                'customer_phone' => $session->customer_phone_number,
                'customer_name' => trim($payload['name'] ?? ''),
                'items' => $items,
                'notes' => $payload['notes'] ?? null,
                'promo_code' => $payload['promo_code'] ?? null,
                'order_type' => ($isDelivery ? 1 : 2), // 1=delivery, 2=pickup ( API convention)
                'address_type' => $addressType,
                'state_id' => $payload['state_id'] ?? null,
                'city_id' => $payload['city_id'] ?? null,
                'block_id' => $payload['block_id'] ?? null,
                'floor' => $payload['floor'] ?? null,
                'house_no' => $payload['house_no'] ?? null,
                'building' => $payload['building'] ?? null,
                'landmark' => $payload['landmark'] ?? null,
                'delivery_address' => $isDelivery ? $deliveryAddress : null,
                'delivery_date' => $isDelivery ? $deliveryDate : null,
                'delivery_fee' => $isDelivery ? $deliveryFee : 0,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $grandTotal,
                'locale' => $locale,
            ]);

            $resp->throw();
            $body = $resp->json();
            $addressForSave = [
                'address_type' => $addressType,
                'address_type_label' => $payload['address_type_label'] ?? null,
                'state_id' => $payload['state_id'] ?? null,
                'city_id' => $payload['city_id'] ?? null,
                'block_id' => $payload['block_id'] ?? null,
                'street' => $payload['street'] ?? null,
                'house_no' => $payload['house_no'] ?? null,
                'building' => $payload['building'] ?? null,
                'floor' => $payload['floor'] ?? null,
                'landmark' => $payload['landmark'] ?? null,
                'delivery_address' => $isDelivery ? $deliveryAddress : null, // human-readable line
            ];

            // 1b) Enrich items for local save with name/price from DB cart
            $itemsForSave = $session->cartItems()->get()->map(function ($ci) {
                return [
                    'id' => (int) $ci->item_id_from_restaurant,
                    'quantity' => (int) $ci->quantity,
                    'addons' => $ci->variations ?? [],
                    'price' => (float) $ci->price,       // keep exact line price you charged
                    'item_name' => (string) $ci->item_name,  // keep exact line name
                ];
            })->toArray();

            // Pass a payload-for-save that *includes* the structured address
            $payloadForSave = $payload;
            $payloadForSave['address'] = $addressForSave;

            // Use the enriched items for saving locally (keep $items for the external API)
            $this->saveOrderToLocalDatabase($session, $restaurant, $payloadForSave, $totals, $body, $itemsForSave, $locale);

            Log::info('[FLOW_COMPLETE] Order created on restaurant API', $body);

        } catch (\Throwable $e) {
            Log::error('Order submission failed:', ['error' => $e->getMessage()]);
            $this->whatsAppService->sendTextMessage(
                $session->customer_phone_number,
                $locale === 'ar' ? 'عذراً، حدثت مشكلة في إرسال طلبك.' : 'Sorry, there was a problem submitting your order.'
            );

            return;
        }

        // ---------- 5) Mark session, (optional) clear cart ----------
        $session->update([
            'status' => 'completed',
            'delivery_address' => null,
            'selected_vendor_id' => null,
            'last_promotional_campaign_id' => null,
        ]);

        // Optional: clear cart rows
        $session->cartItems()->delete();

        // ---------- 6) Send confirmation / payment link ----------
        $branchKey = data_get($body, 'branch.key') ?? data_get($payload, 'branch_key');
        $branchName = null;
        if ($branchKey) {
            $hubBranch = HubBranch::where('external_key', $branchKey)->first();
            if ($hubBranch) {
                $branchName = $locale === 'ar'
                    ? ($hubBranch->name_ar ?: $hubBranch->name_en)
                    : ($hubBranch->name_en ?: $hubBranch->name_ar);
            }
        }
        if (! $branchName) {
            $resName = $restaurant->name;
            if (is_array($resName)) {
                $branchName = $locale === 'ar'
                    ? ($resName['ar'] ?? $resName['en'] ?? 'مطعمنا')
                    : ($resName['en'] ?? $resName['ar'] ?? 'Our Restaurant');
            } else {
                $branchName = $resName ?: ($locale === 'ar' ? 'مطعمنا' : 'Our Restaurant');
            }
        }

        $orderId = $body['order_id'] ?? 'N/A';
        $paymentLink = $body['payment_link'] ?? null;
        $headline = $locale === 'ar'
            ? " تم تأكيد طلبك {$orderId}"
            : " Order {$orderId} confirmed!";
        $customerName = trim($payload['name'] ?? 'Valued Customer');

        if ($paymentLink) {
            $paymentLinkSuffix = Str::afterLast($paymentLink, '/');
            $templateName = $locale === 'ar' ? 'order_payment_v2_ar' : 'order_payment_v2_en';

            $this->whatsAppService->sendPaymentLinkTemplate(
                $session->customer_phone_number,
                $templateName,
                $locale,
                $customerName,     // {{1}}
                (string) $orderId, // {{2}}
                $branchName,       // {{3}}
                $paymentLinkSuffix // {{1}} button
            );
        } else {
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, $headline);
        }
    }

    /**
     * Handles the submission from the simple rating flow.
     */
    private function handleRatingFlowSubmission(WhatsappSession $session, array $response, string $userPhone, ?string $messageId): void
    {
        try {
            Rating::create([
                'whatsapp_session_id' => $session->id,
                'rating' => (int) $response['rating'],
                'comment' => $response['comment'] ?? null,
                'vendor_id' => $response['restaurant_id'],
                'order_number' => $response['order_number'], // Add this line
                'whatsapp_phone' => $userPhone,                // Add this line
            ]);

            Log::info('WhatsApp rating flow saved successfully.', [
                'restaurant_id' => $response['restaurant_id'],
                'message_id' => $messageId,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to process rating flow submission.', [
                'error' => $e->getMessage(),
                'response' => $response,
                'message_id' => $messageId,
            ]);
        }
    }

    private function deliveryFeeFor(int $restaurantId, int $cityId): float
    {
        $area = DeliveryArea::where('city_id', $cityId)
            ->whereHas('branch', fn ($q) => $q->where('vendor_id', $restaurantId)
                ->where('is_active', true))
            ->first();

        return $area?->delivery_fee ? (float) $area->delivery_fee : 0.0;
    }

    private function cartTotals(WhatsappSession $session): float
    {
        return $session->cartItems()->get()->sum(function ($ci) {
            $addonUnitTotal = collect($ci->variations)->sum('price'); // per 1 item
            $qty = (int) $ci->quantity;

            return ($qty * (float) $ci->price) + ($qty * (float) $addonUnitTotal);
        });
    }

    private function minOrderForCity(?int $cityId, ?int $restaurantId = null): ?float
    {
        if (! $cityId) {
            return null;
        }

        // If restaurant not passed, try to read from flow/session later
        if (! $restaurantId) {
            return null;
        }

        $area = DeliveryArea::where('city_id', $cityId)
            ->whereHas('branch', function ($q) use ($restaurantId) {
                $q->where('vendor_id', $restaurantId)
                    ->where('is_active', true);
            })
            ->first();

        return $area?->min_order_value !== null ? (float) $area->min_order_value : null;
    }

    private function handleNativeOrder(WhatsappSession $session, array $orderData, string $locale): void
    {
        $productItems = $orderData['product_items'] ?? [];
        if (empty($productItems)) {
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, 'There was an error reading your cart.');

            return;
        }

        $firstItemId = $productItems[0]['product_retailer_id'];
        $parts = explode('_', $firstItemId);
        $restaurantId = $parts[2] ?? null;

        if (! $restaurantId || ! is_numeric($restaurantId)) {
            Log::error('Could not parse restaurant_id from native order', ['id' => $firstItemId]);

            return;
        }

        $session->update(['selected_vendor_id' => $restaurantId]);
        $session->cartItems()->delete();

        foreach ($productItems as $product) {
            $this->handleAddItemToCart($session, $product['product_retailer_id'], $locale);
        }

        // No need for a text message; just send the checkout flow!
        app('App\Wa\Services\WhatsApp\WhatsAppFlowService')->sendCheckoutFlow(
            $session->customer_phone_number,
            [
                'cart_summary_text' => $locale === 'ar'
                    ? 'تمت إضافة المنتجات. تابع للدفع.'
                    : 'Items added. Proceeding to checkout.',
                // You can pass more keys as needed!
            ],
            $locale
        );
    }

    public function decodeMetaId($value, $prefix = null)
    {
        if (! is_string($value)) {
            return null;
        }
        if ($prefix) {
            $pattern = '/^'.preg_quote($prefix, '/').'_(\d+)$/i';
            if (preg_match($pattern, $value, $m)) {
                return (int) $m[1];
            }
        }
        if (preg_match('/_(\d+)$/', $value, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function handleReorderExecution(WhatsappSession $session, string $orderId, string $locale): void
    {
        $this->whatsAppService->sendTextMessage(
            $session->customer_phone_number,
            $locale === 'ar' ? "جاري إعادة طلبك رقم {$orderId}، لحظات من فضلك..." : "Reordering order #{$orderId} for you, please wait..."
        );

        $orderToRecreate = $this->orderHistoryService->fetchOrderForReorder($orderId);

        if (! $orderToRecreate || $orderToRecreate->customer_phone_number !== $session->customer_phone_number) {
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, 'Sorry, we were unable to find this order.');

            return;
        }

        $restaurant = $orderToRecreate->restaurant;
        if (! $restaurant) {
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, 'Sorry, the restaurant for this order is no longer available.');

            return;
        }

        $details = $orderToRecreate->order_details;
        $address = $details['address'] ?? [];
        $isDelivery = ($details['order_type'] ?? 'delivery') === 'delivery';

        $itemsPayload = $orderToRecreate->items->map(function ($item) {
            return [
                'id' => $item->item_id_from_restaurant,
                'quantity' => $item->quantity,
                'addons' => $item->addons ?? [],
            ];
        })->toArray();

        // Rebuild the single delivery_address string
        $deliveryAddressString = $isDelivery ? implode(', ', array_filter([
            $address['street'] ?? null,
            isset($address['building']) ? 'Building: '.$address['building'] : null,
            isset($address['floor']) ? 'Floor: '.$address['floor'] : null,
            isset($address['house_no']) ? 'House No: '.$address['house_no'] : null,
        ])) : null;

        try {
            $total = $orderToRecreate->subtotal + $orderToRecreate->delivery_fee;
            $apiPayload = [
                'customer_phone' => $orderToRecreate->customer_phone_number,
                'customer_name' => $details['customer_name'] ?? 'Valued Customer',
                'items' => $itemsPayload,
                'notes' => $details['notes'],
                'order_type' => $isDelivery ? 1 : 2,
                'state_id' => $address['state_id'] ?? null,
                'city_id' => $address['city_id'] ?? null,
                'block_id' => $address['block_id'] ?? null,
                'street' => $address['street'] ?? null,
                'house_no' => $address['house_no'] ?? null,
                'building' => $address['building'] ?? null,
                'delivery_address' => $deliveryAddressString, // <-- BUG FIX
                'delivery_date' => now()->addMinutes(45)->format('Y-m-d H:i'),
                'subtotal' => $orderToRecreate->subtotal,
                'delivery_fee' => $orderToRecreate->delivery_fee,
                'discount' => 0,
                'total' => $total,
                'locale' => $locale,
                'source' => 'whatsapp_reorder',
            ];

            $resp = Http::withHeaders([
                'Authorization' => 'Bearer '.$restaurant->api_key,
                'Accept' => 'application/json',
            ])->post(rtrim($restaurant->api_base_url, '/').'/v1/orders', $apiPayload);

            $resp->throw();
            $body = $resp->json();

            // --- NEW: Save the reorder to your local database using the helper ---
            $totals = [
                'subtotal' => $orderToRecreate->subtotal,
                'delivery_fee' => $orderToRecreate->delivery_fee,
                'discount' => 0,
                'grand_total' => $total,
            ];
            $detailsForSave = $details;
            $detailsForSave['name'] = $details['customer_name'] ?? null; // normalize
            $detailsForSave['order_type'] = $isDelivery ? 'delivery' : 'pickup';
            $detailsForSave['delivery_address'] = $deliveryAddressString; // see #2
            $this->saveOrderToLocalDatabase($session, $restaurant, $details, $totals, $body, $itemsPayload, $locale);
            // --- END OF NEW LOGIC ---

            $this->sendOrderConfirmation($session, $body, $details['customer_name'], $locale, $restaurant);

        } catch (\Throwable $e) {
            Log::error('Reorder submission failed:', ['error' => $e->getMessage(), 'order_id' => $orderId]);

            $errorMessage = 'Sorry, there was a problem reordering. Some items may no longer be available.';
            if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response->json('message')) {
                $errorMessage = $e->response->json('message'); // Use the specific error from the API
            }
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, $errorMessage);
        }
    }

    /**
     * Sends the final order confirmation message with a payment link (if available).
     * This is a reusable helper for both new orders and reorders.
     */
    private function sendOrderConfirmation(
        WhatsappSession $session,
        array $apiResponseBody,
        ?string $customerName,
        string $locale,
        \App\Hub\Models\Vendors $restaurant
    ): void {
        $branchKey = data_get($apiResponseBody, 'branch.key');
        $branchName = null;
        if ($branchKey) {
            $hubBranch = \App\Hub\Models\HubBranch::where('external_key', $branchKey)->first();
            if ($hubBranch) {
                $branchName = $locale === 'ar'
                    ? ($hubBranch->name_ar ?: $hubBranch->name_en)
                    : ($hubBranch->name_en ?: $hubBranch->name_ar);
            }
        }

        // Fallback to restaurant name if branch name is not found
        if (! $branchName) {
            $branchName = $restaurant->getTranslation('name', $locale);
        }

        $orderId = $apiResponseBody['order_id'] ?? 'N/A';
        $paymentLink = $apiResponseBody['payment_link'] ?? null;

        if ($paymentLink) {
            $paymentLinkSuffix = Str::afterLast($paymentLink, '/');
            $templateName = $locale === 'ar' ? 'order_payment_v2_ar' : 'order_payment_v2_en';

            $this->whatsAppService->sendPaymentLinkTemplate(
                $session->customer_phone_number,
                $templateName,
                $locale,
                $customerName ?? 'Valued Customer', // {{1}}
                (string) $orderId,                  // {{2}}
                $branchName,                        // {{3}}
                $paymentLinkSuffix                  // {{1}} button
            );
        } else {
            $headline = $locale === 'ar'
                ? " تم تأكيد طلبك {$orderId}"
                : " Order {$orderId} confirmed!";
            $this->whatsAppService->sendTextMessage($session->customer_phone_number, $headline);
        }
    }

    /**
     * Fetches and displays the user's recent orders from the local DB.
     */
    private function handleReorderRequest(WhatsappSession $session, string $locale): void
    {
        $recentOrders = $this->orderHistoryService->fetchRecentOrders($session->customer_phone_number);

        if ($recentOrders->isEmpty()) {
            $this->whatsAppService->sendTextMessage(
                $session->customer_phone_number,
                $locale === 'ar' ? 'لا يوجد لديك طلبات سابقة لإعادة طلبها.' : 'You have no recent orders to reorder.'
            );

            return;
        }

        $listRows = $recentOrders->map(function (\App\Models\Order $order) use ($locale) {
            return [
                'id' => 'reorder_'.$order->id, // e.g., "reorder_123"
                'title' => ($locale === 'ar' ? 'طلب رقم ' : 'Order #').$order->id,
                'description' => ($locale === 'ar' ? 'المجموع: ' : 'Total: ').number_format($order->total, 3).' KWD'
                    ."\n".($locale === 'ar' ? 'التاريخ: ' : 'On: ').$order->created_at->format('d M Y'),
            ];
        })->all();

        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => ['type' => 'text', 'text' => $locale === 'ar' ? 'إعادة طلب' : 'Reorder'],
                'body' => ['text' => $locale === 'ar' ? 'اختر طلباً من القائمة لإعادة طلبه مباشرة.' : 'Select an order from the list to place it again.'],
                'action' => [
                    'button' => $locale === 'ar' ? 'عرض الطلبات' : 'View Orders',
                    'sections' => [['title' => $locale === 'ar' ? 'طلباتك الأخيرة' : 'Your Recent Orders', 'rows' => $listRows]],
                ],
            ],
        ];

        $this->whatsAppService->send($session->customer_phone_number, $payload);
    }

    /**
     * Saves a completed order to the local database.
     * This can be used by both new orders and reorders.
     */
    private function saveOrderToLocalDatabase(
        WhatsappSession $session,
        Vendors $restaurant,
        array $payload,
        array $totals,
        array $apiResponse,
        array $itemsForApi,
        string $locale
    ): void {
        DB::transaction(function () use ($session, $restaurant, $payload, $totals, $apiResponse, $itemsForApi, $locale) {
            // ---------- Address normalization ----------
            $addr = (array) ($payload['address'] ?? []);

            // If no structured address passed, lift from flat fields
            if (empty($addr)) {
                $addr = [
                    'address_type' => $payload['address_type'] ?? null,
                    'address_type_label' => $payload['address_type_label'] ?? null,
                    'state_id' => $payload['state_id'] ?? null,
                    'city_id' => $payload['city_id'] ?? null,
                    'block_id' => $payload['block_id'] ?? null,
                    'street' => $payload['street'] ?? null,
                    'house_no' => $payload['house_no'] ?? null,
                    'building' => $payload['building'] ?? null,
                    'floor' => $payload['floor'] ?? null,
                    'landmark' => $payload['landmark'] ?? null,
                ];
            }

            // Ensure human-readable line is present
            if (! isset($addr['delivery_address']) && ! empty($payload['delivery_address'])) {
                $addr['delivery_address'] = $payload['delivery_address'];
            }

            // ---------- Create Order ----------
            $order = \App\Models\Order::create([
                'whatsapp_session_id' => $session->id,
                'restaurant_id' => $restaurant->id,
                'customer_phone_number' => $session->customer_phone_number,
                'restaurant_order_id' => $apiResponse['order_id'] ?? null,
                'status' => 'completed',
                'subtotal' => (float) $totals['subtotal'],
                'delivery_fee' => (float) $totals['delivery_fee'],
                'discount' => (float) $totals['discount'],
                'total' => (float) $totals['grand_total'],
                'promo_code' => $payload['promo_code'] ?? null,
                'order_details' => [
                    'customer_name' => $payload['name'] ?? ($payload['customer_name'] ?? ''),
                    'notes' => $payload['notes'] ?? null,
                    'order_type' => $payload['order_type'] ?? 'delivery',
                    'address' => $addr,
                ],
                'api_response' => $apiResponse,
            ]);

            // Pre-index API response items for fallback
            $apiItemsById = collect(data_get($apiResponse, 'items', []))
                ->keyBy(fn ($i) => (int) ($i['id'] ?? 0));

            // ---------- Create Order Items ----------
            foreach ($itemsForApi as $itemData) {
                $extId = (int) ($itemData['id'] ?? 0);
                $qty = (int) ($itemData['quantity'] ?? 0);
                $addons = $itemData['addons'] ?? [];

                if ($extId <= 0 || $qty <= 0) {
                    continue;
                }

                $hubItem = \App\Hub\Models\MenuItem::where('vendor_id', $restaurant->id)
                    ->where('external_id', $extId)
                    ->first();

                $apiItem = $apiItemsById->get($extId);

                $itemName = $itemData['item_name']
                    ?? ($apiItem['name'] ?? null)
                    ?? ($hubItem ? $hubItem->getTranslation('name', $locale) : 'Unknown Item');

                $itemPrice = array_key_exists('price', $itemData)
                    ? (float) $itemData['price']
                    : (isset($apiItem['price']) ? (float) $apiItem['price'] : ($hubItem->price ?? 0));

                $order->items()->create([
                    'item_id_from_restaurant' => $extId,
                    'item_name' => $itemName,
                    'quantity' => $qty,
                    'price' => $itemPrice,
                    'addons' => $addons,
                ]);
            }
        });

        Log::info('[DB] Order saved to local Hub database.', ['order_id' => $apiResponse['order_id'] ?? null]);
    }

    /**
     * Accept the raw webhook payload and dispatch each message to handle().
     * Keeps your controller simple and future-proof.
     */
    public function process(array $payload): void
    {
        $value = data_get($payload, 'entry.0.changes.0.value', []);
        $messages = $value['messages'] ?? [];

        if (empty($messages)) {
            // nothing to do (statuses are handled in the controller)
            return;
        }

        foreach ($messages as $message) {
            try {
                $this->handle($message);
            } catch (\Throwable $e) {
                \Log::error('WhatsAppMessageHandler->process() failed', [
                    'error' => $e->getMessage(),
                    'type' => $message['type'] ?? 'unknown',
                    'id' => $message['id'] ?? null,
                ]);
            }
        }
    }

    private function sendCard(string $to, array $card): void
    {
        $image = $card['image_url'] ?? null;
        $caption = trim($card['caption'] ?? '');
        if ($image) {
            $this->whatsAppService->send($to, [
                'type' => 'image',
                'image' => ['link' => $image, 'caption' => $caption],
            ]);
        } else {
            $this->whatsAppService->sendTextMessage($to, $caption);
        }
    }

    private function sendRestaurantAbout(WhatsappSession $session, \App\Hub\Models\Vendors $restaurant, string $locale): void
    {
        // Pull dynamic numbers for placeholders (if available)
        $cityId = (int) ($session->delivery_city_id ?? 0);

        $minOrder = $this->minOrderForCity($cityId, $restaurant->id);
        $deliveryFee = $restaurant->getDeliveryFeeForCity($cityId)
            ?? $this->deliveryFeeFor($restaurant->id, $cityId);

        $fmt = fn ($n) => $n === null ? '' : number_format((float) $n, 3).' KWD';

        $card = $this->aboutService->buildRestaurantCard($restaurant, $locale, [
            'min_order' => $fmt($minOrder),
            'delivery_fee' => $fmt($deliveryFee),
        ]);

        $this->sendCard($session->customer_phone_number, $card);
    }
}
