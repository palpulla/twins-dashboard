# tests/test_ig_assets.py
from engine.ig_assets import scan_inbox


def test_scan_maps_kinds_and_cities(tmp_path):
    (tmp_path / "completed_verona_spring.jpg").write_bytes(b"x")
    (tmp_path / "before_after_sun-prairie_door.png").write_bytes(b"x")
    (tmp_path / "truck_madison.jpg").write_bytes(b"x")
    (tmp_path / "notes.txt").write_bytes(b"x")          # ignored: not an image
    (tmp_path / "random.jpg").write_bytes(b"x")         # ignored: no known kind prefix
    found = scan_inbox(tmp_path)
    kinds = {(a.asset.kind, a.city) for a in found}
    assert ("completed_job", "Verona") in kinds
    assert ("before_after", "Sun Prairie") in kinds
    assert ("truck", "Madison") in kinds
    assert len(found) == 3


def test_empty_inbox_is_fine(tmp_path):
    assert scan_inbox(tmp_path) == []
