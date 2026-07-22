<?php
declare(strict_types=1);

/**
 * Per-city editorial content for location pages.
 *
 * This file is the ONLY place location page copy is authored. Templates and
 * renderers read from it; they are not edited to add or change city copy.
 *
 * 'completedJobs' is real data from completed Twins jobs, aggregated by service
 * ZIP and verified 2026-07-21. It is null where a city shares its ZIPs with
 * Madison and no separable per-city figure exists, and null for cities with no
 * job history yet. When it is null, the copy must not cite any job number.
 *
 * 'metro' selects the NAP and phone shown on the page. It is not editorial and
 * must not be changed while writing copy.
 *
 * Empty 'intro', 'localNotes' and 'faq' are unwritten. Empty is valid and
 * renders the shared default; a page is never blocked on missing copy.
 */

return [
    'madison' => [
        'label' => 'Madison',
        'metro' => 'madison',
        'completedJobs' => null,  // shares ZIPs with Madison; per-city count not separable
        'intro' => 'Twins Garage Doors repairs broken springs, cables, rollers, tracks, and openers across Madison. We also install new garage doors and openers when repair is no longer the sensible choice.',
        'localNotes' => 'Madison puts garage doors through deep freezes, wet spring weather, summer humidity, and road salt. Those swings can stiffen seals, expose weak springs, and turn a slightly noisy roller or hinge into a door that binds.',
        'faq' => [
            ['q' => 'What garage door problems do you repair in Madison?', 'a' => 'We diagnose doors that are stuck, crooked, noisy, off track, or failing to close. We also work on broken springs, damaged cables, worn rollers, safety sensors, and garage door openers.'],
            ['q' => 'Should I replace my Madison garage door or repair it?', 'a' => 'A sound door with one failed part is often worth repairing. Replacement makes more sense when several sections are damaged, the door is badly worn, or you want a different level of insulation and appearance.'],
        ],
    ],
    'verona' => [
        'label' => 'Verona',
        'metro' => 'madison',
        'completedJobs' => 372,
        'intro' => 'Our crew handles garage door repair, opener work, and new installations in Verona. The service record includes 372 completed jobs tied to Verona, so we have seen a broad mix of door and opener trouble here.',
        'localNotes' => 'A Verona door may run differently after a sharp cold snap because metal contracts and grease thickens. If the opener strains or the door feels heavy by hand, stop cycling it until the spring and balance are checked.',
        'faq' => [
            ['q' => 'Can Twins replace a broken garage door spring in Verona?', 'a' => 'Yes, spring repair is part of our regular work. Springs carry high tension, so keep people clear of the door and leave the replacement to a trained technician.'],
            ['q' => 'What does a garage door repair usually cost in Verona?', 'a' => 'Completed repairs from July 2025 through July 2026 commonly fell between $400 and $1,050. That is a planning range, not a quote for your door.'],
        ],
    ],
    'sun-prairie' => [
        'label' => 'Sun Prairie',
        'metro' => 'madison',
        'completedJobs' => 286,
        'intro' => 'Twins fixes garage doors and openers throughout Sun Prairie, from a snapped cable to a door that will not close. We have 286 completed jobs associated with Sun Prairie in the service data.',
        'localNotes' => 'Wind-driven rain and snow can soak the bottom seal on a Sun Prairie garage door, then freeze it to the floor. Pulling against that ice can tear the seal or overload the opener, so free the door before pressing the remote again.',
        'faq' => [
            ['q' => 'Why does my Sun Prairie garage door reverse before closing?', 'a' => 'A blocked or misaligned safety sensor is a common cause, but track resistance and opener settings can produce the same symptom. We check the full system before adjusting parts.'],
            ['q' => 'Do you install new garage door openers in Sun Prairie?', 'a' => 'Yes, we install openers and set up the travel, force, remotes, and safety reverse. Historical installed-opener jobs ranged from $900 to $1,450, which is useful for planning but is not a firm price.'],
        ],
    ],
    'middleton' => [
        'label' => 'Middleton',
        'metro' => 'madison',
        'completedJobs' => 263,
        'intro' => 'Twins services garage doors in Middleton when a spring breaks, an opener quits, or worn hardware makes the door unreliable. Our records show 263 completed Middleton jobs.',
        'localNotes' => 'Salt and slush carried into a Middleton garage can sit along the bottom brackets and lower door sections. Rinsing that residue away and watching for rust helps protect parts that live close to the floor.',
        'faq' => [
            ['q' => 'Can you quiet a loud garage door in Middleton?', 'a' => 'Often, yes. Worn rollers, loose hinges, dry bearings, poor balance, and opener vibration each make a different sound, so we trace the noise before recommending parts.'],
            ['q' => 'Does Twins charge a diagnostic fee for Middleton service?', 'a' => 'The service call and diagnostic is $49. We waive that charge when you complete the repair with Twins.'],
        ],
    ],
    'oregon' => [
        'label' => 'Oregon',
        'metro' => 'madison',
        'completedJobs' => 101,
        'intro' => 'Twins repairs stuck doors, failed openers, broken springs, and damaged hardware in Oregon. The current service record lists 101 completed Oregon jobs.',
        'localNotes' => 'Repeated freeze and thaw cycles can leave an Oregon garage door seal frozen to the concrete in the morning and wet by afternoon. Check the threshold before running the opener when temperatures swing across freezing.',
        'faq' => [
            ['q' => 'Why is my Oregon garage door crooked?', 'a' => 'A cable may have slipped or broken, a roller may have left the track, or the door may be binding. Stop using it until the cause is found, since continued movement can damage sections and hardware.'],
            ['q' => 'Does Twins install insulated garage doors in Oregon?', 'a' => 'We install new garage doors as an authorized Clopay dealer and can discuss insulation choices for an attached or frequently used garage. The right option depends on the opening, the room next to it, and how you use the space.'],
        ],
    ],
    'deforest' => [
        'label' => 'DeForest',
        'metro' => 'madison',
        'completedJobs' => 91,
        'intro' => 'Our crew comes to DeForest for garage door repair, opener replacement, and full door installation. We have recorded 91 completed jobs for DeForest.',
        'localNotes' => 'Cold air can make a marginal DeForest opener sound louder while a tired spring shifts more of the load onto the motor. The useful test is door balance, not how much force the opener can apply.',
        'faq' => [
            ['q' => 'What should I do if my DeForest garage door will not open?', 'a' => 'Look for a blocked doorway or lost power without touching springs or cables. If the door is unusually heavy or a spring has a visible gap, leave it closed and have it inspected.'],
            ['q' => 'Can Twins replace both my garage door and opener in DeForest?', 'a' => 'Yes, we can quote the door and opener as one installation. Completed combined projects from July 2025 through July 2026 ranged from $4,400 to $7,250, but the figure for your home depends on the products and opening.'],
        ],
    ],
    'waunakee' => [
        'label' => 'Waunakee',
        'metro' => 'madison',
        'completedJobs' => 91,
        'intro' => 'Twins works on garage doors throughout Waunakee, including spring, cable, roller, track, panel, and opener problems. Our service data contains 91 completed Waunakee jobs.',
        'localNotes' => 'Snowmelt often collects at the edge of a Waunakee garage slab. A cracked bottom seal can let that water and cold air under the door, while rust near the lower fixtures signals that moisture has been sitting there.',
        'faq' => [
            ['q' => 'Can a damaged garage door panel be replaced in Waunakee?', 'a' => 'Sometimes a matching section is available and the rest of the door is worth keeping. We check the model, damage, door condition, and hardware before comparing a section replacement with a new door.'],
            ['q' => 'How do I know if my Waunakee opener or spring is failing?', 'a' => 'Disconnecting the opener only when the door is closed lets a technician test the balance safely. A door that feels heavy points toward the counterbalance system, while a balanced door that will not move may point toward the opener.'],
        ],
    ],
    'mcfarland' => [
        'label' => 'McFarland',
        'metro' => 'madison',
        'completedJobs' => 67,
        'intro' => 'We repair residential garage doors and openers in McFarland, then explain what failed before work begins. Twins has 67 completed McFarland jobs in the verified record.',
        'localNotes' => 'Water along the bottom of a McFarland door can freeze around the seal and create a false impression that the opener has failed. Repeated attempts may strip a gear or pull hardware loose, so check for ice before resetting anything.',
        'faq' => [
            ['q' => 'Why does my McFarland garage door stop halfway?', 'a' => 'The cause can be track resistance, a weak spring, a damaged roller, or an opener travel problem. We test the door by hand and under power to separate the mechanical load from the motor controls.'],
            ['q' => 'Is Twins licensed and insured for work in McFarland?', 'a' => 'Yes, Twins Garage Doors is licensed and insured. The company is family owned, operated by twin brothers, and is not a franchise.'],
        ],
    ],
    'cottage-grove' => [
        'label' => 'Cottage Grove',
        'metro' => 'madison',
        'completedJobs' => 60,
        'intro' => 'Cottage Grove homeowners call Twins for garage door repairs, new openers, and replacement doors. The job registry shows 60 completed projects tied to Cottage Grove.',
        'localNotes' => 'A west-facing Cottage Grove garage door can heat up in summer and cool fast after sunset. That daily movement works fasteners and hinges, making loose hardware and worn rollers easier to hear.',
        'faq' => [
            ['q' => 'Can you repair a noisy garage door in Cottage Grove?', 'a' => 'Yes, and the sound gives useful clues. Grinding, popping, rattling, and squealing can point to different combinations of rollers, hinges, bearings, tracks, balance, or opener mounting.'],
            ['q' => 'What is the service charge for a Cottage Grove visit?', 'a' => 'The service call and diagnostic costs $49. That fee is removed when Twins completes the repair.'],
        ],
    ],
    'pardeeville' => [
        'label' => 'Pardeeville',
        'metro' => 'madison',
        'completedJobs' => 41,
        'intro' => 'Twins travels to Pardeeville for garage door and opener service, from small adjustments to complete replacement. We have 41 completed Pardeeville jobs in the current data.',
        'localNotes' => 'For a Pardeeville service trip, a clear description of the door size, the failed part, and whether a vehicle is trapped helps us prepare. Photos can also show a broken spring, loose cable, or damaged section without anyone touching it.',
        'faq' => [
            ['q' => 'Will you come to Pardeeville for a broken garage door spring?', 'a' => 'Yes, Pardeeville is within the Madison service market. Keep the door down and do not handle the spring or cable while waiting for professional repair.'],
            ['q' => 'Can I get a planning range before replacing a door in Pardeeville?', 'a' => 'Historical new-door installations completed between July 2025 and July 2026 ran from $3,000 to $4,100. It is not a quote, since size, design, glass, insulation, and installation conditions change the price.'],
        ],
    ],
    'baraboo' => [
        'label' => 'Baraboo',
        'metro' => 'madison',
        'completedJobs' => 40,
        'intro' => 'Our garage door technicians serve Baraboo with repairs, opener installations, and new doors. Twins has completed 40 jobs connected to Baraboo.',
        'localNotes' => 'The drive to Baraboo makes accurate symptoms useful before the truck rolls. Tell us whether the door is open or closed, whether a car is stuck inside, and what the spring, cables, and panels look like from a safe distance.',
        'faq' => [
            ['q' => 'Does Twins service garage door openers in Baraboo?', 'a' => 'Yes, we check opener power, controls, sensors, travel, force, rail, and drive components. We also test the door balance because a spring problem can look like a weak motor.'],
            ['q' => 'How much have Baraboo garage door repairs typically cost?', 'a' => 'Across completed work from July 2025 through July 2026, garage door repairs generally landed between $400 and $1,050. Use that only as a planning range until the door is diagnosed.'],
        ],
    ],
    'deerfield' => [
        'label' => 'Deerfield',
        'metro' => 'madison',
        'completedJobs' => 40,
        'intro' => 'Twins handles garage door repairs and installations for Deerfield homes. The service history includes 40 completed jobs assigned to Deerfield.',
        'localNotes' => 'Wind can push leaves, grit, and snow against a Deerfield garage threshold. Keeping the photo-eye area and track edges clear prevents debris from creating a closing problem that looks electrical.',
        'faq' => [
            ['q' => 'Why will my Deerfield garage door close only when I hold the wall button?', 'a' => 'That behavior often points to a safety-sensor issue. Dirty lenses, poor alignment, damaged wire, bright light, or a failing sensor can interrupt the beam, and each needs a different correction.'],
            ['q' => 'Can Twins install a new opener on my existing Deerfield door?', 'a' => 'Yes, if the door and counterbalance system are in serviceable condition. We inspect and balance the door before matching it with an opener.'],
        ],
    ],
    'janesville' => [
        'label' => 'Janesville',
        'metro' => 'madison',
        'completedJobs' => 72,
        'intro' => 'Our technicians repair garage doors and openers in Janesville and install replacements when the old equipment is finished. Twins has logged 72 completed jobs tied to Janesville.',
        'localNotes' => 'A door that has drifted out of balance can be easy to miss until a Janesville cold spell makes the opener work harder. A heavy door, uneven movement, or a gap under one corner is worth checking before another part fails.',
        'faq' => [
            ['q' => 'Can Twins fix a garage door that came off track in Janesville?', 'a' => 'We repair track, roller, cable, and balance problems after inspecting what pushed the door out of line. Do not force an off-track door, since the sections can fall or bend.'],
            ['q' => 'How much should I plan for a new garage door in Janesville?', 'a' => 'From July 2025 through July 2026, completed new-door installations generally ranged from $3,000 to $4,100. Your door size, construction, windows, hardware, and site conditions determine the actual quote.'],
        ],
    ],
    'stoughton' => [
        'label' => 'Stoughton',
        'metro' => 'madison',
        'completedJobs' => 36,
        'intro' => 'Stoughton garage door service from Twins covers broken hardware, opener faults, and full door replacement. Our records list 36 completed jobs in Stoughton.',
        'localNotes' => 'A Stoughton door can freeze to its weather strip after wet snow is followed by falling temperatures. Tugging with the opener may bend the top section or damage the drive, so loosen the ice at the floor first.',
        'faq' => [
            ['q' => 'What causes a garage door cable to come loose in Stoughton?', 'a' => 'A broken spring, poor balance, a jammed door, or a cable jumping the drum can leave slack on one side. Cables are part of a high-tension system and should not be reset by hand.'],
            ['q' => 'Do you offer new Clopay garage doors in Stoughton?', 'a' => 'Yes, Twins is an authorized Clopay dealer. We can measure the opening and quote available door construction, insulation, window, and finish choices.'],
        ],
    ],
    'portage' => [
        'label' => 'Portage',
        'metro' => 'madison',
        'completedJobs' => 28,
        'intro' => 'We bring garage door repair and installation service to Portage homes. The verified job count for Portage is 28.',
        'localNotes' => 'A longer service run to Portage goes more smoothly when we know if the door is single or double, manual or powered, and trapped open or shut. A photo of the opener label or broken hardware can help identify what the truck may need.',
        'faq' => [
            ['q' => 'Can Twins repair my garage door in Portage?', 'a' => 'Yes, Portage is part of our Madison service area. We work on springs, cables, rollers, tracks, sections, seals, safety sensors, and openers.'],
            ['q' => 'What should a Portage homeowner do with a door stuck open?', 'a' => 'Keep people away from loose cables and do not pull the emergency release if the door looks unsupported. Move valuables inside when it is safe, then have the door secured and diagnosed.'],
        ],
    ],
    'mount-horeb' => [
        'label' => 'Mount Horeb',
        'metro' => 'madison',
        'completedJobs' => 28,
        'intro' => 'Twins repairs garage doors and openers in Mount Horeb and installs new equipment when needed. We have 28 completed jobs tied to Mount Horeb.',
        'localNotes' => 'Open terrain can put steady wind pressure on a Mount Horeb garage door. Loose hinges, worn rollers, and gaps around the perimeter tend to announce themselves with rattles and cold drafts.',
        'faq' => [
            ['q' => 'Can you fix a garage door that rattles in Mount Horeb?', 'a' => 'We can inspect the tracks, rollers, hinges, struts, fasteners, and opener attachment. The repair depends on whether the sound comes from normal vibration, worn hardware, or a section that has lost stiffness.'],
            ['q' => 'What has a new opener installation cost around Mount Horeb?', 'a' => 'Historical completed opener installations from July 2025 to July 2026 ranged from $900 to $1,450. Treat that as a budget guide rather than a quote.'],
        ],
    ],
    'belleville' => [
        'label' => 'Belleville',
        'metro' => 'madison',
        'completedJobs' => 23,
        'intro' => 'Belleville residents can call Twins for garage door repairs, opener problems, and replacement doors. Our completed-job data shows 23 Belleville projects.',
        'localNotes' => 'Dust and grit in a Belleville track can mix with heavy lubricant and form a paste that slows the rollers. Tracks should be clean and correctly aligned, while lubrication belongs only on the parts that call for it.',
        'faq' => [
            ['q' => 'Should I lubricate my garage door tracks in Belleville?', 'a' => 'Do not coat the track surface with grease. Cleaning the tracks and using the correct garage-door lubricant on suitable hinges, rollers, and bearings is a better approach.'],
            ['q' => 'Can you replace weather stripping on a Belleville garage door?', 'a' => 'Yes, worn bottom and perimeter seals can be replaced when compatible material is available. We also check door alignment because a new seal cannot correct a crooked opening by itself.'],
        ],
    ],
    'edgerton' => [
        'label' => 'Edgerton',
        'metro' => 'madison',
        'completedJobs' => 19,
        'intro' => 'Twins serves Edgerton with garage door repair, opener service, and new installations. The current registry contains 19 completed Edgerton jobs.',
        'localNotes' => 'Fine grit can collect along an Edgerton garage floor after wet weather and work into the lower rollers and hinges. A door that starts scraping or shuddering should be checked before the track or a section bends.',
        'faq' => [
            ['q' => 'Why does my Edgerton garage door shake as it opens?', 'a' => 'Worn rollers, a bent track, loose hinges, poor spring balance, or opener vibration can all cause shaking. We run the door through its travel and isolate the source instead of replacing parts by guesswork.'],
            ['q' => 'Can Twins give me options instead of replacing everything?', 'a' => 'Yes, the first goal is to identify which parts are actually worn or broken. We compare repair with replacement when the condition of the door makes both choices reasonable.'],
        ],
    ],
    'reedsburg' => [
        'label' => 'Reedsburg',
        'metro' => 'madison',
        'completedJobs' => 18,
        'intro' => 'Our team repairs garage doors and openers for Reedsburg homeowners and can install a new system when required. Twins has logged 18 completed Reedsburg jobs.',
        'localNotes' => 'For the trip to Reedsburg, details matter: door width, opener brand, visible damage, and whether the door is holding a vehicle inside. That information helps us arrive ready without asking you to touch high-tension parts.',
        'faq' => [
            ['q' => 'Will Twins travel to Reedsburg for garage door service?', 'a' => 'Yes, Reedsburg is included in the Madison market we serve. Explain whether the problem is with the door, the opener, or both so we can prepare for the visit.'],
            ['q' => 'Is it safe to open a Reedsburg garage door with a broken spring?', 'a' => 'Leave it closed if possible. The door can be extremely heavy and may fall, while lifting it can also damage the opener or panels.'],
        ],
    ],
    'evansville' => [
        'label' => 'Evansville',
        'metro' => 'madison',
        'completedJobs' => 15,
        'intro' => 'Evansville garage door work from Twins includes repairs, opener installation, and complete door replacement. Our service data lists 15 completed jobs in Evansville.',
        'localNotes' => 'Cold mornings can make an Evansville door reveal problems that were quieter in warm weather. Slow travel, a heavy manual lift, or a sharp pop can point to balance or hardware trouble rather than a remote-control issue.',
        'faq' => [
            ['q' => 'Why is my Evansville garage door slower in winter?', 'a' => 'Thickened lubricant, stiff seals, contracting metal, and a weakening spring can increase resistance. We check the moving parts and balance before changing opener settings.'],
            ['q' => 'What does Twins inspect during an Evansville service call?', 'a' => 'We look at the springs, cables, drums, rollers, hinges, tracks, sections, seals, opener, controls, and safety reverse. The diagnosis ties the symptom to the failed or worn component.'],
        ],
    ],
    'monroe' => [
        'label' => 'Monroe',
        'metro' => 'madison',
        'completedJobs' => 14,
        'intro' => 'Twins provides garage door and opener service in Monroe, including repair and new installation. We have 14 completed Monroe jobs on record.',
        'localNotes' => 'The distance to Monroe makes a clear first report valuable. A wide photo of the door plus close views of the spring, cables, damaged section, and opener label can answer practical parts questions while everyone stays clear of the mechanism.',
        'faq' => [
            ['q' => 'Can Twins replace a garage door opener in Monroe?', 'a' => 'Yes, we install new openers after confirming that the door itself is balanced and safe to operate. Travel, force, controls, and safety reversal are set as part of the job.'],
            ['q' => 'What budget range should I use for a Monroe opener?', 'a' => 'Completed opener installations between July 2025 and July 2026 typically cost $900 to $1,450. That history is for planning only, not a quote for a specific model or garage.'],
        ],
    ],
    'cross-plains' => [
        'label' => 'Cross Plains',
        'metro' => 'madison',
        'completedJobs' => 12,
        'intro' => 'Cross Plains homeowners use Twins for spring repairs, cable and roller work, opener trouble, and new doors. The verified total for Cross Plains is 12 completed jobs.',
        'localNotes' => 'Wind and blowing snow can pack the sides of a Cross Plains garage door and cover the safety sensors near the floor. Clear the opening and sensor lenses before assuming the opener needs adjustment.',
        'faq' => [
            ['q' => 'Can snow make my Cross Plains garage door stop closing?', 'a' => 'Yes, snow or moisture can block the photo-eye beam, freeze the bottom seal, or add resistance at the threshold. Check those visible areas without reaching near cables, springs, or moving hardware.'],
            ['q' => 'Does Twins repair garage door sections in Cross Plains?', 'a' => 'We assess dented, cracked, or bent sections and check whether a compatible replacement is available. Structural damage, door age, and the condition of the remaining sections determine whether that repair makes sense.'],
        ],
    ],
    'cambridge' => [
        'label' => 'Cambridge',
        'metro' => 'madison',
        'completedJobs' => 11,
        'intro' => 'Our crew handles garage door repairs, replacement doors, and opener work in Cambridge. Twins has completed 11 Cambridge jobs in the service record.',
        'localNotes' => 'An uneven Cambridge garage floor can leave daylight under one side even when the door itself is level. The fix may involve the seal or threshold, but the door should not be forced out of square to match concrete that slopes.',
        'faq' => [
            ['q' => 'Can Twins fix a gap under my Cambridge garage door?', 'a' => 'We check the bottom seal, floor, track position, cable tension, and door level. That tells us whether the gap comes from worn weather material, an uneven slab, or a mechanical alignment problem.'],
            ['q' => 'How much have Cambridge garage door repairs run?', 'a' => 'The historical repair range for completed work from July 2025 to July 2026 is $400 to $1,050. A diagnosis is still needed before we can price the work at your home.'],
        ],
    ],
    'windsor' => [
        'label' => 'Windsor',
        'metro' => 'madison',
        'completedJobs' => 11,
        'intro' => 'Twins repairs garage doors and openers for Windsor homes, and we install new equipment when repair does not pencil out. We have 11 completed Windsor jobs in our data.',
        'localNotes' => 'Newer-looking hardware can still fall out of adjustment after a full Windsor winter. Watch for a door that no longer sits evenly, rubs one track, or needs extra opener force near the floor.',
        'faq' => [
            ['q' => 'Why does my Windsor garage door rub on one side?', 'a' => 'Track alignment, worn rollers, cable tension, a shifted bracket, or a damaged section may be pulling the door sideways. Continuing to run it can deepen the wear, so the cause should be corrected first.'],
            ['q' => 'Can I buy a new Clopay door through Twins in Windsor?', 'a' => 'Yes, we are an authorized Clopay dealer and install new garage doors. We measure the opening and review the construction and design choices that fit the house and budget.'],
        ],
    ],
    'brooklyn' => [
        'label' => 'Brooklyn',
        'metro' => 'madison',
        'completedJobs' => 10,
        'intro' => 'Twins comes to Brooklyn for garage door repairs, opener problems, and replacement projects. The registry shows 10 completed jobs assigned to Brooklyn.',
        'localNotes' => 'A detached Brooklyn garage may stay colder than the house and expose stiff seals or slow opener grease sooner. If the door becomes hard to lift by hand, the spring system needs attention regardless of the garage temperature.',
        'faq' => [
            ['q' => 'Do you service detached garages in Brooklyn?', 'a' => 'Yes, we work on detached and attached residential garages. Let us know about limited power, a narrow drive, or another access detail that affects the work area.'],
            ['q' => 'What should I check before calling about a Brooklyn opener?', 'a' => 'Confirm the garage has power and that nothing blocks the safety sensors or doorway. Do not adjust springs, cables, or opener force to overcome a door that feels heavy.'],
        ],
    ],
    'rio' => [
        'label' => 'Rio',
        'metro' => 'madison',
        'completedJobs' => 10,
        'intro' => 'Rio homeowners can turn to Twins for garage door and opener repairs or a new installation. We have recorded 10 completed Rio jobs.',
        'localNotes' => 'Winter snow can narrow access around a Rio garage, so a cleared path to the door and opener helps the technician work safely. Keep loose cables and broken springs untouched while preparing the area.',
        'faq' => [
            ['q' => 'Will Twins bring garage door parts to a Rio service visit?', 'a' => 'We stock common repair parts and use the symptoms and photos you provide to prepare. Some door-specific sections, glass, or hardware may need to be identified and ordered.'],
            ['q' => 'Can you replace both springs on a Rio garage door?', 'a' => 'We inspect the spring setup and replace the parts required for a safe, balanced door. Because the system is under high tension, spring decisions should follow an in-person assessment.'],
        ],
    ],
    'new-glarus' => [
        'label' => 'New Glarus',
        'metro' => 'madison',
        'completedJobs' => 10,
        'intro' => 'Our technicians repair and install garage doors and openers in New Glarus. The completed-job count for New Glarus is 10.',
        'localNotes' => 'Sloped approaches can send meltwater toward a New Glarus garage threshold. Good bottom sealing matters, but drainage and an even closing edge matter too because a thicker seal cannot correct standing water.',
        'faq' => [
            ['q' => 'Can a new seal stop water under my New Glarus garage door?', 'a' => 'A sound bottom seal can close small gaps, but it will not fix poor drainage or a badly uneven slab. We inspect how the door meets the floor before promising what new weather material can do.'],
            ['q' => 'Does Twins install complete door-and-opener systems in New Glarus?', 'a' => 'Yes, we can replace the door and opener together. Historical combined installations from July 2025 through July 2026 ranged from $4,400 to $7,250 and should be used only as a planning guide.'],
        ],
    ],
    'fall-river' => [
        'label' => 'Fall River',
        'metro' => 'madison',
        'completedJobs' => 10,
        'intro' => 'Twins services Fall River garage doors with repairs, opener work, and new equipment. Our job data includes 10 completed projects for Fall River.',
        'localNotes' => 'Blowing snow around a Fall River garage can crust over the bottom seal and collect beside the tracks. Removing that buildup before operating the door prevents an avoidable jam at the coldest part of the opening.',
        'faq' => [
            ['q' => 'Why did my Fall River garage door cable slip off?', 'a' => 'The door may have met an obstruction, lost spring tension, gone out of level, or developed track trouble. A loose cable can still be under tension, so leave it in place for a technician.'],
            ['q' => 'Can Twins repair an older opener in Fall River?', 'a' => 'We diagnose the opener and the door it moves, then check whether the failed part is serviceable. If repair is not practical, we can explain replacement choices without hiding the condition of the existing door.'],
        ],
    ],
    'beloit' => [
        'label' => 'Beloit',
        'metro' => 'madison',
        'completedJobs' => 10,
        'intro' => 'Beloit homeowners can call Twins for garage door repair, opener installation, and new doors. Twins has 10 completed Beloit jobs in the verified service history.',
        'localNotes' => 'Road salt carried into a Beloit garage settles where lower brackets, cables, and sections are most exposed. Rust around those load-bearing parts deserves a closer look before a cable frays or a fastener loses its grip.',
        'faq' => [
            ['q' => 'What garage door rust should concern a Beloit homeowner?', 'a' => 'Surface marks on a panel are different from corrosion on cables, bottom brackets, springs, or structural supports. We look at the location and depth of the rust before recommending repair or replacement.'],
            ['q' => 'How much does a typical Beloit repair cost?', 'a' => 'For completed jobs between July 2025 and July 2026, the planning range for garage door repair was $400 to $1,050. The actual total follows the diagnosis and approved scope.'],
        ],
    ],
    'marshall' => [
        'label' => 'Marshall',
        'metro' => 'madison',
        'completedJobs' => 9,
        'intro' => 'Twins takes care of garage door repairs and installations in Marshall. The service record contains 9 completed Marshall jobs.',
        'localNotes' => 'A Marshall garage door that faces open wind may flex and rattle even when it still travels. Loose struts, hinges, or fasteners should be corrected before repeated movement enlarges the holes in a section.',
        'faq' => [
            ['q' => 'Can you strengthen a flexing garage door in Marshall?', 'a' => 'We first check the door construction, section condition, struts, hinges, and opener attachment. Reinforcement can help in some cases, while a cracked or badly damaged section may call for a different repair.'],
            ['q' => 'Does the Twins diagnostic charge apply in Marshall?', 'a' => 'Our service call and diagnostic carries a $49 charge. If you approve and complete the repair with Twins, that charge is waived.'],
        ],
    ],
    'columbus' => [
        'label' => 'Columbus',
        'metro' => 'madison',
        'completedJobs' => 9,
        'intro' => 'Our crew repairs residential garage doors and openers in Columbus and installs replacements when needed. We have 9 completed Columbus jobs on record.',
        'localNotes' => 'Condensation can form on cold metal inside a Columbus garage when warmer damp air arrives. Regularly checking lower hardware and wiping away moisture helps expose corrosion before it reaches cables or brackets.',
        'faq' => [
            ['q' => 'Why is there condensation on my Columbus garage door?', 'a' => 'Warm humid air meeting a cold door can leave water on the inside surface. Ventilation, insulation, indoor moisture, and the door construction all affect how much forms.'],
            ['q' => 'Can Twins install an insulated door in Columbus?', 'a' => 'Yes, we install new garage doors and can compare insulation choices. We also measure the opening and confirm the track and headroom requirements before quoting the job.'],
        ],
    ],
    'sauk-city' => [
        'label' => 'Sauk City',
        'metro' => 'madison',
        'completedJobs' => 8,
        'intro' => 'Sauk City garage door service from Twins includes repairs, opener replacements, and new door installation. Our records show 8 completed Sauk City jobs.',
        'localNotes' => 'Moist air and temperature changes can reveal small gaps around a Sauk City door. Light at the sides may come from worn perimeter seal, but an uneven gap can also point to track or cable alignment.',
        'faq' => [
            ['q' => 'Can Twins seal drafts around my Sauk City garage door?', 'a' => 'We can replace suitable perimeter and bottom seals and check whether the door sits square in the opening. Mechanical alignment comes first when the gaps are uneven.'],
            ['q' => 'What if my Sauk City opener works but the door is heavy?', 'a' => 'Stop using the opener until the balance is checked. A motor can sometimes move a poorly balanced door, but the extra load shortens component life and can hide a spring problem.'],
        ],
    ],
    'milton' => [
        'label' => 'Milton',
        'metro' => 'madison',
        'completedJobs' => 8,
        'intro' => 'Twins repairs broken and unreliable garage doors in Milton and installs new doors and openers. The verified data shows 8 completed Milton jobs.',
        'localNotes' => 'A small stone or packed snow at the edge of a Milton track can tip a roller or stop the door. Clear visible debris with the door still, but leave bent track and displaced rollers for trained hands.',
        'faq' => [
            ['q' => 'Can I put a Milton garage door back on track myself?', 'a' => 'That is not a safe homeowner repair when cables, spring tension, or door weight are involved. Secure the area and avoid moving the door until the track system is inspected.'],
            ['q' => 'Does Twins work on garage door remotes in Milton?', 'a' => 'We troubleshoot remotes, wall controls, receivers, sensors, and opener programming. A control problem is only one possibility, so we also confirm that the door travels freely.'],
        ],
    ],
    'lodi' => [
        'label' => 'Lodi',
        'metro' => 'madison',
        'completedJobs' => 8,
        'intro' => 'Lodi homeowners can get garage door repair, opener work, and replacement installation from Twins. We have completed 8 jobs tied to Lodi.',
        'localNotes' => 'A Lodi door exposed to winter wind may leak cold air first at the corners where seals meet. Replacing weather material helps only after the tracks and closing position let the door sit evenly.',
        'faq' => [
            ['q' => 'Why does cold air leak around my Lodi garage door?', 'a' => 'Worn seals, an uneven slab, shifted tracks, or a door that is not closing fully can create gaps. We identify the source before choosing new weather stripping or a mechanical adjustment.'],
            ['q' => 'Can Twins replace a cracked garage door section in Lodi?', 'a' => 'We can check whether the section is still available and whether the remaining door is sound. The safest and most economical answer depends on the damage and the compatibility of replacement parts.'],
        ],
    ],
    'barneveld' => [
        'label' => 'Barneveld',
        'metro' => 'madison',
        'completedJobs' => 7,
        'intro' => 'Twins provides garage door and opener service for Barneveld homes. The completed work history includes 7 Barneveld jobs.',
        'localNotes' => 'Strong temperature swings can change how a Barneveld door fits and sounds from one week to the next. A pop from the spring area, slack cable, or sudden weight change calls for inspection rather than another opener cycle.',
        'faq' => [
            ['q' => 'What does a popping garage door sound mean in Barneveld?', 'a' => 'The sound can come from a binding section, worn roller, loose hinge, spring movement, or track problem. We inspect the location under safe conditions before deciding what needs repair.'],
            ['q' => 'Can you install a new Barneveld door without changing the opener?', 'a' => 'Possibly, if the opener is compatible, correctly sized, and in good working order. We evaluate it with the new door requirements instead of assuming it must be replaced.'],
        ],
    ],
    'fort-atkinson' => [
        'label' => 'Fort Atkinson',
        'metro' => 'madison',
        'completedJobs' => 6,
        'intro' => 'Fort Atkinson garage doors and openers are within the Twins service area. We have 6 completed jobs attributed to Fort Atkinson and handle both repair and replacement work.',
        'localNotes' => 'Spring rain can carry sand and leaves to a Fort Atkinson garage opening. Debris near the bottom brackets, rollers, or sensors can create noise and closing trouble, but those loaded parts should stay untouched.',
        'faq' => [
            ['q' => 'Do you repair safety sensors in Fort Atkinson?', 'a' => 'Yes, we test sensor power, wiring, alignment, lenses, and the opener response. We also look for track or floor obstructions that can mimic a sensor fault.'],
            ['q' => 'What is the historical price range for a new door here?', 'a' => 'Completed new garage door installations from July 2025 through July 2026 ranged from $3,000 to $4,100. Product choices and conditions at the Fort Atkinson opening determine the real quote.'],
        ],
    ],
    'prairie-du-sac' => [
        'label' => 'Prairie du Sac',
        'metro' => 'madison',
        'completedJobs' => 4,
        'intro' => 'Twins brings garage door repair and installation service to Prairie du Sac. Our data lists 4 completed jobs for Prairie du Sac.',
        'localNotes' => 'Seasonal moisture can swell wood trim or soften old perimeter seal around a Prairie du Sac garage. The door needs free track clearance, while the trim and weather material need to meet it without rubbing.',
        'faq' => [
            ['q' => 'Can trim make my Prairie du Sac garage door stick?', 'a' => 'Yes, swollen or shifted trim and weather seal can drag against the sections. We check the contact point along with track alignment and door balance before trimming or replacing material.'],
            ['q' => 'Will Twins service Prairie du Sac even with a smaller job history?', 'a' => 'Yes, the city is part of our service area. The job count is a record of completed work, not a limit on where the crew goes.'],
        ],
    ],
    'watertown' => [
        'label' => 'Watertown',
        'metro' => 'madison',
        'completedJobs' => 16,
        'intro' => 'Our technicians repair garage doors and openers in Watertown and quote replacement systems when the existing door is worn out. Twins has recorded 16 completed Watertown jobs.',
        'localNotes' => 'For a Watertown visit, note whether the problem began after a power outage, freeze, impact, or loud snap. That sequence helps separate a control issue from a mechanical failure before we arrive.',
        'faq' => [
            ['q' => 'Why will my Watertown garage door not work after a power outage?', 'a' => 'The opener may need power restored, controls reconnected, or travel settings checked, depending on the unit. We also confirm that the manual door still moves safely and remains balanced.'],
            ['q' => 'Can Twins replace an old door and opener together in Watertown?', 'a' => 'Yes, we can measure and quote a combined installation. Completed door-and-opener projects between July 2025 and July 2026 ranged from $4,400 to $7,250, which is a planning range rather than a promise.'],
        ],
    ],
    'fitchburg' => [
        'label' => 'Fitchburg',
        'metro' => 'madison',
        'completedJobs' => null,  // shares ZIPs with Madison; per-city count not separable
        'intro' => 'Twins repairs and installs garage doors and openers for Fitchburg homeowners. Describe the sound, movement, or damage you see, and our crew can narrow down what needs attention.',
        'localNotes' => 'Fitchburg winters can harden the bottom seal and leave ice at the threshold. If the door sticks to the slab, clear the ice first because the opener is built to move a balanced door, not break it loose from the floor.',
        'faq' => [
            ['q' => 'Is a broken spring safe to replace myself in Fitchburg?', 'a' => 'No. Garage door springs are under high tension and can cause severe injury, so the door should stay closed until a trained technician handles the work.'],
            ['q' => 'Can you work on an opener even if Twins did not install it?', 'a' => 'Yes, we diagnose opener trouble as part of the complete door system. The first check covers power, controls, sensors, travel, force, and the balance of the door itself.'],
        ],
    ],
    'monona' => [
        'label' => 'Monona',
        'metro' => 'madison',
        'completedJobs' => null,  // shares ZIPs with Madison; per-city count not separable
        'intro' => 'Twins handles garage door repairs, opener service, and replacement installations for Monona homes. Tell us what changed in the door movement, sound, or controls, and we will start with the likely system.',
        'localNotes' => 'Monona garages see the same wet snow, hard freezes, and humid summers that wear on seals and moving hardware across the Madison area. Catching rust, frayed cable, or uneven travel early can keep a small defect from spreading.',
        'faq' => [
            ['q' => 'Can Twins repair a frayed garage door cable in Monona?', 'a' => 'Yes, but do not touch or cut the cable because it may still carry spring tension. Keep the door still until the counterbalance system can be made safe.'],
            ['q' => 'How do you decide whether a Monona opener needs replacement?', 'a' => 'We check the power, controls, sensors, drive, rail, travel, force, and door balance. Replacement is recommended only after the failed component and the condition of the full system are clear.'],
        ],
    ],
    'milwaukee' => [
        'label' => 'Milwaukee',
        'metro' => 'milwaukee',
        'completedJobs' => 1,
        'intro' => 'Twins Garage Doors provides garage door repair, opener service, and replacement installation in Milwaukee. We inspect the complete system, explain what failed, and give you clear options before work begins.',
        'localNotes' => 'Milwaukee snow, freezing rain, and road salt work hardest on the bottom seal and hardware closest to the slab. Ice at the threshold or corrosion around a lower bracket should be handled before the door is forced.',
        'faq' => [
            ['q' => 'What happens during a Milwaukee garage door inspection?', 'a' => 'We check the door balance, springs, cables, rollers, hinges, tracks, opener, controls, and safety sensors. You receive an explanation of the problem and the practical repair or replacement choices.'],
            ['q' => 'What Milwaukee garage door problems can you handle?', 'a' => 'We diagnose broken springs, loose cables, worn rollers, bent tracks, damaged sections, failed seals, opener faults, and safety-sensor trouble. New garage doors and openers are available when repair is not the right path.'],
        ],
    ],
    'wauwatosa' => [
        'label' => 'Wauwatosa',
        'metro' => 'milwaukee',
        'completedJobs' => null,
        'intro' => 'Twins repairs garage doors and openers and installs replacement equipment for Wauwatosa homeowners. We look at the door and opener as one system so the recommendation addresses the cause of the problem.',
        'localNotes' => 'Wauwatosa freeze and thaw cycles can leave the bottom seal stuck to the floor, especially after wet snow. Check the threshold before running the opener because pulling against ice can damage the drive or top section.',
        'faq' => [
            ['q' => 'Who owns the Wauwatosa garage door location?', 'a' => 'Twins Garage Doors is family owned and operated by twin brothers. It is not a franchise, and the Wauwatosa operation belongs to the same independent company.'],
            ['q' => 'Can Twins replace a broken spring in Wauwatosa?', 'a' => 'Yes, we repair garage door spring systems. Springs are under high tension, so leave the door closed and keep hands away from the spring, cables, and brackets.'],
        ],
    ],
    'waukesha' => [
        'label' => 'Waukesha',
        'metro' => 'milwaukee',
        'completedJobs' => 1,
        'intro' => 'Twins provides garage door repair, opener service, and replacement installation in Waukesha. We diagnose the problem, explain the condition of the system, and outline the work before you decide how to proceed.',
        'localNotes' => 'Cold weather can make a Waukesha door with marginal balance feel much heavier. If the opener hums, the rail flexes, or the door rises unevenly, stop cycling it until the spring and cable system is checked.',
        'faq' => [
            ['q' => 'Can Twins repair a Waukesha garage door that feels unusually heavy?', 'a' => 'Yes. We check the spring system, cables, rollers, tracks, and door balance before testing the opener. A heavy door often points to a counterbalance problem that should not be masked by increasing opener force.'],
            ['q' => 'What does a Waukesha diagnostic visit cost?', 'a' => 'The Waukesha service call and diagnostic costs $49. Twins waives it when you complete the repair with us.'],
        ],
    ],
    'brookfield' => [
        'label' => 'Brookfield',
        'metro' => 'milwaukee',
        'completedJobs' => null,
        'intro' => 'Twins helps Brookfield homeowners with garage door repair, opener problems, and replacement planning. We identify the failed part, check the connected hardware, and explain whether repair or replacement is the sounder choice.',
        'localNotes' => 'Salt and meltwater carried into a Brookfield garage collect near cable ends, bottom brackets, and lower door sections. Those components hold real loads, so rust there deserves more attention than a cosmetic spot higher on a panel.',
        'faq' => [
            ['q' => 'What garage door problems do you repair in Brookfield?', 'a' => 'We repair broken springs, damaged cables, worn rollers, track problems, failed seals, noisy hardware, opener faults, and safety-sensor issues. We also inspect the rest of the system for wear connected to the original failure.'],
            ['q' => 'Can you install a Clopay garage door in Brookfield?', 'a' => 'We are authorized to sell and install Clopay garage doors. We can measure the opening and explain the available construction, insulation, window, and finish choices.'],
        ],
    ],
    'new-berlin' => [
        'label' => 'New Berlin',
        'metro' => 'milwaukee',
        'completedJobs' => null,
        'intro' => 'Twins provides garage door repair, opener service, and replacement installation in New Berlin. We start with a complete diagnosis and explain the work in plain language so you can make an informed decision.',
        'localNotes' => 'A New Berlin door that drags near the floor may have a stiff seal, ice at the threshold, track resistance, or poor balance. Raising the opener force can hide the symptom while putting more strain on the door.',
        'faq' => [
            ['q' => 'What should I expect during a New Berlin garage door service visit?', 'a' => 'The technician inspects the door, counterbalance system, moving hardware, opener, and safety controls. You receive a clear explanation of the problem and the available repair or replacement options before work begins.'],
            ['q' => 'Can Twins fix a New Berlin door that reverses at the floor?', 'a' => 'Yes, we check the safety sensors, threshold, track resistance, travel limits, force settings, and door balance. The correction depends on which part interrupts the closing cycle.'],
        ],
    ],
    'greenfield' => [
        'label' => 'Greenfield',
        'metro' => 'milwaukee',
        'completedJobs' => null,
        'intro' => 'Twins handles garage door and opener calls in Greenfield, including repairs, safety checks, and replacement installation. We trace the symptom through the complete system instead of guessing from one noisy or damaged part.',
        'localNotes' => 'Wet snow can pack around a Greenfield garage door and block the photo eyes mounted close to the floor. Clear both lenses and the space between them before changing opener settings or assuming the motor has failed.',
        'faq' => [
            ['q' => 'Is Twins Garage Doors a franchise in Greenfield?', 'a' => 'No. Twins Garage Doors is a family-owned company operated by twin brothers, and the Greenfield service area is handled by the same independent business.'],
            ['q' => 'Do you repair garage door safety sensors in Greenfield?', 'a' => 'We inspect sensor alignment, lenses, wiring, power, and the opener response. We also confirm that the door and tracks are clear of a physical obstruction.'],
        ],
    ],
    'oak-creek' => [
        'label' => 'Oak Creek',
        'metro' => 'milwaukee',
        'completedJobs' => null,
        'intro' => 'Twins works on garage doors and openers for Oak Creek homeowners and can plan a replacement installation when repair is not the right answer. We inspect the opening, hardware, balance, controls, and seals before recommending the work.',
        'localNotes' => 'Cold wind and damp air near Lake Michigan can expose gaps around an Oak Creek garage door. A worn seal is one cause, but uneven cables or shifted tracks can also keep a corner from meeting the floor.',
        'faq' => [
            ['q' => 'Can Twins diagnose drafts around an Oak Creek garage door?', 'a' => 'Yes, we examine the bottom and perimeter seals along with door level, track position, and closing travel. That separates a weather-material problem from a door that is sitting crooked.'],
            ['q' => 'Are you licensed and insured for Oak Creek garage door work?', 'a' => 'Yes. Twins Garage Doors is licensed and insured and operates as a family-owned company rather than a franchise.'],
        ],
    ],
    'rockford' => [
        'label' => 'Rockford',
        'metro' => 'rockford',
        'completedJobs' => null,
        'intro' => 'Twins Garage Doors provides garage door repair, opener service, and replacement installation in Rockford and nearby northern Illinois communities. We inspect the complete door system, explain what failed, and give you clear repair or replacement options before work begins.',
        'localNotes' => 'Rockford winters bring frozen thresholds, stiff seals, and road salt around lower garage door hardware. A door that feels heavy or rises crooked needs a balance check, not more force from the opener.',
        'faq' => [
            ['q' => 'What garage door problems do you repair in Rockford?', 'a' => 'We diagnose broken springs, damaged cables, worn rollers, bent or misaligned tracks, noisy doors, doors that will not close correctly, and opener problems. The technician checks the full system so the recommendation addresses the cause, not only the loudest symptom.'],
            ['q' => 'Do you install garage doors and openers in Rockford?', 'a' => 'Yes. Twins installs replacement garage doors and opener systems after measuring the opening and checking the existing tracks, framing, power, controls, and safety equipment.'],
        ],
    ],
    'loves-park' => [
        'label' => 'Loves Park',
        'metro' => 'rockford',
        'completedJobs' => null,
        'intro' => 'Twins diagnoses garage door and opener failures for Loves Park homeowners and installs replacement equipment when repair is not a sound choice. We check the door balance, moving hardware, controls, and safety system before recommending the work.',
        'localNotes' => 'Snowmelt at a Loves Park garage can refreeze under the bottom seal after sunset. Free the seal from the slab before using the opener so the drive and top section do not take the pull.',
        'faq' => [
            ['q' => 'What should I do if my Loves Park garage door will not open?', 'a' => 'Keep the door closed if possible and avoid repeatedly running the opener. A broken spring, damaged cable, locked door, power problem, or opener fault can cause the same symptom, so the system needs to be checked before force is applied.'],
            ['q' => 'Can Twins repair a broken spring in Loves Park?', 'a' => 'Yes, spring repair is part of our service. Keep the door closed and do not touch the spring or cables because those parts can hold dangerous tension.'],
        ],
    ],
    'machesney-park' => [
        'label' => 'Machesney Park',
        'metro' => 'rockford',
        'completedJobs' => null,
        'intro' => 'Twins serves Machesney Park homeowners with garage door repair, opener service, and replacement installation. We inspect the complete operating system and explain which parts need attention before work begins.',
        'localNotes' => 'Blowing snow can cover the low-mounted safety sensors on a Machesney Park garage door. Clear the lenses and doorway first, but leave cable, spring, track, and force adjustments to a technician.',
        'faq' => [
            ['q' => 'Why will my Machesney Park door not close after snow?', 'a' => 'Snow may block the sensor beam, freeze the bottom seal, or pack into the track area. We check those conditions along with door balance and opener travel when the simple cleanup does not solve it.'],
            ['q' => 'Is Twins a franchise in Machesney Park?', 'a' => 'No, the business is owned and run by twin brothers. The staffed Illinois office is part of Twins Garage Doors itself.'],
        ],
    ],
    'belvidere' => [
        'label' => 'Belvidere',
        'metro' => 'rockford',
        'completedJobs' => null,
        'intro' => 'Twins provides garage door and opener service in Belvidere, including repairs, system inspections, and replacement installations. We identify why the door failed and explain the safest practical way to restore it.',
        'localNotes' => 'Wind can drive grit and leaves against a Belvidere garage threshold and into the edges of the tracks. Scraping, shuddering, or a roller riding near the track lip should be checked before the door comes out of line.',
        'faq' => [
            ['q' => 'Can you fix a Belvidere garage door that came off track?', 'a' => 'We inspect the rollers, tracks, cables, balance, and sections to find what shifted the door. Do not move an off-track door because its weight may no longer be supported correctly.'],
            ['q' => 'Do you service garage door openers in Belvidere?', 'a' => 'Yes. We diagnose power, remotes, wall controls, safety sensors, travel settings, drive components, and the connection between the opener and the door. We also check the door balance so an opener is not blamed for a mechanical problem.'],
        ],
    ],
    'roscoe' => [
        'label' => 'Roscoe',
        'metro' => 'rockford',
        'completedJobs' => null,
        'intro' => 'Roscoe homeowners can call Twins for garage door repair, opener service, and replacement installation. We work on the door, opener, controls, and safety system as one connected setup.',
        'localNotes' => 'A Roscoe garage door can change sound during a deep freeze as seals harden and metal contracts. A sudden pop, slack cable, or heavy lift is different from ordinary cold-weather noise and calls for an inspection.',
        'faq' => [
            ['q' => 'Can Twins quiet a noisy garage door in Roscoe?', 'a' => 'We trace noise through the rollers, hinges, bearings, springs, tracks, sections, and opener mounting. The right repair follows the source rather than a blanket parts swap.'],
            ['q' => 'Can Twins replace a damaged garage door in Roscoe?', 'a' => 'Yes. We measure the opening, inspect the framing and track arrangement, and explain door construction, insulation, window, and finish options before preparing the installation quote.'],
        ],
    ],
    'rockton' => [
        'label' => 'Rockton',
        'metro' => 'rockford',
        'completedJobs' => null,
        'intro' => 'Twins provides garage door and opener service in Rockton, Illinois. The crew repairs failed parts and can measure for a replacement door or opener when the existing equipment is no longer the sound choice.',
        'localNotes' => 'Salt water dripping from vehicles can reach bottom brackets and cable loops in a Rockton garage. Corrosion on loaded hardware matters because those parts help carry and control the door.',
        'faq' => [
            ['q' => 'What rust on a Rockton garage door needs attention?', 'a' => 'Rust on cables, springs, brackets, fasteners, or structural supports is more urgent than a small cosmetic mark. We inspect how deep it is and whether the part still carries its load safely.'],
            ['q' => 'Does Twins install new garage doors in Rockton?', 'a' => 'Yes, we install replacement garage doors and are an authorized Clopay dealer. The opening is measured before product and track options are quoted.'],
        ],
    ],
    'cherry-valley' => [
        'label' => 'Cherry Valley',
        'metro' => 'rockford',
        'completedJobs' => null,
        'intro' => 'Twins repairs garage doors and openers and installs replacement systems for Cherry Valley homeowners. We inspect the complete setup and explain what can be repaired, what should be replaced, and why.',
        'localNotes' => 'Moisture around a Cherry Valley threshold can swell old trim, soften weather seal, or freeze the door to the slab. The repair depends on whether the resistance comes from the opening or the door hardware.',
        'faq' => [
            ['q' => 'Why does my Cherry Valley garage door stick at the bottom?', 'a' => 'Ice, swollen trim, a damaged seal, track resistance, or poor balance can each hold the door near the floor. We separate those causes before adjusting the opener.'],
            ['q' => 'Is Twins licensed and insured for Cherry Valley service?', 'a' => 'Yes. Twins Garage Doors is licensed and insured and operates as a family-owned company rather than a franchise.'],
        ],
    ],
    'poplar-grove' => [
        'label' => 'Poplar Grove',
        'metro' => 'rockford',
        'completedJobs' => null,
        'intro' => 'Twins has added Poplar Grove to the area served from our staffed Rockford office. We can repair garage doors and openers or plan a replacement installation for Illinois homeowners.',
        'localNotes' => 'Open wind can press against a Poplar Grove garage door and make loose hinges, struts, or fasteners rattle. Repeated flexing can widen damaged attachment points, so a new sound deserves a close look.',
        'faq' => [
            ['q' => 'Can you repair a flexing door in Poplar Grove?', 'a' => 'We evaluate the sections, struts, hinges, tracks, and opener connection to see where movement begins. Some doors can be reinforced or repaired, while badly cracked material may need replacement.'],
            ['q' => 'Do you install replacement garage doors in Poplar Grove?', 'a' => 'Yes. We measure the opening, inspect the framing and tracks, and explain door construction, insulation, windows, and finish choices before preparing the quote.'],
        ],
    ],
    'south-beloit' => [
        'label' => 'South Beloit',
        'metro' => 'rockford',
        'completedJobs' => null,
        'intro' => 'Twins serves South Beloit, Illinois with garage door repairs, opener troubleshooting, and replacement installations. Our technicians inspect the door and opener together so the recommendation addresses the underlying failure.',
        'localNotes' => 'Border-area winters give South Beloit doors plenty of freezing moisture and road salt. Check bottom seals for ice and lower hardware for corrosion before forcing a door that has started to bind.',
        'faq' => [
            ['q' => 'Is South Beloit served from Wisconsin or Illinois?', 'a' => 'Twins serves South Beloit from the staffed Rockford, Illinois office. The city belongs to our Illinois market.'],
            ['q' => 'Can you repair a South Beloit opener that hums but will not lift?', 'a' => 'Yes, but stop running it until the door balance is checked. A broken spring or binding door can overload a working motor and make an opener problem look worse.'],
        ],
    ],
    'winnebago' => [
        'label' => 'Winnebago',
        'metro' => 'rockford',
        'completedJobs' => null,
        'intro' => 'Twins services garage doors and openers in Winnebago. We can repair failed components or quote a replacement system after inspecting the door, hardware, controls, and opening.',
        'localNotes' => 'For a Winnebago service trip, photos of the full door, spring area, opener label, and damaged part help the crew prepare. Take them from the floor and keep clear of loose cables or unsupported sections.',
        'faq' => [
            ['q' => 'Will Twins come to Winnebago for garage door repair?', 'a' => 'Yes. When you call, share whether the door is open, closed, crooked, or holding a vehicle so the problem and urgency are clear.'],
            ['q' => 'Can I lift a Winnebago door after its spring breaks?', 'a' => 'Do not try to raise it without professional help. The door may be extremely heavy, can fall without warning, and can damage the opener if the motor is used.'],
        ],
    ],
    'byron' => [
        'label' => 'Byron',
        'metro' => 'rockford',
        'completedJobs' => null,
        'intro' => 'Twins diagnoses and repairs garage door and opener problems for Byron homeowners and installs replacement equipment when needed. We check the whole system before recommending a repair or replacement.',
        'localNotes' => 'A cold Byron garage can stiffen perimeter seals and thicken old lubricant, but a balanced door should still move smoothly by hand. Extra weight, uneven travel, or cable slack points to a mechanical problem beyond the weather.',
        'faq' => [
            ['q' => 'Why is my Byron garage door heavy in cold weather?', 'a' => 'Cold can add resistance, but a weak or broken spring, worn rollers, or binding track may be carrying more of the blame. We test the balance instead of turning up opener force.'],
            ['q' => 'Can Twins repair a Byron garage door that rises unevenly?', 'a' => 'Yes. We inspect the cables, drums, springs, rollers, tracks, and sections to find why one side is moving differently. Stop operating a crooked door because its weight may no longer be supported correctly.'],
        ],
    ],
    'caledonia' => [
        'label' => 'Caledonia',
        'metro' => 'rockford',
        'completedJobs' => null,
        'intro' => 'Twins handles garage door repair, opener service, and replacement installation for Caledonia homeowners. We inspect the door, counterbalance system, moving hardware, and controls before explaining the work.',
        'localNotes' => 'Windblown debris and snow can gather at a Caledonia garage opening and interfere with rollers or safety sensors. Clear what is loose and visible, then stop if the door scrapes, tilts, or leaves a cable slack.',
        'faq' => [
            ['q' => 'What should I do if my Caledonia garage door tilts?', 'a' => 'Stop operating it and keep people away from the opening. A cable, drum, roller, track, or spring issue can leave the door uneven and poorly supported.'],
            ['q' => 'Who performs Twins service in the Caledonia area?', 'a' => 'Service is provided by Twins Garage Doors, a licensed and insured family-owned company run by twin brothers rather than a franchise.'],
        ],
    ],
];
