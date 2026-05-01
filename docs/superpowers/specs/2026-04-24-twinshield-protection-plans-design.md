# TwinShield Protection Plans — Design Spec

**Date:** 2026-04-24
**Owner:** Daniel / Twins Garage Doors
**Status:** Approved design, ready for HCP + website implementation
**Replaces:** "Carefree Club" (existing single-tier program — 24 active members untouched at this time)

---

## 1. Goals

Three goals, in order of weight:

1. **Recurring revenue (MRR)** — predictable monthly cashflow from a growing base
2. **LTV / retention** — keep customers from drifting to competitors; capture the next several jobs
3. **Tech closing tool** — give techs a same-day "yes" hook at the door to close more repairs

Membership is sold primarily by techs at the end of a residential repair visit. Three plans on the website (anchor effect), but techs pitch **Priority first** — Premier as upsell, Essential only if customer pushes back on price.

---

## 2. Pricing Baseline (Twins, today)

These numbers drive every number in the plan. Re-validate before launch:

| Item | Twins price |
|---|---|
| Service call / diagnostic fee | **$0 (free)** |
| Standalone tune-up & inspection | $49 |
| Avg repair ticket (springs, opener, rollers) | $695 |
| Avg new opener install | ~$1,100 |
| Avg new garage door install | ~$5,000 |

**Implication:** any "service call discount" benefit is dead weight (it's already free). The plan must derive value from real things — tune-ups, repair discounts, scheduling priority, retention perks (price-lock, warranty extensions), and equipment credits.

---

## 3. The Three Plans

### TwinShield Essential

**Tagline:** Core protection with immediate savings and preventive care.
**Best for:** Homeowners who want basic protection, annual maintenance, and simple savings.

**Price:** **$12.99/mo** or **$129/yr** (annual saves $27 — about 2 months free)

**Includes:**
- 1 annual garage door tune-up & 25-point safety inspection (covers up to 2 doors)
- 5% off all qualifying garage door and opener repairs
- Priority scheduling within 48 business hours
- Free lubricant + remote/keypad battery replacement during tune-up
- Equipment upgrade credit toward qualifying new doors or openers
- Transferable to next homeowner if you sell

**Equipment credit:** $50 after 12 active months, up to $150 lifetime per home.

**Short description (for website):**
The simple, affordable way to maintain your garage door system, reduce future repair costs, and catch small issues before they become expensive problems.

---

### TwinShield Priority ⭐ Most Popular

**Tagline:** Stronger savings, faster scheduling, and the smartest long-term value.
**Best for:** Most homeowners — the default recommendation.

**Price:** **$18.99/mo** or **$199/yr** (annual saves $29 — about 1.5 months free)

**Includes everything in Essential, plus:**
- 10% off all qualifying repairs (vs. 5%)
- Priority scheduling within 24 business hours
- **Repair Pricing Lock** — Twins' current repair price book is honored for you as long as your membership is active, even if rates go up
- Free safety sensor / safety eye replacement if needed during tune-up
- Stronger equipment upgrade credit

**Equipment credit:** $100 after 12 active months, up to $300 lifetime per home.

**Short description:**
The plan most homeowners pick. Stronger repair savings, faster scheduling, and a Repair Pricing Lock that protects you from future Twins price increases for as long as you're a member.

---

### TwinShield Premier

**Tagline:** Maximum protection and the strongest long-term value.
**Best for:** Homes with older garage door systems, heavy-use doors, multiple doors, or anyone who wants the highest level of convenience and protection.

**Price:** **$27.99/mo** or **$279/yr** (annual saves $57 — about 2 months free)

**Includes everything in Priority, plus:**
- 2 garage door tune-ups & safety inspections per year (spring + fall)
- 15% off all qualifying repairs
- Same-day priority scheduling when available
- **Lifetime spring warranty extension** on Twins-installed springs (active members only)
- 50% off opener surge protection device + install
- Multi-door coverage included up to **4 doors** per home (vs. 2 on other tiers)
- Premium equipment upgrade credit

**Equipment credit:** $200 after 12 active months, up to $500 lifetime per home.

**Short description:**
The most complete protection package, the highest convenience level, and the strongest long-term value toward future garage door or opener upgrades.

---

## 4. Multi-Door Pricing

Doors covered by default:
- Essential / Priority: up to **2 doors** per home
- Premier: up to **4 doors** per home

**Per additional door:** **+$25/yr** (or +$2.50/mo)

Examples:
- 3-door home on Priority: $199 + $25 = **$224/yr**
- 5-door home on Premier: $279 + $25 = **$304/yr**
- 3-door home on Essential: $129 + $25 = **$154/yr**

---

## 5. What's NOT Covered (under any tier)

For the public FAQ, not just the legal terms:

- Commercial, industrial, or high-cycle doors (residential plans only)
- Oversized residential doors (> 12 ft wide or > 20 ft tall)
- Pedestrian / man doors / side-entry doors (not garage doors)
- Hand-chain or manual hoist systems
- Pre-existing damage documented at the first tune-up — Twins will repair it, but the membership repair discount does not apply to the pre-existing item

---

## 6. Sales Positioning

### Website (3 plans visible, side-by-side)

Display all three. Mark Priority "Most Popular." Show Premier on the right (anchor). This is the decoy/anchor effect — Premier's existence makes Priority look like the obvious value choice.

### Tech sales script (sell Priority first)

Default opener:

> "We have a protection plan that helps you avoid breakdowns and save on future repairs. The one most homeowners pick is **Priority — $199 a year**. It includes your annual tune-up, 10% off any future repairs, priority scheduling, and a Repair Pricing Lock so your future repair prices are protected even if our rates go up. There's also a **Premier** option if you want two tune-ups a year and the lifetime spring warranty."

Only mention **Essential** if the customer pushes back on price:

> "If $199 is more than you want to spend, we have **Essential at $129** that still gives you the tune-up and 5% off future repairs."

**Do NOT** open with "we have three plans, let me walk you through all of them." That tanks conversion.

---

## 7. Unit Economics (margin sanity check)

Assumptions:
- Loaded labor: ~$60/hr (tech wage + benefits + truck overhead)
- Tune-up time: 45 min on-site + 30 min drive ≈ 1.25 hr → **~$75 labor**
- Tune-up parts (lube + battery): ~$5
- **Total tune-up COGS: ~$80**
- Members average ~0.4 incremental repairs/year at $695 each
- ~5% of members buy a new door/opener per year (triggering equipment credit)

| Tier | Annual price | Tune-up cost | Expected repair-discount cost | Expected equipment credit cost | Net margin | Margin % |
|---|---|---|---|---|---|---|
| Essential | $129 | $80 | $13.90 (5% × 0.4 × $695) | $2.50 | **$32.60** | ~25% |
| Priority | $199 | $85 | $27.80 (10% × 0.4 × $695) | $5.00 | **$81.20** | ~41% |
| Premier | $279 | $160 | $41.70 (15% × 0.4 × $695) | $10.00 | **$67.30** | ~24% |

**Priority is the money tier** with ~41% gross margin. That's the right outcome — it's also your sales-script default.

**Essential is the budget fallback** — ~25% margin is healthy enough that any sale is a win, and Essential members tend to trade up to Priority at renewal once they see the Repair Pricing Lock benefit at neighbors' homes.

**Why Premier's margin holds up despite 2 tune-ups + 15% off:**
- Premier customers have older systems → higher equipment-credit redemption → equipment credit is a loss-leader for $1,100 opener replacements and $5,000 door replacements
- Premier customers have multi-door homes → using the +$25/door upcharge restores margin
- Premier exists primarily as the anchor that makes Priority look reasonable

---

## 8. Updated Membership Terms

This replaces your original 12-section terms document. Key changes:
- **Removed all references to "service call fee" benefits** (because Twins service calls are free, those benefits were meaningless)
- **Added pre-existing damage carve-out** to repair discounts
- **Added Repair Pricing Lock** language at Priority+
- **Added lifetime spring warranty extension** language at Premier
- **Added multi-door pricing** rules

---

### TwinShield Membership Terms

#### 1. Active Membership Required
TwinShield benefits are available only while the membership is active and in good standing. If the membership is canceled, expired, unpaid, or past due, all benefits, discounts, equipment credits, Repair Pricing Lock, and warranty extensions may no longer apply.

#### 2. Residential Use Only
TwinShield plans are intended for standard residential garage door systems. Commercial doors, industrial doors, high-cycle commercial systems, oversized residential doors (over 12 ft wide or 20 ft tall), pedestrian doors, and specialty systems may be excluded or priced separately.

#### 3. Tune-Up and Safety Inspection
Each included tune-up covers a standard garage door maintenance and safety inspection visit. The tune-up may include inspection of springs, cables, rollers, hinges, tracks, opener operation, safety sensors, balance, force settings, visible wear, lubrication where appropriate, and general system condition.

The tune-up does not include replacement parts, repairs, labor for repairs, new equipment, or correction of existing issues unless separately approved by the customer. Tune-ups for additional doors beyond the included count are billed at the per-door upcharge (see Section 9).

#### 4. Repair Discounts
Repair discounts apply to qualifying garage door and opener repair work performed by Twins Garage Doors at Essential 5%, Priority 10%, and Premier 15%.

Discounts do **not** apply to:
- New garage door installations
- New opener installations unless specifically approved
- Pre-existing damage documented during the first tune-up or inspection
- Already-discounted estimates, coupons, or promotional offers
- Warranty work
- Insurance claims
- Commercial work
- After-hours or emergency service fees
- Diagnostic-only visits
- Previous invoices or past work

Discounts cannot be stacked or combined with other offers unless approved by Twins Garage Doors.

#### 5. Priority Scheduling
Priority scheduling means TwinShield members receive preferred scheduling when available:
- Essential: within 48 business hours
- Priority: within 24 business hours
- Premier: same-day when available

Priority scheduling does not guarantee same-day service, emergency response, or a specific appointment time. Availability depends on technician schedule, service area, weather, parts availability, and existing commitments.

#### 6. Repair Pricing Lock (Priority and Premier only)
Active Priority and Premier members are protected from future Twins repair pricing increases. The Twins repair price book in effect at the time of enrollment is honored on all qualifying repair visits for as long as the membership remains continuously active. If a covered repair item exists in both the locked price book and the current price book, members are charged the lower of the two.

If the membership lapses, is canceled, or is reinstated after a gap, the locked pricing resets to whatever Twins pricing is in effect at the time of the next visit.

The Repair Pricing Lock applies to repair work only. It does not apply to new installations, equipment purchases, or any item not present in the locked price book.

#### 7. Lifetime Spring Warranty Extension (Premier only)
Active Premier members receive a lifetime warranty extension on torsion springs originally installed by Twins Garage Doors. This extension applies for as long as the Premier membership is continuously active and the property is owned by the enrolling household (or transferred per Section 11).

The extension does not apply to springs not installed by Twins, springs damaged by misuse or external events (vehicle impact, fire, flood, vandalism), or commercial-use systems.

#### 8. Equipment Credit
Equipment credits may be used toward qualifying new garage doors, garage door openers, or major installed equipment purchased and installed by Twins Garage Doors.

Equipment credits:
- Have no cash value
- Are not refundable
- Are not transferable except as described in Section 11
- Cannot be applied to past work
- Cannot be used toward service calls, tune-ups, small repairs, or diagnostic fees
- Cannot be combined with certain promotions or discounts unless approved
- Apply only after the required 12-month active membership period has been met

Equipment credits are tied to the property/home, not to a separate customer account.

#### 9. Multi-Door Coverage
Essential and Priority cover up to 2 garage doors per home. Premier covers up to 4 garage doors per home.

Each additional door beyond the included count is billed at **+$25/year** (or +$2.50/month) and receives all the same benefits as included doors.

#### 10. Surge Protection (Premier only)
Premier members receive 50% off opener surge protection device and installation when purchased from Twins Garage Doors. Surge protection coverage applies only to qualifying surge protection products. It does not guarantee coverage for all electrical damage, lightning damage, power-grid events, pre-existing opener issues, improper wiring, water damage, or equipment not installed or serviced by Twins Garage Doors.

#### 11. Cancellations
Monthly memberships may be canceled by the customer at any time. Benefits stop once the membership is canceled or no longer active.

Annual memberships are prepaid and may be non-refundable once the included tune-up, inspection, repair discount, equipment credit, or other membership benefit has been used.

Twins Garage Doors may cancel a membership for misuse, nonpayment, repeated scheduling abuse, commercial use under a residential plan, or other violations of these terms.

#### 12. Non-Transferability
TwinShield memberships are tied to the enrolled property. Memberships may be transferred to the next homeowner of the same property at no charge (a benefit highlighted in your home listing). Memberships may not be transferred between separate properties or to unrelated customers without approval from Twins Garage Doors.

#### 13. No Guarantee Against Future Repairs
TwinShield helps reduce risk through preventive maintenance and member benefits, but does not guarantee that the garage door system will not break, fail, require repairs, or need replacement in the future.

#### 14. Final Approval
All benefits, discounts, equipment credits, Repair Pricing Lock decisions, warranty extension determinations, and eligibility decisions are subject to final approval by Twins Garage Doors.

---

## 9. HCP Setup Instructions

In Housecall Pro → Settings → Memberships:

### Create three Membership types:

**Membership #1**
- Name: `TwinShield Essential`
- Billing: offer both monthly ($12.99) and annual ($129)
- Auto-renew: yes
- Included visits: 1/year (tune-up)
- Discount: 5% off qualifying repairs
- Custom field: `Equipment Credit Balance` (currency, default $0)
- Custom field: `Equipment Credit Lifetime Cap` ($150)

**Membership #2**
- Name: `TwinShield Priority`
- Billing: monthly ($18.99) or annual ($199)
- Auto-renew: yes
- Included visits: 1/year (tune-up)
- Discount: 10% off qualifying repairs
- Custom field: `Equipment Credit Balance` ($0)
- Custom field: `Equipment Credit Lifetime Cap` ($300)
- Custom field: `Pricing Lock Enrollment Date` (date — captured at signup; combined with the dated price book in your records, this defines what pricing applies)

**Membership #3**
- Name: `TwinShield Premier`
- Billing: monthly ($27.99) or annual ($279)
- Auto-renew: yes
- Included visits: 2/year (tune-ups, ~6 months apart)
- Discount: 15% off qualifying repairs
- Custom field: `Equipment Credit Balance` ($0)
- Custom field: `Equipment Credit Lifetime Cap` ($500)
- Custom field: `Pricing Lock Enrollment Date` (date)
- Custom field: `Spring Warranty Extension Active` (yes/no — auto-yes on enrollment)

### Multi-door upcharge:
HCP doesn't natively support per-door upcharges. Two options:
- **Option A (simplest):** create variants like `TwinShield Priority — 3 Doors`, `TwinShield Priority — 4 Doors` etc., priced accordingly. ~3–5 SKUs total.
- **Option B (cleaner):** Keep the 3 base plans, manually add an "Additional Door Coverage" line item ($25/yr or $2.50/mo) per extra door at enrollment.

Recommend **Option B** — fewer SKUs to maintain.

### Tech-side workflow:
- Membership sale gets attached to the closing job
- `sold_by_tech_id` populates from the assigned tech (already wired into your dashboard)
- Tune-up appointments auto-schedule via HCP membership scheduler

### Dashboard-side:
The existing `useMembershipData` hook already tracks plan name, price, status, renewal date, and tech attribution. No code changes needed for the rebrand — just update plan names in HCP. The dashboard's `monthlyData`, `mrr`, and tech leaderboard will populate automatically as TwinShield enrollments come in.

---

## 10. Website Copy (drop-in)

Use this as the copy block for the new `/protection-plans` page (or rename the existing membership page).

### Hero
**Headline:** `TwinShield Protection Plans`
**Subhead:** `Skip the breakdowns. Lock in better pricing. Get priority service when you need it most.`

### Plan comparison block (3 cards, side by side)

```
┌───────────────────┐  ┌──────────────────────┐  ┌───────────────────┐
│ TwinShield        │  │ TwinShield ⭐         │  │ TwinShield        │
│ Essential         │  │ Priority             │  │ Premier           │
│                   │  │ MOST POPULAR         │  │                   │
│ $12.99/mo         │  │ $18.99/mo            │  │ $27.99/mo         │
│ or $129/year      │  │ or $199/year         │  │ or $279/year      │
│                   │  │                      │  │                   │
│ ✓ Annual tune-up  │  │ Everything in        │  │ Everything in     │
│ ✓ 5% off repairs  │  │ Essential, plus:     │  │ Priority, plus:   │
│ ✓ Priority sched. │  │                      │  │                   │
│   (48hr)          │  │ ✓ 10% off repairs    │  │ ✓ 2 tune-ups/yr   │
│ ✓ Free lube +     │  │ ✓ 24-hour priority   │  │ ✓ 15% off repairs │
│   batteries at    │  │   scheduling         │  │ ✓ Same-day        │
│   tune-up         │  │ ✓ Repair Pricing     │  │   scheduling      │
│ ✓ $50 equipment   │  │   LOCK               │  │ ✓ Lifetime spring │
│   credit/yr       │  │ ✓ Free safety sensor │  │   warranty        │
│ ✓ Transferable    │  │   replacement at     │  │   extension       │
│                   │  │   tune-up            │  │ ✓ 50% off opener  │
│ [Choose Plan]     │  │ ✓ $100 equipment     │  │   surge protect.  │
│                   │  │   credit/yr          │  │ ✓ Up to 4 doors   │
│                   │  │                      │  │ ✓ $200 equipment  │
│                   │  │ [Choose Plan]        │  │   credit/yr       │
│                   │  │                      │  │                   │
│                   │  │                      │  │ [Choose Plan]     │
└───────────────────┘  └──────────────────────┘  └───────────────────┘
```

### "What's not covered" block (FAQ-style)

> **Q: What's not covered by my TwinShield membership?**
> Commercial or industrial doors. Oversized residential doors (over 12 ft wide or 20 ft tall). Pedestrian or side-entry doors (not garage doors). Hand-chain or manual hoist systems. Pre-existing damage already documented at your first tune-up.

> **Q: I have a 3-car garage. How does that work?**
> Essential and Priority cover up to 2 doors. Premier covers up to 4. Each additional door is +$25/year ($2.50/month) and gets all the same benefits.

> **Q: What happens if I cancel?**
> Monthly plans can be canceled anytime. Annual plans are prepaid and non-refundable once a benefit has been used. All membership benefits stop on cancellation, including the Repair Pricing Lock and lifetime spring warranty extension.

> **Q: Can I transfer my membership if I sell my house?**
> Yes — TwinShield transfers to the next homeowner at no charge. Highlight it in your home listing.

### Trust block (below pricing)

> 🛠 **Twins Garage Doors — Madison's most trusted garage door team.**
> Locally owned. Background-checked techs. Same-day service across Dane County.

---

## 11. Implementation Checklist

- [ ] Daniel: validate the unit-economics assumptions in Section 7 (especially loaded labor cost and avg repairs/yr per member)
- [ ] Daniel: create the three Memberships in HCP per Section 9
- [ ] Daniel: configure HCP custom fields for equipment credit balance, Pricing Lock Enrollment Date, and spring warranty flag — and **freeze a dated copy of the Twins repair price book** at launch (the artifact the Repair Pricing Lock references)
- [ ] Claude: add `/protection-plans` page to twinsdash.com (or Twins marketing site, whichever is the customer-facing one)
- [ ] Claude: update website nav to link to the new page
- [ ] Daniel: train techs on the Section 6 sales script before deploying
- [ ] Daniel: add Section 8 terms to the website footer / membership-signup flow
- [ ] Daniel: leave the existing 24 Carefree Club members untouched for now (revisit migration later)

---

## 12. Future considerations (out of scope for this spec)

- Migration plan for the 24 existing Carefree Club members (intentionally deferred)
- Snowbird / vacation pause feature (decided against for launch — too much ops complexity)
- Add-ons: smart opener support, side door coverage, commercial tier (defer until residential program is validated)
- Annual rate review: if Twins raises repair pricing in 2027, calculate the cost of the Repair Pricing Lock benefit and decide whether to keep grandfathering or sunset it for new enrollments only
