from pathlib import Path
from engine.config import load_instagram_config

CONFIG_DIR = Path(__file__).resolve().parents[1] / "config"


def test_load_instagram_config():
    cfg = load_instagram_config(CONFIG_DIR / "instagram.yaml")
    assert cfg.approved_offers == ["$0 service call", "$49 tune-up", "GoodLeap financing"]
    assert "specialist" in cfg.banned_corporate_terms
    assert cfg.max_ai_fraction == 0.34
    assert cfg.cycle_anchor == "2026-07-06"
    assert cfg.monthly_mix["real_job"] == 4


def test_plan2_config_fields():
    cfg = load_instagram_config(CONFIG_DIR / "instagram.yaml")
    assert cfg.timezone == "America/Chicago"
    assert cfg.publish_tools["verified"] is True
    assert cfg.publish_tools["rail"] == "ghl"
    assert cfg.publish_tools["ghl_platforms"] == ["facebook", "instagram"]
    assert "illinois" in cfg.banned_place_terms
    assert "commercial garage door" in cfg.commercial_terms
    assert "$49 tune-up" in cfg.approved_offer_phrases
    assert cfg.city_hashtags["Verona"] == "#VeronaWI"
    assert "proof" in cfg.slot_topics and len(cfg.slot_topics["value"]) >= 5
    assert cfg.slot_cta["offer"] == "Call or book through the link in our profile"


def test_hosting_config_fields():
    cfg = load_instagram_config(CONFIG_DIR / "instagram.yaml")
    assert cfg.hosting["bucket"] == "ig-media"
    assert cfg.hosting["key_var"] == "NEW_SUPABASE_SERVICE_ROLE_KEY"
