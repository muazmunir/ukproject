<?php
// app/Services/FeeEngine.php
namespace App\Services;

use App\Models\BookingFee;

class FeeEngine
{
    /**
     * @return array{breakdown: array<int,array{code:string,label:string,amount:float}>, total: float}
     */
    public function calculate(float $subtotal, int $days): array
    {
        $fees = BookingFee::active()->orderBy('id')->get();
        $breakdown = [];
        $total = $subtotal;

        foreach ($fees as $fee) {
            $base = match ($fee->applies_to) {
                'per_day'   => $subtotal * $days,
                'subtotal'  => $subtotal,
                default     => 1.0 // per_booking, use scalar 1 then multiply for flat only
            };

            $amount = 0.0;
            if ($fee->kind === 'percent') {
                $amount = round(($base) * ($fee->value / 100), 2);
            } else { // flat
                $amount = round(
                    $fee->applies_to === 'per_day' ? ($fee->value * $days) : $fee->value,
                    2
                );
            }

            if ($amount <= 0) continue;

            $breakdown[] = ['code'=>$fee->code, 'label'=>$fee->label, 'amount'=>$amount];
            $total += $amount;
        }

        return ['breakdown'=>$breakdown, 'total'=>round($total,2)];
    }
}
