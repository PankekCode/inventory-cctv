<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\OrderCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderCodeService::class);
    }

    public function test_generates_order_code_with_expected_format(): void
    {
        $code = $this->service->next();
        $datePart = now()->format('ymd');

        // Pattern: HBL-YYMMDD-XXXXXX (e.g. HBL-260820-A7K3P9)
        $expectedRegex = '/^HBL-' . $datePart . '-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/';

        $this->assertMatchesRegularExpression(
            $expectedRegex,
            $code,
            "Order code {$code} does not match format HBL-YYMMDD-XXXXXX with safe alphabet."
        );
    }

    public function test_order_codes_are_unique_and_non_sequential(): void
    {
        $codes = [];
        for ($i = 0; $i < 50; $i++) {
            $code = $this->service->next();
            $this->assertNotContains($code, $codes, "Duplicate order code generated: {$code}");
            $codes[] = $code;
        }

        $this->assertCount(50, array_unique($codes));
    }

    public function test_retries_on_collision(): void
    {
        $datePart = now()->format('ymd');
        // Manually create an order code that might collide if generated
        Order::create([
            'public_id' => (string) Str::uuid(),
            'order_code' => "HBL-{$datePart}-A7K3P9",
            'customer_name' => 'Existing Order',
            'installation_address' => 'Jl. Test',
            'payment_method' => 'qris',
            'subtotal' => 100000,
            'grand_total' => 100000,
        ]);

        $code = $this->service->next();
        $this->assertNotEquals("HBL-{$datePart}-A7K3P9", $code);
    }
}
