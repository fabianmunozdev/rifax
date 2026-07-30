<?php

namespace App\Actions\Raffles;

use App\Models\Raffle;
use App\Models\RaffleNumber;
use InvalidArgumentException;

class ProvisionRaffleNumbersAction
{
    public function execute(Raffle $raffle): int
    {
        if ($raffle->status !== 'draft') {
            throw new InvalidArgumentException('Only draft raffles can provision raffle numbers.');
        }

        if ($raffle->result_published_at !== null) {
            throw new InvalidArgumentException('Closed raffles cannot provision raffle numbers.');
        }

        if ($raffle->purchases()->exists() || $raffle->reservations()->where('status', 'active')->exists()) {
            throw new InvalidArgumentException('Cannot provision raffle numbers after raffle activity exists.');
        }

        $padLength = $raffle->normalizedNumberDigits();
        $start = $raffle->numberRangeStart();
        $end = $raffle->numberRangeEnd();

        $existingNumbers = RaffleNumber::query()
            ->where('raffle_id', $raffle->id)
            ->pluck('number')
            ->all();

        $existingLookup = array_fill_keys($existingNumbers, true);
        $timestamp = now();
        $rowsToInsert = [];
        $createdCount = 0;

        foreach (range($start, $end) as $value) {
            $number = str_pad((string) $value, $padLength, '0', STR_PAD_LEFT);

            if (isset($existingLookup[$number])) {
                continue;
            }

            $rowsToInsert[] = [
                'raffle_id' => $raffle->id,
                'number' => $number,
                'status' => 'available',
                'reserved_until' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            if (count($rowsToInsert) === 1000) {
                RaffleNumber::query()->insert($rowsToInsert);
                $createdCount += count($rowsToInsert);
                $rowsToInsert = [];
            }
        }

        if ($rowsToInsert !== []) {
            RaffleNumber::query()->insert($rowsToInsert);
            $createdCount += count($rowsToInsert);
        }

        return $createdCount;
    }
}
