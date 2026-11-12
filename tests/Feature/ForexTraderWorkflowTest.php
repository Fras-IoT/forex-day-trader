<?php

namespace Tests\Feature;

use App\Models\PaperOrder;
use App\Models\PriceAlert;
use App\Models\ScanResult;
use App\Models\SimulationRun;
use App\Models\TradePlan;
use App\Models\TraderSetting;
use App\Services\GeminiService;
use Tests\TestCase;

class ForexTraderWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ScanResult::truncate();
        TradePlan::truncate();
        SimulationRun::truncate();
        PaperOrder::truncate();
        PriceAlert::truncate();
        TraderSetting::truncate();
    }

    public function test_all_pages_render_successfully(): void
    {
        foreach (['/', '/scanner', '/news', '/plans', '/timemachine', '/broker', '/alerts', '/copilot', '/settings'] as $uri) {
            $this->get($uri)->assertStatus(200);
        }
    }

    public function test_full_forex_pipeline_end_to_end(): void
    {
        // 1. Scanner
        $this->post('/scanner/run', ['mode' => 'all'])->assertRedirect('/scanner');
        $this->assertGreaterThan(0, ScanResult::count());

        // 2. Create Trade Plan (Long EUR/USD, 20 pips SL, 2R TP)
        $this->post('/plans', [
            'pair' => 'EUR/USD',
            'side' => 'buy',
            'setup' => 'Session Range Breakout',
            'entry_price' => 1.08500,
            'stop_rule' => 'pips',
            'stop_value' => 20,
            'target_rule' => '2R',
        ])->assertRedirect();

        $plan = TradePlan::first();
        $this->assertNotNull($plan);
        $this->assertEquals('EUR/USD', $plan->pair);
        $this->assertEquals(20.0, (float) $plan->risk_pips);
        $this->assertEquals(2.0, (float) $plan->reward_risk);

        // 3. Time Machine simulation (2,000 replays for fast test)
        $this->post('/timemachine/run', [
            'trade_plan_id' => (string) $plan->_id,
            'replays' => 2000,
            'seed' => 19,
        ])->assertRedirect();

        $sim = SimulationRun::first();
        $this->assertNotNull($sim);
        $this->assertEquals(2000, $sim->replays);
        $this->assertNotEmpty($sim->fan_series);

        // 4. Risk Check & Position Sizing
        $this->post('/broker/size', [
            'trade_plan_id' => (string) $plan->_id,
            'max_loss_usd' => 100,
            'order_type' => 'market',
        ])->assertRedirect();

        $order = PaperOrder::first();
        $this->assertNotNull($order);
        $this->assertEquals('PASS', $order->risk_status);
        $this->assertEquals('pending_approval', $order->status);
        $this->assertEquals(0.5, (float) $order->lots); // $100 / (20 pips * $10/pip) = 0.50 lots

        // 5. Execute Paper Order (requires explicit YES)
        $this->post("/broker/{$order->_id}/execute", ['confirm_yes' => 'YES'])->assertRedirect('/broker');
        $order->refresh();
        $this->assertEquals('open', $order->status);
        $this->assertEquals(2, PriceAlert::count()); // Auto SL + TP alerts

        // 6. Close Paper Order
        $this->post("/broker/{$order->_id}/close")->assertRedirect('/broker');
        $order->refresh();
        $this->assertEquals('closed', $order->status);
    }

    public function test_gemini_service_handles_missing_token_gracefully(): void
    {
        $gemini = app(GeminiService::class);
        $res = $gemini->interact('Hello');
        $this->assertFalse($res['configured']);
        $this->assertFalse($res['ok']);
        $this->assertNotEmpty($res['error']);
    }
}

