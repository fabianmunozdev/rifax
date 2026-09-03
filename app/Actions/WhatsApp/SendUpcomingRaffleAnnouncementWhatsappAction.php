<?php

namespace App\Actions\WhatsApp;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Models\Customer;
use App\Models\Raffle;
use App\Models\User;
use InvalidArgumentException;

class SendUpcomingRaffleAnnouncementWhatsappAction
{
    public function __construct(
        protected QueueOperationalCampaignWhatsappAction $queueOperationalCampaignWhatsappAction,
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    /**
     * @return array{queued: int, skipped: int}
     */
    public function execute(Raffle $raffle, ?User $actor = null): array
    {
        if ($raffle->status !== 'published' || $raffle->result_published_at !== null) {
            throw new InvalidArgumentException('Upcoming raffle announcements are only available for active published raffles.');
        }

        $queued = 0;
        $skipped = 0;

        Customer::query()
            ->where(function ($query): void {
                $query
                    ->whereNotNull('last_interaction_at')
                    ->orWhereHas('purchases');
            })
            ->whereDoesntHave('purchases', function ($query) use ($raffle): void {
                $query
                    ->where('raffle_id', $raffle->id)
                    ->whereIn('status', ['reserved', 'payment_submitted', 'under_review', 'paid']);
            })
            ->orderBy('id')
            ->chunkById(100, function ($customers) use ($raffle, &$queued, &$skipped): void {
                foreach ($customers as $customer) {
                    if (! $customer instanceof Customer) {
                        $skipped++;

                        continue;
                    }

                    $message = $this->queueOperationalCampaignWhatsappAction->execute(
                        customer: $customer,
                        intent: 'upcoming_raffle_announcement',
                        variables: [
                            'customer_name' => $customer->name ?: 'cliente',
                            'raffle_title' => $raffle->title ?: 'nuestra proxima rifa',
                            'draw_date' => $raffle->draw_date?->format('Y-m-d') ?: '-',
                            'draw_time' => $raffle->draw_time ?: '-',
                            'price_per_number' => number_format((float) $raffle->price_per_number, 0),
                            'minimum_numbers' => (string) $raffle->min_numbers_per_purchase,
                            'lottery_name' => $raffle->lottery_name ?: 'la lotería oficial',
                        ],
                        context: [
                            'raffle_id' => $raffle->id,
                            'campaign_type' => 'upcoming_raffle_announcement',
                        ],
                        fallback: 'Hola {customer_name}, ya está disponible {raffle_title}. El sorteo será el {draw_date} a las {draw_time}, cada número cuesta {price_per_number} y la compra mínima es de {minimum_numbers}. Responde MENU para iniciar tu compra.',
                        dedupHours: 72,
                        templateDefaults: [
                            'template_name' => 'upcoming_raffle_announcement',
                        ],
                    );

                    if ($message === null) {
                        $skipped++;

                        continue;
                    }

                    $queued++;
                }
            });

        $this->recordAdminAuditAction->execute(
            event: 'campaign.upcoming_raffle_announcement_requested',
            action: 'launch_upcoming_raffle',
            auditable: $raffle,
            context: [
                'raffle_id' => $raffle->id,
                'queued_count' => $queued,
                'skipped_count' => $skipped,
            ],
            user: $actor,
        );

        return [
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }
}
