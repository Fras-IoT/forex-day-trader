//+------------------------------------------------------------------+
//|                                ForexDayTraderTelegramEA.mq5      |
//|        Human-in-the-Loop Telegram Approval EA for MT5 Finex      |
//+------------------------------------------------------------------+
#property copyright "Forex Day Trader AI"
#property version   "1.10"
#property strict

#include <Trade\Trade.mqh>

input string InpServerUrl        = "http://72.62.126.151:8085"; // URL Server Laravel Anda
input string InpBridgeToken      = "";                          // MT5 Bridge Token (dari menu Settings)
input string InpSymbolSuffix     = "";                          // Akhiran symbol broker (misal "" atau "m" atau ".f")
input int    InpPollSeconds      = 5;                           // Interval cek order disetujui (detik)
input ulong  InpMagicNumber      = 20261112;                    // Magic Number EA
input int    InpDeviationPoints  = 30;                          // Max Slippage (points)
input bool   InpAutoExecute      = true;                        // Eksekusi saat disetujui di Telegram / Web

CTrade trade;

//+------------------------------------------------------------------+
int OnInit()
{
   if(StringLen(InpBridgeToken) == 0)
   {
      Print("ERROR: InpBridgeToken belum diisi! Ambil token dari halaman Settings web.");
      return(INIT_PARAMETERS_INCORRECT);
   }

   trade.SetExpertMagicNumber(InpMagicNumber);
   trade.SetDeviationInPoints(InpDeviationPoints);
   EventSetTimer(MathMax(2, InpPollSeconds));

   Print("ForexDayTraderTelegramEA aktif. Streaming harga & polling ke: ", InpServerUrl);
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
void OnDeinit(const int reason)
{
   EventKillTimer();
}

//+------------------------------------------------------------------+
string BuildQuotesPayload()
{
   string pairs[] = {"EURUSD", "GBPUSD", "USDJPY", "AUDUSD", "USDCHF", "USDCAD", "NZDUSD", "EURGBP", "EURJPY", "GBPJPY", "XAUUSD"};
   string q = "{";
   bool first = true;
   for(int i = 0; i < ArraySize(pairs); i++)
   {
      string p = pairs[i];
      string sym = p + InpSymbolSuffix;
      if(!SymbolInfoInteger(sym, SYMBOL_SELECT))
      {
         if(p == "XAUUSD" && SymbolInfoInteger("GOLD" + InpSymbolSuffix, SYMBOL_SELECT))
            sym = "GOLD" + InpSymbolSuffix;
      }

      double bid = SymbolInfoDouble(sym, SYMBOL_BID);
      double ask = SymbolInfoDouble(sym, SYMBOL_ASK);
      if(bid > 0.0)
      {
         int digits = (int)SymbolInfoInteger(sym, SYMBOL_DIGITS);
         if(!first) q += ",";
         q += "\"" + p + "\":{\"bid\":" + DoubleToString(bid, digits) + ",\"ask\":" + DoubleToString(ask, digits) + ",\"price\":" + DoubleToString(bid, digits) + "}";
         first = false;
      }
   }
   q += "}";

   string algo = TerminalInfoInteger(TERMINAL_TRADE_ALLOWED) ? "true" : "false";
   string payload = "{\"token\":\"" + InpBridgeToken + "\",\"trade_allowed\":" + algo + ",\"quotes\":" + q + "}";
   return payload;
}

//+------------------------------------------------------------------+
void OnTimer()
{
   string url = InpServerUrl + "/api/mt5/pending?token=" + InpBridgeToken;
   string postBody = BuildQuotesPayload();
   char post[], result[];
   StringToCharArray(postBody, post, 0, WHOLE_ARRAY, CP_UTF8);
   ArrayResize(post, ArraySize(post) - 1);

   string headers = "Content-Type: application/json\r\nAccept: application/json\r\nX-MT5-Token: " + InpBridgeToken + "\r\n";
   string resHeaders = "";

   ResetLastError();
   int code = WebRequest("POST", url, headers, 4000, post, result, resHeaders);
   if(code != 200)
   {
      if(code == -1)
         Print("WebRequest gagal (Error ", GetLastError(), "). Pastikan URL ", InpServerUrl, " sudah ditambahkan di Tools -> Options -> Expert Advisors -> Allow WebRequest.");
      return;
   }

   // Hanya eksekusi posisi jika fitur eksekusi aktif dan tombol Algo Trading MT5 menyala
   if(!InpAutoExecute || !TerminalInfoInteger(TERMINAL_TRADE_ALLOWED))
      return;

   string json = CharArrayToString(result, 0, WHOLE_ARRAY, CP_UTF8);
   ProcessOrdersJson(json);
}

//+------------------------------------------------------------------+
void ProcessOrdersJson(const string &json)
{
   int ordersPos = StringFind(json, "\"orders\":[");
   if(ordersPos < 0) return;

   int arrStart = ordersPos + 9;
   int pos = arrStart;

   while(true)
   {
      int objStart = StringFind(json, "{", pos);
      if(objStart < 0) break;
      int objEnd = StringFind(json, "}", objStart);
      if(objEnd < 0) break;

      string obj = StringSubstr(json, objStart, objEnd - objStart + 1);
      pos = objEnd + 1;

      string id     = ExtractJsonString(obj, "id");
      string symbol = ExtractJsonString(obj, "symbol") + InpSymbolSuffix;
      string side   = ExtractJsonString(obj, "side");
      double lots   = StringToDouble(ExtractJsonNumber(obj, "lots"));
      double sl     = StringToDouble(ExtractJsonNumber(obj, "sl"));
      double tp     = StringToDouble(ExtractJsonNumber(obj, "tp"));

      if(StringLen(id) > 0 && StringLen(symbol) > 0 && lots > 0.0)
         ExecuteApprovedOrder(id, symbol, side, lots, sl, tp);
   }
}

//+------------------------------------------------------------------+
void ExecuteApprovedOrder(const string id, const string symbol, const string side, double lots, double sl, double tp)
{
   if(!SymbolSelect(symbol, true))
   {
      ReportExecution(id, "failed", "", 0.0, "Symbol tidak ditemukan di Market Watch: " + symbol);
      return;
   }

   int digits = (int)SymbolInfoInteger(symbol, SYMBOL_DIGITS);
   double volMin  = SymbolInfoDouble(symbol, SYMBOL_VOLUME_MIN);
   double volMax  = SymbolInfoDouble(symbol, SYMBOL_VOLUME_MAX);
   double volStep = SymbolInfoDouble(symbol, SYMBOL_VOLUME_STEP);

   if(volStep > 0)
      lots = MathFloor(lots / volStep) * volStep;
   lots = MathMax(volMin, MathMin(volMax, NormalizeDouble(lots, 2)));

   sl = NormalizeDouble(sl, digits);
   tp = NormalizeDouble(tp, digits);

   bool ok = false;
   if(side == "buy")
   {
      double ask = SymbolInfoDouble(symbol, SYMBOL_ASK);
      ok = trade.Buy(lots, symbol, ask, sl, tp, "TG Approved " + id);
   }
   else if(side == "sell")
   {
      double bid = SymbolInfoDouble(symbol, SYMBOL_BID);
      ok = trade.Sell(lots, symbol, bid, sl, tp, "TG Approved " + id);
   }

   if(ok && (trade.ResultRetcode() == TRADE_RETCODE_DONE || trade.ResultRetcode() == TRADE_RETCODE_PLACED))
   {
      string ticket = IntegerToString((long)trade.ResultOrder());
      double execPx = trade.ResultPrice();
      Print("MT5 Order Executed: #", ticket, " ", symbol, " ", side, " ", lots, " @ ", execPx);
      ReportExecution(id, "executed", ticket, execPx, "");
   }
   else
   {
      string err = "Retcode " + IntegerToString((int)trade.ResultRetcode()) + ": " + trade.ResultRetcodeDescription();
      Print("MT5 Order Failed: ", err);
      ReportExecution(id, "failed", "", 0.0, err);
   }
}

//+------------------------------------------------------------------+
void ReportExecution(const string id, const string status, const string ticket, double execPx, const string errMsg)
{
   string url = InpServerUrl + "/api/mt5/report?token=" + InpBridgeToken;
   string body = StringFormat(
      "{\"id\":\"%s\",\"status\":\"%s\",\"ticket\":\"%s\",\"executed_price\":%.5f,\"error_message\":\"%s\"}",
      id, status, ticket, execPx, errMsg
   );

   char post[], result[];
   StringToCharArray(body, post, 0, WHOLE_ARRAY, CP_UTF8);
   ArrayResize(post, ArraySize(post) - 1); // strip null terminator

   string headers = "Content-Type: application/json\r\nAccept: application/json\r\nX-MT5-Token: " + InpBridgeToken + "\r\n";
   string resHeaders = "";
   WebRequest("POST", url, headers, 5000, post, result, resHeaders);
}

//+------------------------------------------------------------------+
string ExtractJsonString(const string &json, const string key)
{
   string pattern = "\"" + key + "\":\"";
   int start = StringFind(json, pattern);
   if(start < 0) return "";
   start += StringLen(pattern);
   int end = StringFind(json, "\"", start);
   if(end < 0) return "";
   return StringSubstr(json, start, end - start);
}

//+------------------------------------------------------------------+
string ExtractJsonNumber(const string &json, const string key)
{
   string pattern = "\"" + key + "\":";
   int start = StringFind(json, pattern);
   if(start < 0) return "0";
   start += StringLen(pattern);
   int endComma = StringFind(json, ",", start);
   int endBrace = StringFind(json, "}", start);
   int end = (endComma >= 0 && (endBrace < 0 || endComma < endBrace)) ? endComma : endBrace;
   if(end < 0) return "0";
   return StringSubstr(json, start, end - start);
}
//+------------------------------------------------------------------+

