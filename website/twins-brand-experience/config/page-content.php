<?php
declare(strict_types=1);

return [
    '/garage-door-repair/' => [
        'h1' => 'Garage Door Repair',
        'directAnswer' => 'Garage door repair is the service path when a door or related hardware is not working as expected. A technician can inspect the door, explain the findings and available repair or replacement paths, and provide an exact price before work begins. Use the regional call or quote option shown for your selected service area.',
        'needs' => [
            'The door or related hardware is not working as expected.',
            'The concern involves a spring, cable, roller, or a door that will not move correctly.',
            'You want to compare a repair path with a replacement-door path after inspection.',
        ],
        'safety' => 'If movement appears unsafe, stop using the door and keep people, pets, and vehicles clear. Do not handle or adjust the spring system; springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Describe what the door is doing and where it stopped without repeatedly operating it.',
            'A technician inspects the door and related service concern.',
            'Review the findings and the available repair or replacement paths.',
            'Review the exact price before work begins.',
        ],
        'options' => [
            [
                'option' => 'Repair discussion',
                'tradeoff' => 'Use this discussion when inspection supports a repair; no failed part or outcome is predicted before inspection.',
            ],
            [
                'option' => 'Replacement discussion',
                'tradeoff' => 'Use this discussion when you want to compare a new-door path; inspection and pricing come before a decision.',
            ],
            [
                'option' => 'Pause before authorizing work',
                'tradeoff' => 'Review the findings and exact price before choosing a next step.',
            ],
        ],
        'prepare' => [
            'Note what you observed and where the door stopped.',
            'Take a photo from a safe distance only if it can be done without entering the door path.',
            'Clear nearby access only when it is safe to do so.',
            'Keep people, pets, and vehicles away if the door appears unstable.',
        ],
        'faqs' => [
            [
                'question' => 'What does garage door repair cover on this site?',
                'answer' => 'Use this path for a door, hardware, spring, cable, roller, or related concern. Opener repair and installation also have dedicated paths. An inspection by a technician determines the issue; this page does not diagnose a specific door.',
            ],
            [
                'question' => 'Will I know the exact price before work begins?',
                'answer' => 'A technician inspects the door and provides an exact price before repair work begins. This page does not publish a one-size-fits-all price.',
            ],
            [
                'question' => 'Can I compare repair and replacement paths?',
                'answer' => 'The service discussion can include repair and replacement paths. Inspection and individual pricing come before the decision, and no path is assumed in advance.',
            ],
            [
                'question' => 'Which phone number should I use?',
                'answer' => 'Use the regional number shown on this page. It changes with the selected service area, so the shared service copy does not repeat a phone number.',
            ],
            [
                'question' => 'What should I do if the door appears unsafe?',
                'answer' => 'Stop using it, keep people, pets, and vehicles clear, and do not force it. Do not handle the spring system. Use the regional contact shown on the page to request help.',
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
        'directAnswer' => 'Garage door installation covers planning a replacement or new garage door. Start by comparing door choices and noting what matters for the project and appearance. A technician can inspect the project and provide an exact price before work begins. Use the regional call or quote option shown for your selected service area.',
        'needs' => [
            'A replacement garage door is the project.',
            'A new garage door is being planned.',
            'You want to compare door styles before a project conversation.',
        ],
        'safety' => 'Keep people, pets, vehicles, and stored items out of the work area. Do not stand beneath a moving or partially installed door, and follow technician guidance while work is underway.',
        'process' => [
            'Share whether the project is a replacement door or a new-door plan.',
            'Compare door choices and note the appearance you want to discuss.',
            'A technician inspects and prices the project individually.',
            'Review the exact price and next steps before work begins.',
        ],
        'options' => [
            [
                'option' => 'Replacement-door planning',
                'tradeoff' => 'Use this path when an existing garage door is being replaced.',
            ],
            [
                'option' => 'New-door planning',
                'tradeoff' => 'Use this path when a new garage door is part of the project.',
            ],
            [
                'option' => 'Reference-first style comparison',
                'tradeoff' => 'Use the local door builder to compare styles; reference imagery does not prove the exact selected combination.',
            ],
        ],
        'prepare' => [
            'Note whether the project is a replacement or a new garage door.',
            'Save examples of door styles you want to discuss.',
            'Treat builder imagery as a reference and keep selected options separate from final confirmation.',
            'Clear the work area only when it is safe to do so.',
        ],
        'faqs' => [
            [
                'question' => 'Is this page for replacement doors and new-door projects?',
                'answer' => 'Yes. The installation path covers a replacement door or a new garage door project. Project details are inspected and priced individually.',
            ],
            [
                'question' => 'Can I compare door styles before requesting a quote?',
                'answer' => 'Yes. The local door builder supports a collection, design, color, windows, glass, and summary flow. It uses reference imagery, and the selected options remain separate from final appearance confirmation.',
            ],
            [
                'question' => 'Will I receive an exact price before work begins?',
                'answer' => 'A technician inspects and prices the project individually. Review the exact price before work begins; this page does not publish a one-size-fits-all price.',
            ],
            [
                'question' => 'How long does garage door installation take?',
                'answer' => 'Installation timing must be confirmed for the specific project. This fixed page does not promise a duration, lead time, appointment window, or completion date.',
            ],
            [
                'question' => 'Can this page tell me whether other equipment must change?',
                'answer' => 'No. This page does not make track or opener predictions. A technician must inspect the project before that decision is made, and no outcome is assumed in advance.',
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
        'directAnswer' => 'Garage door spring repair addresses a spring-related door concern. Springs are under dangerous tension and should be handled by trained professionals. Do not attempt replacement or adjustment. Leave the spring system untouched, keep people away from the door, and use the regional call or quote option shown on the page to request an assessment.',
        'needs' => [
            'The concern appears related to a garage door spring.',
            'You need safety guidance before anyone handles the spring system.',
            'You want a trained professional to assess the spring-related concern.',
        ],
        'safety' => 'Garage door springs are under dangerous tension and should be handled by trained professionals. Do not attempt to replace, adjust, wind, unwind, or release a spring.',
        'process' => [
            'Describe the concern without touching or moving the spring system.',
            'A trained professional assesses the spring-related concern.',
            'Review the findings and available next steps.',
            'Review the exact price before repair work begins.',
        ],
        'options' => [
            [
                'option' => 'Professional spring assessment',
                'tradeoff' => 'Keeps spring handling with trained professionals and avoids unsafe customer adjustment.',
            ],
            [
                'option' => 'Broader repair discussion',
                'tradeoff' => 'Use this when you want the technician to explain whether another service path should be considered; no diagnosis is made before inspection.',
            ],
            [
                'option' => 'Pause before authorizing work',
                'tradeoff' => 'Review the findings and exact price before choosing a next step.',
            ],
        ],
        'prepare' => [
            'Leave the spring system untouched.',
            'Keep people, pets, and vehicles away from the door.',
            'Note what you observed from a safe distance.',
            'Clear a safe path only if you can do so without passing beneath or moving the door.',
        ],
        'faqs' => [
            [
                'question' => 'Can I replace or adjust a garage door spring myself?',
                'answer' => 'No. Garage door springs are under dangerous tension and should be handled by trained professionals. Do not attempt replacement, adjustment, winding, unwinding, or release.',
            ],
            [
                'question' => 'What should I do before a professional assessment?',
                'answer' => 'Leave the spring system untouched and keep people, pets, and vehicles away from the door. Note only what you can observe from a safe distance.',
            ],
            [
                'question' => 'Can this page tell me which spring or repair is needed?',
                'answer' => 'No. This page cannot diagnose the spring system or select a repair. A trained professional must assess the specific door before options are discussed.',
            ],
            [
                'question' => 'Will I know the exact price before spring repair begins?',
                'answer' => 'A technician provides an exact price before repair work begins. This page does not publish a spring price or numeric range.',
            ],
            [
                'question' => 'How do I confirm emergency spring service availability?',
                'answer' => 'Use the regional contact shown on the page. Current service hours and response timing must be confirmed when you contact the team.',
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
        'directAnswer' => 'Garage door opener repair is the service path for opener troubleshooting, repair, and replacement questions. A technician can inspect the project, explain the findings and available options, and provide an exact price before work begins. Avoid adjusting the spring system, and use the regional call or quote option shown for your selected service area.',
        'needs' => [
            'You have a garage door opener troubleshooting question.',
            'You are considering an opener repair path.',
            'You want to compare opener repair and replacement options.',
        ],
        'safety' => 'Stop using the opener if the door moves unpredictably or the situation appears unsafe. Do not adjust the spring system; springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Describe the opener question and what you observed without repeatedly operating the door.',
            'A technician inspects the opener-related service concern.',
            'Review the findings and the available repair or replacement paths.',
            'Review the exact price before work begins.',
        ],
        'options' => [
            [
                'option' => 'Opener repair discussion',
                'tradeoff' => 'Use this for opener troubleshooting and repair questions; the cause is not predicted before inspection.',
            ],
            [
                'option' => 'Opener replacement discussion',
                'tradeoff' => 'Use this to compare replacement options without assuming replacement is required.',
            ],
            [
                'option' => 'Related door-service discussion',
                'tradeoff' => 'Use this if inspection points to another service path; no cause or outcome is promised in advance.',
            ],
        ],
        'prepare' => [
            'Note what you observed without repeatedly operating the door.',
            'Record the opener make or model only if it is visible from a safe position.',
            'Keep the area around the door clear.',
            'Do not adjust the spring system.',
        ],
        'faqs' => [
            [
                'question' => 'Does this page cover opener repair and replacement questions?',
                'answer' => 'Yes. This path covers opener troubleshooting, repair, and replacement questions. It does not diagnose the cause or assume that replacement is required.',
            ],
            [
                'question' => 'What should I note before contacting the team?',
                'answer' => 'Note what you observed without repeatedly operating the door. Record the opener make or model only if it is visible from a safe position.',
            ],
            [
                'question' => 'Will a repair or replacement be recommended in advance?',
                'answer' => 'No. A technician must inspect the project before options are discussed. This page does not rank causes or assume a repair or replacement outcome.',
            ],
            [
                'question' => 'Will I know the exact price before work begins?',
                'answer' => 'A technician inspects the project and provides an exact price before work begins. This page does not publish a one-size-fits-all price.',
            ],
            [
                'question' => 'What if the opener concern leaves the door stuck or unsafe?',
                'answer' => 'Stop operating it, keep people, pets, and vehicles clear, and use the emergency-service or regional contact path. Confirm current availability when you contact the team.',
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
        'directAnswer' => 'Emergency garage door service is the route for urgent concerns such as a door that is stuck, will not close, is off track, or presents a safety risk. Keep people clear and do not force the door. Use the regional contact shown on the page, and confirm current availability and response timing when you contact the team.',
        'needs' => [
            'The garage door will not close.',
            'The garage door is stuck or off track.',
            'A broken spring or another condition presents a safety concern.',
        ],
        'safety' => 'If anyone is injured, trapped, or in immediate danger, contact emergency services. Keep people, pets, and vehicles clear, do not force the door, and do not touch the spring system.',
        'process' => [
            'Describe the door position, the visible concern, and whether anyone is at risk.',
            'Confirm whether service is currently available; the fixed content does not promise hours or response time.',
            'If service is arranged, a technician inspects the urgent door concern.',
            'Review findings, options, and price before authorizing repair work.',
        ],
        'options' => [
            [
                'option' => 'Urgent service request',
                'tradeoff' => 'Ask whether service is currently available; no hours or response time are implied.',
            ],
            [
                'option' => 'Safety-first pause',
                'tradeoff' => 'Keep clear and do not force the door while the next step is being arranged.',
            ],
            [
                'option' => 'Emergency-services fallback',
                'tradeoff' => 'Use this when a person is injured, trapped, or in immediate danger.',
            ],
        ],
        'prepare' => [
            'Move people and pets away from the door.',
            'Do not force the door open or closed.',
            'From a safe distance, note the door position and anything visibly damaged.',
            'Tell the team whether anyone is trapped or at immediate risk; contact emergency services for immediate human danger.',
        ],
        'faqs' => [
            [
                'question' => 'What concerns fit the emergency garage door service path?',
                'answer' => 'Use this path for urgent concerns such as a door that will not close, is stuck, is off track, involves a broken spring, or presents a safety risk.',
            ],
            [
                'question' => 'How do I confirm emergency garage door service availability?',
                'answer' => 'Use the regional contact shown on the page. Current service hours, arrival timing, and response timing must be confirmed when you contact the team.',
            ],
            [
                'question' => 'What should I do if someone is injured or trapped?',
                'answer' => 'Contact emergency services when a person is injured, trapped, or in immediate danger. Keep others away from the door and do not attempt to move it.',
            ],
            [
                'question' => 'Should I force the garage door open or closed?',
                'answer' => 'No. Keep people, pets, and vehicles clear and do not force the door. Note only what you can observe from a safe distance.',
            ],
            [
                'question' => 'Can this page promise how soon or how much work will be completed?',
                'answer' => 'No. Confirm current service hours, response timing, the service scope, and the next step when you contact the team.',
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
        'directAnswer' => 'Garage door services cover repair, installation, opener work, and urgent help for a door that is not working as expected. Use this page to choose the service path that matches your concern. A technician inspects the specific project and provides an exact price before work begins. Use the regional call or quote option shown.',
        'needs' => [
            'You are not sure which garage door service path fits your concern.',
            'You want to compare repair, installation, and opener paths before contacting the team.',
            'You want a technician to inspect the project before any work is chosen.',
        ],
        'safety' => 'If the door appears unsafe, stop using it and keep people, pets, and vehicles clear. Do not handle the spring system; springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Identify whether the concern involves the door, the opener, or an urgent safety issue.',
            'Choose the matching service path or use the regional contact shown on the page.',
            'A technician inspects the specific service concern.',
            'Review the findings and the exact price before work begins.',
        ],
        'options' => [
            [
                'option' => 'Repair path',
                'tradeoff' => 'Use this when an existing door or its hardware is not working as expected.',
            ],
            [
                'option' => 'Installation path',
                'tradeoff' => 'Use this when a replacement door or a new-door project is being planned.',
            ],
            [
                'option' => 'Opener path',
                'tradeoff' => 'Use this for opener troubleshooting, repair, and replacement questions.',
            ],
            [
                'option' => 'Emergency path',
                'tradeoff' => 'Use this for a door that is stuck, will not close, or presents a safety risk.',
            ],
        ],
        'prepare' => [
            'Note what the door or opener is doing without repeatedly operating it.',
            'Decide whether the concern feels urgent or can wait for a scheduled visit.',
            'Keep people, pets, and vehicles clear if the door appears unsafe.',
            'Have the regional contact shown on the page ready when you call.',
        ],
        'faqs' => [
            [
                'question' => 'What garage door services does Twins provide?',
                'answer' => 'The service paths cover garage door repair, installation, spring repair, opener repair, and emergency service. Each path has its own page with guidance, and a technician inspects the specific project before options are discussed.',
            ],
            [
                'question' => 'How do I know which service path to choose?',
                'answer' => 'Match the concern to the closest path: repair for a door or hardware issue, installation for a replacement or new door, opener repair for opener questions, and emergency service for urgent safety concerns. If you are unsure, use the regional contact and describe what you observed.',
            ],
            [
                'question' => 'Is Twins licensed and insured?',
                'answer' => 'Yes. Twins is a licensed and insured local garage door company. A technician handles inspection and any work that is authorized.',
            ],
            [
                'question' => 'Will I know the exact price before work begins?',
                'answer' => 'A technician inspects the project and provides an exact price before work begins. This page does not publish a one-size-fits-all price.',
            ],
            [
                'question' => 'What should I do if the door appears unsafe?',
                'answer' => 'Stop using it, keep people, pets, and vehicles clear, and do not force it. Do not handle the spring system. Use the emergency service path or the regional contact shown on the page.',
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
        'directAnswer' => 'Garage door cable repair addresses a cable that appears frayed, loose, or off its drum. Cables work with the spring system and are under dangerous tension, so they should be handled by trained professionals. Stop using the door, keep people clear, and use the regional call or quote option shown to request an assessment.',
        'needs' => [
            'A cable appears frayed, loose, snapped, or off its drum.',
            'The door hangs unevenly or moved suddenly and you suspect a cable.',
            'You want a trained professional to assess the cable and spring system.',
        ],
        'safety' => 'Garage door cables work under the same dangerous tension as the spring system and should be handled by trained professionals. Do not pull, reattach, or cut a cable, and do not operate a door with a suspected cable problem.',
        'process' => [
            'Stop operating the door and describe what you observed from a safe distance.',
            'A trained professional assesses the cable and related hardware.',
            'Review the findings and available next steps.',
            'Review the exact price before repair work begins.',
        ],
        'options' => [
            [
                'option' => 'Professional cable assessment',
                'tradeoff' => 'Keeps cable and spring handling with trained professionals and avoids unsafe adjustment.',
            ],
            [
                'option' => 'Broader repair discussion',
                'tradeoff' => 'Use this when the technician should explain whether another service path applies; no cause is assumed before inspection.',
            ],
            [
                'option' => 'Pause before authorizing work',
                'tradeoff' => 'Review the findings and exact price before choosing a next step.',
            ],
        ],
        'prepare' => [
            'Leave the cable and spring system untouched.',
            'Do not operate the door, including with the opener.',
            'Keep people, pets, and vehicles away from the door.',
            'Note what you observed from a safe distance.',
        ],
        'faqs' => [
            [
                'question' => 'Can I fix a garage door cable myself?',
                'answer' => 'No. Cables work under the same dangerous tension as the spring system and should be handled by trained professionals. Do not pull, reattach, or cut a cable.',
            ],
            [
                'question' => 'What are signs of a cable problem?',
                'answer' => 'A cable can look frayed or slack, sit off its drum, or leave the door hanging unevenly. These are observations to share, not a diagnosis; a trained professional must assess the specific door.',
            ],
            [
                'question' => 'Is it safe to keep using the door with a damaged cable?',
                'answer' => 'No. Stop operating the door, including with the opener, and keep people, pets, and vehicles clear until it is assessed.',
            ],
            [
                'question' => 'Are cables and springs repaired together?',
                'answer' => 'That depends on the inspection. Cables and springs work as one counterbalance system, so the assessment covers related hardware, and no combined outcome is assumed in advance.',
            ],
            [
                'question' => 'Will I know the exact price before cable repair begins?',
                'answer' => 'A technician provides an exact price before repair work begins. This page does not publish a cable price or numeric range.',
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
        'directAnswer' => 'Garage door openers can be selected and installed through Twins, which installs LiftMaster openers. Selection depends on the door, the garage layout, and the features you want, so a technician confirms fit during an on-site visit. Compare drive types and features first, then use the regional call or quote option shown.',
        'needs' => [
            'You are choosing a new garage door opener.',
            'You want an opener installed by a technician.',
            'You want to understand drive types and features before a visit.',
            'An existing opener is being replaced.',
        ],
        'safety' => 'Keep people, pets, and vehicles clear during opener installation and testing. Do not adjust the spring system; springs are under dangerous tension and should be handled by trained professionals. If a door must be moved by hand, disconnect the opener first and follow technician guidance.',
        'process' => [
            'Note the door, garage layout, and the features that matter to you.',
            'Discuss opener choices with the team; Twins installs LiftMaster openers.',
            'A technician confirms fit for the specific door and garage during a visit.',
            'Review the exact price before installation begins.',
        ],
        'options' => [
            [
                'option' => 'New opener selection',
                'tradeoff' => 'Use this when choosing an opener for a door that does not have one or has an opener being retired.',
            ],
            [
                'option' => 'Opener replacement',
                'tradeoff' => 'Use this when an existing opener is being replaced; fit is confirmed on site.',
            ],
            [
                'option' => 'Feature comparison',
                'tradeoff' => 'Use this to compare drive types, remote and keypad access, and connected features before a decision; no model is assumed in advance.',
            ],
            [
                'option' => 'Repair-first discussion',
                'tradeoff' => 'Use the opener repair path if the question is about an existing opener that is not working.',
            ],
        ],
        'prepare' => [
            'Note the opener features that matter to you, such as remotes, keypads, or app control.',
            'Record the make or model of any existing opener if it is visible from a safe position.',
            'Leave ceiling clearance and mounting checks to the technician.',
            'Clear the area near the opener and door only when it is safe to do so.',
        ],
        'faqs' => [
            [
                'question' => 'What brands of garage door openers does Twins install?',
                'answer' => 'Twins installs LiftMaster openers. A technician confirms which models fit the specific door and garage during an on-site visit.',
            ],
            [
                'question' => 'How do I choose between opener drive types?',
                'answer' => 'Drive types differ in design and mounting, and the right choice depends on the door and garage layout. Compare the features that matter to you, then confirm fit with the technician; this page does not rank drive types.',
            ],
            [
                'question' => 'Can my existing opener be repaired instead of replaced?',
                'answer' => 'That is decided after inspection. Use the opener repair path for troubleshooting and repair questions; no replacement is assumed in advance.',
            ],
            [
                'question' => 'What features should I consider in a new opener?',
                'answer' => 'Common considerations include remote and keypad access, lighting, battery backup, and connected app control. Which features are available depends on the selected model, and the technician can confirm options during the visit.',
            ],
            [
                'question' => 'Will I know the exact price before opener installation begins?',
                'answer' => 'A technician confirms the project on site and provides an exact price before work begins. This page does not publish a one-size-fits-all price.',
            ],
            [
                'question' => 'Is it safe to install a garage door opener myself?',
                'answer' => 'Opener installation involves overhead mounting, electrical connections, and force and travel settings, and it interacts with the spring system. Leave installation and adjustment to a technician, and never adjust the springs; they are under dangerous tension and should be handled by trained professionals.',
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
        'directAnswer' => 'Weatherstripping repair replaces worn seals around a garage door, including the bottom seal and the side and top trim seals. Worn seals can let in drafts, water, and pests. A technician inspects the door, confirms which seals are involved, and provides an exact price before work begins. Use the regional call or quote option shown.',
        'needs' => [
            'Drafts, water, leaves, or pests are getting in around the garage door.',
            'The bottom seal or the side and top seals look worn, cracked, or flattened.',
            'You want the seals inspected along with the rest of the door.',
        ],
        'safety' => 'Keep people, pets, and vehicles clear while the door is inspected or moved. Do not remove hardware near the bottom of the door yourself, and do not handle the spring system; springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Note where drafts, water, or light are getting in around the door.',
            'A technician inspects the seals and the surrounding door hardware.',
            'Review the findings and which seals are involved.',
            'Review the exact price before work begins.',
        ],
        'options' => [
            [
                'option' => 'Seal-only discussion',
                'tradeoff' => 'Use this when the concern is limited to worn or damaged seals; inspection confirms the scope.',
            ],
            [
                'option' => 'Broader door inspection',
                'tradeoff' => 'Use this when gaps or uneven sealing could involve the door itself; no cause is assumed before inspection.',
            ],
            [
                'option' => 'Pause before authorizing work',
                'tradeoff' => 'Review the findings and exact price before choosing a next step.',
            ],
        ],
        'prepare' => [
            'Note where you see gaps, daylight, or water entry around the closed door.',
            'Clear stored items away from the door opening only when it is safe to do so.',
            'Take a photo of the worn seal from a safe distance if it can be done without entering the door path.',
            'Avoid operating the door repeatedly before the visit.',
        ],
        'faqs' => [
            [
                'question' => 'What does garage door weatherstripping include?',
                'answer' => 'Weatherstripping generally includes the bottom seal on the door and the seals or trim along the sides and top of the opening. A technician confirms which parts apply to the specific door during inspection.',
            ],
            [
                'question' => 'How do I know my weatherstripping needs replacement?',
                'answer' => 'Common observations include visible cracking or flattening, daylight around the closed door, drafts, water entry, or pests. Share what you observed; the inspection confirms what is involved.',
            ],
            [
                'question' => 'Can worn seals affect the rest of the door?',
                'answer' => 'A gap can also reflect how the door sits in the opening, which is why the inspection covers the door and hardware, not just the seal. No cause is assumed before inspection.',
            ],
            [
                'question' => 'Can I replace garage door weatherstripping myself?',
                'answer' => 'The bottom seal and retainers sit close to moving hardware, and an uneven gap can involve the door or its balance. Leave removal and replacement to a technician, and never handle the spring system; springs are under dangerous tension and should be handled by trained professionals.',
            ],
            [
                'question' => 'Will I know the exact price before seal work begins?',
                'answer' => 'A technician inspects the door and provides an exact price before work begins. This page does not publish a one-size-fits-all price.',
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
        'directAnswer' => 'A garage door tune-up is a scheduled visit in which a technician inspects the door, tightens accessible hardware, lubricates moving parts, and checks how the door and opener operate. It is a planned way to have the whole system reviewed. Use the regional call or quote option shown to schedule a tune-up visit.',
        'needs' => [
            'The door works but sounds or moves differently than it used to.',
            'You want the door, hardware, and opener reviewed on a planned visit.',
            'It has been a long time since the door was professionally serviced.',
        ],
        'safety' => 'Do not lubricate, tighten, or adjust the spring system yourself; springs are under dangerous tension and should be handled by trained professionals. If the door appears unsafe before the visit, stop using it and keep people, pets, and vehicles clear.',
        'process' => [
            'Schedule a tune-up visit using the regional contact shown on the page.',
            'A technician inspects the door, hardware, and opener operation.',
            'Review anything the inspection surfaces along with the available next steps.',
            'Review the exact price before any additional work begins.',
        ],
        'options' => [
            [
                'option' => 'Tune-up visit',
                'tradeoff' => 'Use this for a planned inspection and service visit when the door is operating.',
            ],
            [
                'option' => 'Repair path instead',
                'tradeoff' => 'Use the repair path when something is already broken or the door will not move correctly.',
            ],
            [
                'option' => 'Recurring maintenance discussion',
                'tradeoff' => 'Ask about a maintenance plan if you want tune-up visits on a recurring schedule.',
            ],
        ],
        'prepare' => [
            'Note any sounds, hesitation, or changes in how the door moves.',
            'Clear the area around the door and tracks only when it is safe to do so.',
            'List any questions about the door or opener for the technician.',
        ],
        'faqs' => [
            [
                'question' => 'What happens during a garage door tune-up?',
                'answer' => 'A technician inspects the door and hardware, tightens accessible components, lubricates moving parts, and checks how the door and opener operate. Anything the inspection surfaces is reviewed with you before any additional work is discussed.',
            ],
            [
                'question' => 'How is a tune-up different from a repair visit?',
                'answer' => 'A tune-up is a planned service visit for a door that is operating. A repair visit addresses something that is already broken or unsafe. If the door will not move correctly, use the repair or emergency path instead.',
            ],
            [
                'question' => 'Can a tune-up include the opener?',
                'answer' => 'The visit includes checking how the door and opener operate together. Opener repair questions have their own service path if the inspection points there.',
            ],
            [
                'question' => 'Should I lubricate or adjust the door myself before the visit?',
                'answer' => 'No adjustment is needed before the visit. Never lubricate, tighten, or adjust the spring system yourself; springs are under dangerous tension and should be handled by trained professionals.',
            ],
            [
                'question' => 'Will extra work be done without my approval?',
                'answer' => 'No. Anything the inspection surfaces is reviewed with you, and you see the exact price before any additional work begins.',
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
        'directAnswer' => 'A maintenance plan places garage door tune-up visits on a recurring schedule so the door, hardware, and opener are reviewed by a technician at planned intervals. Plan details, scheduling, and pricing are confirmed with the team before enrollment. Use the regional call or quote option shown to ask about a plan for your door.',
        'needs' => [
            'You want tune-up visits handled on a recurring schedule instead of one at a time.',
            'You want a technician reviewing the door and opener at planned intervals.',
            'You manage a home or building where the garage door sees regular use.',
        ],
        'safety' => 'Between visits, stop using the door if it appears unsafe and keep people, pets, and vehicles clear. Do not handle the spring system; springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Ask about a maintenance plan using the regional contact shown on the page.',
            'Confirm plan details, scheduling, and pricing with the team before enrollment.',
            'A technician performs the scheduled visits and reviews findings with you.',
            'Review the exact price before any additional repair work begins.',
        ],
        'options' => [
            [
                'option' => 'Recurring plan discussion',
                'tradeoff' => 'Use this to have visit scheduling handled for you; details are confirmed before enrollment.',
            ],
            [
                'option' => 'Single tune-up instead',
                'tradeoff' => 'Use the tune-up path if you want one planned visit without a recurring plan.',
            ],
            [
                'option' => 'Property portfolio discussion',
                'tradeoff' => 'Use the property management path when multiple doors or buildings are involved.',
            ],
        ],
        'prepare' => [
            'Note how many doors and openers you want covered.',
            'Note any current concerns to raise during the first visit.',
            'Have your preferred contact method ready for scheduling.',
        ],
        'faqs' => [
            [
                'question' => 'What does a garage door maintenance plan include?',
                'answer' => 'A plan puts technician visits on a recurring schedule so the door, hardware, and opener are reviewed at planned intervals. The specific inclusions are confirmed with the team before enrollment; this page does not publish plan terms.',
            ],
            [
                'question' => 'How much does a maintenance plan cost?',
                'answer' => 'Plan pricing is confirmed with the team before enrollment. This page does not publish plan pricing or a numeric range.',
            ],
            [
                'question' => 'How are plan visits scheduled?',
                'answer' => 'Visit scheduling is arranged with the team when the plan is set up, and the team coordinates each visit with you. This page does not promise a visit frequency or appointment window.',
            ],
            [
                'question' => 'Does a plan cover repairs?',
                'answer' => 'Repair work found during a visit is reviewed with you separately, and you see the exact price before any repair begins. Confirm with the team what the plan itself includes.',
            ],
            [
                'question' => 'Can a plan cover more than one door or property?',
                'answer' => 'Ask the team when setting up the plan. Multiple doors or buildings can be discussed, and the property management path covers portfolio needs.',
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
        'directAnswer' => 'Property management services cover garage door repair, installation, and maintenance for buildings and portfolios managed by a property manager or owner. Twins is a licensed and insured local garage door company, and the team can coordinate service across multiple doors or locations. Use the regional call or quote option shown to start the conversation.',
        'needs' => [
            'You manage rental homes, condos, or commercial buildings with garage doors.',
            'You need one contact for repair, installation, and maintenance across properties.',
            'You want documented findings and pricing before authorizing work at a property.',
        ],
        'safety' => 'If a door at a property appears unsafe, take it out of use and keep residents, tenants, and vehicles clear. Do not allow anyone to handle the spring system; springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Describe the properties, doors, and the kind of coverage you need.',
            'Confirm scheduling and billing arrangements with the team.',
            'A technician inspects each service concern at the property.',
            'Review the findings and the exact price before work is authorized.',
        ],
        'options' => [
            [
                'option' => 'Per-request service',
                'tradeoff' => 'Use this to request service property by property as concerns come up.',
            ],
            [
                'option' => 'Recurring maintenance discussion',
                'tradeoff' => 'Ask about scheduled visits across the portfolio; details are confirmed with the team.',
            ],
            [
                'option' => 'Replacement planning',
                'tradeoff' => 'Use the installation path when a door at a property is being replaced.',
            ],
        ],
        'prepare' => [
            'List the properties and the number of garage doors involved.',
            'Note current concerns and which doors they affect.',
            'Identify who can authorize work and receive findings for each property.',
            'Share access instructions for occupied or secured buildings.',
        ],
        'faqs' => [
            [
                'question' => 'Does Twins work with property managers?',
                'answer' => 'Yes. The team works with property managers and owners on repair, installation, and maintenance across residential and commercial buildings, and Twins is licensed and insured.',
            ],
            [
                'question' => 'Can service be coordinated across multiple properties?',
                'answer' => 'Yes. Describe the portfolio and the team can coordinate scheduling across doors and locations. Specific arrangements are confirmed with the team.',
            ],
            [
                'question' => 'How is work authorized and billed for a property?',
                'answer' => 'Authorization and billing arrangements are set up with the team when service begins. A technician provides findings and an exact price before work is authorized; this page does not publish terms.',
            ],
            [
                'question' => 'Can tenants or residents request service directly?',
                'answer' => 'That depends on the arrangement you set up with the team. Confirm who can request and authorize service for each property when the account is established.',
            ],
            [
                'question' => 'What if a door at a property is unsafe right now?',
                'answer' => 'Take the door out of use, keep residents and vehicles clear, and use the emergency service path or the regional contact shown on the page. Confirm current availability when you contact the team.',
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
        'directAnswer' => 'The TwinShield Protection Plan is a service plan for the garage door system, combining planned technician attention with defined plan benefits. What the plan includes, what it costs, and how it is scheduled are confirmed with the team before enrollment. Use the regional call or quote option shown to ask about TwinShield for your door.',
        'needs' => [
            'You want an ongoing service plan for the garage door system rather than one-time visits.',
            'You want plan details explained before deciding to enroll.',
            'You want to compare a protection plan with scheduling individual visits.',
        ],
        'safety' => 'A plan does not change safety basics: if the door appears unsafe, stop using it and keep people, pets, and vehicles clear. Do not handle the spring system; springs are under dangerous tension and should be handled by trained professionals.',
        'process' => [
            'Ask about the TwinShield Protection Plan using the regional contact shown.',
            'Review what the plan includes, what it costs, and how it is scheduled.',
            'Enroll only after the details are confirmed with the team.',
            'Use the plan contact path for service once enrolled.',
        ],
        'options' => [
            [
                'option' => 'TwinShield enrollment discussion',
                'tradeoff' => 'Use this to review plan inclusions, pricing, and scheduling before deciding.',
            ],
            [
                'option' => 'Maintenance plan instead',
                'tradeoff' => 'Use the maintenance plan discussion when recurring tune-up visits are the main goal.',
            ],
            [
                'option' => 'One-time service instead',
                'tradeoff' => 'Use the standard service paths if you prefer to request visits individually.',
            ],
        ],
        'prepare' => [
            'Note the age and condition of the door and opener as you know them.',
            'List the questions you want answered before enrolling.',
            'Decide who in the household or business will manage the plan.',
        ],
        'faqs' => [
            [
                'question' => 'What is the TwinShield Protection Plan?',
                'answer' => 'TwinShield is a service plan for the garage door system offered by Twins. What it includes, what it costs, and how visits are scheduled are explained by the team and confirmed before enrollment; this page does not publish plan terms.',
            ],
            [
                'question' => 'How much does the TwinShield plan cost?',
                'answer' => 'Plan pricing is confirmed with the team before enrollment. This page does not publish plan pricing or a numeric range.',
            ],
            [
                'question' => 'What does the plan cover?',
                'answer' => 'Plan inclusions are reviewed with you before enrollment, and nothing on this page adds to or replaces those confirmed details. Ask the team to walk through the current plan.',
            ],
            [
                'question' => 'How is TwinShield different from a maintenance plan?',
                'answer' => 'Ask the team to compare the two for your situation. The maintenance plan discussion centers on recurring tune-up visits, while TwinShield is presented as a broader service plan; the confirmed details govern in both cases.',
            ],
            [
                'question' => 'Does a plan change what I do in an unsafe situation?',
                'answer' => 'No. Stop using an unsafe door, keep people, pets, and vehicles clear, and do not handle the spring system. Use the emergency service path or the regional contact shown on the page.',
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
