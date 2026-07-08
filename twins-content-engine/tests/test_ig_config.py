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
