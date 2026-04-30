<?php

namespace App\Models;

use App\Enums\TransactionCycleTypeEnum;
use App\Enums\TransactionTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TransactionSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'counterparty_id',
        'transaction_category_id',
        'transaction_type',
        'amount',
        'cycle_type',
        'description',
        'scheduled_date',
        'end_date',
        'status',
        'wallet_id',
        'requires_approval',
        'approved_dates',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cycle_type' => TransactionCycleTypeEnum::class,
        'transaction_type' => TransactionTypeEnum::class,
        'requires_approval' => 'boolean',
        'approved_dates' => 'array',
        'scheduled_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the vendor that owns the transaction schedule
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the counterparty for this schedule
     */
    public function counterparty()
    {
        return $this->belongsTo(Counterparty::class);
    }

    /**
     * Get the transaction category
     */
    public function transactionCategory()
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    /**
     * Get the wallet for this schedule
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Scope to filter by vendor
     */
    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    /**
     * Scope to filter active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get all dates this schedule should appear on calendar for a given date range
     */
    public function getCalendarDates($startDate, $endDate)
    {
        $dates = collect();
        $scheduledDate = Carbon::parse($this->scheduled_date ?? $this->created_at);
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $scheduleEndDate = $this->end_date ? Carbon::parse($this->end_date) : null;

        switch ($this->cycle_type) {
            case TransactionCycleTypeEnum::ONE_TIME:
                if ($scheduledDate->between($start, $end)) {
                    $dates->push($scheduledDate->toDateString());
                }
                break;

            case TransactionCycleTypeEnum::WEEKLY:
                $currentDate = $start->copy();
                while ($currentDate->lte($end)) {
                    if ($currentDate->dayOfWeek === $scheduledDate->dayOfWeek && $currentDate->gte($scheduledDate)) {
                        // Check if we've passed the end_date
                        if ($scheduleEndDate && $currentDate->gt($scheduleEndDate)) {
                            break;
                        }
                        $dates->push($currentDate->toDateString());
                    }
                    $currentDate->addDay();
                }
                break;

            case TransactionCycleTypeEnum::MONTHLY:
                $currentDate = $scheduledDate->copy();
                while ($currentDate->lte($end)) {
                    // Check if we've passed the end_date
                    if ($scheduleEndDate && $currentDate->gt($scheduleEndDate)) {
                        break;
                    }
                    if ($currentDate->gte($start) && $currentDate->gte($scheduledDate)) {
                        $dates->push($currentDate->toDateString());
                    }
                    $currentDate->addMonth();
                }
                break;
        }

        return $dates->toArray();
    }

    /**
     * Check if this schedule should appear on a specific date
     */
    public function shouldAppearOnDate($date)
    {
        $targetDate = Carbon::parse($date);
        $scheduledDate = Carbon::parse($this->scheduled_date ?? $this->created_at);
        $scheduleEndDate = $this->end_date ? Carbon::parse($this->end_date) : null;

        if ($targetDate->toDateString() < $scheduledDate->toDateString()) {
            return false;
        }

        // Check if we've passed the end_date
        if ($scheduleEndDate && $targetDate->gt($scheduleEndDate)) {
            return false;
        }

        return match($this->cycle_type) {
            TransactionCycleTypeEnum::ONE_TIME => $targetDate->toDateString() === $scheduledDate->toDateString(),
            TransactionCycleTypeEnum::WEEKLY => $targetDate->dayOfWeek === $scheduledDate->dayOfWeek,
            TransactionCycleTypeEnum::MONTHLY => $targetDate->day === $scheduledDate->day,
        };
    }

    /**
     * Check if a specific date is already approved
     */
    public function isDateApproved($date)
    {
        $approvedDates = $this->approved_dates ?? [];
        return in_array(Carbon::parse($date)->toDateString(), $approvedDates);
    }

    /**
     * Approve a specific date
     */
    public function approveDate($date)
    {
        $dateString = Carbon::parse($date)->toDateString();
        $approvedDates = $this->approved_dates ?? [];

        if (!in_array($dateString, $approvedDates)) {
            $approvedDates[] = $dateString;
            $this->update(['approved_dates' => $approvedDates]);
        }

        return true;
    }

    /**
     * Clean up old approved dates (older than 30 days)
     * Keep them for audit purposes but remove very old ones
     */
    public function cleanOldApprovedDates($keepDays = 30)
    {
        $approvedDates = $this->approved_dates ?? [];
        $cutoffDate = Carbon::now()->subDays($keepDays)->toDateString();

        // Keep only dates that are newer than cutoff date
        $filteredDates = array_filter($approvedDates, function($date) use ($cutoffDate) {
            return $date >= $cutoffDate;
        });

        // Update if any dates were removed
        if (count($filteredDates) !== count($approvedDates)) {
            $this->update(['approved_dates' => array_values($filteredDates)]);
            return count($approvedDates) - count($filteredDates); // Return number of cleaned dates
        }

        return 0;
    }

    /**
     * Get only future approved dates (for calendar display)
     */
    public function getFutureApprovedDates()
    {
        $approvedDates = $this->approved_dates ?? [];
        $today = Carbon::today()->toDateString();

        return array_filter($approvedDates, function($date) use ($today) {
            return $date >= $today;
        });
    }

    /**
     * Get calendar dates with approval status
     */
    public function getCalendarDatesWithApproval($startDate, $endDate)
    {
        $dates = collect();
        $scheduledDate = Carbon::parse($this->scheduled_date ?? $this->created_at);
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $scheduleEndDate = $this->end_date ? Carbon::parse($this->end_date) : null;
        $approvedDates = $this->approved_dates ?? [];

        switch ($this->cycle_type) {
            case TransactionCycleTypeEnum::ONE_TIME:
                if ($scheduledDate->between($start, $end)) {
                    $dates->push([
                        'date' => $scheduledDate->toDateString(),
                        'is_approved' => in_array($scheduledDate->toDateString(), $approvedDates),
                        'can_approve' => !in_array($scheduledDate->toDateString(), $approvedDates)
                    ]);
                }
                break;

            case TransactionCycleTypeEnum::WEEKLY:
                $currentDate = $start->copy();
                while ($currentDate->lte($end)) {
                    if ($currentDate->dayOfWeek === $scheduledDate->dayOfWeek && $currentDate->gte($scheduledDate)) {
                        // Check if we've passed the end_date
                        if ($scheduleEndDate && $currentDate->gt($scheduleEndDate)) {
                            break;
                        }
                        $dateString = $currentDate->toDateString();
                        $dates->push([
                            'date' => $dateString,
                            'is_approved' => in_array($dateString, $approvedDates),
                            'can_approve' => !in_array($dateString, $approvedDates)
                        ]);
                    }
                    $currentDate->addDay();
                }
                break;

            case TransactionCycleTypeEnum::MONTHLY:
                $currentDate = $scheduledDate->copy();
                while ($currentDate->lte($end)) {
                    // Check if we've passed the end_date
                    if ($scheduleEndDate && $currentDate->gt($scheduleEndDate)) {
                        break;
                    }
                    if ($currentDate->gte($start) && $currentDate->gte($scheduledDate)) {
                        $dateString = $currentDate->toDateString();
                        $dates->push([
                            'date' => $dateString,
                            'is_approved' => in_array($dateString, $approvedDates),
                            'can_approve' => !in_array($dateString, $approvedDates)
                        ]);
                    }
                    $currentDate->addMonth();
                }
                break;
        }

        return $dates->toArray();
    }

    /**
     * Get available statuses
     */
    public static function getStatuses()
    {
        return [
            'active' => 'Активный',
            'paused' => 'Приостановлен',
            'completed' => 'Завершен',
            'cancelled' => 'Отменен',
        ];
    }
}
