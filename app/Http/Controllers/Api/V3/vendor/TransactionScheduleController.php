<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Http\Controllers\Controller;
use App\Http\Traits\VendorEmployeeAccess;
use App\Models\TransactionSchedule;
use App\Enums\TransactionCycleTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransactionScheduleController extends Controller
{
    use VendorEmployeeAccess;

    /**
     * Display a listing of transaction schedules
     */
    public function index(Request $request)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        $query = TransactionSchedule::forVendor($vendor->id)
            ->with(['counterparty', 'transactionCategory']);

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by cycle type
        if ($request->has('cycle_type') && $request->cycle_type) {
            $query->where('cycle_type', $request->cycle_type);
        }

        // Filter by transaction type
        if ($request->has('transaction_type') && $request->transaction_type) {
            $query->where('transaction_type', $request->transaction_type);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $schedules = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $schedules->items(),
            'meta' => [
                'current_page' => $schedules->currentPage(),
                'last_page' => $schedules->lastPage(),
                'per_page' => $schedules->perPage(),
                'total' => $schedules->total(),
            ],
            'links' => [
                'first' => $schedules->url(1),
                'last' => $schedules->url($schedules->lastPage()),
                'prev' => $schedules->previousPageUrl(),
                'next' => $schedules->nextPageUrl(),
            ]
        ]);
    }

    /**
     * Store a newly created transaction schedule
     */
    public function store(Request $request)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        // Validation rules
        $rules = [
            'counterparty_id' => 'required|exists:counterparties,id',
            'transaction_category_id' => 'required|exists:transaction_categories,id',
            'transaction_type' => ['required', 'string', 'in:income,expense,dividends'],
            'amount' => 'required|numeric|min:0.01',
            'cycle_type' => ['required', 'string', 'in:one_time,weekly,monthly'],
            'description' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'scheduled_date' => ['nullable', function ($attribute, $value, $fail) {
                // Accept both Y-m-d and d.m.Y formats
                if ($value) {
                    $parsed = null;
                    if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value)) {
                        try {
                            $parsed = Carbon::createFromFormat('d.m.Y', $value);
                        } catch (\Exception $e) {
                            $fail('The scheduled_date format is invalid.');
                        }
                    } else {
                        try {
                            $parsed = Carbon::parse($value);
                        } catch (\Exception $e) {
                            $fail('The scheduled_date format is invalid.');
                        }
                    }
                    if ($parsed && $parsed->lt(Carbon::today())) {
                        $fail('The scheduled_date must be after or equal to today.');
                    }
                }
            }],
            'subcategory_id' => 'nullable|integer',
            'counterparty_type' => 'nullable|string|in:employee,client,supplier,investor,bank,partner,other',
            'wallet_id' => 'nullable|exists:wallets,id',
            'requires_approval' => 'nullable|boolean',
        ];

        // Add end_date validation based on cycle_type
        if ($request->cycle_type === 'weekly' || $request->cycle_type === 'monthly') {
            $rules['end_date'] = ['required', function ($attribute, $value, $fail) use ($request) {
                if (!$value) {
                    $fail('The end_date field is required for weekly and monthly schedules.');
                    return;
                }
                // Accept both Y-m-d and d.m.Y formats
                $endDateParsed = null;
                if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value)) {
                    try {
                        $endDateParsed = Carbon::createFromFormat('d.m.Y', $value);
                    } catch (\Exception $e) {
                        $fail('The end_date format is invalid.');
                        return;
                    }
                } else {
                    try {
                        $endDateParsed = Carbon::parse($value);
                    } catch (\Exception $e) {
                        $fail('The end_date format is invalid.');
                        return;
                    }
                }

                // Parse scheduled_date for comparison
                $scheduledDateValue = $request->scheduled_date;
                $scheduledDateParsed = null;
                if ($scheduledDateValue) {
                    if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $scheduledDateValue)) {
                        try {
                            $scheduledDateParsed = Carbon::createFromFormat('d.m.Y', $scheduledDateValue);
                        } catch (\Exception $e) {
                            // Will be caught by scheduled_date validation
                        }
                    } else {
                        try {
                            $scheduledDateParsed = Carbon::parse($scheduledDateValue);
                        } catch (\Exception $e) {
                            // Will be caught by scheduled_date validation
                        }
                    }
                }

                if ($scheduledDateParsed && $endDateParsed->lt($scheduledDateParsed)) {
                    $fail('The end_date must be after or equal to scheduled_date.');
                }
            }];
        } else {
            $rules['end_date'] = 'nullable|date';
        }

        $request->validate($rules);

        // Convert date format from DD.MM.YYYY to Y-m-d if needed
        $scheduledDate = $request->scheduled_date;
        if ($scheduledDate && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $scheduledDate)) {
            $scheduledDate = Carbon::createFromFormat('d.m.Y', $scheduledDate)->format('Y-m-d');
        }

        $endDate = $request->end_date;
        if ($endDate && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $endDate)) {
            $endDate = Carbon::createFromFormat('d.m.Y', $endDate)->format('Y-m-d');
        }

        // Verify counterparty belongs to vendor
        $counterparty = \App\Models\Counterparty::where('id', $request->counterparty_id)
            ->where('vendor_id', $vendor->id)
            ->first();

        if (!$counterparty) {
            return response()->json(['message' => 'Контрагент не найден'], 404);
        }

        // Verify transaction category belongs to vendor
        $category = \App\Models\TransactionCategory::where('id', $request->transaction_category_id)
            ->where('vendor_id', $vendor->id)
            ->first();

        if (!$category) {
            return response()->json(['message' => 'Категория транзакций не найдена'], 404);
        }

        // Use description, fallback to notes for backward compatibility
        $description = $request->description ?? $request->notes;

        $schedule = TransactionSchedule::create([
            'vendor_id' => $vendor->id,
            'counterparty_id' => $request->counterparty_id,
            'transaction_category_id' => $request->transaction_category_id,
            'transaction_type' => $request->transaction_type,
            'amount' => $request->amount,
            'cycle_type' => $request->cycle_type,
            'description' => $description,
            'scheduled_date' => $scheduledDate,
            'end_date' => $endDate,
            'status' => 'active',
            'wallet_id' => $request->wallet_id,
            'requires_approval' => $request->requires_approval ?? true,
        ]);

        $schedule->load(['counterparty', 'transactionCategory']);

        return response()->json([
            'message' => 'Расписание транзакций успешно создано',
            'data' => $schedule
        ], 201);
    }

    /**
     * Display the specified transaction schedule
     */
    public function show($id)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        $schedule = TransactionSchedule::forVendor($vendor->id)
            ->with(['counterparty', 'transactionCategory'])
            ->find($id);

        if (!$schedule) {
            return response()->json(['message' => 'Расписание транзакций не найдено'], 404);
        }

        return response()->json(['data' => $schedule]);
    }

    /**
     * Update the specified transaction schedule
     */
    public function update(Request $request, $id)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        $schedule = TransactionSchedule::forVendor($vendor->id)->find($id);

        if (!$schedule) {
            return response()->json(['message' => 'Расписание транзакций не найдено'], 404);
        }

        // Validation rules
        $rules = [
            'counterparty_id' => 'sometimes|exists:counterparties,id',
            'transaction_category_id' => 'sometimes|exists:transaction_categories,id',
            'transaction_type' => ['sometimes', 'string', 'in:income,expense,dividends'],
            'amount' => 'sometimes|numeric|min:0.01',
            'cycle_type' => ['sometimes', 'string', 'in:one_time,weekly,monthly'],
            'description' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'scheduled_date' => ['sometimes', function ($attribute, $value, $fail) {
                // Accept both Y-m-d and d.m.Y formats
                if ($value) {
                    $parsed = null;
                    if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value)) {
                        try {
                            $parsed = Carbon::createFromFormat('d.m.Y', $value);
                        } catch (\Exception $e) {
                            $fail('The scheduled_date format is invalid.');
                        }
                    } else {
                        try {
                            $parsed = Carbon::parse($value);
                        } catch (\Exception $e) {
                            $fail('The scheduled_date format is invalid.');
                        }
                    }
                    if ($parsed && $parsed->lt(Carbon::today())) {
                        $fail('The scheduled_date must be after or equal to today.');
                    }
                }
            }],
            'status' => 'sometimes|in:active,paused,completed,cancelled',
        ];

        // Add end_date validation based on cycle_type
        $cycleType = $request->cycle_type ?? $schedule->cycle_type->value;
        if ($cycleType === 'weekly' || $cycleType === 'monthly') {
            $rules['end_date'] = ['required', function ($attribute, $value, $fail) use ($request, $schedule) {
                if (!$value) {
                    $fail('The end_date field is required for weekly and monthly schedules.');
                    return;
                }
                // Accept both Y-m-d and d.m.Y formats
                $endDateParsed = null;
                if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value)) {
                    try {
                        $endDateParsed = Carbon::createFromFormat('d.m.Y', $value);
                    } catch (\Exception $e) {
                        $fail('The end_date format is invalid.');
                        return;
                    }
                } else {
                    try {
                        $endDateParsed = Carbon::parse($value);
                    } catch (\Exception $e) {
                        $fail('The end_date format is invalid.');
                        return;
                    }
                }

                // Parse scheduled_date for comparison
                $scheduledDateValue = $request->scheduled_date ?? $schedule->scheduled_date?->format('Y-m-d');
                $scheduledDateParsed = null;
                if ($scheduledDateValue) {
                    if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $scheduledDateValue)) {
                        try {
                            $scheduledDateParsed = Carbon::createFromFormat('d.m.Y', $scheduledDateValue);
                        } catch (\Exception $e) {
                            // Will be caught by scheduled_date validation
                        }
                    } else {
                        try {
                            $scheduledDateParsed = Carbon::parse($scheduledDateValue);
                        } catch (\Exception $e) {
                            // Will be caught by scheduled_date validation
                        }
                    }
                }

                if ($scheduledDateParsed && $endDateParsed->lt($scheduledDateParsed)) {
                    $fail('The end_date must be after or equal to scheduled_date.');
                }
            }];
        } else {
            $rules['end_date'] = 'nullable|date';
        }

        $request->validate($rules);

        // Convert date format from DD.MM.YYYY to Y-m-d if needed
        $scheduledDate = $request->scheduled_date;
        if ($scheduledDate && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $scheduledDate)) {
            $scheduledDate = Carbon::createFromFormat('d.m.Y', $scheduledDate)->format('Y-m-d');
        }

        $endDate = $request->end_date;
        if ($endDate && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $endDate)) {
            $endDate = Carbon::createFromFormat('d.m.Y', $endDate)->format('Y-m-d');
        }

        $updateData = $request->only([
            'counterparty_id',
            'transaction_category_id',
            'transaction_type',
            'amount',
            'cycle_type',
            'description',
            'status'
        ]);

        // Update description, supporting both description and notes (description takes precedence)
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        } elseif ($request->has('notes')) {
            $updateData['description'] = $request->notes;
        }

        if ($scheduledDate) {
            $updateData['scheduled_date'] = $scheduledDate;
        }
        if ($endDate !== null) {
            $updateData['end_date'] = $endDate;
        } elseif ($cycleType === 'one_time') {
            // Clear end_date for one_time schedules
            $updateData['end_date'] = null;
        }

        $schedule->update($updateData);

        $schedule->load(['counterparty', 'transactionCategory']);

        return response()->json([
            'message' => 'Расписание транзакций успешно обновлено',
            'data' => $schedule
        ]);
    }

    /**
     * Remove the specified transaction schedule
     */
    public function destroy($id)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        $schedule = TransactionSchedule::forVendor($vendor->id)->find($id);

        if (!$schedule) {
            return response()->json(['message' => 'Расписание транзакций не найдено'], 404);
        }

        $schedule->delete();

        return response()->json(['message' => 'Расписание транзакций успешно удалено']);
    }

    /**
     * Get cycle types for frontend
     */
    public function getCycleTypes()
    {
        return response()->json([
            'cycle_types' => [
                ['value' => 'one_time', 'label' => 'Единоразово'],
                ['value' => 'weekly', 'label' => 'Каждую неделю'],
                ['value' => 'monthly', 'label' => 'Ежемесячно'],
            ]
        ]);
    }

    /**
     * Get available statuses
     */
    public function getStatuses()
    {
        return response()->json([
            'statuses' => collect(TransactionSchedule::getStatuses())->map(function ($label, $value) {
                return ['value' => $value, 'label' => $label];
            })->values()
        ]);
    }

    /**
     * Get calendar data for transaction schedules
     */
    public function calendar($yearMonth)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        // Validate yearMonth format (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            return response()->json(['message' => 'Неверный формат даты. Используйте YYYY-MM'], 400);
        }

        // Calculate start and end dates for the month
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();

        $schedules = TransactionSchedule::forVendor($vendor->id)
            ->with(['counterparty', 'transactionCategory', 'wallet'])
            ->where('status', 'active')
            ->get();

        // Collect all schedule events
        $allEvents = collect();

        foreach ($schedules as $schedule) {
            $dates = $schedule->getCalendarDates($startDate->toDateString(), $endDate->toDateString());

            foreach ($dates as $date) {
                $isApproved = $schedule->isDateApproved($date);

                $allEvents->push([
                    'date' => $date,
                    'amount' => $schedule->amount,
                    'transaction_type' => $schedule->transaction_type->value,
                    'is_approved' => $isApproved,
                    'requires_approval' => $schedule->requires_approval,
                    'schedule' => [
                        'id' => $schedule->id,
                        'title' => $schedule->counterparty->name ?? 'Unknown',
                        'description' => $schedule->description,
                        'cycle_type' => $schedule->cycle_type->value,
                        'cycle_type_label' => $schedule->cycle_type->label(),
                        'category' => $schedule->transactionCategory->name ?? 'Unknown',
                        'created_at' => $schedule->created_at->format('d.m.Y'),
                    ]
                ]);
            }
        }

        // Group by date and aggregate by transaction type
        $calendarData = $allEvents->groupBy('date')->map(function ($dayEvents, $date) {
            $incomeEvents = $dayEvents->filter(function ($event) {
                return $event['transaction_type'] === 'income';
            });
            $expenseEvents = $dayEvents->filter(function ($event) {
                return $event['transaction_type'] === 'expense';
            });
            $dividendsEvents = $dayEvents->filter(function ($event) {
                return $event['transaction_type'] === 'dividends';
            });

            // Get approved events only
            $approvedIncomeEvents = $incomeEvents->filter(function ($event) {
                return !$event['requires_approval'] || $event['is_approved'];
            });
            $approvedExpenseEvents = $expenseEvents->filter(function ($event) {
                return !$event['requires_approval'] || $event['is_approved'];
            });
            $approvedDividendsEvents = $dividendsEvents->filter(function ($event) {
                return !$event['requires_approval'] || $event['is_approved'];
            });

            // Get unapproved events for counting
            $unapprovedEvents = $dayEvents->filter(function ($event) {
                return $event['requires_approval'] && !$event['is_approved'];
            });

            // Totals from all transactions for the day
            $incomeTotalAll = $incomeEvents->sum(function ($event) {
                return (float) $event['amount'];
            });
            $expenseTotalAll = $expenseEvents->sum(function ($event) {
                return (float) $event['amount'];
            });
            $dividendsTotalAll = $dividendsEvents->sum(function ($event) {
                return (float) $event['amount'];
            });

            // Totals only from approved transactions
            $incomeTotalApproved = $approvedIncomeEvents->sum(function ($event) {
                return (float) $event['amount'];
            });
            $expenseTotalApproved = $approvedExpenseEvents->sum(function ($event) {
                return (float) $event['amount'];
            });
            $dividendsTotalApproved = $approvedDividendsEvents->sum(function ($event) {
                return (float) $event['amount'];
            });

            return [
                'date' => $date,
                'income' => [
                    'count' => $incomeEvents->count(),
                    'approved_count' => $approvedIncomeEvents->count(),
                    'total_amount' => $incomeTotalAll,
                    'approved_total_amount' => $incomeTotalApproved,
                ],
                'expense' => [
                    'count' => $expenseEvents->count(),
                    'approved_count' => $approvedExpenseEvents->count(),
                    'total_amount' => $expenseTotalAll,
                    'approved_total_amount' => $expenseTotalApproved,
                ],
                'dividends' => [
                    'count' => $dividendsEvents->count(),
                    'approved_count' => $approvedDividendsEvents->count(),
                    'total_amount' => $dividendsTotalAll,
                    'approved_total_amount' => $dividendsTotalApproved,
                ],
                'total_schedules' => $dayEvents->count(),
                'unapproved_count' => $unapprovedEvents->count(),
                'has_unapproved' => $unapprovedEvents->count() > 0,
                'net_amount' => $incomeTotalAll + $dividendsTotalAll - $expenseTotalAll,
                'approved_net_amount' => $incomeTotalApproved + $dividendsTotalApproved - $expenseTotalApproved,
            ];
        })->sortKeys();

        // Calculate totals across all dates
        $totalIncome = 0;
        $totalExpense = 0;
        $totalCompleted = 0; // Approved income + expense
        $totalNotCompleted = 0; // Unapproved income + expense

        foreach ($calendarData as $dayData) {
            // Add to totals
            $totalIncome += $dayData['income']['total_amount'];
            $totalExpense += $dayData['expense']['total_amount'];
            
            // Completed = approved income + approved expense
            $totalCompleted += $dayData['income']['approved_total_amount'] + $dayData['expense']['approved_total_amount'];
            
            // Not completed = unapproved income + unapproved expense
            $totalNotCompleted += ($dayData['income']['total_amount'] - $dayData['income']['approved_total_amount']) 
                                + ($dayData['expense']['total_amount'] - $dayData['expense']['approved_total_amount']);
        }

        return response()->json([
            'calendar' => $calendarData->values(),
            'total' => [
                'income_total' => $totalIncome,
                'expense_total' => $totalExpense,
                'completed_total' => $totalCompleted, // Approved income + expense
                'not_completed_total' => $totalNotCompleted, // Unapproved income + expense
            ],
        ]);
    }

    /**
     * Get list of schedules that need approval for a specific date
     */
    public function approvalsForDate(Request $request)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date = $request->get('date');

        $schedules = TransactionSchedule::forVendor($vendor->id)
            ->with(['counterparty', 'transactionCategory', 'wallet'])
            ->where('status', 'active')
            ->where('requires_approval', true)
            ->get();

        $approvalsForDate = collect();

        foreach ($schedules as $schedule) {
            // Check if this schedule should appear on the requested date
            if ($schedule->shouldAppearOnDate($date)) {
                $isApproved = $schedule->isDateApproved($date);

                // Only include if not yet approved
                if (!$isApproved) {
                    $approvalsForDate->push([
                        'id' => $schedule->id,
                        'title' => $schedule->counterparty->name ?? 'Unknown',
                        'description' => $schedule->description,
                        'amount' => $schedule->amount,
                        'transaction_type' => $schedule->transaction_type->value,
                        'transaction_type_label' => $schedule->transaction_type->label(),
                        'cycle_type' => $schedule->cycle_type->value,
                        'cycle_type_label' => $schedule->cycle_type->label(),
                        'category' => $schedule->transactionCategory->name ?? 'Unknown',
                        'wallet' => $schedule->wallet->name ?? 'Unknown',
                        'wallet_id' => $schedule->wallet_id,
                        'counterparty_id' => $schedule->counterparty_id,
                        'transaction_category_id' => $schedule->transaction_category_id,
                        'created_at' => $schedule->created_at->format('d.m.Y'),
                        'can_approve' => true,
                        'approval_date' => $date
                    ]);
                }
            }
        }

        return response()->json([
            'date' => $date,
            'approvals' => $approvalsForDate->values(),
            'total_count' => $approvalsForDate->count(),
            'summary' => [
                'income_count' => $approvalsForDate->where('transaction_type', 'income')->count(),
                'expense_count' => $approvalsForDate->where('transaction_type', 'expense')->count(),
                'dividends_count' => $approvalsForDate->where('transaction_type', 'dividends')->count(),
                'total_income_amount' => $approvalsForDate->where('transaction_type', 'income')->sum('amount'),
                'total_expense_amount' => $approvalsForDate->where('transaction_type', 'expense')->sum('amount'),
                'total_dividends_amount' => $approvalsForDate->where('transaction_type', 'dividends')->sum('amount'),
            ]
        ]);
    }

    /**
     * Get calendar events with approval status
     */
    public function calendarWithApproval($yearMonth)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        // Validate yearMonth format (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            return response()->json(['message' => 'Неверный формат даты. Используйте YYYY-MM'], 400);
        }

        // Calculate start and end dates for the month
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();

        $schedules = TransactionSchedule::forVendor($vendor->id)
            ->with(['counterparty', 'transactionCategory', 'wallet'])
            ->where('status', 'active')
            ->where('requires_approval', true)
            ->get();

        $calendarEvents = collect();

        foreach ($schedules as $schedule) {
            $datesWithApproval = $schedule->getCalendarDatesWithApproval($startDate->toDateString(), $endDate->toDateString());

            foreach ($datesWithApproval as $dateInfo) {
                $calendarEvents->push([
                    'id' => $schedule->id,
                    'date' => $dateInfo['date'],
                    'title' => $schedule->counterparty->name ?? 'Unknown',
                    'description' => $schedule->description,
                    'amount' => $schedule->amount,
                    'transaction_type' => $schedule->transaction_type,
                    'cycle_type' => $schedule->cycle_type->value,
                    'cycle_type_label' => $schedule->cycle_type->label(),
                    'category' => $schedule->transactionCategory->name ?? 'Unknown',
                    'wallet' => $schedule->wallet->name ?? 'Unknown',
                    'is_approved' => $dateInfo['is_approved'],
                    'can_approve' => $dateInfo['can_approve'],
                    'requires_approval' => $schedule->requires_approval,
                    'created_at' => $schedule->created_at->format('d.m.Y'),
                ]);
            }
        }

        return response()->json([
            'events' => $calendarEvents->sortBy('date')->values()
        ]);
    }

    /**
     * Approve a transaction schedule for a specific date
     */
    public function approve(Request $request, $id)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        $request->validate([
            'approval_date' => 'required|date',
            'wallet_id' => 'required|exists:vendor_wallets,id',
            'description' => 'nullable|string|max:1000',
        ]);

        // Get VendorWallet and verify it belongs to vendor
        $vendorWallet = \App\Models\VendorWallet::where('id', $request->wallet_id)
            ->where('vendor_id', $vendor->id)
            ->with('wallet')
            ->first();

        if (!$vendorWallet) {
            return response()->json(['message' => 'Кошелек не найден'], 404);
        }

        $schedule = TransactionSchedule::forVendor($vendor->id)
            ->with(['counterparty', 'transactionCategory', 'wallet'])
            ->find($id);

        if (!$schedule) {
            return response()->json(['message' => 'Расписание транзакций не найдено'], 404);
        }

        // Check if date is already approved
        if ($schedule->isDateApproved($request->approval_date)) {
            return response()->json(['message' => 'Эта дата уже одобрена'], 400);
        }

        // Check if the date should appear on calendar for this schedule
        if (!$schedule->shouldAppearOnDate($request->approval_date)) {
            return response()->json(['message' => 'Неверная дата для этого расписания'], 400);
        }
        
        try {
            DB::beginTransaction();

            // Approve the date
            $schedule->approveDate($request->approval_date);

            // Use provided description or fall back to schedule description
            $transactionDescription = $request->description ?? $schedule->description;
            if ($transactionDescription) {
                $transactionDescription .= ' (Одобрено из расписания)';
            } else {
                $transactionDescription = 'Одобрено из расписания';
            }

            // Create the main transaction for business records
            $transaction = \App\Models\Transaction::create([
                'vendor_id' => $vendor->id,
                'name' => $schedule->transactionCategory->name ?? 'Транзакция из расписания',
                'amount' => $schedule->amount,
                'transaction_category_id' => $schedule->transaction_category_id,
                'description' => $transactionDescription,
                'type' => $schedule->transaction_type->value,
                'status' => 'success',
            ]);

            // Calculate wallet amount (negative for expenses)
            $walletAmount = $schedule->transaction_type->value === 'expense'
                ? -$schedule->amount  // Subtract for expenses
                : $schedule->amount;  // Add for income/dividends

            // Create wallet transaction to track money movement
            $walletTransaction = \App\Models\VendorWalletTransaction::create([
                'vendor_id' => $vendor->id,
                'vendor_wallet_id' => $vendorWallet->id,
                'transaction_id' => $transaction->id, // Link to main transaction
                'order_id' => null, // No order associated with schedule approval
                'amount' => $walletAmount, // Negative for expenses, positive for income
                'status' => 'success',
                'reference' => 'schedule_approval_' . $schedule->id . '_' . $request->approval_date,
                'meta' => json_encode([
                    'transaction_type' => $schedule->transaction_type->value,
                    'description' => $transactionDescription,
                    'schedule_id' => $schedule->id,
                    'approval_date' => $request->approval_date,
                    'category_name' => $schedule->transactionCategory->name ?? 'Транзакция из расписания',
                    'counterparty_name' => $schedule->counterparty->name ?? 'Unknown',
                ]),
                'paid_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Транзакция успешно одобрена и создана',
                'data' => [
                    'schedule' => $schedule->fresh(),
                    'transaction' => $transaction,
                    'wallet_transaction' => $walletTransaction
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при одобрении транзакции: ' . $e->getMessage()
            ], 500);
        }
    }
}
