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


def test_uppercase_kind_prefix_still_matches(tmp_path):
    (tmp_path / "Completed_verona_x.JPG").write_bytes(b"x")
    found = scan_inbox(tmp_path)
    assert len(found) == 1 and found[0].asset.kind == "completed_job"
    assert found[0].city == "Verona"


def test_city_normalizes_to_canonical_spelling(tmp_path):
    (tmp_path / "completed_mc-farland_x.jpg").write_bytes(b"x")
    (tmp_path / "truck_sun-prairie.jpg").write_bytes(b"x")
    found = scan_inbox(tmp_path, known_cities=["McFarland", "Sun Prairie"])
    cities = {a.city for a in found}
    assert cities == {"McFarland", "Sun Prairie"}


def test_videos_are_ignored(tmp_path):
    # Photo-first program: reels are Plan 3, so inbox videos must never
    # become RealAssets (they'd otherwise reach the image-post publish path).
    (tmp_path / "completed_verona_clip.mp4").write_bytes(b"x")
    (tmp_path / "truck_madison_pan.mov").write_bytes(b"x")
    (tmp_path / "completed_verona_photo.jpg").write_bytes(b"x")
    found = scan_inbox(tmp_path)
    assert len(found) == 1
    assert found[0].asset.path.endswith("completed_verona_photo.jpg")
