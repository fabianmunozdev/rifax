<?php

namespace Tests\Feature\WhatsApp\Twilio;

use App\Actions\WhatsApp\DispatchOutboundWhatsappMessageAction;
use App\Actions\WhatsApp\DownloadWhatsappMediaToStorageAction;
use App\Models\Customer;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\TwilioWebhookSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TwilioWhatsappProviderTest extends TestCase
{
    use RefreshDatabase;

    protected const SID = 'AC00000000000000000000000000000000';

    protected const TOKEN = 'twilio-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp.provider', 'twilio');
        config()->set('services.whatsapp.send_enabled', true);
        config()->set('services.twilio.account_sid', self::SID);
        config()->set('services.twilio.auth_token', self::TOKEN);
        config()->set('services.twilio.whatsapp_from', '+573150000000');
        config()->set('services.twilio.validate_signature', true);
    }

    public function test_inbound_text_is_processed_and_the_reply_is_sent_through_twilio(): void
    {
        Http::fake([
            'https://api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
            'https://content.twilio.com/*' => Http::response(['sid' => 'HX123'], 201),
        ]);

        $this->postTwilio('/api/webhooks/twilio/whatsapp', [
            'MessageSid' => 'SMin1',
            'From' => 'whatsapp:+573001112233',
            'WaId' => '573001112233',
            'ProfileName' => 'Ana',
            'Body' => 'MENU',
            'NumMedia' => '0',
        ])->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        $this->assertDatabaseHas('customers', ['wa_id' => '573001112233']);
        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'inbound',
            'provider_message_id' => 'SMin1',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'status' => 'sent',
            'provider_message_id' => 'SM123',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/'.self::SID.'/Messages.json'
            && $request['To'] === 'whatsapp:+573001112233'
            && $request['From'] === 'whatsapp:+573150000000'
            && str_ends_with((string) $request['StatusCallback'], '/api/webhooks/twilio/whatsapp/status'));
    }

    public function test_it_rejects_inbound_requests_with_an_invalid_signature(): void
    {
        Http::fake();

        $this->call('POST', '/api/webhooks/twilio/whatsapp', ['Body' => 'MENU', 'WaId' => '573001112233'], server: [
            'HTTP_X_TWILIO_SIGNATURE' => 'invalid',
        ])->assertForbidden();

        $this->assertDatabaseCount('whatsapp_messages', 0);
        Http::assertNothingSent();
    }

    public function test_status_callback_updates_the_outbound_message(): void
    {
        $message = $this->outboundMessage(['provider_message_id' => 'SM999', 'status' => 'sent']);

        $this->postTwilio('/api/webhooks/twilio/whatsapp/status', [
            'MessageSid' => 'SM999',
            'MessageStatus' => 'undelivered',
            'To' => 'whatsapp:+573001112233',
            'ErrorCode' => '63016',
            'ErrorMessage' => 'Outside the 24h window',
        ])->assertOk();

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertSame('failed', $message->provider_status);
        $this->assertSame('63016', $message->payload_json['provider_status_event']['errors'][0]['code']);
    }

    public function test_interactive_buttons_are_sent_as_a_cached_quick_reply_content(): void
    {
        Http::fake([
            'https://content.twilio.com/*' => Http::response(['sid' => 'HXquick'], 201),
            'https://api.twilio.com/*' => Http::sequence()
                ->push(['sid' => 'SMbtn1'], 201)
                ->push(['sid' => 'SMbtn2'], 201),
        ]);

        $buttons = [['id' => 'MENU', 'title' => 'Menú'], ['id' => 'PAGOS', 'title' => 'Pagos']];
        $dispatch = app(DispatchOutboundWhatsappMessageAction::class);

        foreach (['Hola Ana', 'Hola Luis'] as $body) {
            $message = $this->outboundMessage([
                'message_type' => 'interactive',
                'body_text' => $body,
                'payload_json' => ['interactive_buttons' => $buttons],
            ]);

            $this->assertSame('sent', $dispatch->execute($message->customer, $message)->status);
        }

        // La plantilla se crea una sola vez para el mismo conjunto de botones.
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://content.twilio.com/v1/Content'
            && $request['types']['twilio/quick-reply']['actions'][1] === ['id' => 'PAGOS', 'title' => 'Pagos']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'Messages.json')
            && $request['ContentSid'] === 'HXquick'
            && json_decode($request['ContentVariables'], true) === ['1' => 'Hola Luis']);
    }

    public function test_interactive_falls_back_to_plain_text_when_content_creation_fails(): void
    {
        Http::fake([
            'https://content.twilio.com/*' => Http::response(['message' => 'nope'], 400),
            'https://api.twilio.com/*' => Http::response(['sid' => 'SMtxt'], 201),
        ]);

        $message = $this->outboundMessage([
            'message_type' => 'interactive',
            'body_text' => 'Elige una opción',
            'payload_json' => ['interactive_buttons' => [['id' => 'MENU', 'title' => 'Menú']]],
        ]);

        $this->assertSame('sent', app(DispatchOutboundWhatsappMessageAction::class)->execute($message->customer, $message)->status);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'Messages.json')
            && $request['Body'] === "Elige una opción\n\n• Menú"
            && ! isset($request['ContentSid']));
    }

    public function test_documents_are_sent_with_media_url(): void
    {
        Http::fake(['https://api.twilio.com/*' => Http::response(['sid' => 'SMdoc'], 201)]);

        $message = $this->outboundMessage([
            'message_type' => 'document',
            'body_text' => 'Tu boleto',
            'payload_json' => ['document' => ['link' => 'https://rifax.test/t.png', 'filename' => 't.png', 'caption' => 'Tu boleto']],
        ]);

        app(DispatchOutboundWhatsappMessageAction::class)->execute($message->customer, $message);

        Http::assertSent(fn (Request $request): bool => $request['MediaUrl'] === 'https://rifax.test/t.png'
            && $request['Body'] === 'Tu boleto');
    }

    public function test_twilio_errors_are_recorded_on_the_message(): void
    {
        Http::fake(['https://api.twilio.com/*' => Http::response(['code' => 63007, 'message' => 'Invalid From'], 400)]);

        $message = $this->outboundMessage();
        $result = app(DispatchOutboundWhatsappMessageAction::class)->execute($message->customer, $message);

        $this->assertSame('failed', $result->status);
        $this->assertSame('twilio', $result->payload_json['provider']);
        $this->assertSame(63007, $result->payload_json['meta_error']['code']);
        $this->assertSame('Invalid From', $result->payload_json['meta_error']['message']);
    }

    public function test_button_taps_are_mapped_to_interactive_replies(): void
    {
        Http::fake(['*' => Http::response(['sid' => 'SMx'], 201)]);

        $this->postTwilio('/api/webhooks/twilio/whatsapp', [
            'MessageSid' => 'SMbtnin',
            'WaId' => '573001112233',
            'Body' => 'Menú',
            'ButtonText' => 'Menú',
            'ButtonPayload' => 'MENU',
            'NumMedia' => '0',
        ])->assertOk();

        $inbound = WhatsappMessage::query()->where('provider_message_id', 'SMbtnin')->firstOrFail();
        $this->assertSame('interactive', $inbound->message_type);
    }

    public function test_media_is_downloaded_from_twilio_with_basic_auth(): void
    {
        Storage::fake('local');
        Http::fake(['https://api.twilio.com/*' => Http::response('IMG', 200, ['Content-Type' => 'image/jpeg'])]);

        $url = 'https://api.twilio.com/2010-04-01/Accounts/'.self::SID.'/Messages/MM1/Media/ME1';
        $result = app(DownloadWhatsappMediaToStorageAction::class)->execute($url, 'local', 'proofs/1.jpg');

        $this->assertSame('image/jpeg', $result['mime_type']);
        Storage::disk('local')->assertExists('proofs/1.jpg');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode(self::SID.':'.self::TOKEN)));
    }

    /**
     * @param  array<string, string>  $params
     */
    protected function postTwilio(string $uri, array $params): TestResponse
    {
        $signature = TwilioWebhookSignature::sign(self::TOKEN, url($uri), $params);

        return $this->call('POST', $uri, $params, server: ['HTTP_X_TWILIO_SIGNATURE' => $signature]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function outboundMessage(array $attributes = []): WhatsappMessage
    {
        $customer = Customer::query()->firstOrCreate(['wa_id' => '573001112233'], ['phone' => '+573001112233']);

        return WhatsappMessage::query()->create(array_merge([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body_text' => 'Hola',
            'payload_json' => [],
            'status' => 'generated',
            'provider_created_at' => now(),
        ], $attributes))->load('customer');
    }
}
