<?php
declare(strict_types=1);

return [
    '/garage-door-repair/' => [
        'h1' => 'Garage Door Repair',
        'directAnswer' => 'If your garage door is stuck, crooked, noisy, or just not moving right, we can fix it. One of our techs comes out, looks the whole door over, tells you what is wrong, and gives you the exact price before we touch anything. Call or request a quote with the number shown for your area.',
        'needs' => [
            'The door is stuck half open, off its track, or making a grinding or banging noise it did not make before.',
            'You found a snapped spring, a frayed cable, or a roller sitting outside its track.',
            'You are weighing a repair against a new door and want an honest read after we look at it.',
        ],
        'safety' => 'If the door is acting up, stop using it and keep kids, pets, and cars out of the way. Do not touch the torsion spring or the cables. Springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Tell us what the door is doing and where it stopped. Do not keep running it up and down.',
            'One of our techs looks over the door, the springs, the cables, the rollers, and the track.',
            'We walk you through what we found and what it would take to fix it, or what a new door would run if the old one is done.',
            'You see the exact price before we start any work.',
        ],
        'options' => [
            [
                'option' => 'Fix the door you have',
                'tradeoff' => 'We do this when the door is worth fixing. We do not call it until we have looked at it in person.',
            ],
            [
                'option' => 'Talk about a new door',
                'tradeoff' => 'If the door is beat up or you have wanted a new look anyway, we can price a replacement while we are there. No pressure either way.',
            ],
            [
                'option' => 'Sleep on it',
                'tradeoff' => 'You can take what we found and the price and decide later. The door is yours and the call is yours.',
            ],
        ],
        'prepare' => [
            'Jot down what happened and where the door stopped.',
            'Snap a photo from a safe spot if you can do it without walking under the door.',
            'Move bikes, cars, and clutter back from the door if you can reach them safely.',
            'Keep everyone away from the door if it looks like it could fall or slam.',
        ],
        'faqs' => [
            [
                'question' => 'What kinds of repairs do you handle?',
                'answer' => 'Pretty much anything on the door itself: springs, cables, rollers, hinges, bent panels, doors off track, and doors that quit halfway. Opener problems have their own page, but if you are not sure what is wrong, just call and describe it. We will sort it out when we get there.',
            ],
            [
                'question' => 'Do I find out the price before you start?',
                'answer' => 'Yes. Our tech looks at the door first, then gives you the exact price in writing before any work starts. Nothing gets done until you say go.',
            ],
            [
                'question' => 'Should I fix the door or replace it?',
                'answer' => 'Honest answer: we do not know until we see it. Some doors just want a spring or a few rollers. Others have taken enough hits that a new door makes more sense. We lay out both prices and let you decide.',
            ],
            [
                'question' => 'Which number do I call?',
                'answer' => 'Use the number shown on this page. It changes based on the service area you picked, so the right one is already on your screen.',
            ],
            [
                'question' => 'The door looks like it might fall. What do I do?',
                'answer' => 'Stop using it. Keep kids, pets, and cars away, and do not try to force it up or down. Leave the springs and cables alone. Then call the number on this page and tell us what you see.',
            ],
        ],
        'links' => [
            ['label' => 'All Garage Door Services', 'route' => 'services'],
            ['label' => 'Garage Door Spring Repair', 'route' => 'spring-repair'],
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
            ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
        ],
    ],
    '/garage-door-installation/' => [
        'h1' => 'Garage Door Installation',
        'directAnswer' => 'Ready for a new garage door, or replacing one that is past saving? We measure your opening, help you pick a style, and give you the exact price before any work starts. We are an official Clopay dealer. Call or request a quote with the number shown for your area.',
        'needs' => [
            'The old door is dented, rotted, or done, and it is time for a new one.',
            'You are building or finishing a garage and it does not have a door yet.',
            'You want to see styles and colors side by side before anyone comes out.',
        ],
        'safety' => 'Installation day means panels, tracks, and springs moving through your garage. Keep kids, pets, and cars out of the work area, and never stand under a door that is partway up. Our techs will tell you when the garage is safe to use again.',
        'process' => [
            'Tell us if this is a swap for an old door or a brand new opening.',
            'Play with the door builder or look through the Clopay collections and note what you like.',
            'Our tech comes out, measures the opening, and checks the track and framing.',
            'You get the exact price for your door and your garage before we order anything.',
        ],
        'options' => [
            [
                'option' => 'Replace the old door',
                'tradeoff' => 'The common job. We take down the old door and hardware and put the new one in its place.',
            ],
            [
                'option' => 'Door for a new garage',
                'tradeoff' => 'New construction or a garage that never had a door. We measure first, since framing varies house to house.',
            ],
            [
                'option' => 'Browse before you buy',
                'tradeoff' => 'The door builder shows styles, colors, windows, and glass. Treat the pictures as a preview; we confirm the real combination with you before ordering.',
            ],
        ],
        'prepare' => [
            'Decide if you are replacing an old door or starting from a bare opening.',
            'Save photos of doors you like, from the builder or from around the neighborhood.',
            'Note anything odd about your garage, like low ceilings or ductwork near the opening.',
            'Clear the area in front of and inside the garage door when it is safe to do so.',
        ],
        'faqs' => [
            [
                'question' => 'Do you install Clopay doors?',
                'answer' => 'Yes. We are an official Clopay dealer, so we can order their residential lines. Ask us about a style you saw and we will tell you if it fits your opening.',
            ],
            [
                'question' => 'Can I see what the door will look like first?',
                'answer' => 'Use the door builder on this site. You pick the collection, design, color, windows, and glass and see it all together. The images are previews, so we confirm the exact combination with you before we order the door.',
            ],
            [
                'question' => 'What happens to my old door?',
                'answer' => 'Taking down the old door and its hardware is part of a replacement. Ask the tech what happens to the old materials when we price the job, and if you want to keep panels for a project, say so before we load up.',
            ],
            [
                'question' => 'How long does an installation take?',
                'answer' => 'It depends on the door and the shape of your opening, so we give you a real timeline when we quote your job instead of guessing here.',
            ],
            [
                'question' => 'Will my old opener work with the new door?',
                'answer' => 'We check that during the measure visit. A new door can weigh more or less than the old one, so the tech looks at the opener and the springs and tells you plainly what will work.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Collections', 'route' => 'garage-doors'],
            ['label' => 'Design Your Door', 'route' => 'door-builder'],
            ['label' => 'All Garage Door Services', 'route' => 'services'],
            ['label' => 'Contact Twins', 'route' => 'contact'],
        ],
    ],
    '/garage-door-spring-repair/' => [
        'h1' => 'Garage Door Spring Repair',
        'directAnswer' => 'A broken torsion spring is the loud bang from the garage and the door that suddenly weighs a ton. Do not try to lift the door or touch the spring. Springs are under dangerous tension and belong with trained professionals. Call the number shown for your area and we will take it from there.',
        'needs' => [
            'You heard a loud bang from the garage and now the door will not lift.',
            'There is a visible gap in the coil of the spring above the door.',
            'The door feels heavy, lifts crooked, or the opener strains and gives up.',
        ],
        'safety' => 'Garage door springs are under dangerous tension and should be handled by trained professionals. Do not try to replace, adjust, wind, or unwind a spring, and do not lift a door with a broken spring by hand. It is heavier than it looks.',
        'process' => [
            'Leave the door where it is and do not run the opener over and over.',
            'Tell us what you heard and what the spring looks like from a safe distance.',
            'Our tech checks the springs, the cables, and the bearings, since they wear together.',
            'You get the exact price before we start the repair.',
        ],
        'options' => [
            [
                'option' => 'Spring replacement by our tech',
                'tradeoff' => 'The safe way to do it. Our techs have the winding bars and the training, and they check the rest of the hardware while the tension is off.',
            ],
            [
                'option' => 'Look at the whole door',
                'tradeoff' => 'A snapped spring can stress the cables and bearings, so we can go over the rest of the door while we are there. We only do that with your OK.',
            ],
            [
                'option' => 'Hold off and decide',
                'tradeoff' => 'You can hear what we found and the exact price, then decide. Just keep the door closed and hands off the spring in the meantime.',
            ],
        ],
        'prepare' => [
            'Leave the spring, the cables, and the door alone.',
            'Keep kids, pets, and cars away from the door.',
            'If your car is trapped inside, tell us when you call. Do not try to muscle the door up.',
            'Look at the spring from a distance and note whether you see a gap in the coil.',
        ],
        'faqs' => [
            [
                'question' => 'Can I replace the spring myself?',
                'answer' => 'No, and we say that for your safety, not for the sale. Springs are under dangerous tension and should be handled by trained professionals. People get hurt badly trying this with hardware store parts.',
            ],
            [
                'question' => 'How do I know the spring is broken?',
                'answer' => 'The classic signs: a loud bang from the garage, a visible gap in the coil above the door, a door that suddenly feels very heavy, or an opener that hums and quits. Tell us what you see and we will confirm it in person.',
            ],
            [
                'question' => 'My car is stuck in the garage. Now what?',
                'answer' => 'Do not force the door up. A door with a broken spring is dead weight and can drop. Tell us the car is trapped when you call and we will plan around it.',
            ],
            [
                'question' => 'Do you replace both springs or just the broken one?',
                'answer' => 'It depends on your door and how the springs have worn. Two springs on the same door age together, so the tech will show you both and give you a price each way. You pick.',
            ],
            [
                'question' => 'What does a spring repair cost?',
                'answer' => 'We do not quote spring prices sight unseen, because door size, spring size, and hardware condition change the job. The tech gives you the exact price before touching anything.',
            ],
        ],
        'links' => [
            ['label' => 'All Garage Door Services', 'route' => 'services'],
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
            ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
            ['label' => 'Contact Twins', 'route' => 'contact'],
        ],
    ],
    '/garage-door-opener-repair/' => [
        'h1' => 'Garage Door Opener Repair',
        'directAnswer' => 'When the opener hums, clicks, or just stares back at you, we can figure it out. Our tech checks the motor, the trolley, the photo eyes, and the remotes, then tells you if it is a fix or time for a new unit. You see the exact price first. Call the number shown for your area.',
        'needs' => [
            'The opener hums or clicks but the door does not move.',
            'The door reverses partway down, or the wall button works when the remote does not.',
            'The unit is old and loud and you want to know if fixing it is still worth it.',
        ],
        'safety' => 'If the door reverses on its own or moves when it should not, unplug the opener and stop using it. Do not touch the springs or cables while poking at the opener. Springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Tell us what the opener does when you press the button, and do not keep cycling it.',
            'Our tech tests the motor, the drive, the photo eyes, and the safety reverse.',
            'We tell you whether a repair makes sense or the unit is at the end of its road.',
            'You see the exact price before we do the work.',
        ],
        'options' => [
            [
                'option' => 'Repair the opener',
                'tradeoff' => 'Good when the problem is a part we can swap, like a gear, a trolley, or the photo eyes. We confirm the cause in person before promising anything.',
            ],
            [
                'option' => 'Replace the opener',
                'tradeoff' => 'Worth pricing when the unit is old or the motor is going. We install LiftMaster openers and can quote one on the spot.',
            ],
            [
                'option' => 'Check the door itself',
                'tradeoff' => 'Sometimes the opener is fine and the door is binding. If that is what we find, we will show you, not just tell you.',
            ],
        ],
        'prepare' => [
            'Note what the opener does: hums, clicks, moves a few inches, or nothing at all.',
            'Check whether the wall button and the remote behave differently.',
            'Look for the brand and model on the motor housing if you can see it from the floor.',
            'Leave the springs and cables alone while you wait.',
        ],
        'faqs' => [
            [
                'question' => 'Why does my door go back up when it is almost closed?',
                'answer' => 'Check the photo eyes first, the little sensors near the floor on each side of the door. If a lens is dirty or one got bumped out of line, the opener reverses to protect whatever it thinks is under the door. Wipe them, line them up, and try again. Still doing it? Call us.',
            ],
            [
                'question' => 'The remote is dead but the wall button works. Is that a big deal?',
                'answer' => 'Start with the battery in the remote. If a fresh one does not fix it, the remote may want reprogramming or replacing, which is a quick job for the tech during a visit.',
            ],
            [
                'question' => 'Should I repair the opener or buy a new one?',
                'answer' => 'We price both and tell you what we would do in your garage. The age of the unit, what broke, and what parts still exist for it all factor in. We install LiftMaster openers if replacement wins.',
            ],
            [
                'question' => 'Do you tell me the price before you start?',
                'answer' => 'Yes. The tech diagnoses the opener first and gives you the exact price in writing. No work starts until you approve it.',
            ],
            [
                'question' => 'The opener quit with the door down and my car inside. What now?',
                'answer' => 'Every opener has a red release cord hanging from the rail. Pull it and the door disconnects so you can lift it by hand. If the door will not lift or feels very heavy, stop. A spring may be broken. Leave it down and call us.',
            ],
        ],
        'links' => [
            ['label' => 'All Garage Door Services', 'route' => 'services'],
            ['label' => 'Garage Door Spring Repair', 'route' => 'spring-repair'],
            ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
            ['label' => 'Contact Twins', 'route' => 'contact'],
        ],
    ],
    '/emergency-garage-services/' => [
        'h1' => 'Emergency Garage Door Service',
        'directAnswer' => 'A door that will not close, jumped its track, or dropped a spring is not a wait-and-see problem. Keep everyone away from it and do not force it. Call the number shown for your area, tell us what happened and whether anyone is at risk, and we will tell you when we can be there.',
        'needs' => [
            'The door will not close and you cannot leave the house open overnight.',
            'The door came off its track, is hanging crooked, or dropped on one side.',
            'A spring or cable let go and the door is stuck with your car behind it.',
        ],
        'safety' => 'If anyone is hurt or trapped, call 911 first. Otherwise keep people, pets, and cars away from the door, do not force it, and leave the springs and cables alone no matter how tempting a quick fix looks.',
        'process' => [
            'Call and tell us the door position, what let go, and whether anyone is at risk.',
            'We tell you honestly when we can get a tech there. Timing changes day to day, so we confirm it on the phone.',
            'The tech makes the door safe first, then walks you through what it will take to fix it.',
            'You approve the exact price before repair work starts.',
        ],
        'options' => [
            [
                'option' => 'Get us out there',
                'tradeoff' => 'Call and we will tell you when a tech can come. We do not print hours or arrival windows on this page because they change, so ask us live.',
            ],
            [
                'option' => 'Make it safe and wait',
                'tradeoff' => 'If the door is closed and stable, sometimes the sane move is to keep everyone away and book the visit for when it suits you.',
            ],
            [
                'option' => 'Call 911 instead',
                'tradeoff' => 'If a person is pinned, hurt, or trapped under the door, that call comes before ours.',
            ],
        ],
        'prepare' => [
            'Get people and pets away from the door and keep them away.',
            'Do not run the opener again just to see what happens.',
            'From a safe distance, note the door position and anything hanging, bent, or snapped.',
            'If your car is stuck inside and you have somewhere to be, say so when you call.',
        ],
        'faqs' => [
            [
                'question' => 'What counts as a garage door emergency?',
                'answer' => 'A door that will not close, a door off its track, a snapped spring or cable, or anything that leaves the door hanging where it could fall. If you are staring at the door wondering if it is safe, call and describe it. We will tell you.',
            ],
            [
                'question' => 'Can you come right now?',
                'answer' => 'Call the number on this page and ask. We schedule based on what is happening that day, so the honest answer lives on the phone, not on a web page.',
            ],
            [
                'question' => 'The door will not close and I have to leave. What do I do?',
                'answer' => 'Call us first. Depending on what broke, the tech may be able to talk you through disconnecting the opener and closing the door by hand safely. If the spring is broken, do not try it. The door will be far heavier than normal.',
            ],
            [
                'question' => 'Should I try to push the door back onto its track?',
                'answer' => 'No. A door off its track is held up by hardware that is already stressed, and it can come down fast. Keep clear and leave it for the tech.',
            ],
            [
                'question' => 'Do you fix it on the spot?',
                'answer' => 'The tech comes ready to make the door safe and to fix what can be fixed during the visit. If a part has to be ordered, we secure the door and tell you the plan and the price before anything else happens.',
            ],
        ],
        'links' => [
            ['label' => 'All Garage Door Services', 'route' => 'services'],
            ['label' => 'Garage Door Spring Repair', 'route' => 'spring-repair'],
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
            ['label' => 'Contact Twins', 'route' => 'contact'],
        ],
    ],
    '/garage-door-services/' => [
        'h1' => 'Garage Door Services',
        'directAnswer' => 'We repair garage doors, install new ones, fix and replace openers, and handle the emergencies that cannot wait. This page is the map: pick the service that sounds like your problem, or just call the number shown for your area and describe what the door is doing. We will point you right.',
        'needs' => [
            'Something is wrong with the door and you are not sure which service fits.',
            'You want to see everything we do before picking up the phone.',
            'You would rather describe the problem and let us figure out where it goes.',
        ],
        'safety' => 'Whatever brought you here, the same rule applies: if the door looks unsafe, stop using it and keep kids, pets, and cars clear. Never touch the springs or cables. Springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Figure out whether the problem is the door, the opener, or something urgent.',
            'Pick the matching service page, or skip that and call the number shown.',
            'Our tech comes out and looks at the actual door instead of guessing from a description.',
            'You hear what we found and see the exact price before any work starts.',
        ],
        'options' => [
            [
                'option' => 'Repair',
                'tradeoff' => 'The door itself: springs, cables, rollers, panels, doors off track, doors stuck halfway.',
            ],
            [
                'option' => 'Installation',
                'tradeoff' => 'A new door, whether the old one is shot or the garage never had one. We are an official Clopay dealer.',
            ],
            [
                'option' => 'Openers',
                'tradeoff' => 'Troubleshooting, repair, and new units. We install LiftMaster openers.',
            ],
            [
                'option' => 'Emergency',
                'tradeoff' => 'A door that will not close, is off its track, or is hanging in a way that scares you.',
            ],
        ],
        'prepare' => [
            'Watch what the door does once, then stop running it.',
            'Decide whether this can wait for a scheduled visit or cannot wait at all.',
            'Keep everyone clear of the door if it looks off.',
            'Have the number on this page handy when you call.',
        ],
        'faqs' => [
            [
                'question' => 'What services do you offer?',
                'answer' => 'Garage door repair, new door installation, spring and cable repair, opener repair and replacement, and emergency service. Each one has its own page, but every job starts the same way: a tech looks at your door and you see the exact price before work begins.',
            ],
            [
                'question' => 'I do not know what is wrong with my door. Which page do I pick?',
                'answer' => 'Skip the pages and call. Describe what the door is doing, the noise, the position, whatever you noticed, and we will figure out what kind of visit it is. You do not have to diagnose your own door.',
            ],
            [
                'question' => 'Are you licensed and insured?',
                'answer' => 'Yes. We are a licensed and insured local garage door company, and the tech who shows up at your house works for us.',
            ],
            [
                'question' => 'Do you give prices over the phone?',
                'answer' => 'We give exact prices after a tech sees the door, because two doors with the same symptom can be two very different jobs. What we can promise is that you see the full price in writing before we start.',
            ],
            [
                'question' => 'What if my door is unsafe right now?',
                'answer' => 'Stop using it and keep everyone clear. Do not touch the springs or cables. Then use the emergency service page or just call the number shown here.',
            ],
        ],
        'links' => [
            ['label' => 'Garage Door Repair', 'route' => 'repair'],
            ['label' => 'Garage Door Installation', 'route' => 'installation'],
            ['label' => 'Garage Door Spring Repair', 'route' => 'spring-repair'],
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
            ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
        ],
    ],
    '/garage-door-cable-repair/' => [
        'h1' => 'Garage Door Cable Repair',
        'directAnswer' => 'Garage door cables do the heavy lifting alongside the springs, and when one frays or jumps off its drum, the door hangs crooked or stops dead. Do not touch the cable and do not run the door. Call the number shown for your area and we will get a tech out to look at it.',
        'needs' => [
            'A cable is frayed, snapped, or hanging loose beside the track.',
            'The cable came off the drum at the top of the track and the door sits crooked.',
            'One side of the door dropped, or the door slammed down harder than normal.',
        ],
        'safety' => 'Cables carry the same dangerous tension as the springs they work with, and they should be handled by trained professionals. Do not pull, unwind, or reattach a cable, and do not run the door, even with the opener, until it has been looked at.',
        'process' => [
            'Stop using the door, including the opener button.',
            'Tell us which side looks wrong and what you can see from a safe distance.',
            'Our tech checks the cables, the drums, the springs, and the bottom brackets together.',
            'You see the exact price before the repair starts.',
        ],
        'options' => [
            [
                'option' => 'Cable repair by our tech',
                'tradeoff' => 'The tech takes the tension off safely, fixes or replaces the cable, and rewinds everything to spec.',
            ],
            [
                'option' => 'Check the whole counterbalance',
                'tradeoff' => 'A cable rarely fails alone. If the drums, springs, or bottom brackets show wear, we will show you before touching them.',
            ],
            [
                'option' => 'Hold off and decide',
                'tradeoff' => 'Take what we found and the price and think it over. Just keep the door parked and hands off the hardware while you do.',
            ],
        ],
        'prepare' => [
            'Leave the cables, springs, and bottom brackets alone.',
            'Do not press the opener button to test it again.',
            'Keep kids, pets, and cars away from the door.',
            'From a safe spot, note which side looks slack or which cable is off its drum.',
        ],
        'faqs' => [
            [
                'question' => 'Can I put the cable back on the drum myself?',
                'answer' => 'No. The cable is tied into the spring system, and the whole thing is under dangerous tension. Getting it wrong can drop the door or whip the cable. This one is worth the service call.',
            ],
            [
                'question' => 'How do I know it is a cable and not a spring?',
                'answer' => 'You may not, and that is fine. A crooked door or a slack cable points at the cable, and a loud bang points at the spring, but they fail together enough that we check both. Tell us what you see and leave the naming to us.',
            ],
            [
                'question' => 'Can I still use the door until you get here?',
                'answer' => 'No. Every cycle grinds on hardware that is already failing, and that can turn a small repair into a big one or bring the door down. Leave it where it is.',
            ],
            [
                'question' => 'Do you replace cables in pairs?',
                'answer' => 'The tech will tell you after looking at both sides. Cables wear together, so if one is frayed the other gets a hard look. You see the price for whatever we recommend before we start.',
            ],
            [
                'question' => 'What does cable repair cost?',
                'answer' => 'It depends on the door, the drums, and what else got stressed when the cable let go. The tech gives you the exact price on site before doing any work.',
            ],
        ],
        'links' => [
            ['label' => 'All Garage Door Services', 'route' => 'services'],
            ['label' => 'Garage Door Spring Repair', 'route' => 'spring-repair'],
            ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
            ['label' => 'Contact Twins', 'route' => 'contact'],
        ],
    ],
    '/garage-door-openers/' => [
        'h1' => 'Garage Door Openers',
        'directAnswer' => 'Shopping for a garage door opener? We install LiftMaster openers and can help you pick between belt, chain, and wall-mount drives, plus extras like keypads, battery backup, and app control. Our tech checks your door and ceiling before anything is ordered. Call or request a quote with the number shown for your area.',
        'needs' => [
            'The garage has no opener and you are done lifting the door by hand.',
            'The old opener is on its last legs and you want to replace it before it strands you.',
            'You want keypads, remotes, or phone control and the current unit cannot do it.',
            'You are comparing belt, chain, and wall-mount drives and want a plain explanation.',
        ],
        'safety' => 'Keep kids, pets, and cars out of the garage while an opener is being installed and tested. If the door ever has to be moved by hand, pull the release cord first. And never touch the springs. They are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Note what you want out of the opener: remotes, keypad, battery backup, app control.',
            'Talk through the LiftMaster lineup with us and narrow it to a fit for your garage.',
            'Our tech checks your door weight, ceiling clearance, and power outlet on site.',
            'You see the exact price before the install goes on the calendar.',
        ],
        'options' => [
            [
                'option' => 'First opener for the door',
                'tradeoff' => 'For a garage that never had one. The tech confirms the door is balanced before hanging a motor on it.',
            ],
            [
                'option' => 'Swap out an old unit',
                'tradeoff' => 'We take the old opener down, hang the new one, and program your remotes and keypad.',
            ],
            [
                'option' => 'Compare drives and features',
                'tradeoff' => 'Belt, chain, and wall-mount drives each mount and run differently. We explain the differences for your garage instead of reading you a brochure.',
            ],
            [
                'option' => 'Not sure it is dead yet',
                'tradeoff' => 'If the current opener might just want a part, start with the opener repair page instead. We do not replace what we can fix.',
            ],
        ],
        'prepare' => [
            'List what matters to you: remotes, keypad, battery backup, lighting, app control.',
            'Check the brand and model sticker on your current opener if you have one.',
            'Glance at your ceiling and note anything in the way, like storage racks or low beams.',
            'Leave measurements and clearance checks to the tech.',
        ],
        'faqs' => [
            [
                'question' => 'What opener brand do you install?',
                'answer' => 'LiftMaster. We install their residential line, and the tech confirms which models fit your door and ceiling during the visit.',
            ],
            [
                'question' => 'Belt, chain, or wall-mount: how do I choose?',
                'answer' => 'Each mounts and drives the door differently, and ceiling room, door size, and where your outlet sits all matter. Tell us about your garage and we will point you to the drives that fit it.',
            ],
            [
                'question' => 'Can you fix my old opener instead?',
                'answer' => 'If it can be fixed, yes. Head to the opener repair page or just tell us what it is doing when you call. We only quote a new unit when a repair does not make sense.',
            ],
            [
                'question' => 'What is battery backup for?',
                'answer' => 'It runs the opener during a power outage, so the door still opens when the lights are out. Whether it is built in depends on the model, and the tech can show you which ones have it.',
            ],
            [
                'question' => 'Can I install the opener myself?',
                'answer' => 'We would rather you did not. The install means overhead mounting, wiring, and setting the force and travel limits so the door reverses when it should. Done wrong, the door will not stop for a person or a car. It also ties into the spring system, which is under dangerous tension and belongs with trained professionals.',
            ],
            [
                'question' => 'Do I see the price before the install?',
                'answer' => 'Yes. The tech confirms the opener and the work on site and gives you the exact price before anything is ordered or installed.',
            ],
        ],
        'links' => [
            ['label' => 'All Garage Door Services', 'route' => 'services'],
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
            ['label' => 'Garage Door Installation', 'route' => 'installation'],
            ['label' => 'Contact Twins', 'route' => 'contact'],
        ],
    ],
    '/garage-weatherstripping-repair/' => [
        'h1' => 'Weatherstripping Repair',
        'directAnswer' => 'That daylight under the garage door is how the cold, the rain, and the mice get in. We replace worn bottom seals and the side and top trim seals, and we check how the door sits in the opening while we are at it. Call or request a quote with the number shown for your area.',
        'needs' => [
            'You can see daylight under or around the closed door.',
            'Rain, leaves, snow, or mice are finding their way into the garage.',
            'The bottom seal is cracked, flattened, or torn, or the side trim is peeling off.',
        ],
        'safety' => 'The bottom seal sits inches from the rollers and the lift cables, so leave the hardware to us. Keep kids, pets, and cars clear while the door is being worked on, and never touch the springs. They are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Walk the closed door and note where you see light, feel drafts, or find water.',
            'Our tech checks the seals and how the door sits against the floor and the jambs.',
            'We tell you which seals are worn and whether the door itself is part of the gap.',
            'You see the exact price before we replace anything.',
        ],
        'options' => [
            [
                'option' => 'New seals',
                'tradeoff' => 'The simple fix when the rubber is just worn out. We match the seal to your door and its retainer.',
            ],
            [
                'option' => 'Look deeper at the door',
                'tradeoff' => 'If the gap follows the door instead of the seal, the door may be sitting crooked in the opening. We check that before selling you rubber.',
            ],
            [
                'option' => 'Wait and watch',
                'tradeoff' => 'A small gap is not an emergency. Take the price and decide when it suits you.',
            ],
        ],
        'prepare' => [
            'Close the door and note where light or drafts come through.',
            'Take a photo of the worn seal from outside the door opening.',
            'Pull stored items back from the inside of the door so the tech can reach it.',
            'Skip the hardware store fix until we have looked. Some retainers are odd sizes.',
        ],
        'faqs' => [
            [
                'question' => 'What is garage door weatherstripping exactly?',
                'answer' => 'The rubber seal along the bottom of the door plus the trim seals down the sides and across the top of the opening. Together they close the gaps between the door and the outdoors. When any of them crack or flatten, weather and pests come through.',
            ],
            [
                'question' => 'How do I know the seals are shot?',
                'answer' => 'Daylight under the closed door, drafts you can feel, water lines on the floor after rain, leaves in the corners, or rubber that is visibly cracked and stiff. Any one of those is reason enough to have it looked at.',
            ],
            [
                'question' => 'Could the gap be the door and not the seal?',
                'answer' => 'Yes, and that is exactly why we check both. A door that sits crooked in the opening leaves a gap no seal can close. If that is what we find, we show you before any parts get ordered.',
            ],
            [
                'question' => 'Can I replace the bottom seal myself?',
                'answer' => 'The seal slides into a retainer near the rollers and lift cables, and on some doors the retainer itself is worn or bent. We would rather do it than have you working next to that hardware. Leave the springs alone either way; they are under dangerous tension and belong with trained professionals.',
            ],
            [
                'question' => 'Do I get the price before you start?',
                'answer' => 'Yes. The tech checks the seals and the door, tells you what is involved, and gives you the exact price before any work begins.',
            ],
        ],
        'links' => [
            ['label' => 'All Garage Door Services', 'route' => 'services'],
            ['label' => 'Garage Door Repair', 'route' => 'repair'],
            ['label' => 'Contact Twins', 'route' => 'contact'],
        ],
    ],
    '/garage-door-tune-up/' => [
        'h1' => 'Garage Door Tune-Up',
        'directAnswer' => 'A tune-up is a planned visit where our tech tightens the hardware, lubricates the rollers, hinges, and springs, checks the cables and the balance, and runs the opener through its tests. It is the visit you book before something breaks. Call the number shown for your area to set one up.',
        'needs' => [
            'The door works but it is louder, slower, or jerkier than it used to be.',
            'You cannot remember the last time anyone looked at the door.',
            'You want small wear caught before it turns into a stuck door on a work morning.',
        ],
        'safety' => 'Before the visit, do not oil, tighten, or adjust anything on the spring system yourself. Springs are under dangerous tension and should be handled by trained professionals. If the door starts acting unsafe before we get there, stop using it and keep everyone clear.',
        'process' => [
            'Book a visit using the number shown on this page.',
            'Our tech tightens the hardware, lubricates the moving parts, and checks the springs, cables, rollers, and balance.',
            'We run the opener through its safety tests and show you anything wearing out.',
            'If something does come up, you see the exact price before any extra work happens.',
        ],
        'options' => [
            [
                'option' => 'One tune-up visit',
                'tradeoff' => 'Good for a door that runs fine and just deserves a once-over.',
            ],
            [
                'option' => 'Repair visit instead',
                'tradeoff' => 'If the door is already stuck, crooked, or banging, book a repair. A tune-up is for doors that still work.',
            ],
            [
                'option' => 'Put it on a schedule',
                'tradeoff' => 'Ask about a maintenance plan if you want us coming back on a regular rhythm instead of when you remember.',
            ],
        ],
        'prepare' => [
            'Note any new noises, shudders, or slow spots in the door travel.',
            'Move cars and clutter back so the tech can reach the tracks on both sides.',
            'Write down any questions about the door or opener while you think of them.',
        ],
        'faqs' => [
            [
                'question' => 'What do you actually do during a tune-up?',
                'answer' => 'Tighten the hinges and track hardware, lubricate the rollers, hinges, springs, and opener rail, check the cables and bottom brackets for wear, test the door balance, and run the opener safety reverse. If anything looks tired, we show it to you and price the fix separately.',
            ],
            [
                'question' => 'My door works fine. Is a tune-up worth it?',
                'answer' => 'A garage door is the heaviest moving thing in the house, and it wears quietly until the day it does not. A tune-up catches loose hardware and dry rollers while they are still small, boring problems.',
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
                'answer' => 'We show you what we found, on the door, not in a brochure. If nothing is wrong we say so. If something is wearing out, you get the exact price and you decide. Nothing extra happens without your OK.',
            ],
        ],
        'links' => [
            ['label' => 'All Garage Door Services', 'route' => 'services'],
            ['label' => 'Garage Door Repair', 'route' => 'repair'],
            ['label' => 'Garage Door Opener Repair', 'route' => 'opener-repair'],
            ['label' => 'Contact Twins', 'route' => 'contact'],
        ],
    ],
    '/maintenance-plans/' => [
        'h1' => 'Maintenance Plans',
        'directAnswer' => 'A maintenance plan puts your garage door on our calendar instead of yours. We come back at set intervals, run the full tune-up, and flag wear before it strands your car. Plan details and pricing get confirmed with our office before you sign up. Call the number shown for your area to ask.',
        'needs' => [
            'You want the door checked on a schedule without having to remember to book it.',
            'The door gets heavy daily use and small wear adds up fast.',
            'You look after a home or building where a dead garage door becomes your problem at the worst time.',
        ],
        'safety' => 'A plan does not change the ground rules between visits: if the door starts acting unsafe, stop using it and keep everyone clear. Do not touch the springs or cables. Springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Call the number shown and ask about a maintenance plan for your door.',
            'We go over what the plan covers, what it costs, and how visits get scheduled.',
            'Sign up once the details make sense to you. No pressure before that.',
            'Our tech handles the visits and shows you anything worth keeping an eye on.',
        ],
        'options' => [
            [
                'option' => 'Maintenance plan',
                'tradeoff' => 'We track the schedule and come to you. Details and pricing get confirmed with the office before you commit.',
            ],
            [
                'option' => 'One tune-up at a time',
                'tradeoff' => 'If a plan feels like too much, book single tune-ups whenever you like. The door does not care how it gets serviced.',
            ],
            [
                'option' => 'Multiple properties',
                'tradeoff' => 'If you manage several buildings or doors, ask about property management service instead. Same idea, bigger scale.',
            ],
        ],
        'prepare' => [
            'Count the doors and openers you want covered.',
            'Note anything the door is already doing that bugs you, for the first visit.',
            'Have your preferred phone or email ready for scheduling.',
        ],
        'faqs' => [
            [
                'question' => 'What does a garage door maintenance plan include?',
                'answer' => 'Scheduled tune-up visits: hardware tightening, lubrication, and a check of the springs, cables, rollers, balance, and opener. The exact inclusions get spelled out by our office before you enroll, so ask and we will walk you through it.',
            ],
            [
                'question' => 'How much does a maintenance plan cost?',
                'answer' => 'It depends on the doors and the schedule, so the office quotes it for your situation when you call. We do not post plan prices here because they would be wrong for half the people reading them.',
            ],
            [
                'question' => 'How is the visit schedule set?',
                'answer' => 'We agree on it together when you enroll, then our office reaches out ahead of each visit to lock in a time. You are never left guessing when we will show up.',
            ],
            [
                'question' => 'What happens if you find a problem during a visit?',
                'answer' => 'The tech shows you the worn part, explains what it means, and gives you the exact price for the fix. Repairs are separate from the plan visit and never start without your OK.',
            ],
            [
                'question' => 'Can one plan cover several doors or buildings?',
                'answer' => 'Yes, ask when you set it up. For a real portfolio of properties, the property management side of our work is built for exactly that.',
            ],
        ],
        'links' => [
            ['label' => 'All Garage Door Services', 'route' => 'services'],
            ['label' => 'Garage Door Repair', 'route' => 'repair'],
            ['label' => 'Contact Twins', 'route' => 'contact'],
        ],
    ],
    '/property-management-services/' => [
        'h1' => 'Property Management Services',
        'directAnswer' => 'If you manage rentals, condos, or commercial buildings, we can be the one call for every garage door in your portfolio. We are a licensed and insured local company, and we document what we find and price the work before you authorize it. Call the number shown for your area to set things up.',
        'needs' => [
            'You manage properties with garage doors and tenants who call you when they break.',
            'You want one company handling repairs, replacements, and upkeep across your buildings.',
            'You have to justify every invoice, so you want findings and prices in writing before work starts.',
        ],
        'safety' => 'If a door at one of your properties looks unsafe, take it out of service and keep tenants, residents, and cars away from it. Make sure nobody on site touches the springs or cables. Springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Tell us about the properties, how many doors, and what kind of coverage you are after.',
            'We set up scheduling, authorization, and billing the way your office works.',
            'Our tech inspects each door issue on site and documents what we find.',
            'You get the findings and the exact price before you authorize the work.',
        ],
        'options' => [
            [
                'option' => 'Call us as things break',
                'tradeoff' => 'No setup beyond a first conversation. You call when a tenant reports a door problem and we take it from there.',
            ],
            [
                'option' => 'Scheduled upkeep',
                'tradeoff' => 'Regular tune-up visits across the portfolio, so doors get caught wearing out instead of failing. Details get worked out with our office.',
            ],
            [
                'option' => 'Door replacements',
                'tradeoff' => 'When a door at a property is past fixing, we quote the replacement. We are an official Clopay dealer.',
            ],
        ],
        'prepare' => [
            'List the properties and roughly how many garage doors are on each.',
            'Note any doors already acting up and which buildings they are in.',
            'Decide who on your team approves work and who receives the reports.',
            'Gather gate codes or access instructions for buildings we cannot just walk into.',
        ],
        'faqs' => [
            [
                'question' => 'Do you work with property managers?',
                'answer' => 'Yes, on rentals, condos, and commercial buildings. We are a licensed and insured local garage door company, and we are used to working around tenants, lockboxes, and approval chains.',
            ],
            [
                'question' => 'Can you handle doors across several buildings?',
                'answer' => 'Yes. Tell us about the portfolio and we coordinate scheduling across the locations. One point of contact on your side, one on ours.',
            ],
            [
                'question' => 'How do approvals and billing work?',
                'answer' => 'However your office runs. We set up who can authorize work and where invoices go when the account starts, and no job begins until the approved person has the findings and the exact price.',
            ],
            [
                'question' => 'Can tenants call you directly?',
                'answer' => 'Only if you want them to. Some managers route everything through their office, and others let tenants book directly and just get copied on the paperwork. You pick the setup and we follow it.',
            ],
            [
                'question' => 'A door at my property is unsafe right now. What do I do?',
                'answer' => 'Take it out of service, keep tenants and cars away, and call the number on this page. Tell us it is a tenant-facing safety issue and describe what the door is doing.',
            ],
        ],
        'links' => [
            ['label' => 'All Garage Door Services', 'route' => 'services'],
            ['label' => 'Garage Door Repair', 'route' => 'repair'],
            ['label' => 'Garage Door Installation', 'route' => 'installation'],
            ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
            ['label' => 'Contact Twins', 'route' => 'contact'],
        ],
    ],
    '/protection-plans/' => [
        'h1' => 'TwinShield Protection Plan',
        'directAnswer' => 'TwinShield is our service plan for the whole garage door system: scheduled attention from our techs plus the plan benefits that come with membership. What it includes, what it costs, and how visits get scheduled are all spelled out by our office before you enroll. Call the number shown for your area and ask.',
        'needs' => [
            'You want ongoing care for the door and opener instead of booking visits one at a time.',
            'You want the plan explained in plain terms before you commit to anything.',
            'You are comparing a plan against just calling us when something breaks.',
        ],
        'safety' => 'A plan changes how the door gets looked after, not what to do in a bad moment. If the door acts unsafe, stop using it and keep everyone clear. Do not touch the springs or cables. Springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Call the number shown and ask about TwinShield.',
            'We go over what the plan includes, what it costs, and how the visits work.',
            'Enroll once it all makes sense. If it does not fit your situation, we will say so.',
            'After you enroll, use the plan contact info our office gives you whenever the door acts up.',
        ],
        'options' => [
            [
                'option' => 'Ask about TwinShield',
                'tradeoff' => 'Sit down with the details before deciding: inclusions, price, and scheduling, all in writing from our office.',
            ],
            [
                'option' => 'Maintenance plan instead',
                'tradeoff' => 'If scheduled tune-ups are all you are after, the maintenance plan may be the simpler fit. Ask us to compare them for your door.',
            ],
            [
                'option' => 'No plan at all',
                'tradeoff' => 'Also fine. Plenty of customers just call when something breaks, and we treat those jobs the same way.',
            ],
        ],
        'prepare' => [
            'Note roughly how old the door and opener are, if you know.',
            'Write down the questions you want answered before signing anything.',
            'Decide who in the house or the business will manage the plan.',
        ],
        'faqs' => [
            [
                'question' => 'What is the TwinShield Protection Plan?',
                'answer' => 'It is our service plan for the garage door system. Our office walks you through exactly what it includes, what it costs, and how visits are scheduled, and nothing counts until it is confirmed with you in writing at enrollment.',
            ],
            [
                'question' => 'How much does TwinShield cost?',
                'answer' => 'The office quotes it when you call, based on your door and setup. We keep the number off this page so nobody signs up around a stale price.',
            ],
            [
                'question' => 'What does the plan include?',
                'answer' => 'The current inclusions are laid out by our office before you enroll, and that conversation is the source of truth, not this page. Ask us to walk through it line by line.',
            ],
            [
                'question' => 'How is TwinShield different from a maintenance plan?',
                'answer' => 'The maintenance plan is about scheduled tune-up visits. TwinShield is the broader plan built around the whole door system. Ask us to compare the two for your door, since the right answer depends on how you use the garage.',
            ],
            [
                'question' => 'If I am on the plan and the door breaks, what do I do?',
                'answer' => 'Same as anyone: stop using an unsafe door, keep everyone clear, leave the springs alone, and call us. Being on the plan changes the paperwork, not the safety rules.',
            ],
        ],
        'links' => [
            ['label' => 'All Garage Door Services', 'route' => 'services'],
            ['label' => 'Garage Door Repair', 'route' => 'repair'],
            ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
            ['label' => 'Contact Twins', 'route' => 'contact'],
        ],
    ],
];
