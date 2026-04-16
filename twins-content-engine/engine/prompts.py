"""Prompt builders for each content format."""
from __future__ import annotations

from typing import Any, Optional

from engine.config import BrandConfig, PillarsConfig, RulesConfig, ServiceAreaConfig


_SYSTEM_BASE = """You are a content writer for Twins Garage Doors, a local garage door repair company in Madison, Wisconsin.

Voice: {persona}
Structure: {structure}
Reading level: max grade {reading_level}.

Hard rules — violating any of these invalidates the output:
- No copywriter clichés: no "whether you're", "game-changer", "elevate your", "journey", "in today's fast-paced", "nestled in", "seamlessly", "at the end of the day".
- No em-dash pair used as dramatic pause ("— and here's why —"). Single em-dashes are fine.
- No three-adjective lists like "Fast. Reliable. Affordable." Write real sentences instead.
- Every piece must include at least one: specific number, named neighborhood/town, concrete timeframe, or concrete detail (e.g., "10-ft torsion spring", "LiftMaster 8500W").
- No generic superiority claims ("we're the best", "#1 in", "trusted by many").
- Exactly ONE call-to-action per piece. Do not stack CTAs.
- When a phone number is included, use exactly: {phone}
- Use these brand proof points when natural: {proof_points}.
- Avoid these words: {avoid_terms}.

Tone check: a real Madison tech picks up the phone at 2pm on a Tuesday, tired, professional, slightly dry. Write like that.
"""


def _system_prompt(ctx: dict[str, Any]) -> str:
    brand: BrandConfig = ctx["brand"]
    rules: RulesConfig = ctx["rules"]
    return _SYSTEM_BASE.format(
        persona=rules.voice.persona,
        structure=rules.voice.structure,
        reading_level=rules.voice.reading_level_max,
        phone=brand.phone,
        proof_points="; ".join(brand.signature_proof_points) or "(none provided)",
        avoid_terms=", ".join(brand.voice_terms_to_avoid) or "(none)",
    )


_FORMAT_USER_TEMPLATES: dict[str, str] = {
    "caption": """Write a single Instagram/Facebook caption for a Twins Garage Doors post.

Cluster: {cluster_name}
Pillar: {pillar_name}
Funnel stage: {funnel_stage} (CTA type: {cta_type})
Source search query (real homeowner phrasing): "{query_text}"
Target town to mention: {target_town} (mention exactly once, naturally)
Phone number to use in CTA: {phone}

Structure:
1. First line: mirror the search query — same anxious phrasing, question mark OK.
2. 2–3 short lines of practical info. No fluff. No "picture this".
3. One CTA line with the phone number.
4. One hashtag block: max 5 hashtags, first three must be local (e.g., #MadisonWI, #{target_town_hashtag}).

Return ONLY the caption text + hashtags. No YAML frontmatter, no commentary.
""",

    "video_script": """Write a 20-second vertical video script for Twins Garage Doors.

Cluster: {cluster_name}
Pillar: {pillar_name}
Funnel stage: {funnel_stage} (CTA type: {cta_type})
Source search query: "{query_text}"
Target town to reference: {target_town}{neighborhood_hint}

Required structure — use this EXACT block layout:

HOOK (0:00-0:02):
[6-8 words. Must reference a specific symptom, number, or neighborhood. Do NOT start with "Whether", "Are you", "Did you know", "In today's".]

PROBLEM (0:02-0:08):
[3-4 short voiceover lines. No fluff. What's actually happening to the homeowner.]

AUTHORITY (0:08-0:15):
[One specific proof line. Example: "Torsion springs are rated for 10,000 cycles. After 13 years in Madison, I've replaced hundreds."]

CTA (0:15-0:20):
[One action. Phone number on screen.]

SHOT LIST:
- 0:00-0:02: [visual]
- 0:02-0:08: [visual]
- 0:08-0:15: [visual]
- 0:15-0:20: [phone number card]

Return the blocks only, no YAML frontmatter.
""",

    "gbp_post": """Write a Google Business Profile post for Twins Garage Doors.

Cluster: {cluster_name}
Pillar: {pillar_name}
Funnel stage: {funnel_stage} (CTA type: {cta_type})
Source search query: "{query_text}"
Target town: {target_town} — must appear in the first 10 words.

Constraints:
- 900–1400 characters total.
- Two paragraphs, 6–10 sentences total.
- Must mention {target_town} 2–3 times across the whole post (natural, not in consecutive sentences).
- One CTA at the end matching {cta_type}. Include phone number if call-based CTA.

Return ONLY the post body, no frontmatter.
""",

    "faq": """Write 3 FAQ entries for Twins Garage Doors in schema.org-friendly Markdown.

Cluster: {cluster_name}
Pillar: {pillar_name}
Funnel stage: {funnel_stage}
Source search query: "{query_text}"
Target town: {target_town}

Constraints:
- Each Q is a real homeowner question — use the source query as Q1 verbatim if it's already question-form, otherwise reformulate it naturally.
- Each A is 2-4 sentences, FACTUAL and concise. Include the business entity ("Twins Garage Doors") and "{target_town}" (or "Madison") at least twice across the 3 answers — this is critical for AI-search retrieval.
- Include one specific number, price range, or timeframe in at least one answer.

Format:
## Q: [question]
**A:** [answer]

## Q: [question]
**A:** [answer]

## Q: [question]
**A:** [answer]

Return ONLY the Q/A blocks, no frontmatter.
""",

    "blog_snippet": """Write a 250–400 word blog snippet for Twins Garage Doors.

Cluster: {cluster_name}
Pillar: {pillar_name}
Funnel stage: {funnel_stage} (CTA type: {cta_type})
Source search query: "{query_text}"
Target town: {target_town}

Structure:
1. H1 in question form that mirrors the search query.
2. First paragraph: direct answer in 2–3 sentences. First sentence MUST contain a specific number, timeframe, or price range.
3. Three short sections with H2 subheads, 1 paragraph each.
4. Final paragraph: one CTA matching {cta_type}.

Local mentions: reference {target_town} or "Madison" 3–5 times across the piece (spread out, never consecutive sentences).
Use these placeholder internal link tags where a link would make sense: [[link:service/<slug>]]

Return ONLY the Markdown body, no YAML frontmatter.
""",
}


def _town_hashtag(town_display: str) -> str:
    return town_display.replace(" ", "")


def build_generation_prompt(
    *,
    content_format: str,
    query_text: str,
    cluster_name: str,
    pillar_name: str,
    funnel_stage: str,
    target_town_display: str,
    target_neighborhood: Optional[str],
    ctx: dict[str, Any],
) -> tuple[str, str]:
    """Return (system, user) prompt strings for the given format."""
    brand: BrandConfig = ctx["brand"]
    rules: RulesConfig = ctx["rules"]
    cta_type = rules.cta_rules.funnel_stage_mapping.get(funnel_stage, "learn_more")

    template = _FORMAT_USER_TEMPLATES[content_format]
    neighborhood_hint = (
        f" Include the neighborhood '{target_neighborhood}' once if natural."
        if target_neighborhood else ""
    )
    user = template.format(
        cluster_name=cluster_name,
        pillar_name=pillar_name,
        funnel_stage=funnel_stage,
        cta_type=cta_type,
        query_text=query_text,
        target_town=target_town_display,
        target_town_hashtag=_town_hashtag(target_town_display),
        neighborhood_hint=neighborhood_hint,
        phone=brand.phone,
    )
    return _system_prompt(ctx), user


def build_regeneration_prompt(
    *,
    content_format: str,
    previous_output: str,
    critique_lines: list[str],
    query_text: str,
    cluster_name: str,
    pillar_name: str,
    funnel_stage: str,
    target_town_display: str,
    target_neighborhood: Optional[str],
    ctx: dict[str, Any],
) -> tuple[str, str]:
    """Build a prompt that includes the prior output and the critique lines."""
    system, base_user = build_generation_prompt(
        content_format=content_format,
        query_text=query_text,
        cluster_name=cluster_name,
        pillar_name=pillar_name,
        funnel_stage=funnel_stage,
        target_town_display=target_town_display,
        target_neighborhood=target_neighborhood,
        ctx=ctx,
    )
    critique = "\n".join(critique_lines)
    user = (
        f"{base_user}\n\n"
        f"=== YOUR PREVIOUS ATTEMPT ===\n{previous_output}\n=== END PREVIOUS ===\n\n"
        f"That attempt failed these rule checks:\n{critique}\n\n"
        f"Rewrite from scratch — do not minimally edit. Address every listed issue."
    )
    return system, user
