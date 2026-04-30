<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Http\Controllers\Controller;
use App\Http\Traits\VendorEmployeeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class AIInsightsController extends Controller
{
    use VendorEmployeeAccess;

    private $cacheMinutes = 5; // 5 minutes cache per user per page
    private $extendedCacheHours = 24; // 24 hours for unchanged data
    private $groqApiKey;
    private $groqBaseUrl = 'https://api.groq.com/openai/v1';

    public function __construct()
    {
        $this->groqApiKey = config('ai.groq.api_key', env('GROQ_API_KEY'));
        $this->groqBaseUrl = config('ai.groq.base_url', env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'));
        $this->cacheMinutes = config('ai.cache.insights_ttl', env('AI_INSIGHTS_CACHE_MINUTES', 5));
    }

    /**
     * Get AI insights for a specific page
     */
    public function getInsights(Request $request)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        $request->validate([
            'page' => 'required|string|in:customers,orders,products,finance,analytics,financial-overview'
        ]);

        $page = $request->page;
        $cacheKey = "ai_insights_{$vendor->id}_{$page}";
        $extendedCacheKey = "ai_insights_extended_{$vendor->id}_{$page}";

        $dataChanged = $this->hasDataChanged($vendor, $page);

        $cachedInsights = Cache::get($cacheKey);
        if ($cachedInsights) {
            $data = $this->getPageData($vendor, $page);
            $actionLinks = $this->generateActionLinks($page, $data);

            $response = [
                'insights' => $cachedInsights['insights'],
                'recommendations' => $cachedInsights['recommendations'],
                'action_links' => $actionLinks,
                'cached' => true,
                'cache_type' => 'fresh',
                'generated_at' => $cachedInsights['generated_at']
            ];

            return response()->json($response);
        }

        if (!$dataChanged) {
            $extendedCache = Cache::get($extendedCacheKey);
            if ($extendedCache) {
                $data = $this->getPageData($vendor, $page);
                $actionLinks = $this->generateActionLinks($page, $data);

                $response = [
                    'insights' => $extendedCache['insights'],
                    'recommendations' => $extendedCache['recommendations'],
                    'action_links' => $actionLinks,
                    'cached' => true,
                    'cache_type' => 'extended',
                    'generated_at' => $extendedCache['generated_at']
                ];

                return response()->json($response);
            }
        }

        try {
            $data = $this->getPageData($vendor, $page);
            $aiResponse = $this->generateInsights($page, $data);
            $actionLinks = $this->generateActionLinks($page, $data);

            $cacheData = [
                'insights' => $aiResponse['insights'],
                'recommendations' => $aiResponse['recommendations'],
                'action_links' => $actionLinks,
                'generated_at' => Carbon::now()->toISOString()
            ];

            Cache::put($cacheKey, $cacheData, now()->addMinutes($this->cacheMinutes));
            Cache::put($extendedCacheKey, $cacheData, now()->addHours($this->extendedCacheHours));
            $this->updateLastDataChange($vendor, $page);

            $response = [
                'insights' => $aiResponse['insights'],
                'recommendations' => $aiResponse['recommendations'],
                'action_links' => $actionLinks,
                'cached' => false,
                'cache_type' => 'new',
                'generated_at' => $cacheData['generated_at']
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('AI Insights Error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Ошибка при генерации аналитики',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inactive customers with pagination
     */
    public function getInactiveCustomers(Request $request)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'min_absence_days' => 'nullable|integer|min:0'
        ]);

        $perPage = $request->get('per_page', 15);
        $minAbsenceDays = $request->get('min_absence_days', 30);

        $customers = \App\Models\User::where('created_by', $vendor->id)
            ->with(['orders' => function($q) {
                $q->whereNotIn('order_status', ['refunded', 'canceled'])
                  ->orderBy('created_at', 'desc');
            }, 'customerImports'])
            ->orderByRaw('CAST(user_number AS UNSIGNED) DESC')
            ->get();

        $inactiveCustomers = [];

        foreach ($customers as $customer) {
            $absenceDays = $this->calculateCustomerAbsenceDays($customer);

            if ($absenceDays >= $minAbsenceDays) {
                $totalOrderAmount = $customer->orders->sum('order_amount') ?? 0;

                $inactiveCustomers[] = [
                    'id' => $customer->id,
                    'user_number' => $customer->user_number,
                    'name' => trim($customer->f_name . ' ' . $customer->l_name),
                    'phone' => $customer->phone,
                    'absence_days' => $absenceDays,
                    'total_orders' => $customer->orders->count() ?? 0,
                    'total_order_amount' => round($totalOrderAmount, 2),
                    'last_order_date' => $customer->orders->first() ?
                        Carbon::parse($customer->orders->first()->created_at)->format('d.m.Y') : 'Никогда',
                    'favorite_products' => $this->getCustomerFavoriteProducts($customer)
                ];
            }
        }

        usort($inactiveCustomers, function($a, $b) {
            return $b['absence_days'] - $a['absence_days'];
        });

        foreach ($inactiveCustomers as &$customer) {
            $customer['absence_days'] = $customer['absence_days'] === 999999 ? 'Никогда' : $customer['absence_days'];
        }

        $total = count($inactiveCustomers);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $perPage;
        $paginatedCustomers = array_slice($inactiveCustomers, $offset, $perPage);

        return response()->json([
            'data' => $paginatedCustomers,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ],
            'links' => [
                'first' => route('vendor.ai.inactive-customers', ['page' => 1, 'per_page' => $perPage]),
                'last' => route('vendor.ai.inactive-customers', ['page' => ceil($total / $perPage), 'per_page' => $perPage]),
                'prev' => $page > 1 ? route('vendor.ai.inactive-customers', ['page' => $page - 1, 'per_page' => $perPage]) : null,
                'next' => $page < ceil($total / $perPage) ? route('vendor.ai.inactive-customers', ['page' => $page + 1, 'per_page' => $perPage]) : null
            ]
        ]);
    }

    /**
     * Generate intelligent action links based on page and data
     */
    private function generateActionLinks($page, $data)
    {
        $actionLinks = [];

        switch ($page) {
            case 'customers':
                $inactiveCount = $data['inactive_customers'] ?? 0;
                $activityRate = $data['activity_rate'] ?? 0;

                if ($inactiveCount > 0) {
                    $minAbsenceDays = 30;

                    if ($activityRate < 50) {
                        $minAbsenceDays = 14;
                    }

                    $actionLinks[] = [
                        'title' => 'Просмотреть неактивных клиентов',
                        'description' => 'Клиенты без заказов ' . $minAbsenceDays . '+ дней',
                        'type' => 'view_inactive_customers',
                        'url' => route('vendor.ai.inactive-customers', [
                            'min_absence_days' => $minAbsenceDays,
                            'per_page' => 20,
                            'page' => 1
                        ]),
                        'count' => $inactiveCount,
                        'filters' => [
                            'min_absence_days' => $minAbsenceDays,
                            'per_page' => 20
                        ]
                    ];
                } else {
                    Log::debug('No action link created - inactive_customers condition not met', [
                        'inactive_customers' => $inactiveCount,
                        'activity_rate' => $activityRate
                    ]);
                }
                break;

            case 'financial-overview':
                if (isset($data['has_cash_gap']) && $data['has_cash_gap'] && isset($data['absent_customers_count']) && $data['absent_customers_count'] > 0) {
                    $actionLinks[] = [
                        'title' => 'Вернуть неактивных клиентов для пополнения кассы',
                        'description' => 'Клиенты без заказов 30+ дней для срочного привлечения',
                        'type' => 'recover_inactive_customers',
                        'url' => route('vendor.ai.inactive-customers', [
                            'min_absence_days' => 30,
                            'per_page' => 50,
                            'page' => 1
                        ]),
                        'count' => $data['absent_customers_count'],
                        'priority' => 'high',
                        'filters' => [
                            'min_absence_days' => 30,
                            'per_page' => 50
                        ]
                    ];
                }
                break;

            case 'analytics':
                $analyticsData = $data;

                if (isset($analyticsData['customers']['inactive_customers']) && $analyticsData['customers']['inactive_customers'] > 0) {
                    $actionLinks[] = [
                        'title' => 'Просмотреть неактивных клиентов',
                        'description' => 'Клиенты без заказов 30+ дней',
                        'type' => 'view_inactive_customers',
                        'url' => route('vendor.ai.inactive-customers', [
                            'min_absence_days' => 30,
                            'per_page' => 20,
                            'page' => 1
                        ]),
                        'count' => $analyticsData['customers']['inactive_customers'],
                        'filters' => [
                            'min_absence_days' => 30,
                            'per_page' => 20
                        ]
                    ];
                }
                break;
        }

        return $actionLinks;
    }

    /**
     * Get data for specific page
     */
    private function getPageData($vendor, $page)
    {
        switch ($page) {
            case 'customers':
                return $this->getCustomersData($vendor);
            case 'orders':
                return $this->getOrdersData($vendor);
            case 'products':
                return $this->getProductsData($vendor);
            case 'finance':
                return $this->getFinanceData($vendor);
            case 'financial-overview':
                return $this->getFinancialOverviewData($vendor);
            case 'analytics':
                return $this->getAnalyticsData($vendor);
            default:
                return [];
        }
    }

    /**
     * Get customers data for analysis
     */
    private function getCustomersData($vendor)
    {
        $customers = \App\Models\User::where('created_by', $vendor->id)
            ->with(['orders' => function($q) {
                $q->whereNotIn('order_status', ['refunded', 'canceled'])
                  ->orderBy('created_at', 'desc');
            }])
            ->get();

        $totalCustomers = $customers->count();
        $activeCustomers = 0;
        $customersWithAbsence = [];

        foreach ($customers as $customer) {
            $absenceDays = $this->calculateCustomerAbsenceDays($customer);

            if ($absenceDays <= 30) {
                $activeCustomers++;
            }

            $customersWithAbsence[] = [
                'id' => $customer->id,
                'user_number' => $customer->user_number,
                'name' => trim($customer->f_name . ' ' . $customer->l_name),
                'phone' => $customer->phone,
                'absence_days' => $absenceDays === 999999 ? 'Никогда' : $absenceDays,
                'total_orders' => $customer->orders->count() ?? 0,
                'last_order_date' => $customer->orders->first() ?
                    Carbon::parse($customer->orders->first()->created_at)->format('d.m.Y') : 'Никогда',
                'favorite_products' => $this->getCustomerFavoriteProducts($customer)
            ];
        }

        usort($customersWithAbsence, function($a, $b) {
            $aDays = is_string($a['absence_days']) ? 999999 : $a['absence_days'];
            $bDays = is_string($b['absence_days']) ? 999999 : $b['absence_days'];
            return $bDays - $aDays;
        });

        $inactiveCustomers = $totalCustomers - $activeCustomers;

        $topCustomers = $customers->sortByDesc(function($customer) {
            return $customer->orders->count();
        })->take(5)->map(function($customer) {
            return [
                'id' => $customer->id,
                'name' => trim($customer->f_name . ' ' . $customer->l_name),
                'phone' => $customer->phone,
                'total_orders' => $customer->orders->count() ?? 0
            ];
        })->values();

        return [
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'inactive_customers' => $inactiveCustomers,
            'activity_rate' => $totalCustomers > 0 ? round(($activeCustomers / $totalCustomers) * 100, 1) : 0,
            'top_customers' => $topCustomers->toArray(),
            'customers_with_absence' => $customersWithAbsence,
            'most_absent_customers' => array_slice($customersWithAbsence, 0, 10) // Top 10 most absent
        ];
    }

    /**
     * Get orders data for analysis
     */
    private function getOrdersData($vendor)
    {
        $totalOrders = \App\Models\Order::where('vendor_id', $vendor->id)->count();
        $recentOrders = \App\Models\Order::where('vendor_id', $vendor->id)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();
        
        $avgOrderValue = \App\Models\Order::where('vendor_id', $vendor->id)
            ->where('order_status', 'delivered')
            ->avg('order_amount') ?? 0;

        return [
            'total_orders' => $totalOrders,
            'recent_orders' => $recentOrders,
            'avg_order_value' => round($avgOrderValue, 2),
            'weekly_growth' => $this->calculateWeeklyGrowth($vendor)
        ];
    }

    /**
     * Get products data for analysis
     */
    private function getProductsData($vendor)
    {
        $totalProducts = \App\Models\Product::where('vendor_id', $vendor->id)->count();
        $lowStockProducts = \App\Models\Product::where('vendor_id', $vendor->id)
            ->where('quantity', '<', 10)
            ->count();

        return [
            'total_products' => $totalProducts,
            'low_stock_products' => $lowStockProducts,
            'stock_alert_rate' => $totalProducts > 0 ? round(($lowStockProducts / $totalProducts) * 100, 1) : 0
        ];
    }

    /**
     * Get finance data for analysis
     */
    private function getFinanceData($vendor)
    {
        $monthlyRevenue = \App\Models\Order::where('vendor_id', $vendor->id)
            ->where('order_status', 'delivered')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('order_amount') ?? 0;

        return [
            'monthly_revenue' => round($monthlyRevenue, 2),
            'revenue_growth' => $this->calculateMonthlyGrowth($vendor)
        ];
    }

    /**
     * Get financial overview data with 10-day projection and cash gap analysis
     */
    private function getFinancialOverviewData($vendor)
    {
        $today = Carbon::today();
        $tenDaysAhead = $today->copy()->addDays(10);

        $currentFact = app(\App\Services\WalletService::class)->calculateFinancialFact($vendor);

        $schedules = \App\Models\TransactionSchedule::where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->with(['counterparty', 'transactionCategory'])
            ->get();

        $installments = \App\Models\OrderInstallment::where('created_by', $vendor->id)
            ->where('is_paid', false)
            ->where('remaining_balance', '>', 0)
            ->whereBetween('due_date', [$today, $tenDaysAhead])
            ->with('order.customer')
            ->get();

        $projectionDays = [];
        $runningBalance = $currentFact;
        $totalScheduledIncome = 0;
        $totalScheduledExpense = 0;
        $totalInstallmentPayments = 0;
        $cashGapDay = null;
        $cashGapAmount = 0;

        for ($date = $today->copy(); $date->lte($tenDaysAhead); $date->addDay()) {
            $dateString = $date->toDateString();
            $dayIncome = 0;
            $dayExpense = 0;
            $dayInstallments = 0;

            foreach ($schedules as $schedule) {
                if ($schedule->shouldAppearOnDate($dateString)) {
                    if (!$schedule->requires_approval || $schedule->isDateApproved($dateString)) {
                        if ($schedule->transaction_type->value === 'income') {
                            $dayIncome += $schedule->amount;
                        } elseif ($schedule->transaction_type->value === 'expense') {
                            $dayExpense += $schedule->amount;
                        }
                    }
                }
            }

            foreach ($installments as $installment) {
                if ($installment->due_date && $installment->due_date->toDateString() === $dateString) {
                    $dayInstallments += $installment->remaining_balance;
                }
            }

            $totalScheduledIncome += $dayIncome;
            $totalScheduledExpense += $dayExpense;
            $totalInstallmentPayments += $dayInstallments;

            $runningBalance += ($dayIncome + $dayInstallments - $dayExpense);

            if ($runningBalance < 0 && $cashGapDay === null) {
                $cashGapDay = $date->copy();
                $cashGapAmount = abs($runningBalance);
            }

            $projectionDays[] = [
                'date' => $dateString,
                'date_formatted' => $date->format('d.m.Y'),
                'income' => $dayIncome,
                'expense' => $dayExpense,
                'installments' => $dayInstallments,
                'net' => $dayIncome + $dayInstallments - $dayExpense,
                'running_balance' => $runningBalance
            ];
        }

        $minBalance = min(array_column($projectionDays, 'running_balance'));
        $hasCashGap = $minBalance < 0;

        $actualShortfall = 0;
        $salesNeeded = 0;
        $salesNeededWithProjections = 0;

        if ($hasCashGap) {
            $actualShortfall = abs($currentFact + $totalScheduledIncome + $totalInstallmentPayments - $totalScheduledExpense);
            $salesNeeded = abs($currentFact - $totalScheduledExpense);
            $salesNeededWithProjections = max(0, $actualShortfall - $totalScheduledIncome - $totalInstallmentPayments);
        }

        $absentCustomers = [];
        if ($hasCashGap) {
            $customers = \App\Models\User::where('created_by', $vendor->id)
                ->with(['orders' => function($q) {
                    $q->whereNotIn('order_status', ['refunded', 'canceled'])
                      ->orderBy('created_at', 'desc');
                }])
                ->get();

            foreach ($customers as $customer) {
                $absenceDays = $this->calculateCustomerAbsenceDays($customer);
                if ($absenceDays >= 30) {
                    $absentCustomers[] = [
                        'id' => $customer->id,
                        'user_number' => $customer->user_number,
                        'name' => trim($customer->f_name . ' ' . $customer->l_name),
                        'phone' => $customer->phone,
                        'absence_days' => $absenceDays === 999999 ? 'Никогда' : $absenceDays,
                        'total_orders' => $customer->orders->count(),
                        'last_order_date' => $customer->orders->first() ?
                            Carbon::parse($customer->orders->first()->created_at)->format('d.m.Y') : 'Никогда',
                        'favorite_products' => $this->getCustomerFavoriteProducts($customer)
                    ];
                }
            }

            usort($absentCustomers, function($a, $b) {
                return $b['absence_days'] <=> $a['absence_days'];
            });
        }

        return [
            'current_fact' => $currentFact,
            'projection_period' => '10 дней',
            'projection_days' => $projectionDays,
            'min_balance' => $minBalance,
            'has_cash_gap' => $hasCashGap,
            'cash_gap_day' => $cashGapDay ? $cashGapDay->format('d.m.Y') : null,
            'cash_gap_amount' => $hasCashGap ? abs($minBalance) : 0,
            'total_scheduled_income' => $totalScheduledIncome,
            'total_scheduled_expense' => $totalScheduledExpense,
            'total_installment_payments' => $totalInstallmentPayments,
            'actual_shortfall' => $actualShortfall,
            'sales_needed' => $salesNeeded,
            'sales_needed_with_projections' => $salesNeededWithProjections,
            'total_projected_income' => $totalScheduledIncome,
            'total_projected_expense' => $totalScheduledExpense,
            'net_projection' => $totalScheduledIncome + $totalInstallmentPayments - $totalScheduledExpense,
            'absent_customers' => array_slice($absentCustomers, 0, 10),
            'absent_customers_count' => count($absentCustomers)
        ];
    }

    /**
     * Get analytics data
     */
    private function getAnalyticsData($vendor)
    {
        return [
            'customers' => $this->getCustomersData($vendor),
            'orders' => $this->getOrdersData($vendor),
            'products' => $this->getProductsData($vendor),
            'finance' => $this->getFinanceData($vendor)
        ];
    }

    /**
     * Calculate weekly growth
     */
    private function calculateWeeklyGrowth($vendor)
    {
        $thisWeek = \App\Models\Order::where('vendor_id', $vendor->id)
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->count();
        
        $lastWeek = \App\Models\Order::where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [
                Carbon::now()->subWeek()->startOfWeek(),
                Carbon::now()->subWeek()->endOfWeek()
            ])
            ->count();

        if ($lastWeek == 0) return $thisWeek > 0 ? 100 : 0;
        
        return round((($thisWeek - $lastWeek) / $lastWeek) * 100, 1);
    }

    /**
     * Calculate monthly growth
     */
    private function calculateMonthlyGrowth($vendor)
    {
        $thisMonth = \App\Models\Order::where('vendor_id', $vendor->id)
            ->where('order_status', 'delivered')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('order_amount') ?? 0;
        
        $lastMonth = \App\Models\Order::where('vendor_id', $vendor->id)
            ->where('order_status', 'delivered')
            ->whereBetween('created_at', [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth()
            ])
            ->sum('order_amount') ?? 0;

        if ($lastMonth == 0) return $thisMonth > 0 ? 100 : 0;
        
        return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
    }

    /**
     * Generate insights using Groq AI
     */
    private function generateInsights($page, $data)
    {
        // Validate API key
        if (empty($this->groqApiKey)) {
            throw new \Exception('Groq API key not configured');
        }

        $prompt = $this->buildPrompt($page, $data);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type' => 'application/json',
                'User-Agent' => 'Laravel-CRM/1.0'
            ])->timeout(30)->retry(2, 1000)->post($this->groqBaseUrl . '/chat/completions', [
                'model' => config('ai.groq.model', 'llama-3.1-8b-instant'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Ты - эксперт по бизнес-аналитике для CRM системы. Отвечай только на русском языке. Давай краткие, практичные советы для малого бизнеса.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => config('ai.groq.max_tokens', 1000),
                'temperature' => config('ai.groq.temperature', 0.7)
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                throw new \Exception('AI API Error: ' . $errorBody);
            }

            $responseData = $response->json();

            if (!isset($responseData['choices'][0]['message']['content'])) {
                throw new \Exception('Invalid AI response format: ' . json_encode($responseData));
            }

            $aiContent = $responseData['choices'][0]['message']['content'];

            return $this->parseAIResponse($aiContent);

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Build prompt for specific page
     */
    private function buildPrompt($page, $data)
    {
        switch ($page) {
            case 'customers':
                return $this->buildCustomersPrompt($data);

            case 'orders':
                return "Проанализируй заказы:
- Всего заказов: " . ($data['total_orders'] ?? 0) . "
- Заказов за неделю: " . ($data['recent_orders'] ?? 0) . "
- Средний чек: " . ($data['avg_order_value'] ?? 0) . " сом
- Рост за неделю: " . ($data['weekly_growth'] ?? 0) . "%

Дай краткий анализ и 2-3 рекомендации для увеличения продаж.";

            case 'products':
                return "Проанализируй товары:
- Всего товаров: " . ($data['total_products'] ?? 0) . "
- Товаров с низким остатком: " . ($data['low_stock_products'] ?? 0) . "
- Процент товаров с низким остатком: " . ($data['stock_alert_rate'] ?? 0) . "%

Дай анализ и рекомендации по управлению товарами.";

            case 'finance':
                return "Проанализируй финансы:
- Выручка за месяц: " . ($data['monthly_revenue'] ?? 0) . " сом
- Рост выручки: " . ($data['revenue_growth'] ?? 0) . "%

Дай финансовый анализ и рекомендации.";

            case 'financial-overview':
                return $this->buildFinancialOverviewPrompt($data);

            case 'analytics':
                return "Проанализируй общую статистику бизнеса. Дай общий обзор и ключевые рекомендации для развития.";

            default:
                return "Проанализируй общую статистику бизнеса. Дай общий обзор и ключевые рекомендации для развития.";
        }
    }

    /**
     * Build financial overview prompt for detailed cash gap analysis
     */
    private function buildFinancialOverviewPrompt($data)
    {
        $currentFact = $data['current_fact'] ?? 0;
        $minBalance = $data['min_balance'] ?? 0;
        $hasCashGap = $data['has_cash_gap'] ?? false;
        $cashGapDay = $data['cash_gap_day'] ?? null;

        // Enhanced data for detailed analysis
        $scheduledIncome = $data['total_scheduled_income'] ?? 0;
        $scheduledExpense = $data['total_scheduled_expense'] ?? 0;
        $installmentPayments = $data['total_installment_payments'] ?? 0;
        $actualShortfall = $data['actual_shortfall'] ?? 0;
        $salesNeeded = $data['sales_needed'] ?? 0;
        $salesNeededWithProjections = $data['sales_needed_with_projections'] ?? 0;
        $absentCustomersCount = $data['absent_customers_count'] ?? 0;

        $prompt = "АНАЛИЗ КАССОВОГО ПОТОКА НА 10 ДНЕЙ:

ТЕКУЩЕЕ СОСТОЯНИЕ:
- Фактическая касса: {$currentFact} сом
- Запланированные доходы: {$scheduledIncome} сом
- Запланированные расходы: {$scheduledExpense} сом
- Погашения должников: {$installmentPayments} сом
- Минимальный баланс в периоде: {$minBalance} сом";

        if ($hasCashGap) {
            $gapDayText = $cashGapDay ? "через " . $this->calculateDaysUntil($cashGapDay) . " дн" : "";

            $prompt .= "

⚠️ КАССОВЫЙ РАЗРЫВ {$gapDayText}
Основание: Запланированные расходы на {$cashGapDay}

ДЕТАЛЬНЫЙ АНАЛИЗ:
1. Фактическая касса: +{$currentFact} сом
2. Запланированные расходы: -{$scheduledExpense} сом
3. Запланированный доход: +{$scheduledIncome} сом
4. Погашения должников: +{$installmentPayments} сом
5. Фактическое отставание: {$actualShortfall} сом

ДОСТУПНЫЕ ВАРИАНТЫ:
1. Сделать продажи на {$salesNeeded} сом (консервативный подход)
2. Оприходовать запланированный доход +{$scheduledIncome} сом, собрать долги +{$installmentPayments} сом, сделать продажи на +{$salesNeededWithProjections} сом

РЕКОМЕНДАЦИИ:
Не рекомендуется опираться на запланированные доходы, так как они могут не осуществиться. Рекомендуем выбрать вариант 1.

РАБОТА С КЛИЕНТАМИ:
- Клиентов не возвращались 30+ дней: {$absentCustomersCount}

Проанализируй ситуацию и дай конкретные рекомендации:
1. Запустите рекламную кампанию и проверьте уведомления клиентов
2. Стратегия возврата неактивных клиентов (30+ дней отсутствия)
3. Промо-акции и скидки для быстрого привлечения продаж (на 3 дня)
4. Каналы коммуникации для экстренного привлечения клиентов

Дай практичные советы для малого бизнеса в кризисной ситуации.";
        } else {
            $prompt .= "

✅ КАССОВЫЙ ПОТОК СТАБИЛЬНЫЙ

АНАЛИЗ:
- Прогнозируемый доход: +{$scheduledIncome} сом
- Прогнозируемые расходы: -{$scheduledExpense} сом
- Ожидаемые поступления от должников: +{$installmentPayments} сом
- Итоговый баланс: положительный

Проанализируй финансовое состояние и дай рекомендации:
1. Как улучшить денежный поток
2. Возможности для роста бизнеса
3. Профилактика будущих кассовых разрывов
4. Оптимизация работы с должниками

Дай практичные советы для развития бизнеса.";
        }

        return $prompt;
    }

    /**
     * Calculate days until a given date
     */
    private function calculateDaysUntil($dateString)
    {
        try {
            $targetDate = Carbon::parse($dateString);
            $today = Carbon::today();
            return max(0, $today->diffInDays($targetDate));
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * Parse AI response into structured format
     */
    private function parseAIResponse($content)
    {
        // Try to extract insights and recommendations from AI response
        $lines = explode("\n", trim($content));
        $insights = '';
        $recommendations = [];

        $inRecommendations = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Check if this line starts recommendations section
            if (preg_match('/рекомендаци|советы|предложения/ui', $line)) {
                $inRecommendations = true;
                continue;
            }

            // If we're in recommendations section and line starts with bullet or number
            if ($inRecommendations && (preg_match('/^[-•*\d\.]\s*(.+)/', $line, $matches) || preg_match('/^(.+)$/', $line, $matches))) {
                $recommendation = trim($matches[1] ?? $line);
                if (!empty($recommendation) && strlen($recommendation) > 10) {
                    $recommendations[] = $recommendation;
                }
            } else if (!$inRecommendations) {
                // Build insights from non-recommendation lines
                $insights .= $line . ' ';
            }
        }

        // Fallback: if no structured recommendations found, split by sentences
        if (empty($recommendations)) {
            $sentences = preg_split('/[.!?]+/', $content);
            $insights = trim(implode('. ', array_slice($sentences, 0, 2))) . '.';
            $recommendations = array_filter(array_map('trim', array_slice($sentences, 2, 3)), function($s) {
                return strlen($s) > 10;
            });
        }

        return [
            'insights' => trim($insights) ?: 'Анализ данных показывает текущее состояние вашего бизнеса.',
            'recommendations' => array_values(array_slice($recommendations, 0, 3))
        ];
    }

    /**
     * Check if data has changed since last analysis
     */
    private function hasDataChanged($vendor, $page)
    {
        $lastChangeKey = "ai_last_change_{$vendor->id}_{$page}";
        $lastChangeTime = Cache::get($lastChangeKey);

        if (!$lastChangeTime) {
            return true; // No previous analysis, consider as changed
        }

        $lastChangeCarbon = Carbon::parse($lastChangeTime);

        // Check for new data based on page type
        switch ($page) {
            case 'customers':
                return $this->hasNewCustomers($vendor, $lastChangeCarbon);
            case 'orders':
                return $this->hasNewOrders($vendor, $lastChangeCarbon);
            case 'products':
                return $this->hasNewProducts($vendor, $lastChangeCarbon);
            case 'finance':
                return $this->hasNewTransactions($vendor, $lastChangeCarbon);
            case 'financial-overview':
                return $this->hasNewFinancialData($vendor, $lastChangeCarbon);
            case 'analytics':
                return $this->hasAnyNewData($vendor, $lastChangeCarbon);
            default:
                return true;
        }
    }

    /**
     * Update last data change timestamp
     */
    private function updateLastDataChange($vendor, $page)
    {
        $lastChangeKey = "ai_last_change_{$vendor->id}_{$page}";
        Cache::put($lastChangeKey, Carbon::now()->toISOString(), now()->addDays(7));
    }

    /**
     * Check for new customers
     */
    private function hasNewCustomers($vendor, $lastChangeTime)
    {
        return \App\Models\User::where('created_by', $vendor->id)
            ->where('created_at', '>', $lastChangeTime)
            ->exists();
    }

    /**
     * Check for new orders
     */
    private function hasNewOrders($vendor, $lastChangeTime)
    {
        return \App\Models\Order::where('vendor_id', $vendor->id)
            ->where('created_at', '>', $lastChangeTime)
            ->exists();
    }

    /**
     * Check for new products
     */
    private function hasNewProducts($vendor, $lastChangeTime)
    {
        return \App\Models\Product::where('vendor_id', $vendor->id)
            ->where('created_at', '>', $lastChangeTime)
            ->exists();
    }

    /**
     * Check for new transactions
     */
    private function hasNewTransactions($vendor, $lastChangeTime)
    {
        return \App\Models\Transaction::where('vendor_id', $vendor->id)
            ->where('created_at', '>', $lastChangeTime)
            ->exists();
    }

    /**
     * Check for new financial data (transactions and schedules)
     */
    private function hasNewFinancialData($vendor, $lastChangeTime)
    {
        // Check for new transactions
        $hasNewTransactions = \App\Models\Transaction::where('vendor_id', $vendor->id)
            ->where('created_at', '>', $lastChangeTime)
            ->exists();

        // Check for new or updated transaction schedules
        $hasNewSchedules = \App\Models\TransactionSchedule::where('vendor_id', $vendor->id)
            ->where(function($query) use ($lastChangeTime) {
                $query->where('created_at', '>', $lastChangeTime)
                      ->orWhere('updated_at', '>', $lastChangeTime);
            })
            ->exists();

        return $hasNewTransactions || $hasNewSchedules;
    }

    /**
     * Check for any new data (for analytics page)
     */
    private function hasAnyNewData($vendor, $lastChangeTime)
    {
        return $this->hasNewCustomers($vendor, $lastChangeTime) ||
               $this->hasNewOrders($vendor, $lastChangeTime) ||
               $this->hasNewProducts($vendor, $lastChangeTime) ||
               $this->hasNewTransactions($vendor, $lastChangeTime);
    }

    /**
     * Calculate customer absence days (similar to existing resource logic)
     * Checks both orders and customerImports for last purchase date
     */
    private function calculateCustomerAbsenceDays($customer)
    {
        $lastOrderDate = null;

        $lastOrder = $customer->orders->first();
        if ($lastOrder) {
            $lastOrderDate = Carbon::parse($lastOrder->created_at);
        }

        if ($customer->customerImports && $customer->customerImports->isNotEmpty()) {
            $lastImportPurchase = $customer->customerImports
                                       ->sortByDesc('purchase_date')
                                       ->first();

            if ($lastImportPurchase && $lastImportPurchase->purchase_date) {
                $importDate = Carbon::parse($lastImportPurchase->purchase_date);

                if (!$lastOrderDate || $importDate->gt($lastOrderDate)) {
                    $lastOrderDate = $importDate;
                }
            }
        }

        if (!$lastOrderDate) {
            return 999999;
        }

        return $lastOrderDate->diffInDays(Carbon::now());
    }

    /**
     * Get customer's favorite products based on order history
     */
    private function getCustomerFavoriteProducts($customer)
    {
        if ($customer->orders->isEmpty()) {
            return 'Нет заказов';
        }

        $productCounts = [];
        if ($customer->orders) {
            foreach ($customer->orders as $order) {
                foreach ($order->details as $detail) {
                    if ($detail->food && $detail->food->name) {
                        $productName = $detail->food->name;
                        $productCounts[$productName] = ($productCounts[$productName] ?? 0) + $detail->quantity;
                    }
                }
            }
        }

        if (empty($productCounts)) {
            return 'Нет данных о продуктах';
        }

        arsort($productCounts);
        $topProducts = array_slice(array_keys($productCounts), 0, 3);

        return implode(', ', $topProducts);
    }

    /**
     * Build detailed customers prompt with absence analysis
     */
    private function buildCustomersPrompt($data)
    {
        $absentCustomers = $data['most_absent_customers'] ?? $data['absent_customers'] ?? [];
        $mostAbsent = array_slice($absentCustomers, 0, 5);

        $absentInfo = [];
        if (!empty($mostAbsent)) {
            $absentInfo = array_map(function($customer) {
                return "• " . ($customer['name'] ?? 'N/A') . " (" . ($customer['phone'] ?? 'N/A') . ") - " . ($customer['absence_days'] ?? 0) . " дней без заказов, любимые товары: " . ($customer['favorite_products'] ?? 'Нет данных');
            }, $mostAbsent);
        }

        $absentInfoText = !empty($absentInfo) ? implode("\n", $absentInfo) : "Нет данных о клиентах с длительным отсутствием";

        return "Проанализируй клиентскую базу с фокусом на возврат неактивных клиентов:

ОБЩАЯ СТАТИСТИКА:
- Всего клиентов: " . ($data['total_customers'] ?? 'N/A') . "
- Активных клиентов (30 дней): " . ($data['active_customers'] ?? 'N/A') . "
- Неактивных клиентов: " . ($data['inactive_customers'] ?? 'N/A') . "
- Процент активности: " . ($data['activity_rate'] ?? 'N/A') . "%

ТОП-5 КЛИЕНТОВ С НАИБОЛЬШИМ ОТСУТСТВИЕМ:
" . $absentInfoText . "

ЗАДАЧА: Дай анализ проблемы отсутствия клиентов и 3 конкретных персонализированных совета как вернуть неактивных клиентов, основываясь на их любимых товарах и истории покупок. Предложи конкретные акции или предложения.";
    }




}
