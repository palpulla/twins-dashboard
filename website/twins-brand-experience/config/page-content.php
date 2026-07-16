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
];
