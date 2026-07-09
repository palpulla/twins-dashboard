# tests/test_ig_captions.py
import datetime as dt
from pathlib import Path
from engine.config import load_instagram_config, load_brand
from engine.ig_captions import pick_topic, build_prompt, generate_caption
from engine.ig_planner import plan_slot

CONFIG = Path(__file__).resolve().parents[1] / "config"
CFG = load_instagram_config(CONFIG / "instagram.yaml")
BRAND = load_brand(CONFIG / "brand.yaml")
ANCHOR = "2026-07-06"


def test_pick_topic_is_deterministic_and_rotates():
    slot = plan_slot(dt.date(2026, 7, 8), ANCHOR, hiring=False)   # value slot
    t1 = pick_topic(slot, CFG)
    t2 = pick_topic(slot, CFG)
    assert t1 == t2 and t1 in CFG.slot_topics["value"]
    later = plan_slot(dt.date(2026, 7, 15), ANCHOR, hiring=False)
    assert pick_topic(later, CFG) != t1   # next week rotates


def test_build_prompt_carries_voice_and_topic():
    slot = plan_slot(dt.date(2026, 7, 6), ANCHOR, hiring=False)
    prompt = build_prompt(slot, "Broken spring repair", "Verona", CFG, BRAND)
    assert "Broken spring repair" in prompt
    assert "Verona" in prompt
    assert "no emojis" in prompt.lower()


def test_proof_prompt_forbids_invented_timing():
    slot = plan_slot(dt.date(2026, 7, 6), ANCHOR, hiring=False)  # proof
    prompt = build_prompt(slot, "New garage door installation", "Madison", CFG, BRAND)
    assert "do not claim when the job happened" in prompt.lower()
    value_slot = plan_slot(dt.date(2026, 7, 8), ANCHOR, hiring=False)
    value_prompt = build_prompt(value_slot, "Repair versus replacement - how to decide", None, CFG, BRAND)
    assert "do not claim when the job happened" not in value_prompt.lower()


def test_generate_caption_appends_cta(mocker):
    slot = plan_slot(dt.date(2026, 7, 6), ANCHOR, hiring=False)
    mocker.patch("engine.ig_captions.complete", return_value="Broken spring repair in Verona. Done the same day.")
    caption = generate_caption(slot, "Broken spring repair", "Verona", CFG, BRAND)
    assert caption.endswith(CFG.slot_cta["proof"])
    assert "Broken spring repair in Verona." in caption


def test_proof_prompt_forbids_specific_city_when_unknown():
    slot = plan_slot(dt.date(2026, 7, 6), ANCHOR, hiring=False)  # proof
    prompt = build_prompt(slot, "New garage door installation", None, CFG, BRAND)
    assert "do not name a specific city" in prompt.lower()
    with_city = build_prompt(slot, "New garage door installation", "Verona", CFG, BRAND)
    assert "do not name a specific city" not in with_city.lower()
