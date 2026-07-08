from engine.config import load_instagram_config
from engine.ig_rules import check_instagram_caption
from pathlib import Path

CFG = load_instagram_config(Path(__file__).resolve().parents[1] / "config" / "instagram.yaml")


def _ids(report):
    return {v.rule_id for v in report.violations}


def test_clean_caption_passes():
    text = "Broken spring repair in Verona. Send us a photo of the spring and opener label."
    report = check_instagram_caption(text, CFG)
    assert report.passed, report.violations


def test_same_day_is_allowed():
    report = check_instagram_caption("We offer same-day garage door service.", CFG)
    assert report.passed, report.violations


def test_emoji_is_blocked():
    report = check_instagram_caption("New opener installed \U0001F600", CFG)
    assert "ig:emoji" in _ids(report)


def test_kentucky_is_blocked():
    report = check_instagram_caption("Serving Madison and Lexington KY.", CFG)
    assert "ig:out_of_state" in _ids(report)


def test_corporate_term_is_blocked():
    report = check_instagram_caption("Our specialists deliver solutions.", CFG)
    assert "ig:corporate_term" in _ids(report)


def test_unapproved_dollar_offer_is_blocked():
    report = check_instagram_caption("Get $25 off any repair today.", CFG)
    assert "ig:unapproved_offer" in _ids(report)


def test_approved_offer_passes():
    report = check_instagram_caption("Book the $49 tune-up before winter.", CFG)
    assert "ig:unapproved_offer" not in _ids(report)


def test_decimal_dollar_is_blocked():
    report = check_instagram_caption("Book the $49.99 tune-up today.", CFG)
    assert "ig:unapproved_offer" in _ids(report)


def test_plural_corporate_term_is_blocked():
    report = check_instagram_caption("Our specialists will call you back.", CFG)
    assert "ig:corporate_term" in _ids(report)


def test_two_dollar_amounts_one_caption():
    # $4 is not approved and must be flagged even though $49 (approved) is present.
    report = check_instagram_caption("Get $4 off before the $49 tune-up ends.", CFG)
    unapproved = [v for v in report.violations if v.rule_id == "ig:unapproved_offer"]
    assert len(unapproved) == 1
    assert "$4" in unapproved[0].message


def test_single_decimal_dollar_is_blocked():
    report = check_instagram_caption("Special: $49.5 off your repair.", CFG)
    assert "ig:unapproved_offer" in _ids(report)


def test_trailing_comma_on_approved_offer_passes():
    report = check_instagram_caption("Only $49, book now before winter.", CFG)
    assert "ig:unapproved_offer" not in _ids(report)


def test_singular_corporate_term_is_blocked():
    report = check_instagram_caption("We offer a garage door solution.", CFG)
    assert "ig:corporate_term" in _ids(report)
