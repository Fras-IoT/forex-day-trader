<?php

namespace App\Http\Controllers;

use App\Models\SimulationRun;
use App\Models\TradePlan;
use App\Services\ForexTimeMachineService;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class TimeMachineController extends Controller
{
    public function index(GeminiService $gemini)
    {
        $plans = TradePlan::orderBy('created_at', 'desc')->take(10)->get();
        $selectedPlanId = request('plan_id');
        $activePlan = $selectedPlanId ? TradePlan::find($selectedPlanId) : $plans->first();
        $sim = $activePlan
            ? SimulationRun::where('trade_plan_id', (string) $activePlan->_id)->orderBy('created_at', 'desc')->first()
            : SimulationRun::orderBy('created_at', 'desc')->first();

        return view('timemachine', [
            'plans' => $plans,
            'activePlan' => $activePlan,
            'sim' => $sim,
            'geminiConfigured' => $gemini->isConfigured(),
        ]);
    }

    public function run(Request $request, ForexTimeMachineService $tm)
    {
        $data = $request->validate([
            'trade_plan_id' => 'required|string',
            'replays' => 'nullable|integer|min:1000|max:20000',
            'seed' => 'nullable|integer|min:1|max:999999',
        ]);

        $plan = TradePlan::findOrFail($data['trade_plan_id']);
        $replays = (int) ($data['replays'] ?? 10000);
        $seed = (int) ($data['seed'] ?? 19);

        $sim = $tm->runSimulation($plan, $replays, $seed);
        $plan->status = 'simulated';
        $plan->save();

        return redirect()->route('timemachine.index', ['plan_id' => (string) $plan->_id])
            ->with('status', "Time Machine selesai: {$replays} simulasi dijalankan untuk {$plan->pair}.");
    }

    public function analyze(string $id, GeminiService $gemini)
    {
        $sim = SimulationRun::findOrFail($id);
        $hist = $sim->history_stats ?? ['fires' => 0, 'wins' => 0, 'losses' => 0, 'neither' => 0];

        $prompt = "Interpret this Forex Time Machine Monte Carlo simulation ({$sim->replays} replays, seed {$sim->seed}):\n"
            . "Pair: {$sim->pair} | Side: " . strtoupper($sim->side) . "\n"
            . "Entry: {$sim->entry_price} | SL: {$sim->stop_loss} | TP: {$sim->take_profit}\n"
            . "Target First: {$sim->target_first_pct}% | Stop First: {$sim->stop_first_pct}% | Still Open (Neither): {$sim->neither_pct}%\n"
            . "Expected R: {$sim->expected_r}R | Break-even Win%: {$sim->breakeven_win_pct}% | System Verdict: {$sim->verdict}\n"
            . "Historical Backtest: Fired {$hist['fires']}x -> {$hist['wins']} Wins, {$hist['losses']} Losses, {$hist['neither']} Neither\n\n"
            . "Berikan interpretasi jujur dalam Bahasa Indonesia (apakah edge-nya layak diambil, risiko drawdown, dan rekomendasi lanjut ke Risk Check atau batalkan).";

        $res = $gemini->interact(
            $prompt,
            'You are a quantitative Forex risk analyst. Interpret Monte Carlo odds honestly and remind that past bars do not guarantee future results.'
        );

        if (!$res['ok']) {
            return redirect()->route('timemachine.index', ['plan_id' => $sim->trade_plan_id])->with('error', $res['error']);
        }

        $sim->ai_verdict = $res['text'];
        $sim->save();

        return redirect()->route('timemachine.index', ['plan_id' => $sim->trade_plan_id])
            ->with('status', 'Gemini AI Verdict untuk Time Machine telah dibuat.');
    }
}

