<?php

namespace Tests\Feature;

use App\Models\PaperOrder;
use App\Models\PriceAlert;
use App\Models\ScanResult;
use App\Models\SimulationRun;
use App\Models\TradePlan;
use App\Models\TraderSetting;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForexTraderWorkflowTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        ScanResult::truncate();
        TradePlan::truncate();
        SimulationRun::truncate();
        PaperOrder::truncate();
        PriceAlert::truncate();
        TraderSetting::truncate();
        User::truncate();

        $this->admin = User::create([
            'name' => 'Master Trader',
            'email' => 'admin@forex.local',
            'password' => Hash::make('admin12345'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($this->admin);
    }

    public function test_all_pages_render_successfully(): void
    {
        foreach (['/', '/scanner', '/news', '/plans', '/timemachine', '/broker', '/alerts', '/copilot', '/accounts', '/settings'] as $uri) {
            $this->get($uri)->assertStatus(200);
        }

        $this->getJson('/live-prices?pairs=EUR/USD,GBP/USD')
            ->assertStatus(200)
            ->assertJsonStructure(['quotes' => ['EUR/USD' => ['price', 'decimals']], 'server_time']);
    }

    public function test_login_and_account_management(): void
    {
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertStatus(200);

        $this->post('/login', [
            'email' => 'admin@forex.local',
            'password' => 'admin12345',
        ])->assertRedirect('/');

        $this->post('/accounts', [
            'name' => 'Trader Dua',
            'email' => 'trader2@forex.local',
            'role' => 'trader',
            'password' => 'secret123',
        ])->assertRedirect('/accounts');

        $created = User::where('email', 'trader2@forex.local')->first();
        $this->assertNotNull($created);
        $this->assertTrue((bool) $created->is_active);

        $this->post("/accounts/{$created->_id}/toggle")->assertRedirect('/accounts');
        $created->refresh();
        $this->assertFalse((bool) $created->is_active);

        $this->delete("/accounts/{$created->_id}")->assertRedirect('/accounts');
        $this->assertNull(User::where('email', 'trader2@forex.local')->first());
    }

    public function test_full_forex_pipeline_end_to_end(): void
    {
        // 1. Scanner
        $this->post('/scanner/run', ['mode' => 'all'])->assertRedirect('/scanner');
        $scan = ScanResult::first();
        $this->assertNotNull($scan);
        $this->assertArrayHasKey('trend_bias', $scan->hits[0]);

        // 1b. Auto-ATR Stop Loss Plan
        $this->post('/plans', [
            'pair' => 'GBP/USD',
            'side' => 'sell',
            'setup' => 'Asian Range Breakdown',
            'stop_rule' => 'atr',
            'stop_value' => 1.5,
            'target_rule' => '2R',
        ])->assertRedirect();
        $atrPlan = TradePlan::where('pair', 'GBP/USD')->first();
        $this->assertNotNull($atrPlan);
        $this->assertGreaterThanOrEqual(5.0, (float) $atrPlan->risk_pips);

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

        $plan = TradePlan::where('pair', 'EUR/USD')->first();
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
        config(['services.gemini.api_key' => '']);
        $gemini = app(GeminiService::class);
        $res = $gemini->interact('Hello');
        $this->assertFalse($res['configured']);
        $this->assertFalse($res['ok']);
        $this->assertNotEmpty($res['error']);
    }
}

