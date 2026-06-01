// Builds the Aman internship kickoff package + daily checklist as branded .docx,
// matching the existing Twins Aman/Ivory/Charles doc set (navy + gold, badge, footer).
const fs = require("fs");
const path = require("path");
const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
  Header, Footer, AlignmentType, LevelFormat, HeadingLevel, ImageRun,
  BorderStyle, WidthType, ShadingType, PageNumber,
} = require("docx");

const DIR = __dirname;
const NAVY = "1A2B4E", YELLOW = "F2B705", LIGHT_YELLOW = "FCF1D0";
const GREY_TEXT = "595959", GREY_BORDER = "BFBFBF", ROW_ALT = "FAFAFA";
const CALLOUT = "FFF8E2", GOLD = "F2B705", LIGHT_GRAY = "8C95A4", MUTED = "7A8290";
const border = { style: BorderStyle.SINGLE, size: 4, color: GREY_BORDER };
const borders = { top: border, bottom: border, left: border, right: border };
const cellMargins = { top: 100, bottom: 100, left: 140, right: 140 };
const LOGO = fs.readFileSync(path.join(DIR, "twins_badge.png"));

const sharedStyles = {
  default: { document: { run: { font: "Arial", size: 22 } } },
  paragraphStyles: [
    { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true,
      run: { size: 32, bold: true, font: "Arial", color: NAVY },
      paragraph: { spacing: { before: 320, after: 120 }, outlineLevel: 0 } },
    { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true,
      run: { size: 26, bold: true, font: "Arial", color: NAVY },
      paragraph: { spacing: { before: 240, after: 80 }, outlineLevel: 1 } },
  ],
};
const numberingConfig = { config: [
  { reference: "bullets", levels: [
    { level: 0, format: LevelFormat.BULLET, text: "•", alignment: AlignmentType.LEFT,
      style: { paragraph: { indent: { left: 540, hanging: 270 } } } }] },
  { reference: "nums", levels: [
    { level: 0, format: LevelFormat.DECIMAL, text: "%1.", alignment: AlignmentType.LEFT,
      style: { paragraph: { indent: { left: 540, hanging: 270 } } } }] },
] };
const sectionPage = { page: { size: { width: 12240, height: 15840 },
  margin: { top: 1080, right: 1080, bottom: 1080, left: 1080 } } };

function headerBand() {
  return [
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 80 },
      children: [new ImageRun({ type: "png", data: LOGO, transformation: { width: 170, height: 78 },
        altText: { title: "Twins Garage Doors", description: "logo", name: "Logo" } })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 40 },
      children: [new TextRun({ text: "Twins Garage Doors, LLC", bold: true, color: NAVY, size: 24 })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 120 },
      children: [new TextRun({ text: "2921 Landmark Pl, Suite #206  ·  Madison, WI 53713", color: GREY_TEXT, size: 18 })] }),
    new Paragraph({ spacing: { after: 240 },
      border: { bottom: { style: BorderStyle.SINGLE, size: 12, color: YELLOW, space: 1 } },
      children: [new TextRun("")] }),
  ];
}
function title(text, subtitle) {
  const out = [new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 60 },
    children: [new TextRun({ text, bold: true, color: NAVY, size: 42 })] })];
  if (subtitle) out.push(new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 280 },
    children: [new TextRun({ text: subtitle, italics: true, color: GREY_TEXT, size: 22 })] }));
  return out;
}
const section = (t) => new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun(t)] });
const subsection = (t) => new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun(t)] });
function para(text, opts = {}) {
  return new Paragraph({ spacing: { after: opts.after ?? 120, before: opts.before ?? 0 },
    children: [new TextRun({ text, bold: !!opts.bold, italics: !!opts.italics, color: opts.color, size: opts.size })] });
}
function caption(text) {
  return new Paragraph({ spacing: { after: 140 },
    children: [new TextRun({ text, italics: true, color: GREY_TEXT, size: 20 })] });
}
function bullet(text, ref = "bullets") {
  return new Paragraph({ numbering: { reference: ref, level: 0 }, spacing: { after: 60 },
    children: [new TextRun(text)] });
}
// agenda script line: gold-bordered box of what to say, with [blanks] in gold
function sayLine(parts) {
  const runs = parts.map(([text, o = {}]) => new TextRun({ text, font: "Arial", size: 20,
    bold: o.blank || o.bold, color: o.blank ? GOLD : (o.color || NAVY), italics: o.italics }));
  return new Paragraph({ spacing: { after: 100 }, indent: { left: 360 },
    border: { left: { style: BorderStyle.SINGLE, size: 12, color: GOLD, space: 8 } }, children: runs });
}
function stage(text) {
  return new Paragraph({ spacing: { after: 80 }, indent: { left: 360 },
    children: [new TextRun({ text, italics: true, size: 18, color: LIGHT_GRAY, font: "Arial" })] });
}
function ask(label, text) {
  return new Paragraph({ spacing: { before: 60, after: 100 }, indent: { left: 360 },
    border: { left: { style: BorderStyle.SINGLE, size: 12, color: GOLD, space: 8 } },
    children: [ new TextRun({ text: `${label}: `, bold: true, size: 20, color: NAVY, font: "Arial" }),
      new TextRun({ text, italics: true, size: 20, color: NAVY, font: "Arial" }) ] });
}
function blockHeader(num, text, time) {
  return new Paragraph({ spacing: { before: 240, after: 80 }, children: [
    new TextRun({ text: `${num}.  `, bold: true, size: 26, color: GOLD, font: "Arial" }),
    new TextRun({ text, bold: true, size: 26, color: NAVY, font: "Arial" }),
    new TextRun({ text: `   ~${time}`, italics: true, size: 18, color: MUTED, font: "Arial" }) ] });
}
function paragraphCell(text, opts = {}) {
  return new Paragraph({ spacing: { after: 0 },
    children: [new TextRun({ text, bold: !!opts.bold, color: opts.color, size: opts.size, italics: !!opts.italics })] });
}
function dataTable(widths, header, rows) {
  const headerRow = new TableRow({ tableHeader: true, children: header.map((h, i) => new TableCell({
    borders, width: { size: widths[i], type: WidthType.DXA },
    shading: { fill: NAVY, type: ShadingType.CLEAR }, margins: cellMargins,
    children: [paragraphCell(h, { bold: true, color: "FFFFFF" })] })) });
  const body = rows.map((r, idx) => new TableRow({ children: r.map((c, i) => new TableCell({
    borders, width: { size: widths[i], type: WidthType.DXA },
    shading: idx % 2 ? { fill: ROW_ALT, type: ShadingType.CLEAR } : undefined, margins: cellMargins,
    children: (Array.isArray(c) ? c : [String(c)]).map(line =>
      new Paragraph({ spacing: { after: 0 }, children: [new TextRun(String(line))] })) })) }));
  return new Table({ width: { size: widths.reduce((a, b) => a + b, 0), type: WidthType.DXA },
    columnWidths: widths, rows: [headerRow, ...body] });
}
function quoteBox(lines) {
  const w = 10280;
  const kids = lines.map((ln, i) => new Paragraph({ spacing: { after: i === lines.length - 1 ? 0 : 120 },
    children: [new TextRun({ text: ln, color: NAVY, italics: true, size: 22 })] }));
  return new Table({ width: { size: w, type: WidthType.DXA }, columnWidths: [w],
    rows: [new TableRow({ children: [new TableCell({
      borders: { top: { style: BorderStyle.NONE }, bottom: { style: BorderStyle.NONE },
        right: { style: BorderStyle.NONE }, left: { style: BorderStyle.SINGLE, size: 18, color: GOLD, space: 12 } },
      width: { size: w, type: WidthType.DXA }, shading: { fill: CALLOUT, type: ShadingType.CLEAR },
      margins: { top: 160, bottom: 160, left: 220, right: 200 }, children: kids })] })] });
}
function calloutBox(t, body) {
  const w = 10280;
  return new Table({ width: { size: w, type: WidthType.DXA }, columnWidths: [w],
    rows: [new TableRow({ children: [new TableCell({
      borders: { top: { style: BorderStyle.SINGLE, size: 8, color: YELLOW }, bottom: { style: BorderStyle.SINGLE, size: 8, color: YELLOW },
        left: { style: BorderStyle.SINGLE, size: 8, color: YELLOW }, right: { style: BorderStyle.SINGLE, size: 8, color: YELLOW } },
      width: { size: w, type: WidthType.DXA }, shading: { fill: CALLOUT, type: ShadingType.CLEAR },
      margins: { top: 160, bottom: 160, left: 200, right: 200 }, children: [
        new Paragraph({ spacing: { after: 80 }, children: [new TextRun({ text: t, bold: true, color: NAVY, size: 24 })] }),
        new Paragraph({ spacing: { after: 0 }, children: [new TextRun({ text: body, color: NAVY })] }) ] })] })] });
}
function pageFooter() {
  return new Footer({ children: [
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 0 },
      children: [new TextRun({ text: "Twins Garage Doors, LLC  ·  Internal Operations", color: GREY_TEXT, size: 18 })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 0 },
      children: [ new TextRun({ text: "Page ", color: GREY_TEXT, size: 18 }),
        new TextRun({ children: [PageNumber.CURRENT], color: GREY_TEXT, size: 18 }) ] }) ] });
}
function pageHeader(name) {
  return new Header({ children: [new Paragraph({ alignment: AlignmentType.RIGHT, spacing: { after: 0 },
    children: [new TextRun({ text: `Twins Garage Doors  ·  ${name}`, color: GREY_TEXT, size: 18 })] })] });
}

// =========================================================
// DOC A: Kickoff Package
// =========================================================
function buildKickoff() {
  const c = [];
  c.push(...headerBand());
  c.push(...title("Internal Operations Internship", "Kickoff Package  ·  Aman Kharga"));
  c.push(para("This package runs the kickoff call and sets up Aman's first 30 days. It turns the senior Internal Operations Manager role into a focused, part-time internship starting point (about 20 hours per week, remote, $30 per hour), while keeping the long-term path to ownership and a private equity exit clear. SOPs already exist; the work is to audit, organize, and operationalize them, not write them from scratch.", { after: 160 }));

  // Agenda
  c.push(section("The 45-Minute Kickoff Agenda"));
  c.push(para("How to use this: lines in gold-bordered boxes are what you say. Gold [blanks] are yours to fill in. Italic gray lines are stage directions for you, not for him.", { italics: true, color: GREY_TEXT, size: 20 }));
  c.push(dataTable([1000, 7280, 2000], ["#", "Block", "Time"], [
    ["1", "Frame: the seat vs. the internship", "5 min"],
    ["2", "How we work + what I need from you", "5 min"],
    ["3", "Why PE exit readiness matters (early)", "5 min"],
    ["4", "Your first two projects", "12 min"],
    ["5", "Cadence, tools, accountability", "8 min"],
    ["6", "Your week-one deliverable", "5 min"],
    ["7", "The path: internship to ownership", "3 min"],
    ["8", "Gut-check and close", "2 min"],
  ]));

  c.push(blockHeader("1", "Frame: the seat vs. the internship", "5 min"));
  c.push(stage("Say this first. It takes the pressure off and sets the real expectation."));
  c.push(sayLine([["“The role description you read is the senior seat. That is where this can go. It is not what I am asking you to own this summer.”"]]));
  c.push(sayLine([["“Right now you are an intern with limited hours. Your job is to get deep on the business and do a few high-leverage projects extremely well. Not to run the whole back office.”"]]));
  c.push(sayLine([["“If this goes well, the path to owning that senior seat is real. We will both know a lot more in ninety days.”"]]));

  c.push(blockHeader("2", "How we work + what I need from you", "5 min"));
  c.push(stage("Set the bar: creative, resourceful, organized, proactive. The role did not exist before him."));
  c.push(sayLine([["“This role did not exist before you. There is no playbook handed to you. I need you creative, resourceful, organized, and proactive. You will hit things with no obvious owner. When you do, bring me a proposed answer, not just the problem.”"]]));
  c.push(sayLine([["“We run on EOS. Numbers, weekly rhythm, accountability. You will feel that fast.”"]]));
  c.push(sayLine([["“Default to action on research and drafting. Default to asking me on anything that spends money, touches a vendor, or reaches outside the company.”"]]));

  c.push(blockHeader("3", "Why PE exit readiness matters", "5 min"));
  c.push(stage("Introduce the exit early. It is the why behind the work and it plays to his background."));
  c.push(sayLine([["“We are building toward a private equity or strategic sale in 24 to 36 months. Almost everything we do points at that.”"]]));
  c.push(sayLine([["“For a buyer to pay a strong multiple, the business has to be legible. Documented, measurable, not dependent on what is in my head. That is the work, and it plays directly to your finance and PE background.”"]]));
  c.push(sayLine([["“Two ground rules. Everything about the exit stays between us and a small group of advisors. And you will sign an NDA before day one. During the internship your PE work is research and drafting only. No contacting buyers. That switch flips later, with me.”"]]));

  c.push(blockHeader("4", "Your first two projects", "12 min"));
  c.push(stage("The heart of the call. Make the two projects concrete. Both are remote and need little access."));
  c.push(sayLine([["“Project one: a PE diligence checklist and buyer research. What does a buyer of a home services company actually want to see in diligence. You build the checklist, then we map what we have and what is missing. Pure research and structure to start. This is your wheelhouse.”"]]));
  c.push(sayLine([["“Project two: our SOPs. We already have them. They are scattered and uneven. I want you to audit what exists, find what is outdated, missing, or not being followed, and reorganize the library so it is usable for training, for daily accountability, and eventually for diligence. You are not writing them from scratch. You are making them real.”"]]));
  c.push(sayLine([["“Both of these you can do remotely with the access I give you. Neither needs you to run a meeting or manage a person yet.”"]]));
  c.push(ask("Ask", "“Reading both of those, which would you attack first, and why?”"));

  c.push(blockHeader("5", "Cadence, tools, accountability", "8 min"));
  c.push(stage("Hand off the daily checklist and the EOD report link live, on the call."));
  c.push(sayLine([["“Access to "], ["[systems list]", { blank: true }], [" will be ready before day one.”"]]));
  c.push(sayLine([["“Every day you work, you fill out a short end-of-day report. Two minutes. It is how I stay in the loop and how we make sure nothing stalls. Here is the link.”  "], ["[EOD form link]", { blank: true }]]));
  c.push(sayLine([["“Here is your daily checklist. Start of day, during, end of day. Follow it until it is habit.”  "], ["[hand off the checklist]", { blank: true }]]));
  c.push(sayLine([["“We will do a weekly 1:1, "], ["[day/time]", { blank: true }], [", 30 minutes, Zoom. Day to day reach me on "], ["[channel]", { blank: true }], [". Urgent, just call.”"]]));

  c.push(blockHeader("6", "Your week-one deliverable", "5 min"));
  c.push(sayLine([["“By the end of your first week I want one page. Two things on it. First, an inventory of every SOP that exists today: name, where it lives, last updated, who owns it. Second, a first rough outline of the PE diligence checklist from your own research. Rough is fine. I want to see how you organize and how you research.”"]]));

  c.push(blockHeader("7", "The path: internship to ownership", "3 min"));
  c.push(sayLine([["“If the projects land and the fit is there, this converts to the full Internal Operations Manager role, and over a two to three year arc it can grow into a COO-type seat. That is conditional, not promised. The internship is how we both find out.”"]]));

  c.push(blockHeader("8", "Gut-check and close", "2 min"));
  c.push(stage("Most important two questions. Ask them, then stop talking."));
  c.push(ask("Q1", "“Is there anything that would make you not start?”"));
  c.push(stage("Pause. Do not fill the silence."));
  c.push(ask("Q2", "“What is worrying you most about the first month?”"));
  c.push(sayLine([["“Last thing, let me lock our next steps: offer and NDA back by "], ["[date]", { blank: true }], [", day one is "], ["[date/time]", { blank: true }], [", first deliverable due "], ["[date]", { blank: true }], [", and our first 1:1 is "], ["[date/time]", { blank: true }], [".”"]]));

  // Opening script
  c.push(section("Opening Script"));
  c.push(caption("A way to open the call so the intern-vs-senior reframe lands in the first sixty seconds."));
  c.push(quoteBox([
    "“Aman, glad we are doing this. Before anything else I want to be clear about one thing, because it matters. The role description you read is the long-term seat, the senior Internal Operations job. That is where this can go. It is not what I am putting on your plate this summer.",
    "Right now you are an intern with limited hours, and your job is simpler and sharper than that whole document. Get deep on how this business actually runs, and do two or three projects so well that they make a real difference. That is it. Nobody expects you to run the back office on day one.",
    "If it goes well, the path to that bigger seat is real, and we will both know a lot more in ninety days. Sound good? Then let me walk you through how I want to use our time today.”",
  ]));

  // First-week assignment
  c.push(section("First-Week Assignment"));
  c.push(para("One page, two artifacts, due end of week one. Keep it to about 20 hours. Inventory and research only, no system changes and no meetings to run."));
  c.push(bullet("SOP inventory: every SOP that exists today, in a simple table. Name, where it lives, last updated, owner. The point is a complete picture of what we have."));
  c.push(bullet("PE diligence checklist v0: a rough outline, from his own research, of what a buyer of a home services or trades company expects to see in diligence. Categories and line items. Rough is the goal; it shows how he organizes and researches."));

  // 30-day plan
  c.push(section("30-Day Internship Plan"));
  c.push(caption("About 20 hours per week, milestone-based. Ladders straight into Phase 1 (Onboard & Listen) of the role description."));
  c.push(dataTable([1700, 4380, 4200],
    ["Week", "Focus", "Deliverable"],
    [
      ["Week 1", "Orient + inventory. Read the role description and this doc, get access, skim one vendor call and one CSR call, build the inventory and checklist.", "SOP inventory + diligence checklist v0"],
      ["Week 2", "Audit pass. Score each existing SOP (exists / outdated / not-followed / missing). Research what buyers want in home-services diligence.", "SOP audit matrix + “what buyers want” brief"],
      ["Week 3", "Build the trackers. Stand up an exit-readiness tracker (item, owner, priority, timeline), reorganize the SOP library, start a buyer-universe file (no contact).", "Exit-readiness tracker v1 + SOP library index + buyer-universe file"],
      ["Week 4", "Synthesize + present. Write a one-page State of the Back-Office memo and propose the next 30 days. Present in the monthly 1:1.", "State of the Back-Office memo + next-30-days proposal"],
    ]));

  // Not to delegate
  c.push(section("What NOT to Delegate Yet"));
  c.push(caption("Research and drafting on all of these is welcome. Decisions and outside contact are not."));
  ["Any contact with potential buyers, PE firms, or M&A advisors.",
   "Vendor termination, renewal, or contract changes.",
   "Pricing changes of any kind.",
   "Hiring, firing, or pay decisions.",
   "Payroll, banking, or money-movement access and approvals.",
   "Signing or committing on behalf of Twins.",
   "Customer-facing authority as a Twins decision-maker.",
   "Running the weekly Level-10 meeting (defer until he has ramped).",
   "Unsupervised access to sensitive financials beyond what a specific task needs.",
  ].forEach(t => c.push(bullet(t)));

  // Questions
  c.push(section("Questions to Ask in the Call"));
  ["“When you read the role description, which of the priorities did you want to attack first, and why?” (his application prompt)",
   "“You have limited hours. If you only had five productive hours this week, what would you spend them on?”",
   "“Where have you actually used AI to save real time? Name the tool and the outcome.”",
   "“Walk me through how you would build a diligence checklist for a business like ours from scratch.”",
   "“When there is no process and no obvious owner, what do you do?”",
   "“How will you keep me from becoming your bottleneck?”",
   "“What part of this feels least comfortable to you right now?”",
  ].forEach(t => c.push(bullet(t, "nums")));

  // Follow-up
  c.push(section("Follow-Up Message  ·  Send Same Day"));
  c.push(caption("Plain and warm. Fill the four blanks before sending."));
  c.push(quoteBox([
    "Aman,",
    "Good talking today. Quick recap so we are aligned.",
    "You are starting as a part-time intern, around 20 hours a week, remote, at $30/hour. Day one is [date]. Your first two projects are the PE diligence checklist plus buyer research, and the audit and reorganization of our existing SOPs.",
    "By the end of your first week, send me one page: an inventory of every SOP we have today (name, where it lives, last updated, owner), and a rough first outline of the diligence checklist from your research. Rough is fine. I want to see how you organize and research.",
    "Three things to set up: the role description [link], your daily checklist [link], and your end-of-day report, which you fill out each day you work [link].",
    "We will do a weekly 1:1 on [day/time]. For day-to-day, reach me at [channel]. If it is urgent, call.",
    "Reply with your plan for week one by [date]. Looking forward to building this with you.",
    "Daniel",
  ]));

  return new Document({ styles: sharedStyles, numbering: numberingConfig, sections: [{
    properties: sectionPage, headers: { default: pageHeader("Internship Kickoff Package") },
    footers: { default: pageFooter() }, children: c }] });
}

// =========================================================
// DOC B: Daily Checklist
// =========================================================
function buildChecklist() {
  const c = [];
  c.push(...headerBand());
  c.push(...title("Internal Operations Daily Checklist", "Aman Kharga"));
  c.push(para("Run this every day you work. It keeps you organized, keeps Daniel in the loop, and makes sure nothing stalls. It should take a few minutes at each end of the day, not more."));

  c.push(section("Start of Day"));
  c.push(bullet("Check Daniel's replies and any messages since you last worked."));
  c.push(bullet("Open your project trackers: the SOP audit matrix and the exit-readiness tracker."));
  c.push(bullet("Pick the top one or two outcomes you will move today. Write them down."));

  c.push(section("During the Day"));
  c.push(bullet("Log your work as you go. Keep the SOP audit matrix and exit-readiness tracker current."));
  c.push(bullet("Tag every research source so it is traceable later. A buyer will eventually ask how you know."));
  c.push(bullet("Research and drafting only on anything PE or vendor related. No outside contact."));

  c.push(section("End of Day  ·  Each Day You Work"));
  c.push(bullet("Update the trackers with what changed today."));
  c.push(bullet("Submit your end-of-day report (link in the box below)."));
  c.push(bullet("Note at least one improvement idea this week."));

  c.push(section("When You Have Spare Time"));
  c.push(caption("Downtime playbook. When a project is blocked or you are waiting on Daniel, pick from here."));
  c.push(bullet("Advance the buyer-universe research."));
  c.push(bullet("Clean up and reorganize the SOP and document library."));
  c.push(bullet("Draft or improve one SOP from the existing set."));
  c.push(bullet("Read one diligence or trades-M&A resource and bring back a takeaway."));

  c.push(new Paragraph({ spacing: { before: 120 }, children: [new TextRun("")] }));
  c.push(calloutBox("Your end-of-day report",
    "https://form.typeform.com/to/XcMEqQ1C  ·  fill this out each day you work, before you log off."));

  return new Document({ styles: sharedStyles, numbering: numberingConfig, sections: [{
    properties: sectionPage, headers: { default: pageHeader("Daily Checklist") },
    footers: { default: pageFooter() }, children: c }] });
}

async function writeDoc(doc, file) {
  const buf = await Packer.toBuffer(doc);
  fs.writeFileSync(path.join(DIR, file), buf);
  console.log("Wrote", file, buf.length, "bytes");
}
(async () => {
  await writeDoc(buildKickoff(), "Aman_Internship_Kickoff_Package.docx");
  await writeDoc(buildChecklist(), "Aman_Daily_Checklist.docx");
  console.log("Done.");
})().catch(e => { console.error(e); process.exit(1); });
