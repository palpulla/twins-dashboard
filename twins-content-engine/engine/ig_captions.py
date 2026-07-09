"""Instagram caption generation: deterministic topic rotation + Claude call."""
from __future__ import annotations

import datetime as dt

from engine.claude_client import make_client
from engine.config import BrandConfig, InstagramConfig
from engine.ig_planner import SlotSpec

_SYSTEM_PROMPT = (
    "You are a copywriter for a small, family-run residential garage door "
    "company in Wisconsin. Follow the user's instructions exactly and return "
    "only the requested caption text, nothing else."
)


def complete(prompt: str) -> str:
    """Adapter over engine.claude_client's real complete(*, system, user, ...) API.

    ig_captions only needs a single-string-in, single-string-out call, so this
    wraps the richer ClaudeClientProtocol (system/user/max_tokens/temperature ->
    CompletionResult) the rest of the engine uses. Tests patch this function
    directly (engine.ig_captions.complete), so callers never see the adapter.
    """
    client = make_client()
    result = client.complete(system=_SYSTEM_PROMPT, user=prompt, max_tokens=400, temperature=0.7)
    return result.text


def pick_topic(slot: SlotSpec, cfg: InstagramConfig) -> str:
    topics = cfg.slot_topics[slot.slot]
    week_index = dt.date.fromisoformat(slot.date).isocalendar().week
    return topics[week_index % len(topics)]


def build_prompt(slot: SlotSpec, topic: str, city: str | None,
                 cfg: InstagramConfig, brand: BrandConfig) -> str:
    place = f" in {city}" if city else ""
    avoid = ", ".join(cfg.banned_corporate_terms)
    proof_rules = ""
    if slot.slot == "proof":
        # Proof posts show a real photo, but the model must not fabricate a
        # story around it (a live smoke test produced "wrapped up today ...
        # before we packed up for the day" — an invented narrative).
        proof_rules = (
            " Describe the kind of work shown in general terms (e.g. 'A recent "
            "garage door installation in Madison by our crew'). Do NOT claim when "
            "the job happened (no 'today', 'this morning', 'this week', 'just "
            "wrapped up') and do NOT invent a narrative about how the job went."
        )
    return (
        f"Write a short Instagram caption for {brand.business_name}, a residential "
        f"garage door company serving Madison and Dane County, Wisconsin.\n"
        f"Post type: {slot.slot}. Topic: {topic}{place}.\n"
        f"Rules: plain trade language, no emojis, no hashtags, no exclamation streaks, "
        f"avoid these words: {avoid}. 2-4 sentences. If a city is given, name it in the "
        f"first sentence. Do not invent job details, prices, or customer names. "
        f"Same-day service may be mentioned truthfully.{proof_rules}\n"
        f"Return ONLY the caption text."
    )


def generate_caption(slot: SlotSpec, topic: str, city: str | None,
                     cfg: InstagramConfig, brand: BrandConfig) -> str:
    body = complete(build_prompt(slot, topic, city, cfg, brand)).strip()
    cta = cfg.slot_cta[slot.slot]
    return f"{body}\n\n{cta}"
