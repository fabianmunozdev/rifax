<?php

namespace App\Actions\WhatsApp;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Payment;

class NotifySupportOfPaymentProofAction
{
    public function __construct(
        protected QueueOperationalCampaignWhatsappAction $queueOperationalCampaignWhatsappAction,
    ) {}

    public function execute(Payment $payment): void
    {
        $payment->loadMissing([
            'purchase.customer',
            'purchase.raffle',
            'purchase.numbers',
            'proofs',
        ]);

        $supportPhone = $this->normalizePhone(CompanySetting::query()->value('support_phone'));

        if ($supportPhone === null) {
            return;
        }

        $purchase = $payment->purchase;

        if ($purchase === null) {
            return;
        }

        $proof = $payment->proofs->first();
        $proofUrl = $proof !== null && filled($proof->storage_path) && $proof->storage_disk === 'public'
            ? asset('storage/'.$proof->storage_path)
            : '-';

        $supportCustomer = Customer::query()->firstOrCreate(
            ['phone' => $supportPhone],
            [
                'name' => 'Soporte Rifax',
                'wa_id' => preg_replace('/\D+/', '', $supportPhone),
            ],
        );

        if (blank($supportCustomer->wa_id)) {
            $supportCustomer->forceFill([
                'wa_id' => preg_replace('/\D+/', '', $supportPhone),
            ])->save();
        }

        $paymentAdminUrl = url(PaymentResource::getUrl('view', ['record' => $payment]));
        $customer = $purchase->customer;
        $customerName = $customer?->name ?: 'Cliente sin nombre';
        $customerPhone = $customer?->phone ?: '-';
        $customerDocument = $customer?->document_number ?: '-';
        $raffleTitle = $purchase->raffle_title_snapshot ?: $purchase->raffle?->title ?: 'Rifa sin titulo';
        $numbers = $purchase->numbers->pluck('number')->implode(', ') ?: '-';

        $this->queueOperationalCampaignWhatsappAction->execute(
            customer: $supportCustomer,
            intent: 'admin_payment_proof_submitted',
            variables: [
                'purchase_id' => (string) $purchase->id,
                'payment_id' => (string) $payment->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_document' => $customerDocument,
                'raffle_title' => $raffleTitle,
                'purchase_total' => number_format((float) $purchase->total_amount, 0),
                'purchase_numbers' => $numbers,
                'proof_url' => $proofUrl,
                'payment_admin_url' => $paymentAdminUrl,
            ],
            context: [
                'purchase_id' => $purchase->id,
                'payment_id' => $payment->id,
                'campaign_type' => 'admin_payment_proof_submitted',
            ],
            fallback: 'Nuevo comprobante recibido.'.PHP_EOL.PHP_EOL
                .'Compra: #{purchase_id}'.PHP_EOL
                .'Pago: #{payment_id}'.PHP_EOL
                .'Cliente: {customer_name}'.PHP_EOL
                .'Telefono: {customer_phone}'.PHP_EOL
                .'Documento: {customer_document}'.PHP_EOL
                .'Rifa: {raffle_title}'.PHP_EOL
                .'Numeros: {purchase_numbers}'.PHP_EOL
                .'Total: {purchase_total}'.PHP_EOL
                .'Comprobante: {proof_url}'.PHP_EOL
                .'Revisar pago: {payment_admin_url}',
            dedupHours: 24,
            templateDefaults: [
                'template_name' => 'admin_payment_proof_submitted',
            ],
        );
    }

    protected function normalizePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone ?? '');

        if ($digits === null || $digits === '') {
            return null;
        }

        return '+'.$digits;
    }
}
