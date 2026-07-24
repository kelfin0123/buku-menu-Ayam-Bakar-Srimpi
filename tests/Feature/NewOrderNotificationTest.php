<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\OrderController;
use App\Jobs\SendNewOrderNotification;
use App\Models\DeviceToken;
use App\Models\Order;
use App\Services\FirebaseMessagingService;
use App\Services\FirestoreOrderService;
use App\Services\NewOrderNotificationDispatcher;
use App\Services\NewOrderNotificationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class NewOrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_register_refresh_and_disable_current_token(): void
    {
        $this->postJson('/api/device-tokens', [
            'token' => 'customer-cannot-register',
        ])->assertUnauthorized();

        $storeRequest = Request::create('/api/device-tokens', 'POST', [
            'token' => 'fcm-device-token-1',
            'platform' => 'android',
            'device_name' => 'Android Test',
            'sound_enabled' => false,
            'vibration_enabled' => true,
        ]);
        $storeRequest->attributes->set('firebase_uid', 'employee-1');
        $storeRequest->attributes->set('firebase_role', 'employee');
        app(DeviceTokenController::class)->store($storeRequest);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => 'employee-1',
            'role' => 'employee',
            'token' => 'fcm-device-token-1',
            'is_active' => true,
            'sound_enabled' => false,
        ]);

        $deleteRequest = Request::create('/api/device-tokens/current', 'DELETE', [
            'token' => 'fcm-device-token-1',
        ]);
        $deleteRequest->attributes->set('firebase_uid', 'employee-1');
        app(DeviceTokenController::class)->destroyCurrent($deleteRequest);
        $this->assertDatabaseHas('device_tokens', [
            'token' => 'fcm-device-token-1',
            'is_active' => false,
        ]);
    }

    public function test_fcm_payload_contains_notification_and_string_data(): void
    {
        $order = $this->order();
        $device = DeviceToken::create([
            'token' => 'token',
            'role' => 'employee',
            'sound_enabled' => true,
            'vibration_enabled' => true,
        ]);

        $payload = app(FirebaseMessagingService::class)->payload($order, $device);

        $this->assertSame('Pesanan Baru Masuk 🔔', $payload['notification']['title']);
        $this->assertSame('new_order', $payload['data']['type']);
        $this->assertSame((string) $order->id, $payload['data']['order_id']);
        $this->assertSame('cash', $payload['data']['payment_method']);
        $this->assertSame('new_order', $payload['data']['order_status']);
        $this->assertSame('new_order_channel_v1', $payload['android']['notification']['channel_id']);
        foreach ($payload['data'] as $value) {
            $this->assertIsString($value);
        }
    }

    public function test_job_is_idempotent_and_cancelled_order_is_not_sent(): void
    {
        $order = $this->order();
        $messaging = Mockery::mock(FirebaseMessagingService::class);
        $messaging->shouldReceive('sendNewOrder')->once()->andReturn(2);

        $job = new SendNewOrderNotification($order->id);
        $policy = app(NewOrderNotificationPolicy::class);
        $job->handle($messaging, $policy);
        $job->handle($messaging, $policy);

        $order->refresh();
        $this->assertNotNull($order->new_order_notification_sent_at);
        $this->assertSame($job->notificationId, $order->new_order_notification_id);
        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff);

        $cancelled = $this->order(Order::STATUS_CANCELLED, 'WEB-CANCELLED');
        (new SendNewOrderNotification($cancelled->id))->handle($messaging, $policy);
        $this->assertNull($cancelled->fresh()->new_order_notification_sent_at);
    }

    public function test_notification_policy_matches_restaurant_payment_flow(): void
    {
        $policy = app(NewOrderNotificationPolicy::class);

        $cashDraft = $this->order(
            Order::STATUS_WAITING_PAYMENT,
            'WEB-CASH-DRAFT',
            Order::PAYMENT_METHOD_CASH,
        );
        $cashReady = $this->order(
            Order::STATUS_NEW_ORDER,
            'WEB-CASH-READY',
            Order::PAYMENT_METHOD_CASH,
        );
        $qrisPending = $this->order(
            Order::STATUS_WAITING_PAYMENT,
            'WEB-QRIS-PENDING',
            Order::PAYMENT_METHOD_QRIS,
        );
        $qrisPaid = $this->order(
            Order::STATUS_NEW_ORDER,
            'WEB-QRIS-PAID',
            Order::PAYMENT_METHOD_QRIS,
            Order::PAYMENT_STATUS_PAID,
        );
        $bankTransfer = $this->order(
            Order::STATUS_NEW_ORDER,
            'WEB-BANK',
            Order::PAYMENT_METHOD_BANK_TRANSFER,
        );

        $this->assertFalse($policy->isEligible($cashDraft));
        $this->assertTrue($policy->isEligible($cashReady));
        $this->assertFalse($policy->isEligible($qrisPending));
        $this->assertTrue($policy->isEligible($qrisPaid));
        $this->assertFalse($policy->isEligible($bankTransfer));
    }

    public function test_dispatcher_only_queues_eligible_orders(): void
    {
        Queue::fake();
        $dispatcher = app(NewOrderNotificationDispatcher::class);
        $cashDraft = $this->order(
            Order::STATUS_WAITING_PAYMENT,
            'WEB-DISPATCH-DRAFT',
            Order::PAYMENT_METHOD_CASH,
        );
        $cashReady = $this->order(
            Order::STATUS_NEW_ORDER,
            'WEB-DISPATCH-CASH',
            Order::PAYMENT_METHOD_CASH,
        );
        $qrisPending = $this->order(
            Order::STATUS_WAITING_PAYMENT,
            'WEB-DISPATCH-QRIS-PENDING',
            Order::PAYMENT_METHOD_QRIS,
        );
        $qrisPaid = $this->order(
            Order::STATUS_NEW_ORDER,
            'WEB-DISPATCH-QRIS-PAID',
            Order::PAYMENT_METHOD_QRIS,
            Order::PAYMENT_STATUS_PAID,
        );

        $this->assertFalse($dispatcher->dispatchIfEligible($cashDraft));
        $this->assertTrue($dispatcher->dispatchIfEligible($cashReady));
        $this->assertFalse($dispatcher->dispatchIfEligible($qrisPending));
        $this->assertTrue($dispatcher->dispatchIfEligible($qrisPaid));

        Queue::assertPushed(
            SendNewOrderNotification::class,
            fn (SendNewOrderNotification $job) => $job->orderId === $cashReady->id,
        );
        Queue::assertPushed(
            SendNewOrderNotification::class,
            fn (SendNewOrderNotification $job) => $job->orderId === $qrisPaid->id,
        );
        Queue::assertPushed(SendNewOrderNotification::class, 2);
    }

    public function test_mark_seen_does_not_change_order_status(): void
    {
        $order = $this->order(Order::STATUS_WAITING_PAYMENT);
        $firestore = Mockery::mock(FirestoreOrderService::class);
        $firestore->shouldReceive('sync')->once()->andReturnTrue();
        $this->app->instance(FirestoreOrderService::class, $firestore);

        $request = Request::create("/api/orders/{$order->id}/seen", 'PATCH');
        $request->attributes->set('firebase_uid', 'employee-seen');
        $response = app(OrderController::class)->markSeen($request, $order);
        $this->assertTrue($response->getData(true)['data']['is_seen']);

        $order->refresh();
        $this->assertTrue($order->is_seen);
        $this->assertSame('employee-seen', $order->seen_by);
        $this->assertSame(Order::STATUS_WAITING_PAYMENT, $order->status);
    }

    private function order(
        string $status = Order::STATUS_NEW_ORDER,
        string $code = 'WEB-00123',
        string $paymentMethod = Order::PAYMENT_METHOD_CASH,
        string $paymentStatus = Order::PAYMENT_STATUS_PENDING,
    ): Order {
        return Order::create([
            'order_code' => $code,
            'customer_name' => 'Salsa',
            'customer_phone' => '628123456789',
            'is_delivery' => true,
            'order_type' => 'delivery',
            'subtotal' => 60000,
            'shipping_cost' => 0,
            'total' => 60000,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'status' => $status,
        ]);
    }
}
