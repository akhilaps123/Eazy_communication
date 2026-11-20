<?php

namespace Tests\Feature\Customer;

use App\Mail\EsimReadyMail;
use App\Models\Esim;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\ProviderOrder;
use App\Models\User;
use App\Services\Esim\EsimReadyNotifier;
use App\Services\EsimGo\FulfilmentService;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Feature\Database\DatabaseTestCase;
use Tests\Feature\EsimGo\EsimGoFixtures;

/**
 * Phase 9E: "Your eSIM is ready" email. eSIM Go is faked (purchases counted),
 * mail is faked (or a failing fake transport), storage is fake.
 */
class EsimReadyEmailTest extends DatabaseTestCase
{
    use CustomerFixtures;
    use EsimGoFixtures;

    private const ICCID = '8944000000000009301';

    private const MATCHING_ID = 'MAIL-SECRET-MATCH-9E03';

    private const SMDP = 'rsp.mail-secret-9e.example';

    private const REF = '9e3c8a52-1f3b-4e7a-9c55-0a1b2c3d4e5f';

    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        $this->configureEsimGo(['esimgo.currency' => 'USD']);
        config(['esimgo.fulfilment.enabled' => true, 'esimgo.fulfilment.allow_test_payments' => true]);   // test process only

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if ($request->url() === self::BASE.'/orders') {
                $type = $request->data()['type'] ?? null;

                return $this->answers[$type] ?? ($type === 'validate'
                    ? Http::response(['order' => [], 'total' => 2.0, 'valid' => true, 'currency' => 'USD', 'assigned' => false])
                    : Http::response($this->purchaseBody()));
            }
            if (str_starts_with($request->url(), self::BASE.'/esims/assignments')) {
                return $this->answers['assignments'] ?? Http::response([]);
            }

            return null;
        });
    }

    private function purchaseBody(array $esims = [['iccid' => self::ICCID, 'matchingId' => self::MATCHING_ID, 'smdpAddress' => self::SMDP]], int $quantity = 1): array
    {
        return ['order' => [['type' => 'bundle', 'item' => 'SECRET_BUNDLE', 'quantity' => $quantity, 'subTotal' => 2.0, 'pricePerUnit' => 2.0,
            'iccids' => array_column($esims, 'iccid'), 'esims' => $esims]],
            'total' => 2.0, 'currency' => 'USD', 'status' => 'completed', 'statusMessage' => 'Order completed', 'orderReference' => self::REF, 'assigned' => true];
    }

    private function customer(): User
    {
        $email = User::query()->where('email', 'mira.mailtest@example.test')->exists() ? fake()->unique()->safeEmail() : 'mira.mailtest@example.test';

        return User::factory()->create(['name' => 'Mira Mailtest', 'email' => $email]);
    }

    private function paidOrder(): Order
    {
        return $this->paidVerified($this->pendingOrder($this->customer()));
    }

    private function purchaseRequests(): int
    {
        return Http::recorded(fn (Request $r) => $r->url() === self::BASE.'/orders' && ($r->data()['type'] ?? null) === 'transaction')->count();
    }

    private function notification(Order $order): ?OrderNotification
    {
        return OrderNotification::query()->where('order_id', $order->id)->first();
    }

    private function useFailingMailer(): void
    {
        Mail::extend('failing9e', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection to smtp.example refused (user secret-smtp-pass)');
            }

            public function __toString(): string
            {
                return 'failing9e://';
            }
        });
        config(['mail.mailers.failing9e' => ['transport' => 'failing9e'], 'mail.default' => 'failing9e']);
        Mail::purge('failing9e');
    }

    // ------------------------------------------------------------------ 19: only after successful fulfilment

    public function test_the_email_is_sent_once_after_successful_fulfilment(): void
    {
        Mail::fake();
        $order = $this->paidOrder();

        $result = app(FulfilmentService::class)->fulfil($order);

        $this->assertSame(FulfilmentService::RESULT_FULFILLED, $result['result']);
        Mail::assertSent(EsimReadyMail::class, fn (EsimReadyMail $m) => $m->hasTo('mira.mailtest@example.test') && $m->order->is($order));
        Mail::assertSentCount(1);
        $n = $this->notification($order);
        $this->assertSame(OrderNotification::STATUS_SENT, $n->status);
        $this->assertSame(1, $n->attempts);
        $this->assertNotNull($n->sent_at);
        $this->assertSame(['esim_ready_email_queued', 'esim_ready_email_sent'], IntegrationLog::query()->where('service', 'mail')->orderBy('id')->pluck('operation')->all());
    }

    public function test_multiple_esims_in_one_order_produce_one_email_listing_each_esim(): void
    {
        Mail::fake();
        $order = $this->pendingOrder($this->customer());
        // Two eSIMs: quantity 2 on the single item (test data only).
        \Illuminate\Support\Facades\DB::table('order_items')->where('order_id', $order->id)->update(['quantity' => 2]);
        $order = $this->paidVerified($order);
        $this->answers['transaction'] = Http::response($this->purchaseBody([
            ['iccid' => self::ICCID, 'matchingId' => self::MATCHING_ID, 'smdpAddress' => self::SMDP],
            ['iccid' => '8944000000000009302', 'matchingId' => 'MAIL-SECRET-MATCH-9E04', 'smdpAddress' => self::SMDP],
        ], 2));

        app(FulfilmentService::class)->fulfil($order);

        Mail::assertSentCount(1);
        $html = null;
        Mail::assertSent(EsimReadyMail::class, function (EsimReadyMail $m) use (&$html) {
            $html = $m->render();

            return true;
        });
        $ids = Esim::query()->orderBy('id')->pluck('id');
        $this->assertCount(2, $ids);
        foreach ($ids as $id) {
            $this->assertStringContainsString(route('my-esims.show', $id), $html);
        }
    }

    public function test_no_email_while_payment_is_pending_or_unverified(): void
    {
        Mail::fake();
        $notifier = app(EsimReadyNotifier::class);

        $pending = $this->pendingOrder($this->customer());
        $this->assertSame(EsimReadyNotifier::RESULT_NOT_READY, $notifier->queueIfReady($pending, 'test')['result']);
        $this->assertSame('The order is not paid.', $notifier->blockingReason($pending));

        $unverified = $this->deliveredEsim(User::factory()->create(), '8944000000000009399', 'UNVERIFIED-MATCH');
        \Illuminate\Support\Facades\DB::table('payments')->where('order_id', $unverified->orderItem->order_id)->update(['verified_at' => null]);
        $this->assertStringContainsString('server-verified', $notifier->blockingReason($unverified->orderItem->order));
        $notifier->queueIfReady($unverified->orderItem->order, 'test');

        Mail::assertNothingSent();
        $this->assertSame(0, OrderNotification::query()->count());
    }

    // ------------------------------------------------------------------ 21: failed fulfilment

    public function test_no_email_when_fulfilment_fails_or_is_uncertain(): void
    {
        Mail::fake();
        $this->answers['transaction'] = Http::response(['message' => 'Insufficient balance'], 400);
        $failed = $this->paidOrder();
        $this->assertSame(FulfilmentService::RESULT_FAILED, app(FulfilmentService::class)->fulfil($failed)['result']);

        $this->answers['transaction'] = Http::response(['message' => 'boom'], 503);
        $uncertain = $this->paidOrder();
        $this->assertSame(FulfilmentService::RESULT_NEEDS_ATTENTION, app(FulfilmentService::class)->fulfil($uncertain)['result']);

        Mail::assertNothingSent();
        $this->assertSame(0, OrderNotification::query()->count());
    }

    public function test_no_email_until_every_qr_code_exists(): void
    {
        Mail::fake();
        // eSIM Go returns an SM-DP+ address that cannot become a QR code: the order is fulfilled, but no email.
        $this->answers['transaction'] = Http::response($this->purchaseBody([['iccid' => self::ICCID, 'matchingId' => self::MATCHING_ID, 'smdpAddress' => 'not a host']]));
        $order = $this->paidOrder();

        $this->assertSame(FulfilmentService::RESULT_FULFILLED, app(FulfilmentService::class)->fulfil($order)['result']);

        Mail::assertNothingSent();
        $this->assertNull($this->notification($order));
        $this->assertStringContainsString('activation details are incomplete', app(EsimReadyNotifier::class)->blockingReason($order));
    }

    // ------------------------------------------------------------------ 22: no secrets

    public function test_the_email_contains_links_but_no_activation_secrets(): void
    {
        $esim = $this->deliveredEsim($this->customer(), self::ICCID, self::MATCHING_ID, self::SMDP);
        $order = $esim->orderItem->order;
        $mail = new EsimReadyMail($order);

        $html = $mail->render();
        $mail->assertHasSubject('Your eSIM is ready – Eazy Communication');
        $this->assertStringContainsString('Mira Mailtest', $html);
        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString('Test eSIM · 5 GB - 7 Days', $html);
        $this->assertStringContainsString(route('my-esims.show', $esim), $html);
        $this->assertStringContainsString(route('my-esims'), $html);
        $this->assertStringContainsString('sign in', $html);
        foreach ([self::MATCHING_ID, self::SMDP, 'LPA:1', 'rsp.', '/qr', '.png', 'esim-qr', 'base64', self::ICCID, 'SECRET_BUNDLE', 'X-API-Key', self::API_KEY] as $secret) {
            $this->assertStringNotContainsString($secret, $html, $secret);
        }
    }

    // ------------------------------------------------------------------ 23: duplicates

    public function test_repeated_fulfilment_runs_reconciliation_and_triggers_never_send_twice(): void
    {
        Mail::fake();
        $order = $this->paidOrder();
        $service = app(FulfilmentService::class);
        $notifier = app(EsimReadyNotifier::class);

        $service->fulfil($order);
        $service->fulfil($order);                          // repeated job
        $service->reconcile($order);                       // reconciliation of a fulfilled order
        $notifier->queueIfReady($order, 'webhook');        // e.g. a repeated webhook
        $notifier->queueIfReady($order, 'qr_generated');
        app(\App\Jobs\SendEsimReadyEmail::class, ['notificationId' => $this->notification($order)->id])->handle($notifier);   // replayed job

        Mail::assertSentCount(1);
        $this->assertSame(1, OrderNotification::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, $this->notification($order)->attempts);
        $this->assertSame(1, $this->purchaseRequests());
    }

    public function test_an_unconfirmed_send_is_never_repeated_automatically(): void
    {
        Mail::fake();
        $esim = $this->deliveredEsim($this->customer(), self::ICCID, self::MATCHING_ID, self::SMDP);
        $order = $esim->orderItem->order;
        OrderNotification::query()->create(['order_id' => $order->id, 'type' => 'esim_ready', 'status' => OrderNotification::STATUS_SENDING, 'attempts' => 1]);

        app(EsimReadyNotifier::class)->queueIfReady($order, 'test');
        app(EsimReadyNotifier::class)->deliver($this->notification($order)->id);

        Mail::assertNothingSent();
        $this->assertSame(OrderNotification::STATUS_SENDING, $this->notification($order)->status);
    }

    // ------------------------------------------------------------------ 24: failure does not reverse fulfilment

    public function test_an_email_failure_never_reverses_fulfilment(): void
    {
        $this->useFailingMailer();
        $order = $this->paidOrder();

        $result = app(FulfilmentService::class)->fulfil($order);

        $this->assertSame(FulfilmentService::RESULT_FULFILLED, $result['result']);
        $order->refresh();
        $this->assertSame(Order::FULFILLMENT_FULFILLED, $order->fulfillment_status);
        $this->assertSame(ProviderOrder::STATUS_COMPLETED, $order->providerOrder->status);
        $this->assertSame(1, Esim::query()->count());
        $n = $this->notification($order);
        $this->assertSame(OrderNotification::STATUS_FAILED, $n->status);
        $this->assertSame('The mail server did not accept the message (TransportException).', $n->last_error);
        // The transport's message (which could contain server details) is never stored.
        $this->assertStringNotContainsString('secret-smtp-pass', json_encode(IntegrationLog::query()->get()->map->getAttributes()));
        $this->assertStringNotContainsString('secret-smtp-pass', (string) $n->last_error);
    }

    // ------------------------------------------------------------------ 25: admin retry never buys

    public function test_admin_retry_after_a_failure_sends_the_email_without_buying_anything(): void
    {
        $this->useFailingMailer();
        $order = $this->paidOrder();
        app(FulfilmentService::class)->fulfil($order);
        $this->assertSame(OrderNotification::STATUS_FAILED, $this->notification($order)->status);
        $purchases = $this->purchaseRequests();
        $esims = Esim::query()->count();
        $provider = ProviderOrder::query()->sole()->only(['status', 'attempts', 'provider_reference']);

        Mail::fake();   // mail server back
        $this->actingAs($admin = $this->admin());
        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Customer “eSIM ready” email')->assertSee('The mail server did not accept the message')->assertSee('Retry email');

        $this->post(route('admin.orders.notifications.esim-ready', $order), ['reason' => 'SMTP fixed'])
            ->assertRedirect(route('admin.orders.show', $order))->assertSessionHas('status', 'The eSIM-ready email has been queued again.');

        Mail::assertSentCount(1);
        $this->assertSame(OrderNotification::STATUS_SENT, $this->notification($order)->status);
        $this->assertSame($purchases, $this->purchaseRequests(), 'Retrying the email must never buy an eSIM.');
        $this->assertSame($esims, Esim::query()->count());
        $this->assertSame($provider, ProviderOrder::query()->sole()->only(['status', 'attempts', 'provider_reference']));
        $this->assertSame(Order::FULFILLMENT_FULFILLED, $order->fresh()->fulfillment_status);
        $audit = IntegrationLog::query()->where('service', 'admin')->sole();
        $this->assertSame('email_retry_queued', $audit->operation);
        $this->assertSame('SMTP fixed', $audit->request_body['reason']);
        $this->assertSame($admin->id, $audit->request_body['admin_user_id']);

        // A second retry after it was sent is refused (no duplicate).
        $this->post(route('admin.orders.notifications.esim-ready', $order))->assertSessionHasErrors('action');
        Mail::assertSentCount(1);
        $this->assertSame('email_retry_rejected', IntegrationLog::query()->where('service', 'admin')->latest('id')->first()->operation);
    }

    public function test_admin_retry_is_refused_for_an_order_that_is_not_ready(): void
    {
        Mail::fake();
        $order = $this->paidOrder();   // paid, not fulfilled
        $this->actingAs($this->admin());

        $this->post(route('admin.orders.notifications.esim-ready', $order))->assertSessionHasErrors('action');

        $this->assertStringContainsString('not fulfilled', session('errors')->first('action'));
        Mail::assertNothingSent();
        $this->assertSame(0, $this->purchaseRequests());
        $this->assertSame(0, OrderNotification::query()->count());
    }

    public function test_activation_data_never_appears_in_logs_during_the_email_flow(): void
    {
        $messages = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$messages) {
            $messages[] = $e->message.' '.json_encode($e->context);
        });
        Mail::fake();
        $order = $this->paidOrder();

        app(FulfilmentService::class)->fulfil($order);

        $all = implode("\n", $messages).json_encode(IntegrationLog::query()->get()->map->getAttributes())
            .json_encode(OrderNotification::query()->get()->map->getAttributes());
        foreach ([self::MATCHING_ID, self::SMDP, 'LPA:1'] as $secret) {
            $this->assertStringNotContainsString($secret, $all, $secret);
        }
        Mail::assertSentCount(1);
    }

    public function test_without_the_migration_nothing_breaks_and_nothing_is_sent(): void
    {
        Mail::fake();
        \Illuminate\Support\Facades\Schema::drop('order_notifications');
        $order = $this->paidOrder();

        $this->assertSame(FulfilmentService::RESULT_FULFILLED, app(FulfilmentService::class)->fulfil($order)['result']);

        Mail::assertNothingSent();
        $this->assertStringContainsString('migration', app(EsimReadyNotifier::class)->blockingReason($order));
        $this->actingAs($this->admin());
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Email tracking is not set up yet');
        $this->assertTrue(Storage::disk('esim_qr')->exists(Esim::query()->sole()->qr_code_path));
    }
}
