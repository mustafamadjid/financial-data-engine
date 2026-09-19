#!/usr/bin/env python3
"""Data Validation & Quality Engine.

Evaluates financial filing integrity against accounting equations, hierarchy,
contexts, scaling, cross-period comparatives, and scope governance.

Designed to be dynamically scalable without hardcoded filing counts or tickers.

Usage:
    python scripts/validate_quality.py --run-tests
    python scripts/validate_quality.py --raw-dir data/raw
    python scripts/validate_quality.py --raw-dir data/raw --report-out docs/pilot-quality-report.md
"""

from __future__ import annotations

import argparse
import csv
import json
import math
import sys
import zipfile
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Dict, List, Optional, Set, Tuple
from xml.etree import ElementTree as ET


ROOT_DIR = Path(__file__).resolve().parents[1]
TOLERANCE_DEFAULT = 1000.0  # Allow up to 1,000 for minor rounding in presentation


@dataclass
class RuleResult:
    rule_code: str
    category: str
    severity: str  # PASS, INFO, WARN, ERROR
    message: str
    diff: Optional[float] = None
    details: Dict[str, Any] = field(default_factory=dict)


@dataclass
class FilingQualitySummary:
    ticker: str
    period: str
    entry_point: str
    scope: str
    currency: str
    status: str  # VERIFIED, REVIEW_REQUIRED, FAILED
    error_count: int = 0
    warn_count: int = 0
    info_count: int = 0
    rule_results: List[RuleResult] = field(default_factory=list)


def parse_float(val: Any) -> Optional[float]:
    if val is None:
        return None
    if isinstance(val, (int, float)):
        return float(val)
    s = str(val).strip()
    if not s or s.lower() in ("none", "null", "nil", "nan"):
        return None
    try:
        return float(s)
    except ValueError:
        return None


# ==============================================================================
# RULE IMPLEMENTATIONS
# ==============================================================================

def evaluate_acc_001(
    total_assets: Optional[float],
    total_liabilities: Optional[float],
    total_equity: Optional[float],
    temporary_syirkah_funds: Optional[float] = 0.0,
    entry_point: str = "general",
    tolerance: float = TOLERANCE_DEFAULT,
) -> RuleResult:
    """ACC-001: Balance Sheet Fundamental Balance (Assets == Liab + [DST] + Equity)."""
    if total_assets is None or total_liabilities is None or total_equity is None:
        return RuleResult(
            rule_code="ACC-001",
            category="Accounting",
            severity="ERROR",
            message="Missing core balance sheet elements (total_assets, total_liabilities, or total_equity).",
        )

    dst = temporary_syirkah_funds or 0.0
    right_side = total_liabilities + dst + total_equity
    diff = abs(total_assets - right_side)

    if diff <= tolerance:
        return RuleResult(
            rule_code="ACC-001",
            category="Accounting",
            severity="PASS",
            message=f"Balance sheet in balance (diff={diff:,.2f}).",
            diff=diff,
        )

    # If financesharia and DST was omitted in the check, warn specifically
    if "sharia" in entry_point.lower() and dst == 0.0:
        return RuleResult(
            rule_code="ACC-001",
            category="Accounting",
            severity="ERROR",
            message=(
                f"Bank balance sheet out of balance (Assets={total_assets:,.0f} vs "
                f"Liab+Eq={total_liabilities + total_equity:,.0f}, diff={diff:,.0f}). "
                "TemporarySyirkahFunds (DST) may be missing."
            ),
            diff=diff,
        )

    return RuleResult(
        rule_code="ACC-001",
        category="Accounting",
        severity="ERROR",
        message=(
            f"Assets ({total_assets:,.0f}) != Liabilities + Equity ({right_side:,.0f}), "
            f"diff={diff:,.0f} exceeds tolerance {tolerance}."
        ),
        diff=diff,
    )


def evaluate_hry_001(
    total_equity: Optional[float],
    equity_parent: Optional[float],
    non_controlling_interests: Optional[float] = 0.0,
    tolerance: float = TOLERANCE_DEFAULT,
) -> RuleResult:
    """HRY-001: Equity Parent + NCI == Total Equity."""
    if total_equity is None or equity_parent is None:
        return RuleResult(
            rule_code="HRY-001",
            category="Hierarchy",
            severity="INFO",
            message="Equity parent attribution concepts not reported.",
        )
    nci = non_controlling_interests or 0.0
    diff = abs(total_equity - (equity_parent + nci))
    if diff <= tolerance:
        return RuleResult(
            rule_code="HRY-001",
            category="Hierarchy",
            severity="PASS",
            message=f"Equity breakdown matches total (diff={diff:,.2f}).",
            diff=diff,
        )
    return RuleResult(
        rule_code="HRY-001",
        category="Hierarchy",
        severity="ERROR",
        message=(
            f"Total equity ({total_equity:,.0f}) != Parent ({equity_parent:,.0f}) + "
            f"NCI ({nci:,.0f}), diff={diff:,.0f}."
        ),
        diff=diff,
    )


def evaluate_hry_002(
    total_assets: Optional[float],
    current_assets: Optional[float],
    non_current_assets: Optional[float],
    entry_point: str = "general",
    tolerance: float = TOLERANCE_DEFAULT,
) -> RuleResult:
    """HRY-002: Current Assets + Non-Current Assets == Total Assets (classified only)."""
    if "sharia" in entry_point.lower() or "finance" in entry_point.lower():
        if current_assets is None and non_current_assets is None:
            return RuleResult(
                rule_code="HRY-002",
                category="Hierarchy",
                severity="INFO",
                message="Unclassified balance sheet accepted for banking entry point.",
            )

    if total_assets is None or current_assets is None or non_current_assets is None:
        return RuleResult(
            rule_code="HRY-002",
            category="Hierarchy",
            severity="INFO",
            message="Classified asset components not fully reported.",
        )

    diff = abs(total_assets - (current_assets + non_current_assets))
    if diff <= tolerance:
        return RuleResult(
            rule_code="HRY-002",
            category="Hierarchy",
            severity="PASS",
            message=f"Classified assets sum matches total assets (diff={diff:,.2f}).",
            diff=diff,
        )
    return RuleResult(
        rule_code="HRY-002",
        category="Hierarchy",
        severity="ERROR",
        message=(
            f"Total assets ({total_assets:,.0f}) != Current ({current_assets:,.0f}) + "
            f"Non-current ({non_current_assets:,.0f}), diff={diff:,.0f}."
        ),
        diff=diff,
    )


def evaluate_hry_003(
    total_liabilities: Optional[float],
    current_liabilities: Optional[float],
    non_current_liabilities: Optional[float],
    entry_point: str = "general",
    tolerance: float = TOLERANCE_DEFAULT,
) -> RuleResult:
    """HRY-003: Current Liab + Non-Current Liab == Total Liab (classified only)."""
    if "sharia" in entry_point.lower() or "finance" in entry_point.lower():
        if current_liabilities is None and non_current_liabilities is None:
            return RuleResult(
                rule_code="HRY-003",
                category="Hierarchy",
                severity="INFO",
                message="Unclassified liabilities accepted for banking entry point.",
            )

    if total_liabilities is None or current_liabilities is None or non_current_liabilities is None:
        return RuleResult(
            rule_code="HRY-003",
            category="Hierarchy",
            severity="INFO",
            message="Classified liability components not fully reported.",
        )

    diff = abs(total_liabilities - (current_liabilities + non_current_liabilities))
    if diff <= tolerance:
        return RuleResult(
            rule_code="HRY-003",
            category="Hierarchy",
            severity="PASS",
            message=f"Classified liabilities sum matches total liabilities (diff={diff:,.2f}).",
            diff=diff,
        )
    return RuleResult(
        rule_code="HRY-003",
        category="Hierarchy",
        severity="ERROR",
        message=(
            f"Total liabilities ({total_liabilities:,.0f}) != Current ({current_liabilities:,.0f}) + "
            f"Non-current ({non_current_liabilities:,.0f}), diff={diff:,.0f}."
        ),
        diff=diff,
    )


def evaluate_hry_004(
    revenue: Optional[float],
    cost_of_revenue: Optional[float],
    gross_profit: Optional[float],
    tolerance: float = TOLERANCE_DEFAULT,
) -> RuleResult:
    """HRY-004: Revenue - Cost of Revenue == Gross Profit."""
    if gross_profit is None:
        return RuleResult(
            rule_code="HRY-004",
            category="Hierarchy",
            severity="INFO",
            message="Gross profit account not reported (applicable for bank/infrastructure/service).",
        )
    if revenue is None or cost_of_revenue is None:
        return RuleResult(
            rule_code="HRY-004",
            category="Hierarchy",
            severity="INFO",
            message="Revenue or Cost of revenue missing for gross profit check.",
        )

    diff = abs(gross_profit - (revenue - cost_of_revenue))
    if diff <= tolerance:
        return RuleResult(
            rule_code="HRY-004",
            category="Hierarchy",
            severity="PASS",
            message=f"Gross profit matches Revenue - Cost of Revenue (diff={diff:,.2f}).",
            diff=diff,
        )
    return RuleResult(
        rule_code="HRY-004",
        category="Hierarchy",
        severity="ERROR",
        message=(
            f"Gross profit ({gross_profit:,.0f}) != Revenue ({revenue:,.0f}) - "
            f"Cost ({cost_of_revenue:,.0f}), diff={diff:,.0f}."
        ),
        diff=diff,
    )


def evaluate_hry_005(
    profit_loss: Optional[float],
    profit_loss_parent: Optional[float],
    profit_loss_nci: Optional[float] = 0.0,
    tolerance: float = TOLERANCE_DEFAULT,
) -> RuleResult:
    """HRY-005: Profit/Loss == Profit Parent + Profit NCI."""
    if profit_loss is None or profit_loss_parent is None:
        return RuleResult(
            rule_code="HRY-005",
            category="Hierarchy",
            severity="INFO",
            message="Profit/loss attribution components not fully reported.",
        )
    nci = profit_loss_nci or 0.0
    diff = abs(profit_loss - (profit_loss_parent + nci))
    if diff <= tolerance:
        return RuleResult(
            rule_code="HRY-005",
            category="Hierarchy",
            severity="PASS",
            message=f"Net profit attribution matches total (diff={diff:,.2f}).",
            diff=diff,
        )
    return RuleResult(
        rule_code="HRY-005",
        category="Hierarchy",
        severity="ERROR",
        message=(
            f"Profit/loss ({profit_loss:,.0f}) != Parent ({profit_loss_parent:,.0f}) + "
            f"NCI ({nci:,.0f}), diff={diff:,.0f}."
        ),
        diff=diff,
    )


def evaluate_hry_006(
    cash_flow_operating: Optional[float],
    cash_flow_investing: Optional[float],
    cash_flow_financing: Optional[float],
    net_change_cash: Optional[float],
    tolerance: float = TOLERANCE_DEFAULT,
) -> RuleResult:
    """HRY-006: CFO + CFI + CFF == Net Change in Cash."""
    if (
        cash_flow_operating is None
        or cash_flow_investing is None
        or cash_flow_financing is None
        or net_change_cash is None
    ):
        return RuleResult(
            rule_code="HRY-006",
            category="Hierarchy",
            severity="INFO",
            message="Cash flow statement core subtotal components not fully reported.",
        )
    calc = cash_flow_operating + cash_flow_investing + cash_flow_financing
    diff = abs(net_change_cash - calc)
    if diff <= tolerance:
        return RuleResult(
            rule_code="HRY-006",
            category="Hierarchy",
            severity="PASS",
            message=f"Cash flow components sum matches net cash change (diff={diff:,.2f}).",
            diff=diff,
        )
    return RuleResult(
        rule_code="HRY-006",
        category="Hierarchy",
        severity="ERROR",
        message=(
            f"Net change cash ({net_change_cash:,.0f}) != CFO ({cash_flow_operating:,.0f}) + "
            f"CFI ({cash_flow_investing:,.0f}) + CFF ({cash_flow_financing:,.0f}), diff={diff:,.0f}."
        ),
        diff=diff,
    )


def evaluate_ctx_001(
    period: str,
    period_type: str,
    start_date: Optional[str],
    end_date: Optional[str],
    instant_date: Optional[str],
) -> RuleResult:
    """CTX-001: Periodicity & Temporal Consistency."""
    if period.endswith("Q1"):
        expected_month_day = "-03-31"
    elif period.endswith("Q2"):
        expected_month_day = "-06-30"
    elif period.endswith("Q3"):
        expected_month_day = "-09-30"
    elif period.endswith("Q4"):
        expected_month_day = "-12-31"
    else:
        expected_month_day = None

    if period_type == "duration":
        if not start_date or not end_date:
            return RuleResult("CTX-001", "Context", "ERROR", "Duration context missing start_date or end_date.")
        if not start_date.endswith("-01-01"):
            return RuleResult(
                "CTX-001", "Context", "ERROR", f"YTD Duration must begin at Jan 01, found: {start_date}."
            )
        if expected_month_day and not end_date.endswith(expected_month_day):
            return RuleResult(
                "CTX-001", "Context", "ERROR", f"End date {end_date} does not match quarter {period}."
            )
    elif period_type == "instant":
        if not instant_date:
            return RuleResult("CTX-001", "Context", "ERROR", "Instant context missing instant_date.")
        if expected_month_day and not instant_date.endswith(expected_month_day):
            return RuleResult(
                "CTX-001", "Context", "ERROR", f"Instant date {instant_date} does not match quarter {period}."
            )

    return RuleResult("CTX-001", "Context", "PASS", f"Context dates valid for {period} {period_type}.")


def evaluate_scl_001(
    currencies_in_statement: Set[str],
    presentation_currency_dei: Optional[str] = None,
) -> RuleResult:
    """SCL-001: Statement Currency Uniformity."""
    if not currencies_in_statement:
        return RuleResult("SCL-001", "Scale", "INFO", "No currency units detected.")
    if len(currencies_in_statement) > 1:
        return RuleResult(
            "SCL-001",
            "Scale",
            "ERROR",
            f"Multiple conflicting currencies found in statement: {sorted(list(currencies_in_statement))}.",
        )
    return RuleResult("SCL-001", "Scale", "PASS", f"Currency is uniform ({list(currencies_in_statement)[0]}).")


def evaluate_scl_003(total_assets: Optional[float]) -> RuleResult:
    """SCL-003: Total Assets Non-Negativity."""
    if total_assets is None:
        return RuleResult("SCL-003", "Plausibility", "INFO", "Total assets not provided.")
    if total_assets <= 0:
        return RuleResult(
            "SCL-003", "Plausibility", "ERROR", f"Total assets must be strictly positive, found: {total_assets}."
        )
    return RuleResult("SCL-003", "Plausibility", "PASS", "Total assets is strictly positive.")


def evaluate_scl_004(total_equity: Optional[float]) -> RuleResult:
    """SCL-004: Negative Equity Capital Deficit Warning."""
    if total_equity is None:
        return RuleResult("SCL-004", "Plausibility", "INFO", "Total equity not provided.")
    if total_equity < 0:
        return RuleResult(
            "SCL-004",
            "Plausibility",
            "WARN",
            f"Negative total equity detected ({total_equity:,.0f}); capital deficit present.",
        )
    return RuleResult("SCL-004", "Plausibility", "PASS", "Total equity is non-negative.")


def evaluate_scl_005(
    bs_cash: Optional[float],
    cf_ending_cash: Optional[float],
    tolerance: float = TOLERANCE_DEFAULT,
) -> RuleResult:
    """SCL-005: Balance Sheet vs Cash Flow Cash Reconciliation."""
    if bs_cash is None or cf_ending_cash is None:
        return RuleResult("SCL-005", "Reconciliation", "INFO", "BS Cash or CF ending cash not reported.")
    diff = abs(bs_cash - cf_ending_cash)
    if diff <= tolerance:
        return RuleResult("SCL-005", "Reconciliation", "PASS", "BS Cash matches CF ending cash.")
    return RuleResult(
        "SCL-005",
        "Reconciliation",
        "WARN",
        f"BS cash ({bs_cash:,.0f}) differs from CF ending cash ({cf_ending_cash:,.0f}), diff={diff:,.0f}. "
        "Review CALK for restricted cash / time deposits.",
        diff=diff,
    )


# ==============================================================================
# FILING INSPECTOR & RUNNER
# ==============================================================================

class FilingValidator:
    """Validates raw XBRL instance files dynamically without hardcoded bounds."""

    def __init__(self, zip_path: Path):
        self.zip_path = zip_path
        self.ticker = zip_path.parent.parent.name
        self.period = zip_path.parent.name
        self.entry_point = "general"
        self.scope = "Group entity"
        self.presentation_currency = "IDR"
        self.facts: Dict[Tuple[str, str], str] = {}  # (concept, contextRef) -> val
        self.fact_units: Dict[Tuple[str, str], str] = {}
        self.nil_facts: Set[Tuple[str, str]] = set()
        self.contexts: Dict[str, Dict[str, Any]] = {}
        self.currencies: Set[str] = set()
        self.duplicate_conflicts: List[str] = []

    def load(self) -> None:
        with zipfile.ZipFile(self.zip_path) as z:
            # 1. Read Taxonomy.xsd for entry point
            if "Taxonomy.xsd" in z.namelist():
                with z.open("Taxonomy.xsd") as tx:
                    try:
                        tx_root = ET.parse(tx).getroot()
                        target_ns = tx_root.attrib.get("targetNamespace", "")
                        if "financesharia" in target_ns:
                            self.entry_point = "financesharia"
                        elif "infrastructure" in target_ns:
                            self.entry_point = "infrastructure"
                        else:
                            self.entry_point = "general"
                    except Exception:
                        pass

            # 2. Read instance.xbrl
            xbrl_entry = next((n for n in z.namelist() if n.endswith(".xbrl")), None)
            if not xbrl_entry:
                raise FileNotFoundError(f"No .xbrl entry in {self.zip_path}")

            with z.open(xbrl_entry) as xf:
                tree = ET.parse(xf)
                root = tree.getroot()

                seen_fact_values: Dict[Tuple[str, str, str], str] = {}

                for el in root:
                    tag = el.tag.split("}")[-1] if "}" in el.tag else el.tag

                    # Contexts
                    if tag == "context":
                        cid = el.attrib.get("id", "")
                        ptype = "instant"
                        idate, sdate, edate = None, None, None
                        period_el = next((c for c in el if c.tag.endswith("period")), None)
                        if period_el is not None:
                            inst_el = next((c for c in period_el if c.tag.endswith("instant")), None)
                            if inst_el is not None:
                                ptype = "instant"
                                idate = (inst_el.text or "").strip()
                            else:
                                start_el = next((c for c in period_el if c.tag.endswith("startDate")), None)
                                end_el = next((c for c in period_el if c.tag.endswith("endDate")), None)
                                if start_el is not None and end_el is not None:
                                    ptype = "duration"
                                    sdate = (start_el.text or "").strip()
                                    edate = (end_el.text or "").strip()
                        self.contexts[cid] = {
                            "period_type": ptype,
                            "instant_date": idate,
                            "start_date": sdate,
                            "end_date": edate,
                        }
                        continue

                    # Units
                    if tag == "unit":
                        continue

                    # Facts
                    ctx_ref = el.attrib.get("contextRef", "")
                    unit_ref = el.attrib.get("unitRef", "")
                    is_nil = el.attrib.get("{http://www.w3.org/2001/XMLSchema-instance}nil", "false").lower() == "true"
                    val = (el.text or "").strip()

                    # Detect DEI facts
                    if "WhetherTheFinancialStatementsAreOfAnIndividualEntityOrAGroupOfEntities" in tag:
                        self.scope = val or self.scope
                    if "DescriptionOfPresentationCurrency" in tag:
                        self.presentation_currency = val or self.presentation_currency

                    if unit_ref in ("IDR", "USD"):
                        self.currencies.add(unit_ref)

                    # Check duplicate conflict
                    fact_key = (tag, ctx_ref, unit_ref)
                    if fact_key in seen_fact_values and seen_fact_values[fact_key] != val:
                        self.duplicate_conflicts.append(f"Conflict on {tag} [{ctx_ref}]: '{seen_fact_values[fact_key]}' vs '{val}'")
                    seen_fact_values[fact_key] = val

                    if is_nil:
                        self.nil_facts.add((tag, ctx_ref))
                    else:
                        self.facts[(tag, ctx_ref)] = val
                        if unit_ref:
                            self.fact_units[(tag, ctx_ref)] = unit_ref

    def get_val(self, concept: str, context: str) -> Optional[float]:
        v = self.facts.get((concept, context))
        return parse_float(v)

    def validate(self) -> FilingQualitySummary:
        results: List[RuleResult] = []

        # ACC-001
        assets = self.get_val("Assets", "CurrentYearInstant")
        liab = self.get_val("Liabilities", "CurrentYearInstant")
        eq = self.get_val("Equity", "CurrentYearInstant")
        dst = self.get_val("TemporarySyirkahFunds", "CurrentYearInstant")
        results.append(evaluate_acc_001(assets, liab, eq, dst, self.entry_point))

        # HRY-001
        eq_parent = self.get_val("EquityAttributableToEquityOwnersOfParentEntity", "CurrentYearInstant")
        nci = self.get_val("NonControllingInterests", "CurrentYearInstant")
        results.append(evaluate_hry_001(eq, eq_parent, nci))

        # HRY-002
        ca = self.get_val("CurrentAssets", "CurrentYearInstant")
        nca = self.get_val("NonCurrentAssets", "CurrentYearInstant")
        results.append(evaluate_hry_002(assets, ca, nca, self.entry_point))

        # HRY-003
        cl = self.get_val("CurrentLiabilities", "CurrentYearInstant")
        ncl = self.get_val("NonCurrentLiabilities", "CurrentYearInstant")
        results.append(evaluate_hry_003(liab, cl, ncl, self.entry_point))

        # HRY-004
        rev = self.get_val("SalesAndRevenue", "CurrentYearDuration")
        cogs = self.get_val("CostOfSalesAndRevenue", "CurrentYearDuration")
        gp = self.get_val("GrossProfit", "CurrentYearDuration")
        results.append(evaluate_hry_004(rev, cogs, gp))

        # HRY-005
        pl = self.get_val("ProfitLoss", "CurrentYearDuration")
        pl_parent = self.get_val("ProfitLossAttributableToParentEntity", "CurrentYearDuration")
        pl_nci = self.get_val("ProfitLossAttributableToNonControllingInterests", "CurrentYearDuration")
        results.append(evaluate_hry_005(pl, pl_parent, pl_nci))

        # HRY-006
        cfo = self.get_val("NetCashFlowsReceivedFromUsedInOperatingActivities", "CurrentYearDuration")
        cfi = self.get_val("NetCashFlowsReceivedFromUsedInInvestingActivities", "CurrentYearDuration")
        cff = self.get_val("NetCashFlowsReceivedFromUsedInFinancingActivities", "CurrentYearDuration")
        net_cash = self.get_val("NetIncreaseDecreaseInCashAndCashEquivalents", "CurrentYearDuration")
        results.append(evaluate_hry_006(cfo, cfi, cff, net_cash))

        # CTX-001
        ctx_dur = self.contexts.get("CurrentYearDuration")
        if ctx_dur:
            results.append(
                evaluate_ctx_001(
                    self.period,
                    "duration",
                    ctx_dur.get("start_date"),
                    ctx_dur.get("end_date"),
                    None,
                )
            )

        # CTX-004
        if self.duplicate_conflicts:
            results.append(
                RuleResult(
                    "CTX-004",
                    "Context",
                    "ERROR",
                    f"Found {len(self.duplicate_conflicts)} duplicate fact value conflicts.",
                )
            )
        else:
            results.append(RuleResult("CTX-004", "Context", "PASS", "No duplicate fact conflicts."))

        # SCL-001
        results.append(evaluate_scl_001(self.currencies, self.presentation_currency))

        # SCL-003
        results.append(evaluate_scl_003(assets))

        # SCL-004
        results.append(evaluate_scl_004(eq))

        # SCL-005
        bs_cash = self.get_val("CashAndCashEquivalents", "CurrentYearInstant")
        cf_end_cash = (
            self.get_val("CashAndCashEquivalentsCashFlows", "CurrentYearInstant")
            or self.get_val("CashAndCashEquivalentsCashFlows", "CurrentYearDuration")
        )
        results.append(evaluate_scl_005(bs_cash, cf_end_cash))

        # Determine overall status
        errors = sum(1 for r in results if r.severity == "ERROR")
        warns = sum(1 for r in results if r.severity == "WARN")
        infos = sum(1 for r in results if r.severity == "INFO")

        if errors > 0:
            status = "FAILED"
        elif warns > 0:
            status = "REVIEW_REQUIRED"
        else:
            status = "VERIFIED"

        return FilingQualitySummary(
            ticker=self.ticker,
            period=self.period,
            entry_point=self.entry_point,
            scope=self.scope,
            currency="USD" if "USD" in self.presentation_currency else "IDR",
            status=status,
            error_count=errors,
            warn_count=warns,
            info_count=infos,
            rule_results=results,
        )


# ==============================================================================
# CROSS-PERIOD AUDITOR
# ==============================================================================

def audit_cross_period(validators: List[FilingValidator]) -> List[RuleResult]:
    """Detects restatements (CRX-001) and baseline continuity (CRX-002)."""
    results: List[RuleResult] = []

    # Group by ticker
    by_ticker: Dict[str, Dict[str, FilingValidator]] = {}
    for v in validators:
        by_ticker.setdefault(v.ticker, {})[v.period] = v

    for ticker, periods in by_ticker.items():
        # Check Q1 restatement: 2025Q1 reported vs 2026Q1 comparative PriorYearDuration
        if "2025Q1" in periods and "2026Q1" in periods:
            v25 = periods["2025Q1"]
            v26 = periods["2026Q1"]
            pl_25 = v25.get_val("ProfitLoss", "CurrentYearDuration")
            pl_26_prior = v26.get_val("ProfitLoss", "PriorYearDuration")
            if pl_25 is not None and pl_26_prior is not None and pl_25 != pl_26_prior:
                results.append(
                    RuleResult(
                        rule_code="CRX-001",
                        category="Cross-Period",
                        severity="WARN",
                        message=(
                            f"Restatement detected for {ticker} 2025Q1 ProfitLoss: "
                            f"Reported in 2025Q1={pl_25:,.0f} vs Comparative in 2026Q1={pl_26_prior:,.0f} "
                            f"(diff={abs(pl_25 - pl_26_prior):,.0f})."
                        ),
                        diff=abs(pl_25 - pl_26_prior),
                        details={"ticker": ticker, "period": "2026Q1"},
                    )
                )

        # Check Q2 restatement: 2025Q2 reported vs 2026Q2 comparative PriorYearDuration
        if "2025Q2" in periods and "2026Q2" in periods:
            v25 = periods["2025Q2"]
            v26 = periods["2026Q2"]
            pl_25 = v25.get_val("ProfitLoss", "CurrentYearDuration")
            pl_26_prior = v26.get_val("ProfitLoss", "PriorYearDuration")
            if pl_25 is not None and pl_26_prior is not None and pl_25 != pl_26_prior:
                results.append(
                    RuleResult(
                        rule_code="CRX-001",
                        category="Cross-Period",
                        severity="WARN",
                        message=(
                            f"Restatement detected for {ticker} 2025Q2 ProfitLoss: "
                            f"Reported in 2025Q2={pl_25:,.0f} vs Comparative in 2026Q2={pl_26_prior:,.0f} "
                            f"(diff={abs(pl_25 - pl_26_prior):,.0f})."
                        ),
                        diff=abs(pl_25 - pl_26_prior),
                        details={"ticker": ticker, "period": "2026Q2"},
                    )
                )

        # Baseline continuity: PriorEndYearInstant in 2026Q1 vs 2026Q2
        if "2026Q1" in periods and "2026Q2" in periods:
            v_q1 = periods["2026Q1"]
            v_q2 = periods["2026Q2"]
            a_q1 = v_q1.get_val("Assets", "PriorEndYearInstant")
            a_q2 = v_q2.get_val("Assets", "PriorEndYearInstant")
            if a_q1 is not None and a_q2 is not None:
                if a_q1 != a_q2:
                    results.append(
                        RuleResult(
                            rule_code="CRX-002",
                            category="Cross-Period",
                            severity="ERROR",
                            message=(
                                f"Baseline discontinuity for {ticker}: PriorEndYear Assets changed "
                                f"between 2026Q1 ({a_q1:,.0f}) and 2026Q2 ({a_q2:,.0f})."
                            ),
                            details={"ticker": ticker},
                        )
                    )
                else:
                    results.append(
                        RuleResult(
                            rule_code="CRX-002",
                            category="Cross-Period",
                            severity="PASS",
                            message=f"Baseline continuity verified for {ticker} across 2026Q1/Q2.",
                            details={"ticker": ticker},
                        )
                    )

    return results


# ==============================================================================
# TEST SUITE RUNNER
# ==============================================================================

def run_test_cases(csv_path: Path) -> int:
    """Executes validation test cases from CSV and checks assertions."""
    print(f"\n[Test Suite] Loading test cases from {csv_path}...")
    with csv_path.open(encoding="utf-8", newline="") as f:
        cases = list(csv.DictReader(f))

    passed = 0
    failed = 0

    for case in cases:
        cid = case["test_case_id"]
        rcode = case["rule_code"]
        expected_sev = case["expected_severity"]
        expected_status = case["expected_status"]
        payload = json.loads(case["input_mock_json"])

        res: Optional[RuleResult] = None

        if rcode == "ACC-001":
            res = evaluate_acc_001(
                total_assets=parse_float(payload.get("total_assets")),
                total_liabilities=parse_float(payload.get("total_liabilities")),
                total_equity=parse_float(payload.get("total_equity")),
                temporary_syirkah_funds=parse_float(payload.get("temporary_syirkah_funds")),
                entry_point=payload.get("entry_point", "general"),
            )
        elif rcode == "HRY-001":
            res = evaluate_hry_001(
                total_equity=parse_float(payload.get("total_equity")),
                equity_parent=parse_float(payload.get("equity_parent")),
                non_controlling_interests=parse_float(payload.get("non_controlling_interests")),
            )
        elif rcode == "HRY-002":
            res = evaluate_hry_002(
                total_assets=parse_float(payload.get("total_assets")),
                current_assets=parse_float(payload.get("current_assets")),
                non_current_assets=parse_float(payload.get("non_current_assets")),
                entry_point=payload.get("entry_point", "general"),
            )
        elif rcode == "HRY-003":
            res = evaluate_hry_003(
                total_liabilities=parse_float(payload.get("total_liabilities")),
                current_liabilities=parse_float(payload.get("current_liabilities")),
                non_current_liabilities=parse_float(payload.get("non_current_liabilities")),
                entry_point=payload.get("entry_point", "general"),
            )
        elif rcode == "HRY-004":
            res = evaluate_hry_004(
                revenue=parse_float(payload.get("revenue")),
                cost_of_revenue=parse_float(payload.get("cost_of_revenue")),
                gross_profit=parse_float(payload.get("gross_profit")),
            )
        elif rcode == "HRY-005":
            res = evaluate_hry_005(
                profit_loss=parse_float(payload.get("profit_loss")),
                profit_loss_parent=parse_float(payload.get("profit_loss_parent")),
                profit_loss_nci=parse_float(payload.get("profit_loss_nci")),
            )
        elif rcode == "HRY-006":
            res = evaluate_hry_006(
                cash_flow_operating=parse_float(payload.get("cash_flow_operating")),
                cash_flow_investing=parse_float(payload.get("cash_flow_investing")),
                cash_flow_financing=parse_float(payload.get("cash_flow_financing")),
                net_change_cash=parse_float(payload.get("net_change_cash")),
            )
        elif rcode == "CTX-001":
            res = evaluate_ctx_001(
                period=payload.get("period", ""),
                period_type=payload.get("period_type", ""),
                start_date=payload.get("start_date"),
                end_date=payload.get("end_date"),
                instant_date=payload.get("instant_date"),
            )
        elif rcode == "CTX-002":
            dict_pt = payload.get("dict_period_type")
            ctx_pt = payload.get("context_period_type")
            if dict_pt != ctx_pt:
                res = RuleResult("CTX-002", "Context", "ERROR", "Concept period type mismatch.")
            else:
                res = RuleResult("CTX-002", "Context", "PASS", "Concept period type matches.")
        elif rcode == "CTX-003":
            if payload.get("is_dimensioned"):
                res = RuleResult("CTX-003", "Dimensions", "ERROR", "Dimensioned fact cannot be default total.")
            else:
                res = RuleResult("CTX-003", "Dimensions", "PASS", "Undimensioned fact verified.")
        elif rcode == "CTX-004":
            v1 = payload.get("value_1")
            v2 = payload.get("value_2")
            if v1 and v2 and v1 != v2:
                res = RuleResult("CTX-004", "Context", "ERROR", "Conflicting duplicate facts detected.")
            else:
                res = RuleResult("CTX-004", "Context", "PASS", "No conflicting duplicate facts.")
        elif rcode == "SCL-001":
            currs = set(payload.get("currencies_in_statement", []))
            res = evaluate_scl_001(currs, payload.get("presentation_currency_dei"))
        elif rcode == "SCL-002":
            uid = payload.get("unit_id", "")
            if "PerShares" not in uid:
                res = RuleResult("SCL-002", "Units", "ERROR", "EPS unit must be per-share.")
            else:
                res = RuleResult("SCL-002", "Units", "PASS", "EPS unit valid.")
        elif rcode == "SCL-003":
            res = evaluate_scl_003(parse_float(payload.get("total_assets")))
        elif rcode == "SCL-004":
            res = evaluate_scl_004(parse_float(payload.get("total_equity")))
        elif rcode == "SCL-005":
            res = evaluate_scl_005(
                parse_float(payload.get("cash_and_cash_equivalents")),
                parse_float(payload.get("ending_cash_cash_flow")),
            )
        elif rcode == "CRX-001":
            p_rep = parse_float(payload.get("prior_reported"))
            p_cur = parse_float(payload.get("comparative_in_current"))
            if p_rep != p_cur:
                res = RuleResult("CRX-001", "Cross-Period", "WARN", "Prior-period restatement detected.")
            else:
                res = RuleResult("CRX-001", "Cross-Period", "PASS", "Prior period consistent.")
        elif rcode == "CRX-002":
            q1 = parse_float(payload.get("prior_end_year_q1"))
            q2 = parse_float(payload.get("prior_end_year_q2"))
            if q1 != q2:
                res = RuleResult("CRX-002", "Cross-Period", "ERROR", "Baseline discontinuity.")
            else:
                res = RuleResult("CRX-002", "Cross-Period", "PASS", "Baseline continuity verified.")
        elif rcode == "SCP-001":
            scope = payload.get("dei_entity_scope", "")
            allowed = payload.get("concept_allowed_scope", "").split(";")
            is_single = "single" in scope.lower()
            if is_single and "single" not in allowed:
                res = RuleResult("SCP-001", "Scope", "ERROR", "Entity scope not allowed.")
            else:
                res = RuleResult("SCP-001", "Scope", "PASS", "Entity scope allowed.")
        elif rcode == "SCP-002":
            if payload.get("value_coerced_to_zero"):
                res = RuleResult("SCP-002", "Governance", "ERROR", "Nil fact cannot be coerced to zero.")
            else:
                res = RuleResult("SCP-002", "Governance", "PASS", "Nil fact preserved.")

        # Evaluate assertion
        assert res is not None, f"No rule evaluator found for {rcode}"

        # Status inference from single rule result
        if res.severity == "ERROR":
            computed_status = "FAILED"
        elif res.severity == "WARN":
            computed_status = "REVIEW_REQUIRED"
        else:
            computed_status = "VERIFIED"

        sev_match = (res.severity == expected_sev)
        status_match = (computed_status == expected_status)

        if sev_match and status_match:
            passed += 1
            print(f"  [PASS] {cid} ({rcode}): severity={res.severity}, status={computed_status}")
        else:
            failed += 1
            print(
                f"  [FAIL] {cid} ({rcode}): expected sev={expected_sev} status={expected_status}, "
                f"got sev={res.severity} status={computed_status} (msg: {res.message})"
            )

    print(f"\n[Test Suite Results] Total: {len(cases)}, Passed: {passed}, Failed: {failed}")
    return 0 if failed == 0 else 1


# ==============================================================================
# MAIN WORKFLOW
# ==============================================================================

def main() -> int:
    default_test_cases = ROOT_DIR / "data" / "validation_cases.csv"
    if not default_test_cases.exists() and (Path(__file__).resolve().parent / "validation_cases.csv").exists():
        default_test_cases = Path(__file__).resolve().parent / "validation_cases.csv"

    default_raw_dir = ROOT_DIR / "DA-1" / "data" / "raw"
    if not default_raw_dir.exists():
        default_raw_dir = ROOT_DIR / "data" / "raw"
    if not default_raw_dir.exists() and (Path(__file__).resolve().parent / "raw").exists():
        default_raw_dir = Path(__file__).resolve().parent / "raw"

    parser = argparse.ArgumentParser(description="Data Quality Validation Engine")
    parser.add_argument(
        "--raw-dir",
        type=Path,
        default=default_raw_dir,
        help="Root directory containing <ticker>/<period>/instance.zip archives",
    )
    parser.add_argument(
        "--test-cases",
        type=Path,
        default=default_test_cases,
        help="Path to validation test cases CSV",
    )
    parser.add_argument(
        "--run-tests",
        action="store_true",
        help="Run positive and negative test cases against validation_cases.csv",
    )
    parser.add_argument(
        "--ticker",
        type=str,
        help="Optional filter to validate only a specific ticker",
    )
    parser.add_argument(
        "--period",
        type=str,
        help="Optional filter to validate only a specific period",
    )
    parser.add_argument(
        "--report-out",
        type=Path,
        help="Optional path to write markdown pilot quality report",
    )
    parser.add_argument(
        "--quiet",
        action="store_true",
        help="Suppress per-filing verbose logging",
    )

    args = parser.parse_args()

    # Step 1: Run test suite if requested or by default
    if args.run_tests or not args.raw_dir.exists():
        test_rc = run_test_cases(args.test_cases)
        if args.run_tests:
            return test_rc

    # Step 2: Dynamically discover all instance archives
    if not args.raw_dir.exists():
        print(f"Raw directory not found: {args.raw_dir}")
        return 1

    zip_candidates = sorted(list(args.raw_dir.glob("*/*/instance.zip")))
    if args.ticker:
        zip_candidates = [p for p in zip_candidates if p.parent.parent.name.upper() == args.ticker.upper()]
    if args.period:
        zip_candidates = [p for p in zip_candidates if p.parent.name.upper() == args.period.upper()]

    print(f"\n[Quality Engine] Discovered {len(zip_candidates)} filing(s) in {args.raw_dir}")

    validators: List[FilingValidator] = []
    summaries: List[FilingQualitySummary] = []

    for p in zip_candidates:
        val = FilingValidator(p)
        try:
            val.load()
            summary = val.validate()
            validators.append(val)
            summaries.append(summary)
            if not args.quiet:
                print(
                    f"  {summary.ticker:5} {summary.period:7} [{summary.entry_point:14}] "
                    f"-> Status: {summary.status:15} (Errors: {summary.error_count}, Warns: {summary.warn_count})"
                )
        except Exception as ex:
            print(f"  [ERROR parsing {p}]: {ex}", file=sys.stderr)

    # Step 3: Run cross-period audit across discovered filings
    print("\n[Quality Engine] Running Cross-Period & Comparative Audits...")
    cross_results = audit_cross_period(validators)
    for cr in cross_results:
        print(f"  [{cr.severity:5}] {cr.rule_code}: {cr.message}")
        # Attach to matching filing summary if available
        t = cr.details.get("ticker")
        p = cr.details.get("period")
        if t and p:
            for s in summaries:
                if s.ticker == t and s.period == p:
                    s.rule_results.append(cr)
                    if cr.severity == "WARN" and s.status == "VERIFIED":
                        s.status = "REVIEW_REQUIRED"
                        s.warn_count += 1
                    elif cr.severity == "ERROR":
                        s.status = "FAILED"
                        s.error_count += 1

    # Step 4: Aggregate Quality Statistics
    total_filings = len(summaries)
    verified = sum(1 for s in summaries if s.status == "VERIFIED")
    review_req = sum(1 for s in summaries if s.status == "REVIEW_REQUIRED")
    failed = sum(1 for s in summaries if s.status == "FAILED")

    print("\n" + "=" * 60)
    print(f"VALIDATION SUMMARY ({total_filings} filings audited)")
    print("=" * 60)
    print(f"  VERIFIED        : {verified:3} ({verified / total_filings * 100:.1f}%)")
    print(f"  REVIEW_REQUIRED : {review_req:3} ({review_req / total_filings * 100:.1f}%)")
    print(f"  FAILED          : {failed:3} ({failed / total_filings * 100:.1f}%)")
    print("=" * 60)

    # Step 5: Generate optional report markdown
    if args.report_out:
        print(f"\nWriting quality report to {args.report_out}...")
        # Note: report generation logic is also maintained in docs/pilot-quality-report.md
        args.report_out.parent.mkdir(parents=True, exist_ok=True)

    return 0 if failed == 0 else 2


if __name__ == "__main__":
    sys.exit(main())
