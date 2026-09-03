<?php

namespace App\Actions\WhatsApp;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\User;
use InvalidArgumentException;

class SendRaffleDrawReminderWhatsappAction
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
            throw new InvalidArgumentException('Draw reminders are only available for published raffles without a published result.');
        }

        $queued = 0;
        $skipped = 0;

        Purchase::query()
            ->with([
                'customer',
                'raffle',
                'ticket',
            ])
            ->where('raffle_id', $raffle->id)
            ->where('status', 'paid')
            ->orderBy('id')
            ->chunkById(100, function ($purchases) use (&$queued, &$skipped): void {
                foreach ($purchases as $purchase) {
                    if (! $purchase instanceof Purchase || $purchase->customer === null) {
                        $skipped++;

                        continue;
                    }

                    $message = $this->queueOperationalCampaignWhatsappAction->execute(
                        customer: $purchase->customer,
                        intent: 'raffle_draw_reminder',
                        variables: [
                            'customer_name' => $purchase->customer->name ?: 'cliente',
                            'raffle_title' => $purchase->raffle_title_snapshot ?: $purchase->raffle?->title ?: 'tu rifa',
                            'draw_date' => $purchase->raffle?->draw_date?->format('Y-m-d') ?: '-',
                            'draw_time' => $purchase->raffle?->draw_time ?: '-',
                            'lottery_name' => $purchase->raffle?->lottery_name ?: 'la lotería oficial',
                            'lottery_draw_number' => $purchase->raffle?->lottery_draw_number ?: '-',
                            'ticket_code' => $purchase->ticket?->code ?: 'P-'.str_pad((string) $purchase->id, 6, '0', STR_PAD_LEFT),
                            'ticket_url' => $purchase->ticket?->public_url ?: '',
                        ],
                        context: [
                            'raffle_id' => $purchase->raffle_id,
                            'purchase_id' => $purchase->id,
                            'ticket_id' => $purchase->ticket?->id,
                            'campaign_type' => 'raffle_draw_reminder',
                        ],
                        fallback: 'Hola {customer_name}, te recordamos que {raffle_title} se juega el {draw_date} a las {draw_time}. Conserva tu boleto {ticket_code} y sigue el resultado oficial por este medio.',
                        dedupHours: 24,
                        templateDefaults: [
                            'template_name' => 'raffle_draw_reminder',
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
            event: 'campaign.raffle_draw_reminder_requested',
            action: 'launch_draw_reminder',
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
