<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function recordSalePayment(
        Sale $sale,
        float $amount,
        string $paymentDate,
        string $method,
        ?string $reference,
        ?string $notes,
        User $user
    ): Payment {
        $this->ensurePermission($user, 'create payments');

        return DB::transaction(function () use (
            $sale,
            $amount,
            $paymentDate,
            $method,
            $reference,
            $notes,
            $user
        ): Payment {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if (! $sale->isCompleted()) {
                throw ValidationException::withMessages([
                    'payment' => 'Payments can only be recorded for completed sales.',
                ]);
            }

            return $this->record(
                $sale,
                Payment::DIRECTION_RECEIPT,
                $amount,
                $paymentDate,
                $method,
                $reference,
                $notes,
                $user
            );
        }, attempts: 3);
    }

    public function recordPurchasePayment(
        Purchase $purchase,
        float $amount,
        string $paymentDate,
        string $method,
        ?string $reference,
        ?string $notes,
        User $user
    ): Payment {
        $this->ensurePermission($user, 'create payments');

        return DB::transaction(function () use (
            $purchase,
            $amount,
            $paymentDate,
            $method,
            $reference,
            $notes,
            $user
        ): Payment {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if (! in_array($purchase->status, [Purchase::STATUS_APPROVED, Purchase::STATUS_RECEIVED], true)) {
                throw ValidationException::withMessages([
                    'payment' => 'Payments can only be recorded for approved or received purchases.',
                ]);
            }

            return $this->record(
                $purchase,
                Payment::DIRECTION_PAYMENT,
                $amount,
                $paymentDate,
                $method,
                $reference,
                $notes,
                $user
            );
        }, attempts: 3);
    }

    private function record(
        Sale|Purchase $payable,
        string $direction,
        float $amount,
        string $paymentDate,
        string $method,
        ?string $reference,
        ?string $notes,
        User $user
    ): Payment {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        $remainingDue = max(0, (float) $payable->due_amount);

        if ($amount > $remainingDue) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount cannot exceed the remaining due amount.',
            ]);
        }

        $payment = $payable->payments()->create([
            'payment_number' => $this->generatePaymentNumber(),
            'direction' => $direction,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'method' => $method,
            'reference' => filled($reference) ? trim($reference) : null,
            'notes' => filled($notes) ? trim($notes) : null,
            'created_by' => $user->id,
        ]);

        $newPaid = (float) $payable->paid_amount + $amount;
        $grandTotal = (float) $payable->grand_total;
        $newDue = max(0, $grandTotal - $newPaid);

        $payable->update([
            'paid_amount' => $newPaid,
            'due_amount' => $newDue,
            'payment_status' => $newDue <= 0
                ? 'paid'
                : ($newPaid > 0 ? 'partial' : 'unpaid'),
        ]);

        return $payment->fresh(['payable', 'createdBy']);
    }

    private function generatePaymentNumber(): string
    {
        do {
            $number = 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Payment::withTrashed()->where('payment_number', $number)->exists());

        return $number;
    }

    private function ensurePermission(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }
}
