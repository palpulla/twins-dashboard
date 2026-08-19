<?php
declare(strict_types=1);

/**
 * Fixed service-page records, rewritten 2026-08-18 from the approved copy set
 * in docs/marketing/website-rebuild/copy/services/ (voice rules per
 * copy/guarantee-and-voice.md). Records are market-neutral: one record serves
 * every market prefix, so no city, state, or phone literal appears here; the
 * template supplies the market phone and NAP. Every dollar figure traces to
 * docs/marketing/website-rebuild/data/price-ranges.json (completed HCP jobs,
 * last 24 months) or, on the membership page, the approved TwinShield program.
 * The guarantee line is template-owned and rendered verbatim on every page.
 */
return [
    '/garage-door-repair/' => [
        'h1' => 'Garage Door Repair',
        'directAnswer' => 'The most common garage door repair we run is a broken spring, and most spring jobs land between $575 and $1,225, based on 432 completed jobs over the last two years. Cable repairs usually run $325 to $625. We quote a flat price before work starts and waive the service call fee when we do the repair.',
        'answerFacts' => [
            'cost' => 'Springs $575 to $1,225, cables $325 to $625 on most jobs',
            'timing' => 'Same-day and emergency service in most cases',
            'call' => 'A door that is stuck, crooked, banging, or suddenly heavy',
        ],
        'priceRange' => ['min' => 575, 'max' => 1225],
        'warningSigns' => [
            'A gunshot bang from the garage and a door that now feels like dead weight. That is a snapped spring. Do not lift the door yourself.',
            'A crooked door, a loose dangling cable, or one side moving faster than the other. Cables do the quiet work of keeping the weight balanced.',
            'Nothing happens when you press the button, or the motor grinds without moving the door. That is opener territory.',
            'Daylight under the closed door, snow dust inside, or a bottom seal gone stiff and cracked. A cheap fix with a big comfort difference.',
            'A door stuck open at night or a car trapped before work. Some problems cannot wait for a convenient slot.',
        ],
        'process' => [
            'Book online or call, and a real person confirms your arrival window. You get a text with your tech\'s name when he is on the way.',
            'Your tech inspects the whole system, not just the part that squeaked, and shows you what he found in plain English.',
            'You get one flat number for the whole job. No hourly meter, no surprise line items. Work starts only after you say yes.',
            'We do the repair, run the door through full open-and-close cycles, check the safety reverse, and leave your garage the way we found it.',
        ],
        'costParagraphs' => [
            'It depends on the part. Springs, the most common repair, usually land between $575 and $1,225 including the parts that wear with the springs, based on 432 completed jobs. Cable repairs run $325 to $625 from 50 completed jobs. Those are our own numbers, not a national average.',
            'The service call is $0 when we do the repair, so finding out what is wrong costs you nothing. And the flat quote comes before the work: the number you approve is the number you pay.',
            'The cheapest repair is the one you never need. Our $49 tune-up is a full inspection and lubrication of the whole system, and it catches small wear before it strands you.',
        ],
        'safety' => null,
        'plans' => null,
        'reviewTag' => 'general',
        'faqs' => [
            [
                'question' => 'How much does it cost to repair a garage door?',
                'answer' => 'It depends on the part. Springs, the most common repair, usually land between $575 and $1,225 including the parts that wear with them, and cable repairs run $325 to $625, based on our own completed jobs. You approve a flat quote before work starts, and the service call is $0 when we do the repair.',
            ],
            [
                'question' => 'What is the most common garage door repair?',
                'answer' => 'Broken springs, by a wide margin. They do the actual lifting, they are rated for roughly 10,000 open-and-close cycles, and cold snaps push tired ones over the edge. Cables, rollers, and opener problems fill out the rest of a typical week for our techs.',
            ],
            [
                'question' => 'Can I fix my garage door myself?',
                'answer' => 'Lubricating rollers, replacing remote batteries, and clearing sensor lenses are fine DIY jobs. Springs and cables are not. They hold hundreds of pounds of tension and injure people every year. If the door is heavy, crooked, or went bang, stop using it and call us. Diagnosis is $0 with repair.',
            ],
            [
                'question' => 'How quickly can you get a tech to my house?',
                'answer' => 'We run same-day and emergency service across our service area. Call the number shown on this page, tell us what the door is doing, and we will give you an honest arrival window. You get a text with your tech\'s name when he is on the way.',
            ],
            [
                'question' => 'Do I need to replace both springs if only one broke?',
                'answer' => 'Usually yes, and we will show you why on site. Springs on the same door age together through the same 10,000-cycle life, so the survivor is typically close behind, and a mismatched pair puts uneven strain on the door. Your tech quotes both options flat, one spring or two, and you pick.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Spring Repair', 'route' => 'spring-repair'],
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
            ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
        ],
    ],
    '/garage-door-installation/' => [
        'h1' => 'Garage Door Installation',
        'directAnswer' => 'A new installed single garage door typically runs $2,625 to $3,525, and a double runs $3,425 to $4,400, based on 62 completed installations over the last two years. Style, insulation, and windows move the number inside those ranges. We install Clopay doors, quote flat after measuring your opening, and offer financing through GoodLeap with monthly payments available on new doors.',
        'answerFacts' => [
            'cost' => 'Singles typically $2,625 to $3,525 installed, doubles $3,425 to $4,400',
            'timing' => 'Measure visit first, then a real timeline quoted with your door',
            'call' => 'Rust, spreading cracks, stacking repair bills, or a look you are done with',
        ],
        'priceRange' => ['min' => 2625, 'max' => 4400],
        'warningSigns' => [
            'Rust eating the bottom section or cracks spreading across multiple panels. One dinged panel can often be repaired; a failing structure cannot.',
            'The repair bills keep coming. When springs, cables, and rollers take turns failing on a door that is decades old, the math tips.',
            'The garage is freezing and the house feels it. An uninsulated or gapping door leaks heat all winter into the rooms above and beside it.',
            'You hit it with the car. A hard hit that bends the frame usually means a new door, and insurance often helps.',
        ],
        'process' => [
            'Build the door you want in the online designer, or describe it when you call. We schedule a visit that fits your day.',
            'Your tech measures the opening, checks headroom, tracks, and framing, and walks you through the real options. Then you get one flat installed number.',
            'Clopay builds your configuration: your color, your windows, your insulation. We keep you posted and schedule the install when it arrives.',
            'We remove the old door, install the new one with fresh tracks and springs sized to it, balance and test everything, and haul the old door away.',
        ],
        'costParagraphs' => [
            'A new installed single door, 8 to 10 feet wide, typically runs $2,625 to $3,525. A double door, 14 to 20 feet wide, runs $3,425 to $4,400. Those ranges come from 62 of our own completed installations over the last two years, not a national average.',
            'What moves the number: insulation level, window sections, style line, and hardware. The ranges are for the door itself installed; adding a new opener at the same time is quoted separately, usually $775 to $1,400.',
            'Financing through GoodLeap means monthly payments are available on new doors. And the flat quote comes after real measurements, so the number you approve is the number you pay.',
        ],
        'safety' => null,
        'plans' => null,
        'reviewTag' => 'installation',
        'faqs' => [
            [
                'question' => 'How much does a new garage door cost?',
                'answer' => 'A new installed single door typically runs $2,625 to $3,525, and a double runs $3,425 to $4,400, based on 62 completed installations over the last two years. Insulation, windows, and style move the number. You approve one flat installed quote after we measure, and GoodLeap financing means monthly payments are available.',
            ],
            [
                'question' => 'How long does a garage door usually last?',
                'answer' => 'The door itself commonly outlasts its moving parts: springs are rated for about 10,000 cycles, roughly 7 to 10 years of normal use, while a well-maintained steel door keeps going long after its first spring set. Panels, seals, and hardware age with weather and use, which is what our $49 tune-up keeps an eye on.',
            ],
            [
                'question' => 'Can you install a new garage door on my old tracks?',
                'answer' => 'We do not, and you should not want us to. Tracks and springs are engineered for a specific door\'s weight and thickness, and a new door on worn, mismatched tracks runs rough and wears fast. Our installed prices include new tracks and springs sized to your door.',
            ],
            [
                'question' => 'What type of garage door should I buy?',
                'answer' => 'For a cold climate, insulated steel is the default answer: warmer, quieter, and stiffer. From there it is style and budget: Classic panel looks, Gallery grooved designs, Coachman carriage-house, or Modern Steel contemporary lines. Build combinations in the online designer, then your tech confirms fit and quotes flat.',
            ],
            [
                'question' => 'Do I need to replace the whole door, or just a panel?',
                'answer' => 'Sometimes just the panel. If one section is dented and the rest of the door is sound, a section replacement is the honest fix and we will quote it. When damage crosses multiple panels or the frame is bent, replacement wins. Your tech shows you both numbers and you decide.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Collections', 'route' => 'garage-doors'],
            ['label' => 'Design Your Door', 'route' => 'door-builder'],
            ['label' => 'Garage Door Openers', 'route' => 'openers'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
        ],
    ],
    '/garage-door-spring-repair/' => [
        'h1' => 'Garage Door Spring Repair',
        'directAnswer' => 'Most spring jobs land between $575 and $1,225 including the parts that wear with the springs, based on 432 completed jobs over the last two years. Springs are rated for about 10,000 open-and-close cycles, roughly 7 to 10 years for most homes. If your door made a loud bang and will not lift, stop using it and call us.',
        'answerFacts' => [
            'cost' => 'Most jobs $575 to $1,225, and that is the whole ticket',
            'timing' => 'Same-day service in most cases',
            'call' => 'A loud bang, a heavy door, or a visible gap in the spring coil',
        ],
        'priceRange' => ['min' => 575, 'max' => 1225],
        'warningSigns' => [
            'A loud bang from the garage. That gunshot sound was the spring snapping and unwinding in a fraction of a second. Do not try to lift the door.',
            'The door feels heavy or will not lift. A healthy spring makes a door feel almost weightless; if yours suddenly takes real muscle, the spring is losing tension or already gone.',
            'A visible gap in the coil above the door. A healthy torsion spring is one continuous spiral. A gap of an inch or two means it has snapped.',
            'The door lifts crooked or slams shut. Uneven or runaway movement means the counterbalance system is not holding its side of the bargain.',
        ],
        'process' => [
            'Leave the door where it is and do not run the opener over and over. Book online or call, and a real person confirms your arrival window.',
            'Your tech inspects the whole system: the springs, the cables, the drums, and the bearings, since they wear together. He shows you what he found before anything else happens.',
            'You get one flat number for the whole job. No hourly meter, no surprise line items. Work starts only after you say yes.',
            'We do the repair, run the door through full open-and-close cycles, check the safety reverse, and leave your garage the way we found it.',
        ],
        'costParagraphs' => [
            'Most spring jobs land between $575 and $1,225, and that is the whole ticket: springs are usually replaced together with the parts that wear alongside them, like cables, bearings, and rollers, which is what a real customer actually pays. That range comes from 432 of our own completed jobs over the last two years, not a national average.',
            'What moves the number: door weight and size, one spring or two, and the parts that need replacing with them. Springs are rated for about 10,000 cycles, so this is not a bill you should see often.',
            'Two things keep your cost down: the service call is $0 when we do the repair, and the flat quote comes before work starts. The number you approve is the number you pay.',
        ],
        'safety' => 'Garage door springs are under dangerous tension and should be handled by trained professionals. Do not try to replace, adjust, wind, or unwind a spring, and do not lift a door with a broken spring by hand. It is heavier than it looks, and people get badly hurt trying this every year.',
        'plans' => null,
        'reviewTag' => 'springs',
        'faqs' => [
            [
                'question' => 'How much does garage door spring replacement cost?',
                'answer' => 'Most spring jobs land between $575 and $1,225 including the parts that wear with the springs, like cables and rollers, based on 432 of our own completed jobs. You approve a flat quote before work starts, and the $0 service call with repair means diagnosis costs nothing.',
            ],
            [
                'question' => 'How long do garage door springs last?',
                'answer' => 'Springs are rated in cycles, one full open and close, and standard springs run about 10,000 cycles. For most homes that is roughly 7 to 10 years. Heavy daily use burns through them faster, and winter cold snaps tend to finish off springs that were already near the end.',
            ],
            [
                'question' => 'Can I replace garage door springs myself?',
                'answer' => 'Please do not. A wound torsion spring stores hundreds of pounds of energy, and releasing it wrong sends steel and winding bars flying. People are seriously injured doing this every year. Call us instead; with the $0 service call with repair, the diagnosis costs you nothing.',
            ],
            [
                'question' => 'Do I need to replace both springs if only one broke?',
                'answer' => 'Usually yes. Both springs have aged through the same 10,000-cycle life, so the survivor is typically close behind, and a fresh spring paired with a tired one lifts unevenly. Your tech quotes one spring and two as separate flat numbers, and the call is yours.',
            ],
            [
                'question' => 'What is the difference between torsion and extension springs?',
                'answer' => 'Torsion springs are the wound coil on a bar above your door; they twist to lift. Extension springs stretch along the horizontal tracks. Most doors built in recent decades use torsion, which lifts more smoothly. We repair and replace both types.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
            ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
            ['label' => 'Maintenance and Tune-Ups', 'route' => 'maintenance-plans'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
        ],
    ],
    '/garage-door-opener-repair/' => [
        'h1' => 'Garage Door Opener Repair',
        'directAnswer' => 'Small opener repairs like sensors, remotes, and keypads usually run $100 to $300, based on our own completed jobs. A failing motor head usually means replacement, which runs $775 to $1,400 installed. If you press the button and nothing happens, or the motor runs without moving the door, call us. We work on every major brand.',
        'answerFacts' => [
            'cost' => 'Small repairs usually $100 to $300, new installs $775 to $1,400',
            'timing' => 'Same-day service in most cases',
            'call' => 'A dead button, a grinding motor, flashing lights, or a door that reverses on its own',
        ],
        'priceRange' => ['min' => 100, 'max' => 300],
        'warningSigns' => [
            'Nothing happens when you press the button. Could be the remote battery, the logic board, or power to the unit. Often one of the cheapest calls we run.',
            'The motor runs but the door does not move. That usually means a stripped gear or a broken trolley. Stop running it before the grinding makes things worse.',
            'The door reverses for no reason. Nine times out of ten this is the safety sensors: misaligned, dirty, or with a failing wire.',
            'The remote only works up close. A dying battery or an aging logic board losing its hearing. Worth fixing before it strands you in the driveway.',
        ],
        'process' => [
            'Tell us what the opener does when you press the button, and do not keep cycling it. A real person confirms your arrival window.',
            'Your tech tests the motor, the drive, the photo eyes, and the safety reverse, and checks whether the door itself moves freely by hand.',
            'We tell you straight whether a repair makes sense or the unit is at the end of its road, with a flat number for each path.',
            'You approve the price, we do the work, and the door runs its full safety tests before we leave.',
        ],
        'costParagraphs' => [
            'Small opener repairs like sensors, remotes, keypads, and surge protectors usually run $100 to $300, based on our own completed jobs. A failing motor head usually means replacement, which runs $775 to $1,400 installed as a LiftMaster.',
            'What moves the number: which part failed, the age and brand of the unit, and whether the repair is worth making on a motor near the end of its life. Your tech tells you which side of that line you are on before anything starts.',
            'The service call is $0 when we do the repair, and the flat quote comes first. The number you approve is the number you pay.',
        ],
        'safety' => null,
        'plans' => null,
        'reviewTag' => 'openers',
        'faqs' => [
            [
                'question' => 'How much does garage door opener repair cost?',
                'answer' => 'Small repairs like safety sensors, remotes, and keypads usually run $100 to $300, based on our own completed jobs. A failing motor head usually means replacement, which runs $775 to $1,400 installed. Either way you approve a flat quote first, and the service call is $0 with repair.',
            ],
            [
                'question' => 'Why will my garage door not open when I press the button?',
                'answer' => 'Start simple: remote battery, then the wall button, then whether the opener has power. If those check out, it is usually the logic board or a mechanical failure in the drive. And if the door also feels heavy by hand, suspect a spring, not the opener. Call us and we will sort out which it is.',
            ],
            [
                'question' => 'Why are the lights on my garage door opener flashing?',
                'answer' => 'Flashing lights are the opener\'s error code, and most often they mean the safety sensors are blocked, dirty, or out of alignment. Check for a leaf or cobweb across the lens first. If the flashing continues, the sensors or their wiring need service, typically in the $100 to $300 small-repair range.',
            ],
            [
                'question' => 'How do I open my garage door during a power outage?',
                'answer' => 'Pull the red emergency release cord hanging from the opener rail, then lift the door by hand. It should feel manageable; if it is very heavy, you may have a spring problem, so stop and call us. Newer LiftMaster units we install include battery backup, so an outage does not even slow them down.',
            ],
            [
                'question' => 'Do you repair my opener brand?',
                'answer' => 'Yes. We repair every major brand, belt or chain, smart or basic, and we install new LiftMaster units when the old motor is not worth saving. Small part swaps usually run $100 to $300; a new installed LiftMaster runs $775 to $1,400. Your tech quotes both paths when it is a close call.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Openers', 'route' => 'openers'],
            ['label' => 'Garage Door Spring Repair', 'route' => 'spring-repair'],
            ['label' => 'Maintenance and Tune-Ups', 'route' => 'maintenance-plans'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
        ],
    ],
    '/emergency-garage-services/' => [
        'h1' => 'Emergency Garage Door Service',
        'directAnswer' => 'Door stuck open at night, car trapped before work, door hanging off its track: call the number shown for your area and tell us what happened. We run same-day and emergency service, quote a flat price before work starts, and waive the service call fee when we do the repair. The most common emergencies are snapped springs and cable failures.',
        'answerFacts' => [
            'cost' => 'Springs $575 to $1,225, cables $325 to $625 on most jobs',
            'timing' => 'Same-day and emergency service, honest arrival windows',
            'call' => 'A door that will not close, a trapped car, or anything hanging or snapped',
        ],
        'priceRange' => ['min' => 575, 'max' => 1225],
        'warningSigns' => [
            'The door will not close and will not lock. A door stuck open is a security problem and, in winter, a heating problem. Do not force it down.',
            'Your car is trapped inside. A snapped spring turns the door into a few hundred pounds of dead weight the opener cannot lift. Do not lift it yourself.',
            'The door is off its track or hanging crooked. A door partly out of its opening can come the rest of the way out. Keep people and cars clear.',
            'Someone or something hit the door. Impact damage can hide bent tracks and stressed springs, so even a door that still moves deserves a same-day look.',
        ],
        'process' => [
            'Call and describe what happened. A real person gives you an honest arrival window, not a vague sometime today.',
            'A tech heads your way, and you get a text with his name when he is on the way. No mystery vans.',
            'First move is making the door safe: securing a hanging door, releasing a trapped car, unloading failed tension. Then you get one flat number for the full repair.',
            'We fix what can be fixed on the spot, test the full system, and leave your garage secure. If a part has to be ordered, we secure the door and tell you the plan first.',
        ],
        'costParagraphs' => [
            'Emergencies get the same flat-quote treatment as everything else we do: your tech quotes the whole job before work starts, and the number you approve is the number you pay. Emergency timing never turns into an open meter.',
            'The two most common emergency repairs have real price history behind them: most spring jobs land between $575 and $1,225 including the parts that wear with the springs, based on 432 completed jobs, and cable repairs usually run $325 to $625.',
            'The service call is $0 when we do the repair, whatever the clock says.',
        ],
        'safety' => 'If anyone is hurt or trapped, call 911 first. Otherwise keep people, pets, and cars away from the door and do not force it. Springs and cables are under dangerous tension and should be handled by trained professionals, no matter how tempting a quick fix looks.',
        'plans' => null,
        'reviewTag' => 'emergency',
        'faqs' => [
            [
                'question' => 'What counts as a garage door emergency?',
                'answer' => 'A door that will not close, a door off its track, a snapped spring or cable, or anything that leaves the door hanging where it could fall. If you are staring at the door wondering if it is safe, call and describe it. We will tell you.',
            ],
            [
                'question' => 'How much does emergency garage door repair cost?',
                'answer' => 'The same flat-quote rule applies at any hour: you approve one number before work starts. The most common emergency, a snapped spring, lands between $575 and $1,225 on most jobs including the parts that wear with the springs. Cable failures usually run $325 to $625. The service call is $0 with repair.',
            ],
            [
                'question' => 'My car is trapped behind a door that will not open. What do I do?',
                'answer' => 'Do not lift the door, especially if you heard a bang; that is a snapped spring and the door is now dead weight. Call and tell us a car is trapped; that moves the priority up. A tech can safely release the door, free the car, and quote the repair flat.',
            ],
            [
                'question' => 'The door is stuck open. Is my garage safe overnight?',
                'answer' => 'Treat it as open to the street, because it is. Move valuables inside, lock the interior door, and call us. If a tech cannot get there immediately, we will talk you through safely disconnecting the opener and whether the door can be lowered by hand without risk.',
            ],
            [
                'question' => 'Do you charge extra for same-day or emergency calls?',
                'answer' => 'You get the same offer structure as any visit: a $0 service call when we do the repair, and a flat quote you approve before work starts. Whatever the clock says, the number you approve is the number you pay. That is the whole pricing policy.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Spring Repair', 'route' => 'spring-repair'],
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
            ['label' => 'Maintenance and Tune-Ups', 'route' => 'maintenance-plans'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
        ],
    ],
    '/garage-door-services/' => [
        'h1' => 'Garage Door Services',
        'directAnswer' => 'We repair garage doors, install new ones, fix and replace openers, and handle the emergencies that cannot wait. This page is the map: pick the service that sounds like your problem, or just call the number shown for your area and describe what the door is doing. We will point you right.',
        'answerFacts' => [
            'cost' => 'Real price ranges from completed jobs on every service page',
            'timing' => 'Same-day and emergency service in most cases',
            'call' => 'Not sure which service fits? Call and describe what the door is doing',
        ],
        'priceRange' => null,
        'warningSigns' => [
            'Something is wrong with the door and you are not sure which service fits. Pick the closest page, or skip the guessing and call.',
            'The door itself is the problem: springs, cables, rollers, panels, doors off track, doors stuck halfway. That is repair.',
            'The door is fine but the button does nothing. That is opener territory: troubleshooting, repair, and new LiftMaster units.',
            'The door is beat up, rusted, or you want a new look. That is installation, and we are an official Clopay dealer.',
            'The door will not close, is off its track, or is hanging in a way that scares you. That is an emergency. Call now.',
        ],
        'process' => [
            'Figure out whether the problem is the door, the opener, or something urgent. Or skip that and call; you do not have to diagnose your own door.',
            'Our tech comes out and looks at the actual door instead of guessing from a description.',
            'You hear what we found and see the flat price before any work starts.',
            'Work begins only after you approve the number, and the number you approve is the number you pay.',
        ],
        'costParagraphs' => [
            'Every service page here publishes its real price range from our own completed jobs: springs, cables, openers, new doors, and tune-ups. Two doors with the same symptom can be two very different jobs, so the exact price always comes from a tech looking at your actual door.',
            'Two offers apply across the board: the service call is $0 when we do the repair, and the $49 tune-up is a full inspection and lubrication of the whole system.',
        ],
        'safety' => null,
        'plans' => null,
        'reviewTag' => 'general',
        'faqs' => [
            [
                'question' => 'What services do you offer?',
                'answer' => 'Garage door repair, new door installation, spring and cable repair, opener repair and replacement, weatherstripping, tune-ups and maintenance plans, and emergency service. Each one has its own page, but every job starts the same way: a tech looks at your door and you see the flat price before work begins.',
            ],
            [
                'question' => 'I do not know what is wrong with my door. Which page do I pick?',
                'answer' => 'Skip the pages and call. Describe what the door is doing, the noise, the position, whatever you noticed, and we will figure out what kind of visit it is. You do not have to diagnose your own door.',
            ],
            [
                'question' => 'Are you licensed and insured?',
                'answer' => 'Yes. We are a licensed and insured local garage door company, family owned and run by twin brothers, and the tech who shows up at your house works for us.',
            ],
            [
                'question' => 'Do you give prices over the phone?',
                'answer' => 'We publish real ranges from completed jobs on each service page, and we give the exact price after a tech sees the door. What we can promise is that you see the full flat price in writing before we start, and the service call is $0 when we do the repair.',
            ],
            [
                'question' => 'What if my door is unsafe right now?',
                'answer' => 'Stop using it and keep everyone clear. Do not touch the springs or cables; they are under dangerous tension and belong with trained professionals. Then use the emergency service page or just call the number shown here.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Repair', 'route' => 'repair'],
            ['label' => 'Garage Door Installation', 'route' => 'installation'],
            ['label' => 'Garage Door Spring Repair', 'route' => 'spring-repair'],
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
        ],
    ],
    '/garage-door-cable-repair/' => [
        'h1' => 'Garage Door Cable Repair',
        'directAnswer' => 'Most cable repairs run $325 to $625, based on 50 completed jobs over the last two years. If your door is hanging crooked, jammed halfway, or has a cable dangling loose, stop using it and call us. Cables are connected to the spring system and under real tension. The service call is $0 when we do the repair.',
        'answerFacts' => [
            'cost' => 'Most jobs $325 to $625',
            'timing' => 'Same-day service in most cases',
            'call' => 'A crooked door, a slack or dangling cable, or fraying near the bottom bracket',
        ],
        'priceRange' => ['min' => 325, 'max' => 625],
        'warningSigns' => [
            'The door hangs crooked. One side higher than the other means one side has lost its lift. That is almost always a cable off its drum or snapped outright.',
            'A cable is dangling or coiled loose near the track or the bottom bracket. It has left its drum. Do not touch it, and do not run the door.',
            'Fraying near the bottom bracket. Cables rust and fray from the bottom up, where snow melt and road salt collect. Visible broken strands mean borrowed time.',
            'The door jams, sticks, or slams. A door that binds halfway or closes with a bang has lost its balance, and cables and springs share that job.',
        ],
        'process' => [
            'Stop using the door, including the opener button. Every cycle grinds on hardware that is already failing.',
            'Tell us which side looks wrong and what you can see from a safe distance. A real person confirms your arrival window.',
            'Your tech checks the cables, the drums, the springs, and the bottom brackets together, because a cable rarely fails alone.',
            'You see the flat price before the repair starts, and the door gets rebalanced and tested before we leave.',
        ],
        'costParagraphs' => [
            'Most cable repairs run $325 to $625, based on 50 of our own completed jobs over the last two years, not a national average.',
            'What moves the number: whether one cable failed or both get replaced, since they age together, the condition of the drums and bottom brackets, and how much rebalancing the door needs afterward. Cables live in the salt-and-slush zone of the garage, so winter is genuinely hard on them.',
            'The service call is $0 when we do the repair, and the flat quote comes before work starts.',
        ],
        'safety' => 'Cables carry the same dangerous tension as the springs they work with, and they should be handled by trained professionals. Do not pull, unwind, or reattach a cable, and do not run the door, even with the opener, until it has been looked at.',
        'plans' => null,
        'reviewTag' => 'general',
        'faqs' => [
            [
                'question' => 'How much does garage door cable repair cost?',
                'answer' => 'Most cable repairs run $325 to $625, based on 50 completed jobs over the last two years. The exact number depends on one cable or two and the condition of the drums and brackets. You approve a flat quote first, and the service call is $0 when we do the repair.',
            ],
            [
                'question' => 'Why did my garage door cable snap?',
                'answer' => 'Rust and wear, usually together. Cables fray from the bottom up, where snow melt and salt collect around the bottom brackets, and every open and close flexes the same strands. A cable can also jump its drum when the door hits an obstruction or a spring fails. We will find which one happened.',
            ],
            [
                'question' => 'Can I fix a garage door cable myself?',
                'answer' => 'No, and this one matters. The cables carry the tension the springs create, hundreds of pounds of it. Reattaching a cable without safely unloading the spring system is how people get hurt. Diagnosis is $0 when we do the repair, so there is no money saved taking the risk.',
            ],
            [
                'question' => 'My door is off its track. Is that the same problem?',
                'answer' => 'Often related. A snapped or jumped cable lets one side drop, which can pull rollers out of the track. Stop using the door either way; an off-track door can come out of the opening entirely. We repair cables, tracks, and rollers on the same visit, with one flat quote.',
            ],
            [
                'question' => 'Should both cables be replaced at once?',
                'answer' => 'Usually yes. Both cables have flexed through the same years and the same weather, so when one fails the other is rarely far behind, and fresh cable paired with worn cable lifts unevenly. Your tech quotes one and both as separate flat numbers, and you choose.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Spring Repair', 'route' => 'spring-repair'],
            ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
            ['label' => 'Maintenance and Tune-Ups', 'route' => 'maintenance-plans'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
        ],
    ],
    '/garage-door-openers/' => [
        'h1' => 'Garage Door Openers',
        'directAnswer' => 'A new garage door opener runs $775 to $1,400 installed, based on 93 completed installs over the last two years. We install LiftMaster, belt drive or chain drive, with options like battery backup and myQ smartphone control, and we haul away the old unit. Call for a flat quote, or keep reading to pick the right one.',
        'answerFacts' => [
            'cost' => 'Most installs $775 to $1,400, the whole job',
            'timing' => 'Flat quote first, install scheduled around your day',
            'call' => 'A motor at the end of its road, or features your old opener will never have',
        ],
        'priceRange' => ['min' => 775, 'max' => 1400],
        'warningSigns' => [
            'The repairs are stacking up. A sensor here, a gear kit there. When a tired motor head starts collecting repair bills, replacement is usually the cheaper path.',
            'You can hear it from the other end of the house. Old chain drives rattle the whole ceiling; a modern belt drive runs quiet enough to mount under a bedroom.',
            'No safety sensors or no battery backup. If your opener predates modern safety standards or dies with the power, it is behind what current units do as standard.',
            'You want the door on your phone. Left-open alerts, remote closing, access codes for guests. If you have ever turned the car around to check the door, myQ pays for itself.',
        ],
        'process' => [
            'Note what you want out of the opener: remotes, keypad, battery backup, lighting, app control. Then book online or call.',
            'Your tech checks the door, the springs, the balance, and your garage layout, then walks you through which LiftMaster fits your door and your budget.',
            'One flat number for the whole job: unit, installation, programming, and haul-away of the old opener. Work starts only after you say yes.',
            'We install the unit, program your remotes and keypad, connect myQ if you want it, run the door through full cycles, and check the safety reverse.',
        ],
        'costParagraphs' => [
            'Most opener installations run $775 to $1,400 installed, based on 93 of our own completed jobs over the last two years, not a national average.',
            'What moves the number: belt or chain drive, motor power for heavier doors, and features like battery backup, myQ, and cameras. That price is the whole job, including installation, programming, and haul-away of the old unit.',
            'We size the opener to your actual door instead of upselling the biggest box on the truck, and an opener install is also a checkup: we inspect springs, cables, and balance while we are up there, because a new motor on an unbalanced door will not last.',
        ],
        'safety' => null,
        'plans' => null,
        'reviewTag' => 'openers',
        'faqs' => [
            [
                'question' => 'How much does garage door opener installation cost?',
                'answer' => 'Most installs run $775 to $1,400 installed, based on 93 completed jobs over the last two years. Drive type, motor power, and features like battery backup and myQ move the number within that range. You approve one flat quote covering the unit, install, and haul-away.',
            ],
            [
                'question' => 'Belt or chain garage door opener?',
                'answer' => 'Belt for most homes: it is the quiet one, and the difference matters if anyone sleeps near the garage. Chain is the rugged, budget-friendly workhorse. Both fall inside the $775 to $1,400 installed range, and your tech will point you to the drive that fits your garage.',
            ],
            [
                'question' => 'What size opener do I need?',
                'answer' => 'It depends on your door\'s size and weight; a heavy insulated double door needs more motor than a light single. Rather than guessing from a box label, your tech checks the actual door and quotes the right motor flat.',
            ],
            [
                'question' => 'Do I need a smart opener?',
                'answer' => 'Need, no. But myQ smartphone control is the feature people thank us for later: left-open alerts, closing the door from anywhere, and access codes for guests or deliveries. It is built into the LiftMaster units we install most, within the same $775 to $1,400 installed range.',
            ],
            [
                'question' => 'Can you fix my old opener instead?',
                'answer' => 'If it can be fixed for less, we say so. Small repairs like sensors, remotes, and keypads usually run $100 to $300, and we are happy to make them. Replacement earns its keep when the motor head itself is failing or parts stop being worth chasing. We quote both paths and you pick.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
            ['label' => 'Garage Door Installation', 'route' => 'installation'],
            ['label' => 'Maintenance and Tune-Ups', 'route' => 'maintenance-plans'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
        ],
    ],
    '/garage-weatherstripping-repair/' => [
        'h1' => 'Weatherstripping Repair',
        'directAnswer' => 'Weatherstripping repair is one of the least expensive repairs we run, and one of the highest-comfort ones in a hard winter. If you see daylight under your closed door, feel drafts, or find snow dust inside, the seals are done. You get a flat quote before work starts, and the service call is $0 when we do the repair.',
        'answerFacts' => [
            'cost' => 'One of the least expensive repairs on the door, quoted flat on site',
            'timing' => 'Same-day service in most cases',
            'call' => 'Daylight under the door, drafts, snow dust, leaves, or mice getting in',
        ],
        'priceRange' => null,
        'warningSigns' => [
            'Daylight under the closed door. Stand inside with the lights off; any glow along the bottom edge is a gap letting in wind, water, and everything smaller than the light.',
            'Snow dust or leaves inside. If fine snow shows up along the door line after a windy night, the perimeter seals have quit.',
            'A bottom seal gone stiff, cracked, or flat. Healthy rubber is flexible and springs back; a seal that is hard or split cannot compress against the floor anymore.',
            'Mice or bugs finding their way in. Pests do not break in, they walk through gaps. Fresh seals close the easiest entrance your house has.',
        ],
        'process' => [
            'Walk the closed door and note where you see light, feel drafts, or find water. Then book online or call.',
            'Your tech checks the bottom seal, the retainer it slides into, the side and top seals, and the door\'s alignment, because a gap can also mean the door is sitting crooked.',
            'You get one flat number for the whole job before any work starts.',
            'We fit the new seals, close the door on the light test, and leave your garage the way we found it, minus the draft.',
        ],
        'costParagraphs' => [
            'Weatherstripping is honest work at an honest size: it is one of the least expensive repairs on the whole door, and your tech quotes it flat before starting, based on which seals need replacing and the length of your door.',
            'We do not publish a dollar range for this one because the jobs vary from a single bottom seal to a full perimeter reseal. The quote in your driveway is the real number, and it comes before any work.',
            'The service call is $0 when we do the repair, and the $49 tune-up checks every seal as part of the full inspection and lubrication, so you can catch this before winter does.',
        ],
        'safety' => null,
        'plans' => null,
        'reviewTag' => 'general',
        'faqs' => [
            [
                'question' => 'How much does garage door weatherstripping cost?',
                'answer' => 'It is one of the least expensive repairs we run, and the exact number depends on which seals need replacing and your door\'s size, so your tech quotes it flat on site before any work. The service call is $0 when we do the repair, so finding out costs you nothing.',
            ],
            [
                'question' => 'How important is it to have your garage door sealed?',
                'answer' => 'In a cold climate, very. The garage door is the biggest opening in the house, and bad seals leak heat all winter into rooms above and beside an attached garage. Fresh weatherstripping is the cheap half of the fix; an insulated door is the thorough half.',
            ],
            [
                'question' => 'Can I replace the bottom seal myself?',
                'answer' => 'It is one of the more DIY-friendly jobs on a garage door, honestly. The catch is the retainer track, which is often corroded or bent, and a seal fitted to a crooked door still gaps. With a $0 service call with repair, we will do the whole perimeter right.',
            ],
            [
                'question' => 'Why is there still a gap after the door closes?',
                'answer' => 'If new-looking seals still show daylight, the door itself may be sitting crooked, the floor may have settled unevenly, or the opener\'s close limit is stopping the door early. Your tech checks alignment and balance along with the seals, so the quote fixes the actual cause, not just the symptom.',
            ],
            [
                'question' => 'What can I do to maintain my garage door seals?',
                'answer' => 'Rinse grit off the bottom seal a few times a year, keep the floor line clear of ice buildup, and do not let the door slam. The rest is inspection, which is exactly what our $49 tune-up covers: seals, springs, rollers, and lubrication in one visit.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Repair', 'route' => 'repair'],
            ['label' => 'Garage Door Installation', 'route' => 'installation'],
            ['label' => 'Maintenance and Tune-Ups', 'route' => 'maintenance-plans'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
        ],
    ],
    '/garage-door-tune-up/' => [
        'h1' => 'Garage Door Tune-Up',
        'directAnswer' => 'The $49 tune-up is a full inspection and lubrication of your whole garage door system, and most maintenance visits run $50 to $100 all-in, based on 52 completed jobs over the last two years. Your tech checks the springs, cables, rollers, balance, and opener safety tests, then gives you a plain rundown of what is healthy and what is wearing.',
        'answerFacts' => [
            'cost' => '$49 tune-up, most visits $50 to $100 all-in',
            'timing' => 'One scheduled visit, booked around your day',
            'call' => 'Grinding or squealing, slower travel, or more than a year since the last look',
        ],
        'priceRange' => ['min' => 50, 'max' => 100],
        'warningSigns' => [
            'Grinding, squealing, or rattling. Dry rollers and hinges announce themselves, and lubrication now is the difference between an annoyance and a worn-out part.',
            'The door moves slower or shakes. Wear in the rollers or a spring losing tension. Caught early, it is an adjustment; ignored, it is a failure.',
            'It has been more than a year. Springs are rated for about 10,000 cycles, and weather works on rubber and steel all year. An annual look is the honest minimum.',
            'You are heading into winter. Cold is when tired parts quit, and a fall tune-up catches them before the coldest morning of the year does.',
        ],
        'process' => [
            'Book a visit online or with the number shown on this page.',
            'Your tech works the whole system: spring tension and wear, cables from drum to bottom bracket, rollers and hinges, track alignment, opener drive and sensors, and the weatherstripping.',
            'Everything that moves gets lubricated with the right product, everything loosening gets tightened, and the door runs its full safety tests.',
            'You get the straight report: what is healthy, what is wearing, and a flat quote only if something genuinely needs work. Nothing extra happens without your OK.',
        ],
        'costParagraphs' => [
            'The headline number is simple: the tune-up is $49, and most maintenance visits run $50 to $100 all-in, based on 52 of our own completed jobs over the last two years.',
            'If the inspection turns up something that genuinely needs fixing, you get a flat quote before any work, and the $0 service call with repair applies, so the diagnosis never costs extra.',
            'Compare that to the jobs maintenance exists to prevent: most spring tickets land between $575 and $1,225, and cable repairs run $325 to $625. The math is not subtle.',
        ],
        'safety' => null,
        'plans' => null,
        'reviewTag' => 'general',
        'faqs' => [
            [
                'question' => 'What does the $49 tune-up include?',
                'answer' => 'A full inspection of springs, cables, rollers, tracks, opener, and safety sensors, lubrication of every moving part, tightening of loosening hardware, a safety reverse test, and a plain rundown of what is wearing. It is the whole system, not a squirt of lube and a handshake.',
            ],
            [
                'question' => 'My door works fine. Is a tune-up worth it?',
                'answer' => 'A garage door is the heaviest moving thing in the house, and it wears quietly until the day it does not. A tune-up catches loose hardware, dry rollers, and tired springs while they are still small, boring problems. Most spring jobs land between $575 and $1,225, so a $49 look is cheap insurance.',
            ],
            [
                'question' => 'Is a tune-up the same as a repair visit?',
                'answer' => 'No. A tune-up is for a working door. If the door is stuck, off track, or has a snapped spring, book a repair or emergency visit instead so the tech comes ready for that job.',
            ],
            [
                'question' => 'Can I just spray the door with lubricant myself?',
                'answer' => 'A light garage door lube on the rollers and hinges does not hurt. But stay away from the springs and cables. They are under dangerous tension and belong with trained professionals, and the tune-up handles them the right way.',
            ],
            [
                'question' => 'Will you upsell me during the visit?',
                'answer' => 'We show you what we found, on the door, not in a brochure. If nothing is wrong we say so. If something is wearing out, you get the flat price and you decide. Nothing extra happens without your OK.',
            ],
        ],
        'links' => [
            ['label' => 'Maintenance Plans', 'route' => 'maintenance-plans'],
            ['label' => 'Garage Door Repair', 'route' => 'repair'],
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
        ],
    ],
    '/maintenance-plans/' => [
        'h1' => 'Garage Door Maintenance',
        'directAnswer' => 'Our $49 tune-up is a full inspection and lubrication of your whole garage door system, and most maintenance visits run $50 to $100, based on 52 completed jobs over the last two years. TwinShield, our maintenance membership, puts that visit on a schedule so worn springs, fraying cables, and drying seals get caught before they become repair bills.',
        'answerFacts' => [
            'cost' => '$49 tune-up, most visits $50 to $100 all-in',
            'timing' => 'Visits on a schedule we manage, not one you have to remember',
            'call' => 'You want the door checked on a calendar instead of after a breakdown',
        ],
        'priceRange' => ['min' => 50, 'max' => 100],
        'warningSigns' => [
            'The pattern we see every week: a spring that announced itself for months finally snaps on the coldest morning of the year. Doors almost never fail without notice.',
            'The door gets heavy daily use, and small wear adds up fast. Heavy households burn through the 10,000-cycle spring life quicker.',
            'You cannot remember the last time anyone looked at the door. An annual inspection is the honest minimum for a door that runs daily.',
            'You look after a home or building where a dead garage door becomes your problem at the worst time.',
        ],
        'process' => [
            'Call or book online and ask about a tune-up or TwinShield membership.',
            'We go over what the visit covers, what the membership adds, and how visits get scheduled. No pressure before it makes sense to you.',
            'Your tech runs the full tune-up: inspection, lubrication, tightening, balance, and the opener safety tests, then gives you the straight report.',
            'If something genuinely needs work, you get a flat quote first, and repairs never start without your OK.',
        ],
        'costParagraphs' => [
            'The tune-up is $49, and most maintenance visits run $50 to $100 all-in, based on 52 of our own completed jobs over the last two years.',
            'TwinShield puts that visit on a schedule and adds member savings on repairs and equipment. Ask about the tiers when you call, or read the TwinShield page for the full program.',
            'Compare that to the jobs maintenance exists to prevent: most spring tickets land between $575 and $1,225, and cable repairs run $325 to $625.',
        ],
        'safety' => null,
        'plans' => null,
        'reviewTag' => 'general',
        'faqs' => [
            [
                'question' => 'How much does garage door maintenance cost?',
                'answer' => 'Our tune-up is $49, and most maintenance visits run $50 to $100 all-in, based on 52 completed jobs over the last two years. If the inspection finds something that needs repair, you approve a flat quote first, and the $0 service call with repair applies.',
            ],
            [
                'question' => 'What does a maintenance visit include?',
                'answer' => 'A full inspection of springs, cables, rollers, tracks, opener, and safety sensors, lubrication of every moving part, tightening of loosening hardware, a balance check, a safety reverse test, and a plain rundown of what is healthy and what is wearing.',
            ],
            [
                'question' => 'How often should a garage door be serviced?',
                'answer' => 'Once a year is the honest minimum for a door that runs daily, and fall is the smart timing, before cold weather stresses tired parts. Springs are rated for about 10,000 cycles, so a busy household burns through them faster and benefits from a schedule.',
            ],
            [
                'question' => 'What can I do to maintain my garage door myself?',
                'answer' => 'Plenty: keep the tracks free of debris, rinse grit off the bottom seal, replace remote batteries, and wipe the sensor lenses. Leave spring tension, cable work, and drive adjustments to us; those carry real risk. The $49 tune-up covers everything on the dangerous side of that line.',
            ],
            [
                'question' => 'What is TwinShield?',
                'answer' => 'TwinShield is our maintenance membership: scheduled tune-up visits plus member savings on repairs, service calls, and future equipment, in three tiers. It exists so the tune-up actually happens, on a schedule, without you remembering it. Ask us to compare the tiers for your door.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Repair', 'route' => 'repair'],
            ['label' => 'Garage Door Spring Repair', 'route' => 'spring-repair'],
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
        ],
    ],
    '/property-management-services/' => [
        'h1' => 'Property Management Services',
        'directAnswer' => 'We handle garage door repair, replacement, and maintenance for property managers, HOAs, landlords, and small commercial buildings. One call covers any door in your portfolio: same-day emergency response, flat quotes your office approves before work starts, and invoices that name the property and unit so your bookkeeping stays clean.',
        'answerFacts' => [
            'cost' => 'Real ranges below, every job quoted flat per property',
            'timing' => 'Same-day emergency response for tenant calls',
            'call' => 'Tenant emergencies, turnovers, and scheduled upkeep across the portfolio',
        ],
        'priceRange' => null,
        'warningSigns' => [
            'Tenant emergency calls: trapped cars, doors stuck open, snapped springs. We coordinate access with your tenant directly if you want us to.',
            'Repairs across every unit type: springs, cables, openers, tracks, panels, and seals on rental homes, condo garages, and small commercial doors.',
            'Turnovers and replacements: new Clopay doors and LiftMaster openers when a unit turns over or a door ages out.',
            'Scheduled maintenance: portfolio-wide tune-ups on a calendar we manage, so doors get caught wearing out instead of failing.',
        ],
        'process' => [
            'One call or one booking opens the job, with the property address and unit. We take it from there, including tenant scheduling if you want us dealing with the tenant directly.',
            'The tech diagnoses on site and documents what he found. The quote goes to whoever approves spend: your office, the owner, or the board, as one flat number.',
            'Nothing starts until the approver says yes. No tenant-authorized surprises landing on your invoice.',
            'We do the work, test the full system including the safety reverse, and close the loop with your office. Invoices name the property and unit.',
        ],
        'costParagraphs' => [
            'Our prices come from our own completed jobs over the last two years, not a rate card built for negotiation: most spring jobs land between $575 and $1,225 including the parts that wear with the springs, cable repairs usually run $325 to $625, a new opener installed runs $775 to $1,400, and tune-ups run $50 to $100 per door on most visits.',
            'A new double door runs $3,425 to $4,400 installed on most jobs, and singles typically $2,625 to $3,525, so you can budget a turnover before we ever quote the specific opening.',
            'Every job is still quoted flat per property before work starts, and the $0 service call with repair applies across the portfolio. For multi-property maintenance scheduling, call and we will build the calendar around your buildings.',
        ],
        'safety' => null,
        'plans' => null,
        'reviewTag' => 'general',
        'faqs' => [
            [
                'question' => 'What does garage door repair cost across a portfolio?',
                'answer' => 'The same real numbers as any job we run: most spring tickets land between $575 and $1,225, cable repairs run $325 to $625, and tune-ups run $50 to $100 per door, from our own completed jobs. Every job is quoted flat to your approver before work starts.',
            ],
            [
                'question' => 'Can tenants schedule directly with you?',
                'answer' => 'Yes, if that is how you want it. Tenants can call and book the visit, and the quote still routes to your office or the owner for approval before any work starts. You choose the workflow once, and we follow it on every job after that.',
            ],
            [
                'question' => 'Do you handle HOAs and condo buildings?',
                'answer' => 'Yes. HOAs, condo associations, apartment garages, rental homes, and small commercial doors. Board approval workflows are fine; the flat quote waits for whoever signs. For buildings with many doors, a scheduled maintenance calendar at $50 to $100 per door beats emergency pricing every time.',
            ],
            [
                'question' => 'How fast can you respond to a tenant emergency?',
                'answer' => 'Same-day and emergency service, same as our residential promise. Call, give us the property and unit, and we will give you an honest arrival window. The tech texts ahead, so tenants are not opening the door to a mystery.',
            ],
            [
                'question' => 'Are you licensed and insured for commercial property work?',
                'answer' => 'Yes. Licensed and insured, family owned, and happy to send certificates of insurance to your office or your building\'s management company before the first job. It is the paperwork question every good manager asks, and the answer should always be this easy.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Repair', 'route' => 'repair'],
            ['label' => 'Garage Door Installation', 'route' => 'installation'],
            ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
        ],
    ],
    '/protection-plans/' => [
        'h1' => 'TwinShield Protection Plans',
        'directAnswer' => 'TwinShield is our 12-month garage-door membership in three tiers. Core is $12.99 a month or $149 once, Priority is $18.99 a month or $199 once, and Premier is $24.99 a month or $279 once. Every tier includes 10% off the qualifying repair you enroll on, a tune-up, repair savings, and equipment credit.',
        'answerFacts' => [
            'cost' => 'Core $12.99, Priority $18.99, Premier $24.99 a month',
            'timing' => 'A fixed 12-month membership, paid annually or in 12 monthly payments',
            'call' => 'Ask about TwinShield when booking a repair or tune-up',
        ],
        'priceRange' => null,
        'warningSigns' => [
            'You want ongoing care for the door and opener plus savings on repairs instead of booking visits one at a time.',
            'You want to build equipment credit toward a future door or opener purchase.',
            'You are comparing a membership against just calling us when something breaks.',
            'You enrolled on a repair and want to make the 10% enrollment savings count.',
        ],
        'process' => [
            'Call the number shown and ask about TwinShield Core, Priority, or Premier.',
            'Pick a tier and a payment option: one annual payment, or 12 automatic monthly payments for the fixed 12-month term. Monthly billing is a payment schedule, not a cancel-anytime plan.',
            'Enroll on a qualifying repair and that job gets 10% off. You sign the membership acknowledgment at enrollment.',
            'Use your included tune-up visits and member savings during the term. Our office verifies equipment credit before it is applied.',
        ],
        'costParagraphs' => [
            'Core is $12.99 a month or $149 once. Priority is $18.99 a month or $199 once. Premier is $24.99 a month or $279 once.',
            'Monthly billing means 12 payments across the fixed term: $155.88 for Core, $227.88 for Priority, and $299.88 for Premier. The annual option totals less than twelve monthly payments.',
        ],
        'safety' => null,
        // Membership tiers. The service template renders these as pricing cards
        // by splitting each tradeoff into a price line and benefits on the
        // first ' - ' delimiter. The middle tier is the recommended one and
        // gets the badge by position.
        'plans' => [
            [
                'option' => 'TwinShield Core - Essential Care',
                'tradeoff' => '$12.99/mo or $149/yr - 1 tune-up and safety inspection, 5% off future qualifying repairs, 50% off future standard service-call fees, and equipment credit of 25 cents per dollar in eligible membership payments, up to $150.',
            ],
            [
                'option' => 'TwinShield Priority - Best Value',
                'tradeoff' => '$18.99/mo or $199/yr - 1 tune-up and safety inspection, priority scheduling subject to availability, 7.5% off future qualifying repairs, 1 standard service-call fee waived then 50% off additional, and equipment credit of 35 cents per dollar, up to $300.',
            ],
            [
                'option' => 'TwinShield Premier - Maximum Care',
                'tradeoff' => '$24.99/mo or $279/yr - 2 tune-ups and safety inspections, highest scheduling priority subject to availability, 10% off future qualifying repairs, 1 service-call fee waived then 50% off additional, an opener surge-protection benefit, and equipment credit of 50 cents per dollar, up to $500.',
            ],
        ],
        'reviewTag' => 'general',
        'faqs' => [
            [
                'question' => 'Is the monthly option month-to-month?',
                'answer' => 'No. TwinShield is a fixed 12-month membership. Monthly billing means 12 automatic monthly payments for that term; it is not a month-to-month or cancel-anytime plan.',
            ],
            [
                'question' => 'How much does TwinShield cost?',
                'answer' => 'Core is $12.99 a month or $149 once. Priority is $18.99 a month or $199 once. Premier is $24.99 a month or $279 once. Monthly billing means 12 payments across the fixed term: $155.88 for Core, $227.88 for Priority, and $299.88 for Premier. The annual option totals less than twelve monthly payments.',
            ],
            [
                'question' => 'How does equipment credit work?',
                'answer' => 'Core earns 25 cents, Priority 35 cents, and Premier 50 cents for every dollar in eligible membership payments received, up to $150, $300, and $500. Twelve monthly Core payments earn $38.97, or $37.25 on the annual option. Priority earns $79.76 or $69.65. Premier earns $149.94 or $139.50. Failed, refunded, or charged-back payments earn nothing, and our office verifies the exact balance before it is quoted or applied.',
            ],
            [
                'question' => 'Does TwinShield cover every repair for free?',
                'answer' => 'No. TwinShield is a maintenance-and-savings membership. It is not insurance and not a promise of free repairs or free replacement equipment. Each tier provides the specific maintenance, discount, scheduling, service-call, and equipment-credit benefits listed, governed by the TwinShield Membership Agreement.',
            ],
            [
                'question' => 'How is TwinShield different from a maintenance plan?',
                'answer' => 'The maintenance plan is about scheduled tune-up visits. TwinShield adds repair discounts, service-call savings, scheduling priority on higher tiers, and equipment credit. Ask us to compare the two for your door.',
            ],
        ],
        'links' => [
            ['label' => 'Maintenance Plans', 'route' => 'maintenance-plans'],
            ['label' => 'Garage Door Repair', 'route' => 'repair'],
            ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
        ],
    ],
];
