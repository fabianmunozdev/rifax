<?php

namespace App\Actions\Purchases;

use App\Models\Raffle;
use InvalidArgumentException;

class SelectRandomRaffleNumbersAction
{
    /**
     * @return list<string>
     */
    public function execute(Raffle $raffle, int $quantity): array
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('La cantidad solicitada para asignacion aleatoria no es valida.');
        }

        if ($quantity > $raffle->expectedNumberCatalogCount()) {
            throw new InvalidArgumentException('La cantidad solicitada excede el total de numeros de esta rifa.');
        }

        $availableCount = $raffle->numbers()
            ->where('status', 'available')
            ->count();

        if ($availableCount < $quantity) {
            throw new InvalidArgumentException('No hay suficientes numeros disponibles para asignacion aleatoria en este momento.');
        }

        if (! $raffle->random_selection_by_blocks) {
            return $this->selectFromGlobalPool($raffle, $quantity);
        }

        return $this->selectOneNumberPerBlock($raffle, $quantity);
    }

    /**
     * @return list<string>
     */
    protected function selectFromGlobalPool(Raffle $raffle, int $quantity): array
    {
        return $raffle->numbers()
            ->where('status', 'available')
            ->inRandomOrder()
            ->limit($quantity)
            ->pluck('number')
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function selectOneNumberPerBlock(Raffle $raffle, int $quantity): array
    {
        $digits = $raffle->normalizedNumberDigits();
        $selectedNumbers = [];

        foreach ($this->buildBlocks($raffle, $quantity) as $block) {
            $number = $raffle->numbers()
                ->where('status', 'available')
                ->whereBetween('number', [
                    str_pad((string) $block['start'], $digits, '0', STR_PAD_LEFT),
                    str_pad((string) $block['end'], $digits, '0', STR_PAD_LEFT),
                ])
                ->inRandomOrder()
                ->value('number');

            if ($number === null) {
                continue;
            }

            $selectedNumbers[] = $number;
        }

        $remaining = $quantity - count($selectedNumbers);

        if ($remaining > 0) {
            $fallbackNumbers = $raffle->numbers()
                ->where('status', 'available')
                ->when($selectedNumbers !== [], fn ($query) => $query->whereNotIn('number', $selectedNumbers))
                ->inRandomOrder()
                ->limit($remaining)
                ->pluck('number')
                ->all();

            $selectedNumbers = array_values(array_merge($selectedNumbers, $fallbackNumbers));
        }

        if (count($selectedNumbers) !== $quantity) {
            throw new InvalidArgumentException('No hay suficientes numeros disponibles para asignacion aleatoria en este momento.');
        }

        return $selectedNumbers;
    }

    /**
     * @return list<array{start: int, end: int}>
     */
    protected function buildBlocks(Raffle $raffle, int $quantity): array
    {
        $totalNumbers = $raffle->expectedNumberCatalogCount();
        $baseSize = intdiv($totalNumbers, $quantity);
        $remainder = $totalNumbers % $quantity;
        $blocks = [];
        $currentStart = $raffle->numberRangeStart();

        for ($index = 0; $index < $quantity; $index++) {
            $blockSize = $baseSize + ($index < $remainder ? 1 : 0);
            $currentEnd = $currentStart + $blockSize - 1;

            $blocks[] = [
                'start' => $currentStart,
                'end' => $currentEnd,
            ];

            $currentStart = $currentEnd + 1;
        }

        return $blocks;
    }
}
