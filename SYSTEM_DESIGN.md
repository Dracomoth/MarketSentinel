# SYSTEM DESIGN: Argus Wealth (Personal Investment Decision Support Terminal)

## 1. High-Level Vision & Objectives
Argus Wealth is a private, self-hosted, multi-agent financial advisory and portfolio monitoring system. It is designed for individual operators who want institutional-grade market research, adversarial vetting, and ongoing position vigilance—delivered in clear, plain language with zero financial jargon.

### Core Philosophy
1. **Designed for Non-Financial Operators:** The application strips away quantitative day-trading noise (RSI, ATR, Wilder smoothing, moving-average cross technicalities) from the presentation layer. Every suggestion answers four fundamental questions: *What is this company? Why is it attractive now? What is the realistic downside risk? What exact action is suggested?*
2. **Intent-Driven Discovery ("The Hunter"):** The user does not need to pre-screen stocks or supply ticker symbols. The system takes high-level intent in plain English (e.g., risk appetite, budget, target asset classes, timeframe, strategy) and autonomously scans global exchanges to propose complete, coherent portfolios.
3. **Air-Gapped Capital:** The platform has zero programmatic broker execution rights. All real-world order routing is executed manually by the investor.
4. **Adversarial Multi-Agent Audit:** No asset enters a portfolio without surviving a structured debate between specialized AI analysts (Bull vs. Bear) and a Risk Officer who enforces diversification, sensible sizing, and cash reserves.
5. **Intelligent Cadence & Timezone Awareness:** Background monitoring is resource-efficient. High-volatility short-term holdings are reviewed frequently during active market hours, while long-term anchor bonds and index funds are reviewed bi-weekly or monthly. The system synchronizes monitoring with local exchange hours (US, European, Asian/Pacific, and 24/7 Crypto) to prevent wasted API consumption on closed exchanges.

---

## 2. End-to-End Decision & Discovery Flow

```
                     ┌──────────────────────────────────────────────┐
                     │          User Natural Language Prompt        │
                     │  "€3,000 budget, aggressive penny stocks,    │
                     │   6-12 month horizon, 4-6 assets, high risk" │
                     └──────────────────────┬───────────────────────┘
                                            │
                                            ▼
                     ┌──────────────────────────────────────────────┐
                     │          1. Strategy Architect Agent         │
                     │  - Deconstructs intent into screening rules  │
                     │  - Allocates capital & determines cash buffer│
                     │  - Proposes portfolio name & plain thesis    │
                     └──────────────────────┬───────────────────────┘
                                            │ Screening Filters
                                            ▼
                     ┌──────────────────────────────────────────────┐
                     │          2. Market Hunter (FMP Screener)     │
                     │  Fetches 20-30 candidate tickers matching    │
                     │  price, cap, volume, and sector constraints  │
                     └──────────────────────┬───────────────────────┘
                                            │ Screened Candidate Pool
                                            ▼
                     ┌──────────────────────────────────────────────┐
                     │   Real-Time Data Ingestion Layer             │
                     │   (Quotes, Candles, SEC, News, Sentiment)    │
                     └──────────────────────┬───────────────────────┘
                                            │
        ┌───────────────────────────────────┼───────────────────────────────────┐
        ▼                                   ▼                                   ▼
┌───────────────────────┐       ┌───────────────────────┐       ┌───────────────────────┐
│ Macro & News Agent    │       │ Fundamental Agent     │       │ Technical Agent       │
│ (World events,        │       │ (Revenue, debt ratios,│       │ (Trend structure,     │
│  regulatory catalyst) │       │  cash runway/burn)    │       │  volatility & volume) │
└───────────┬───────────┘       └───────────┬───────────┘       └───────────┬───────────┘
            │                               │                               │
            └───────────────────────────────┼───────────────────────────────┘
                                            │ Structured Evidence Dossier
                                            ▼
                                ┌───────────────────────┐
                                │ Debate Room           │
                                │ Bull Agent vs.        │
                                │ Bear Agent            │
                                └───────────┬───────────┘
                                            │ Opposing Arguments & Traps
                                            ▼
                                ┌───────────────────────┐
                                │ Risk & Sizing Agent   │
                                │ (Capital allocation,  │
                                │  cash buffer, stops)  │
                                └───────────┬───────────┘
                                            │ Qualified Setup Data
                                            ▼
                                ┌───────────────────────┐
                                │ Plain English         │
                                │ Translation Layer     │
                                └───────────┬───────────┘
                                            │
                                            ▼
                     ┌──────────────────────────────────────────────┐
                     │         4. Portfolio Proposal Screen         │
                     │  - Plain-English Strategy Overview           │
                     │  - Asset cards: What, Why, Downside, Shares  │
                     │  - Actions: Save, Scout Again, Inspect Debate│
                     └──────────────────────┬───────────────────────┘
                                            │ User clicks "Save"
                                            ▼
                     ┌──────────────────────────────────────────────┐
                     │            5. Active Guardian Engine         │
                     │  - Tracks purchase confirmation & real basis │
                     │  - Schedules checks by exchange timezone     │
                     │  - Flags "Urgent / Action Needed" on alerts  │
                     └──────────────────────────────────────────────┘
```

---

## 3. The Multi-Agent Council Roles

### Step 1: Strategy Architect & Discovery
* **Strategy Architect Agent:** Receives the user's natural language prompt, budget, target time horizon, and risk appetite. Translates this intent into a screening envelope (market cap limits, price ceilings, exchange list, sector filters, minimum average daily volume) and calculates target portfolio diversification (number of holdings and cash buffer).
* **Market Hunter:** Queries external screener APIs (Financial Modeling Prep Screener) with the architect's parameters to retrieve a pool of 20–30 live tradable assets currently meeting those bounds.

### Step 2: Parallel Specialist Council
Each candidate from the Hunter is enriched with live financial data and ingested concurrently by three specialist agents:
* **Fundamental Agent:** Evaluates solvency, debt-to-equity, cash runway, gross margin health, share dilution history, and valuation ratios. Weeds out firms at immediate risk of bankruptcy or emergency equity offerings.
* **Macro & News Agent:** Evaluates company press releases, SEC filings, recent sector trends, and global macroeconomic indicators via Finnhub. Identifies binary catalysts (earnings, clinical trial outcomes, regulatory filings).
* **Technical & Trend Agent:** Evaluates multi-timeframe price structure, trading volume patterns, support/resistance consolidation zones, and volatility. Identifies entry liquidity without exposing technical jargon to the end user.

### Step 3: Adversarial Debate Room (Bull vs. Bear)
* **Bull Analyst:** Synthesizes the specialist findings to make the strongest possible case for appreciation. Outlines the primary catalyst and near-term upside target.
* **Bear Analyst:** Acts as the designated skeptic. Identifies valuation overextension, hidden debt covenants, customer concentration risk, upcoming share lockup expirations, and macro headwinds. Outlines the fatal flaw (thesis killer).

### Step 4: Risk Arbiter & Plain English Translation
* **Risk Arbiter:** Weighs the debate, assigns a human-friendly Risk Tier (`Low`, `Moderate`, `High`, `Speculative`), determines exact share quantities to buy within the portfolio's cash budget, establishes an explicit invalidation threshold (when to walk away), and assigns an ongoing monitoring cadence.
* **Plain English Translator:** Synthesizes the council's internal technical debate into four simple points for the user:
  1. *What it is* (company summary in plain terms).
  2. *Why it was picked* (core upside catalyst).
  3. *The major risk* (the Bear's biggest concern).
  4. *Proposed purchase allocation* (number of shares and estimated investment cost).

---

## 4. Intelligent Cadence & Timezone Awareness Matrix

To avoid burning API credits on idle assets or closed markets, the background vigilance worker observes local exchange sessions:

| Asset Class & Role | Intended Horizon | Monitoring Cadence | Active Exchange Window | Trigger Strategy |
| :--- | :--- | :--- | :--- | :--- |
| **High-Beta Equities / Breakout Plays** | Short-Term (Days to Weeks) | Every 2–4 hours during open market | NYSE/NASDAQ (14:30–21:00 UTC) | Mid-session price spike/drop & 30m post-market close |
| **Growth Equities / Sector Turnarounds** | Mid-Term (1–6 Months) | Daily (Once per trading day) | Primary exchange local hours | 45 minutes after exchange closing bell |
| **Blue-Chip Equities / Dividend Compounders** | Long-Term (1–5+ Years) | Semi-Weekly (2x per week) | Primary exchange local hours | Tuesday and Friday post-market close |
| **Government Bonds / Defensive Cash Anchors** | Ultra Long-Term / Capital Preservation | Bi-Weekly / Monthly (14–30 Days) | Global / Sovereign | Bi-weekly or upon major central bank interest rate announcements |
| **Crypto Assets** | Any | Dynamic (4h to 24h) | Global (24/7/365, UTC) | Uniform interval regardless of day of week |

### Exchange Timezone Support
* **North America:** `America/New_York` (NYSE, NASDAQ)
* **Europe:** `Europe/Madrid`, `Europe/London`, `Europe/Paris` (BME, LSE, Euronext)
* **Asia/Pacific:** `Asia/Tokyo` (TSE), `Asia/Hong_Kong` (HKEX)
* **Crypto / Forex:** `UTC`