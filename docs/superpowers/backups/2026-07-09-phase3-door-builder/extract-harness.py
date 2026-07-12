#!/usr/bin/env python3
"""Generate local-harness.html FROM twins-door-builder-snippet.php (no drift).

Extracts the <style id="twxdb-css"> block and the single <script> block
verbatim from the snippet file, wraps them in a standalone page with the
--tw-navy/--tw-yellow tokens, data-region="main" and a httpbin test endpoint,
plus a harness-only fetch shim that records POST bodies into
window.__twxdbPosts for test inspection.

Run:  python3 extract-harness.py   (from this directory)
"""
import pathlib
import re

HERE = pathlib.Path(__file__).resolve().parent
SRC = HERE / "twins-door-builder-snippet.php"
OUT = HERE / "local-harness.html"

src = SRC.read_text(encoding="utf-8")

css_m = re.search(r'<style id="twxdb-css">(.*?)</style>', src, re.S)
js_m = re.search(r"<script>(.*?)</script>", src, re.S)
if not css_m or not js_m:
    raise SystemExit("could not find style/script blocks in snippet file")

html = f"""<!doctype html>
<!-- GENERATED FILE — do not edit by hand.
     Built by extract-harness.py from twins-door-builder-snippet.php
     (CSS + JS below are verbatim copies; regenerate after any snippet edit). -->
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>twxdb local harness</title>
<style>
:root {{ --tw-navy: #022751; --tw-yellow: #FBBD04; }}
body {{ font-family: Montserrat, Arial, sans-serif; margin: 0; padding: 24px; background: #fff; }}
</style>
</head>
<body>
<div id="twxdb" class="twxdb" data-region="main" data-endpoint="https://httpbin.org/status/200"></div>
<style id="twxdb-css">{css_m.group(1)}</style>
<script>
/* harness-only shim: record POST bodies for test inspection */
(function () {{
	var of = window.fetch;
	window.fetch = function (u, o) {{
		try {{
			if (o && o.method === 'POST') {{
				window.__twxdbPosts = window.__twxdbPosts || [];
				window.__twxdbPosts.push({{ url: String(u), body: o.body }});
			}}
		}} catch (e) {{}}
		return of.apply(window, arguments);
	}};
}})();
</script>
<script>{js_m.group(1)}</script>
</body>
</html>
"""

OUT.write_text(html, encoding="utf-8")
print(f"wrote {OUT.name}: {len(html)} bytes")
