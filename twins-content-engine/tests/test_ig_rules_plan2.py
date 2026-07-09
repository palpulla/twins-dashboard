from pathlib import Path
from engine.config import load_instagram_config
from engine.ig_rules import check_instagram_caption, check_instagram_draft

CFG = load_instagram_config(Path(__file__).resolve().parents[1] / "config" / "instagram.yaml")


def _ids(report):
    return {v.rule_id for v in report.violations}


def test_out_of_area_city_blocked():
    assert "ig:out_of_area" in _ids(check_instagram_caption("Now serving Rockford and Chicago.", CFG))


def test_wisconsin_cities_pass():
    r = check_instagram_caption("Broken spring repair in Sun Prairie today.", CFG)
    assert r.passed, r.violations


def test_street_address_blocked():
    assert "ig:private_info" in _ids(check_instagram_caption("Job done at 4128 Maple Ave for a happy customer.", CFG))


def test_commercial_content_blocked():
    assert "ig:commercial" in _ids(check_instagram_caption("We repair commercial garage door systems and loading dock equipment.", CFG))


def test_offer_value_in_wrong_phrase_blocked():
    # $49 is an approved VALUE but "$49 off" is not an approved PHRASE
    assert "ig:unapproved_offer_phrase" in _ids(check_instagram_caption("This week only: $49 off any repair.", CFG))


def test_offer_value_in_approved_phrase_passes():
    r = check_instagram_caption("Book the $49 tune-up before winter.", CFG)
    assert r.passed, r.violations


def test_adjacent_approved_phrase_does_not_launder_bad_offer():
    r = check_instagram_caption("Our $49 tune-up. Also $49 off any repair.", CFG)
    assert "ig:unapproved_offer_phrase" in _ids(r)


def test_chicagoland_blocked():
    assert "ig:out_of_area" in _ids(check_instagram_caption("Serving the Chicagoland area.", CFG))


def test_whitespace_and_cents_variants_of_approved_offer_pass():
    assert check_instagram_caption("Get our $49  tune-up today.", CFG).passed
    assert check_instagram_caption("Book the $49.00 tune-up.", CFG).passed


def test_draft_check_enforces_cta_whitelist():
    bad = check_instagram_draft("Broken spring repair in Verona.", "DM us on WhatsApp!!", CFG)
    good = check_instagram_draft("Broken spring repair in Verona.", CFG.slot_cta["proof"], CFG)
    assert "ig:cta" in _ids(bad)
    assert good.passed, good.violations
