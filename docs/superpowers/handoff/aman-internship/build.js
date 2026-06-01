// Builds the Aman internship kickoff package + daily checklist as branded .docx,
// matching the existing Twins Aman/Ivory/Charles doc set (navy + gold, badge, footer).
// Focus: what Aman does day to day and a concrete task list. Not comp, not strategy.
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
// a checkbox to-do line
function todo(text) {
  return new Paragraph({ spacing: { after: 70 }, indent: { left: 360 },
    children: [ new TextRun({ text: "☐  ", size: 24, color: NAVY }),
      new TextRun({ text, size: 22, color: NAVY }) ] });
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
// DOC A: Kickoff Package (day-to-day + tasks focused)
// =========================================================
function buildKickoff() {
  const c = [];
  c.push(...headerBand());
  c.push(...title("Internal Operations Internship", "Kickoff Call  ·  Aman Kharga"));
  c.push(para("This runs the kickoff call and doubles as Aman's reference for how to work. He is a part-time intern, about 20 hours a week, remote, working out of Google Workspace. Keep it practical and collaborative: bring him in, ask his opinions, and treat the first tasks as a starting point he can shape, not a script. The goal is that he leaves clear on how his day works and what to start on, and feeling like he had a hand in it.", { after: 160 }));

  // Agenda
  c.push(section("The 45-Minute Kickoff Agenda"));
  c.push(para("How to use this: lines in gold-bordered boxes are what you say. Gold [blanks] are yours to fill in. Italic gray lines are cues for you, not for him. The detail pages (Your Day-to-Day, Your Weekly Rhythm, Your First Tasks) are below; share your screen and walk them.", { italics: true, color: GREY_TEXT, size: 20 }));
  c.push(dataTable([1000, 7280, 2000], ["#", "Block", "Time"], [
    ["1", "The frame, fast (intern now, senior later)", "3 min"],
    ["2", "What your day-to-day looks like", "8 min"],
    ["3", "Your weekly rhythm", "4 min"],
    ["4", "Your first tasks (the heart of the call)", "15 min"],
    ["5", "How you track and report", "8 min"],
    ["6", "What you decide vs. what needs me", "4 min"],
    ["7", "Gut-check and close", "3 min"],
  ]));

  c.push(blockHeader("1", "The frame, fast", "3 min"));
  c.push(stage("Say this, then move on. Do not spend the call here."));
  c.push(sayLine([["“The role description you read is the senior seat, where this can grow. This summer you are a part-time intern. Your job is to get deep on the business and nail a couple of projects. Not to run the back office.”"]]));
  c.push(sayLine([["“One thing up front: we are pointing at selling the business in a couple of years, so a lot of your work is making us organized and documented. That stays between us. You already signed the NDA, so we are set there. It is research and drafting only, and you never contact anyone outside the company without me.”"]]));
  c.push(sayLine([["“And I want you shaping this with me. This role did not exist before you. Nothing I lay out today is set in stone. If something does not make sense or you would do it differently, say so. I would rather hear it now.”"]]));
  c.push(ask("Ask", "“After reading the role and talking with us, what is your honest first read? What excites you, and what gives you pause?”"));

  c.push(blockHeader("2", "What your day-to-day looks like", "8 min"));
  c.push(stage("Open 'Your Day-to-Day' below and walk it while screen-sharing the Google Drive folder and the task sheet."));
  c.push(sayLine([["“Every day you work, it is the same loop: check in, pick your tasks, do the work, update your sheet, send your end-of-day report. Let me show you.”"]]));

  c.push(blockHeader("3", "Your weekly rhythm", "4 min"));
  c.push(stage("Walk 'Your Weekly Rhythm' below. Let him set the cadence with you."));
  c.push(sayLine([["“You set your own hours, around 20 a week. Just send me your rough working days. We talk live once a week, "], ["[day/time]", { blank: true }], [", 30 minutes.”"]]));
  c.push(sayLine([["“Two standing meetings to put on your calendar. Leadership L10 is Wednesdays at 8 AM Central, that is you and the leadership team. The all-team L10 is Thursdays "], ["[time]", { blank: true }], [" Central. Plan to join both.”"]]));
  c.push(ask("Ask", "“What working days and rhythm actually fit your schedule? And do you want to talk more or less than once a week, especially early on?”"));

  c.push(blockHeader("4", "Your first tasks", "15 min"));
  c.push(stage("The heart of the call. Open 'Your First Tasks' and go through it together as a draft, not a mandate. Let him react to each track."));
  c.push(sayLine([["“Here is a starting point for week one. I am not trying to box you in, so push on it. Setup, plus two project tracks: audit our existing SOPs, and research a buyer-diligence checklist.”"]]));
  c.push(sayLine([["“Read these, then add at least three of your own. If you spot something I missed, that is exactly the instinct I am hiring for.”"]]));
  c.push(ask("Ask", "“Do these two projects feel like the right place to start? If you were in my seat, what would you put in front of the first hire?”"));
  c.push(ask("Ask", "“Anything on this list you would drop, reorder, or approach differently?”"));

  c.push(blockHeader("5", "How you track and report", "8 min"));
  c.push(stage("Walk it, but make clear the docs and access come AFTER the call in writing, not just verbally now."));
  c.push(sayLine([["“You track everything in your task sheet, with a status on each item, and save your work in the Drive folder, named clearly.”"]]));
  c.push(sayLine([["“End of every day you work, you fill out a two-minute end-of-day report. It is how I stay in the loop, and it flags anything you need from me.”"]]));
  c.push(sayLine([["“Right after this call I will send you everything in writing: your daily checklist, the end-of-day report link, the role doc, and a task-sheet template to copy. So do not worry about catching it all now.”"]]));
  c.push(sayLine([["“And I will get you access to every platform you need, Google Workspace and Drive, our CRM, scheduling, anything a task calls for. If you ever need access to something, just ask and I will set it up that same day. Through the week I will keep sending whatever reports or checklists you need as we go.”"]]));

  c.push(blockHeader("6", "What you decide vs. what needs me", "4 min"));
  c.push(sayLine([["“Default to action on research, drafting, and organizing. Default to asking me on anything that spends money, touches a vendor, or reaches outside the company. When you hit something with no owner, bring me a proposed answer, not just the problem.”"]]));

  c.push(blockHeader("7", "Gut-check and close", "3 min"));
  c.push(ask("Q1", "“Anything we talked about that you would do differently, or any idea you have that I have not thought of?”"));
  c.push(ask("Q2", "“Is anything unclear about what you actually do day to day, and what is worrying you about week one?”"));
  c.push(sayLine([["“Here is what happens right after we hang up: I will send you the access to every platform, plus your checklist, the report link, the role doc, and a task-sheet template. Through the day and week I will send anything else you need. You will not be stuck waiting on me for a tool.”"]]));
  c.push(sayLine([["“Next steps on your side: day one is "], ["[date/time]", { blank: true }], [", your week-one deliverable is due "], ["[date]", { blank: true }], [", and our first 1:1 is "], ["[date/time]", { blank: true }], [". I will send calendar invites.”"]]));

  // Reference: Day-to-Day
  c.push(section("Your Day-to-Day"));
  c.push(caption("Run this loop every day you work. About four hours, on your own schedule."));
  [
    "Open the Internal Operations folder in Google Drive and your task sheet.",
    "Check email and texts from Daniel since you last worked. Reply to the quick ones.",
    "In your task sheet, pick the one or two tasks you will finish this session. Mark them In Progress.",
    "Do the work. Save everything in the right Drive folder, named clearly (date plus what it is).",
    "Update your task sheet and any trackers: Done, In Progress, or Blocked with a note.",
    "Submit your end-of-day report before you log off.",
  ].forEach(t => c.push(bullet(t, "nums")));

  // Reference: Weekly Rhythm
  c.push(section("Your Weekly Rhythm"));
  [
    "About 20 hours a week, on your own schedule. Send Daniel your rough working days each week.",
    "Leadership L10: Wednesdays, 8:00 AM Central. You and the leadership team. Standing meeting.",
    "All-team L10: Thursdays, [time] Central. The whole team. Plan to join.",
    "Weekly 1:1 with Daniel, 30 minutes, [day/time].",
    "End-of-day report every day you work.",
    "Bring at least one improvement idea each week.",
    "Friday: skim your task sheet, update your project tracker, flag anything for the 1:1.",
  ].forEach(t => c.push(bullet(t)));

  // Reference: First Tasks
  c.push(section("Your First Tasks  ·  Week 1"));
  c.push(caption("A starter list. Track each item in your task sheet. Then add at least three of your own."));
  c.push(subsection("Setup"));
  c.push(todo("Accept Google Workspace access and confirm you can open the Internal Operations Drive folder."));
  c.push(todo("Make your task tracker: a simple Google Sheet with columns Task, Project, Status, Notes, Link. Create your working subfolders in Drive."));
  c.push(todo("30-minute intro call with Charles, the Field Operations Manager. Peer to peer, no Daniel needed."));
  c.push(subsection("Project 1  ·  SOP audit (organize what we already have)"));
  c.push(todo("Find every place SOPs and process docs currently live: Drive, Cowork, HouseCall Pro, email, anywhere. List the locations."));
  c.push(todo("Build an SOP inventory in a sheet: name, where it lives, last updated, owner, format."));
  c.push(todo("Mark each one: looks current, looks outdated, or can't tell. Note any obvious gaps."));
  c.push(subsection("Project 2  ·  PE diligence checklist (research only)"));
  c.push(todo("Research what a buyer of a home-services or trades business wants to see in diligence. Use three or four credible sources and save the links."));
  c.push(todo("Draft the checklist categories: financials, operations, legal, customers, team and HR, systems, and so on."));
  c.push(todo("List the line items under each category. Rough is fine."));
  c.push(subsection("Wrap the week"));
  c.push(todo("Put the SOP inventory and the diligence checklist v0 on one page. That is your week-one deliverable for Daniel."));
  c.push(todo("Add at least three tasks of your own that you think belong on this list."));

  // What you'll get after the call
  c.push(section("What You'll Get After This Call"));
  c.push(caption("Daniel sends all of this in writing right after the call, and keeps it flowing as needed. You never wait on a tool."));
  c.push(bullet("Access to every platform you need: Google Workspace and Drive, our CRM, scheduling, and anything a specific task calls for. Need something else later? Ask, and it is set up the same day."));
  c.push(bullet("Your daily checklist (separate doc)."));
  c.push(bullet("Your end-of-day report link."));
  c.push(bullet("The role description."));
  c.push(bullet("A task-sheet template to copy for your own task tracker."));
  c.push(bullet("Any additional reports or checklists, sent through the day and week as the work calls for them."));

  // 30-day plan
  c.push(section("Where This Goes  ·  First 30 Days"));
  c.push(caption("About 20 hours per week, milestone-based. Week 1 is the task list above."));
  c.push(dataTable([1700, 4380, 4200],
    ["Week", "Focus", "Deliverable"],
    [
      ["Week 1", "Setup, SOP inventory, and a first diligence-checklist draft (the task list above).", "SOP inventory + diligence checklist v0"],
      ["Week 2", "Score each existing SOP (current / outdated / not-followed / missing). Turn the buyer research into a short brief.", "SOP audit matrix + “what buyers want” brief"],
      ["Week 3", "Stand up an exit-readiness tracker (item, owner, priority, timeline), reorganize the SOP library, start a buyer-universe file (no contact).", "Exit-readiness tracker v1 + SOP library index"],
      ["Week 4", "Write a one-page State of the Back-Office memo and propose his own next 30 days. Present it in the 1:1.", "State of the Back-Office memo + next-30-days proposal"],
    ]));

  // What needs Daniel
  c.push(section("What You Decide vs. What Needs Daniel"));
  c.push(para("Do on your own:", { bold: true }));
  ["Research, drafting, organizing, and reorganizing documents.",
   "Building inventories, trackers, checklists, and your own task list.",
   "Booking your intro call with Charles and setting your own working hours.",
  ].forEach(t => c.push(bullet(t)));
  c.push(para("Always check with Daniel first:", { bold: true, before: 80 }));
  ["Anything that spends money or commits Twins to a cost.",
   "Anything that touches a vendor (marketing, bookkeeping, IT).",
   "Any contact with someone outside the company, especially anything exit or buyer related.",
   "Hiring, pay, pricing, or changing how a customer is handled.",
  ].forEach(t => c.push(bullet(t)));

  // Questions
  c.push(section("Questions to Ask Him"));
  ["“Looking at the week-one task list, what would you add, and what would you start with?”",
   "“If you only had five productive hours this week, where would you spend them?”",
   "“Where have you actually used AI to save real time? Name the tool and the outcome.”",
   "“When there is no process and no obvious owner, what do you do?”",
   "“How will you keep me from becoming your bottleneck?”",
   "“What part of this feels least comfortable right now?”",
  ].forEach(t => c.push(bullet(t, "nums")));

  // Opening script
  c.push(section("Opening Script"));
  c.push(caption("A way to open so the reframe lands fast, then you get into the day-to-day."));
  c.push(quoteBox([
    "“Aman, glad we are doing this. Quick frame, then we get practical. The role description you read is the long-term seat. This summer you are a part-time intern, and your job is simpler than that whole document: get deep on how this business runs, and nail a couple of projects.",
    "Most of today is about what you actually do day to day and where to start, and I want your take on all of it. I will share my screen and walk you through your folder, your task sheet, and your first week, and I want you pushing back and adding your own ideas as we go. None of it is set in stone. Sound good?”",
  ]));

  // Follow-up
  c.push(section("Follow-Up Message  ·  Send Same Day"));
  c.push(caption("Plain and practical. Fill the blanks before sending."));
  c.push(quoteBox([
    "Aman,",
    "Good talking today, and thanks for the ideas on the call. Quick recap.",
    "You start [date], part-time and remote, working out of Google Workspace. Week one is setup plus two tracks: audit and inventory our existing SOPs, and research and draft a buyer-diligence checklist. The full task list is in the doc I shared. Treat it as a starting point, not a script. If you would reorder or add to it, do.",
    "I have set up your access to everything you need: Google Workspace and Drive, plus the other platforms. If you hit something you cannot get into, or need access to another tool, just tell me and I will fix it the same day.",
    "Everything is attached or linked: the role description [link], your daily checklist [link], your end-of-day report which you fill out each day you work [link], and a task-sheet template to copy [link]. I will keep sending any other reports or checklists as the work calls for them.",
    "By Friday, send me one page: the SOP inventory (name, where it lives, last updated, owner) and a rough diligence checklist, plus a few tasks of your own. Rough is fine; I want to see how you think.",
    "Standing meetings to add to your calendar: Leadership L10 Wednesdays at 8 AM Central, and the all-team L10 Thursdays at [time] Central. Plan to join both. Our weekly 1:1 is [day/time]. Day to day, reach me by text or email anytime.",
    "Reply with your plan for week one by [date]. Looking forward to it.",
    "Daniel",
  ]));

  return new Document({ styles: sharedStyles, numbering: numberingConfig, sections: [{
    properties: sectionPage, headers: { default: pageHeader("Internship Kickoff") },
    footers: { default: pageFooter() }, children: c }] });
}

// =========================================================
// DOC B: Daily Checklist (Google Workspace concrete)
// =========================================================
function buildChecklist() {
  const c = [];
  c.push(...headerBand());
  c.push(...title("Internal Operations Daily Checklist", "Aman Kharga"));
  c.push(para("Run this every day you work. It keeps you organized, keeps Daniel in the loop, and makes sure nothing stalls. A few minutes at each end of the day."));

  c.push(section("Start of Day"));
  c.push(todo("Open the Internal Operations folder in Google Drive and your task sheet."));
  c.push(todo("Check email and texts from Daniel since you last worked. Reply to the quick ones."));
  c.push(todo("Pick the one or two tasks you will finish today. Mark them In Progress in your sheet."));

  c.push(section("During the Day"));
  c.push(todo("Save everything in the right Drive folder, named clearly (date plus what it is)."));
  c.push(todo("Keep your task sheet honest: Done, In Progress, or Blocked with a note."));
  c.push(todo("Tag every research source with its link so it is traceable later."));
  c.push(todo("Research and drafting only on anything PE or vendor related. No outside contact."));

  c.push(section("End of Day  ·  Each Day You Work"));
  c.push(todo("Update your task sheet and any trackers with what changed today."));
  c.push(todo("Submit your end-of-day report (link in the box below)."));
  c.push(todo("Jot at least one improvement idea this week."));

  c.push(section("When You Have Spare Time"));
  c.push(caption("When a task is blocked or you are waiting on Daniel, pick from here."));
  c.push(bullet("Advance the buyer-universe research."));
  c.push(bullet("Clean up and reorganize the SOP and document library in Drive."));
  c.push(bullet("Draft or improve one SOP from the existing set."));
  c.push(bullet("Read one diligence or trades-M&A resource and bring back a takeaway."));

  c.push(para("Need access to a tool, or another report or checklist? Text or email Daniel. You will have it the same day. Never sit blocked waiting on access.", { before: 160 }));

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
