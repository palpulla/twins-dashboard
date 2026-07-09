import frontmatter
from scripts.approve_ig import approve_draft, reject_draft


def _mk(pending, name="2026-07-06_proof.md"):
    p = pending / name
    p.write_text("---\nslot: proof\ndate: '2026-07-06'\n---\nCaption body.")
    return p


def test_approve_moves_to_approved(tmp_path):
    pending = tmp_path / "pending"; pending.mkdir()
    approved = tmp_path / "approved"
    f = _mk(pending)
    out = approve_draft(f, approved)
    assert not f.exists() and out.exists()
    assert frontmatter.loads(out.read_text())["approved"] is True


def test_reject_records_reason(tmp_path):
    pending = tmp_path / "pending"; pending.mkdir()
    rejected = tmp_path / "rejected"
    f = _mk(pending)
    out = reject_draft(f, rejected, "wrong city")
    assert not f.exists() and out.exists()
    post = frontmatter.loads(out.read_text())
    assert post["rejected_reason"] == "wrong city"
