import json

BOOK="https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true"
ICONL="https://twinsgaragedoors.com/wp-content/uploads/2026/03/ICONLeft-1.png"
ICONR="https://twinsgaragedoors.com/wp-content/uploads/2026/03/ICONright.png"

def html(h): return {"elType":"widget","widgetType":"html","settings":{"html":h}}
def sec(*widgets, pad=None):
    s={"stretch_section":"section-stretched","layout":"full_width","gap":"no"}
    if pad: s["padding"]={"unit":"px","top":str(pad[0]),"bottom":str(pad[1]),"left":"0","right":"0","isLinked":False}
    return {"elType":"section","settings":s,"elements":[{"elType":"column","settings":{"_column_size":100,"_inline_size":None},"elements":list(widgets)}]}

def build(city, tel_disp, tel_href, nap, url):
    faq=[
      ("How much does a garage door cost in "+city+"?",
       "Most garage door repairs in "+city+" run between $400 and $1,050, and a new garage door installed runs between $3,000 and $4,100, based on our completed jobs over the last 12 months. A new opener installed runs $900 to $1,450. Call "+tel_disp+" for an exact quote."),
      ("How much does garage door spring replacement cost?",
       "Repairs that include spring replacement typically run $780 to $1,660, depending on whether your door needs one or two springs and any related parts like cables or rollers. Call "+tel_disp+" and we can give you an exact price after a quick look."),
      ("How much does a new garage door opener cost installed?",
       "Most opener installations run between $900 and $1,450, based on our completed jobs over the last 12 months, including the opener and labor."),
      ("Should I repair or replace my garage door?",
       "If your door is newer and the problem is a broken spring, cable, or opener, a repair is usually the better value. If the door is old, dented, or failing in several places, a new door can cost less over time and comes with a fresh warranty. We give you both options with honest pricing so you can decide."),
      ("Why do garage door prices vary so much?",
       "Price depends on the door material (steel, wood-look, or full-view glass), the size (single or double), insulation, the type of springs, and the opener drive. That is why we give an exact, upfront quote before any work starts."),
      ("Do you offer financing on a new garage door?",
       "Yes. Twins Garage Doors offers financing through GoodLeap, so you can spread the cost of a new door over monthly payments."),
      ("Is there a fee for a service call?",
       "Our service call and diagnostic fee is $49. A technician comes out, inspects your door, and gives you an exact, upfront quote before any work begins."),
      ("Do you give free quotes on new garage doors?",
       "Yes. Quotes on new garage door installations are free. You can also design your exact door online with our door builder to get a quote."),
    ]
    faq_details="".join('<details><summary>'+q+'</summary><p>'+a+'</p></details>' for q,a in faq)
    faq_style=('<style>.twx2-faqwrap{background:var(--tw-soft);padding:38px 48px;font-family:Montserrat,\'Avenir Next\',\'Helvetica Neue\',Arial,sans-serif}'
      '.twx2-faqwrap .twx2-fhead{text-align:center;margin-bottom:20px}.twx2-faqwrap h3{color:var(--tw-navy);font-size:22px;font-weight:800;margin:6px 0 0}'
      '.twx2-faq{max-width:860px;margin:0 auto}.twx2-faq details{background:#fff;border:3px solid var(--tw-navy);border-radius:12px;margin-bottom:12px;padding:0 22px;box-shadow:4px 4px 0 rgba(1,13,56,.85)}'
      '.twx2-faq summary{cursor:pointer;font-weight:700;font-size:15.5px;color:var(--tw-navy);padding:16px 0;list-style:none;position:relative;padding-right:30px}'
      '.twx2-faq summary::-webkit-details-marker{display:none}.twx2-faq summary:after{content:"+";position:absolute;right:2px;top:50%;transform:translateY(-50%);font-size:22px;color:var(--tw-yellow);font-weight:800}'
      '.twx2-faq details[open] summary:after{content:"\\2212"}.twx2-faq details p{margin:0 0 16px;color:#42556e;font-size:14.5px;line-height:1.6}</style>')

    # JSON-LD
    graph={"@context":"https://schema.org","@graph":[
      {"@type":"LocalBusiness","name":"Twins Garage Doors","telephone":tel_href.replace("tel:",""),
       "address":{"@type":"PostalAddress","streetAddress":nap["st"],"addressLocality":nap["loc"],"addressRegion":"WI","postalCode":nap["zip"]},
       "areaServed":nap["area"],"url":url},
      {"@type":"FAQPage","mainEntity":[{"@type":"Question","name":q,"acceptedAnswer":{"@type":"Answer","text":a}} for q,a in faq]}]}
    ld='<script type="application/ld+json">'+json.dumps(graph,ensure_ascii=False)+'</script>'

    hero=('<div class="twx2-hero"><div class="twx2-stamp">2026 pricing</div><div class="twx2-eyebrow">[ Real local pricing ]</div>'
      '<h1>How Much Does a Garage Door Cost in '+city+', WI?</h1>'
      '<p class="twx2-sub">Real pricing from our completed '+city+' jobs, from quick repairs to full new-door installs.</p>'
      '<div class="twx2-cta"><a class="twx2-btn twx2-btn--gold" href="'+tel_href+'">Call '+tel_disp+'</a>'
      '<a class="twx2-btn twx2-btn--ghost" href="https://twinsgaragedoors.com/wi/door-builder/">Design Your Door</a></div>'
      '<div class="twx2-trustline"><b>&#9733;&#9733;&#9733;&#9733;&#9733;</b>&nbsp; 5.0 on Google &#183; Licensed and insured &#183; Local crew</div>'
      '<div class="twx2-pair"><img class="twx2-back" src="'+ICONL+'" alt="Twins Garage Doors mascot" data-no-lazy="1"><img class="twx2-front" src="'+ICONR+'" alt="Twins Garage Doors mascot" data-no-lazy="1"></div></div>')

    answer=('<div style="background:#F2F5F7;padding:42px 20px"><div style="max-width:880px;margin:0 auto;background:#fff;border:3px solid #022751;border-radius:14px;box-shadow:6px 6px 0 rgba(1,13,56,.85);padding:26px 30px;position:relative">'
      '<div style="position:absolute;top:-14px;left:24px;background:#FBBD04;color:#022751;font-weight:800;font-size:12px;letter-spacing:.06em;text-transform:uppercase;padding:4px 12px;border-radius:999px;border:2px solid #022751">The short answer</div>'
      '<p style="color:#022751;font-size:16.5px;line-height:1.7;margin:6px 0 0;font-weight:500">Most garage door repairs in '+city+' run between $400 and $1,050, and a new garage door installed runs between $3,000 and $4,100, based on our completed jobs over the last 12 months. A new opener installed runs $900 to $1,450. Call '+tel_disp+' for an exact quote.</p>'
      '<p style="color:#8a97a8;font-size:12.5px;margin:14px 0 0">Last updated: July 10, 2026</p></div></div>')

    ribbon=('<div class="twx2-ribbon"><div class="twx2-rib-item"><span class="twx2-rib-ico"><svg viewBox="0 0 24 24"><path d="M4 7h13l3 5-3 5H4z"/><path d="M9 10v4M13 10v4"/></svg></span><span><b>Upfront, flat pricing</b><small>Exact quote before work starts</small></span></div>'
      '<div class="twx2-rib-item"><span class="twx2-rib-ico"><svg viewBox="0 0 24 24"><path d="M3 12l5 5L21 4"/></svg></span><span><b>Real completed-job data</b><small>Not made-up estimates</small></span></div>'
      '<div class="twx2-rib-item"><span class="twx2-rib-ico"><svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2M9 2h6"/></svg></span><span><b>Same-day help</b><small>Call before noon, seen today</small></span></div></div>')

    price=('<style>.twx2-pricewrap{background:#fff;padding:16px 48px 8px}.twx2-pricebox{max-width:820px;margin:0 auto}'
      '.twx2-price{width:100%;border-collapse:separate;border-spacing:0;border:3px solid #022751;border-radius:12px;overflow:hidden;box-shadow:6px 6px 0 rgba(1,13,56,.85);font-family:Montserrat,Arial,sans-serif}'
      '.twx2-price th{background:#022751;color:#fff;text-align:left;padding:14px 18px;font-size:15px}'
      '.twx2-price td{padding:13px 18px;font-size:15px;color:#22344d;border-top:1px solid #e6ebf1}'
      '.twx2-price tr td:last-child{font-weight:800;color:#022751;white-space:nowrap}'
      '.twx2-price tr:nth-child(even) td{background:#F7F9FB}.twx2-pricenote{max-width:820px;margin:12px auto 0;color:#8a97a8;font-size:12.5px}</style>'
      '<div class="twx2-pricewrap"><div class="twx2-pricebox"><div class="twx2-eyebrow twx2-eyebrow--section" style="margin-bottom:8px">[ Price ranges ]</div>'
      '<table class="twx2-price"><tr><th>Service</th><th>Typical range</th></tr>'
      '<tr><td>Service call &amp; diagnostic</td><td>$49</td></tr>'
      '<tr><td>Garage door repair</td><td>$400 to $1,050</td></tr>'
      '<tr><td>New opener installed</td><td>$900 to $1,450</td></tr>'
      '<tr><td>New garage door installed</td><td>$3,000 to $4,100</td></tr>'
      '<tr><td>New door + opener</td><td>$4,400 to $7,250</td></tr></table>'
      '<p class="twx2-pricenote">Based on our completed jobs over the last 12 months. Your exact price depends on your door, size, and parts.</p></div></div>')

    affects=('<div class="twx2-steps"><div class="twx2-eyebrow twx2-eyebrow--section">[ What affects the price ]</div>'
      '<h2 class="twx2-h3" style="font-size:22px;margin:6px 0 14px">Why two quotes can look different</h2>'
      '<p style="color:#42556e;font-size:15.5px;line-height:1.7;margin:0;max-width:860px">A few things move the number: the door material (insulated steel, wood-look composite, or full-view glass), the size (single or double), how much insulation you want, whether you need one spring or two, and the type of opener drive. We walk you through the options and give one flat quote, so there are no surprises.</p></div>')

    financing=('<div class="twx2-steps"><div class="twx2-eyebrow twx2-eyebrow--section">[ Financing ]</div>'
      '<h2 class="twx2-h3" style="font-size:22px;margin:6px 0 14px">Can I finance a new garage door?</h2>'
      '<p style="color:#42556e;font-size:15.5px;line-height:1.6;margin:0;max-width:860px">Yes. Twins Garage Doors offers financing through GoodLeap, plus a $0 service call on new-door quotes. Ask about monthly payment options when you get your quote.</p></div>')

    navy=('<style>.twx2-steps--navy{background:#010D38!important}.twx2-steps--navy h3{color:#fff!important}.twx2-steps--navy .twx2-step b{color:#fff!important}.twx2-steps--navy .twx2-step p{color:#b8c4d6!important}.twx2-steps--navy .twx2-step{background:rgba(255,255,255,.04);border:2px solid rgba(251,189,4,.45);border-radius:12px;padding:18px}.twx2-steps--navy .twx2-num{color:#FBBD04!important}</style>'
      '<div class="twx2-steps twx2-steps--navy"><div class="twx2-eyebrow twx2-eyebrow--section" style="color:#FBBD04">[ How to get an exact quote ]</div>'
      '<h3>Your exact price in three steps</h3><div class="twx2-row3">'
      '<div class="twx2-step"><span class="twx2-num">01</span><b>Call or book online</b><p>Tell us what is going on with your door.</p></div>'
      '<div class="twx2-step"><span class="twx2-num">02</span><b>On-site diagnosis</b><p>A technician inspects and prices it, $49 diagnostic.</p></div>'
      '<div class="twx2-step"><span class="twx2-num">03</span><b>Flat quote, then the work</b><p>You approve the price before we start.</p></div></div></div>')

    faqwrap=(faq_style+'<div class="twx2-faqwrap"><div class="twx2-fhead"><div class="twx2-eyebrow twx2-eyebrow--section">[ Cost FAQ ]</div><h3>Garage Door Cost Questions</h3></div><div class="twx2-faq">'+faq_details+'</div></div>')

    zip=('<style>.twx2-zip{background:#022751;padding:34px 20px;text-align:center;font-family:Montserrat,Arial,sans-serif}'
      '.twx2-zip h3{color:#fff;font-size:20px;font-weight:800;margin:0 0 4px}.twx2-zip p.sub{color:#b8c4d6;font-size:14px;margin:0 0 16px}'
      '.twx2-zip .row{display:flex;gap:10px;max-width:420px;margin:0 auto;justify-content:center}'
      '.twx2-zip input{flex:1;max-width:220px;padding:13px 14px;border-radius:8px;border:2px solid #FBBD04;font-size:16px}'
      '.twx2-zip button{background:#FBBD04;color:#022751;font-weight:800;border:0;border-radius:8px;padding:13px 22px;font-size:15px;cursor:pointer}'
      '.twx2-zip .msg{color:#fff;font-size:14px;margin:14px 0 0;min-height:20px}.twx2-zip .msg a{color:#FBBD04;font-weight:700}</style>'
      '<div class="twx2-zip"><h3>Not sure if we cover your area?</h3><p class="sub">Enter your ZIP code and we will point you to the right page.</p>'
      '<div class="row"><input id="twxzip" type="text" inputmode="numeric" maxlength="5" placeholder="ZIP code" aria-label="ZIP code"><button type="button" onclick="twxZip()">Check</button></div>'
      '<p class="msg" id="twxzipmsg"></p></div>'
      '<script>function twxZip(){var z=(document.getElementById("twxzip").value||"").trim().slice(0,5);var m=document.getElementById("twxzipmsg");'
      'if(!/^[0-9]{5}$/.test(z)){m.textContent="Please enter a 5-digit ZIP code.";return;}var p=z.slice(0,3);'
      'if(p==="537"){location.href="/wi/garage-door-installation/";}'
      'else if(p==="531"||p==="532"){location.href="/wi/garage-door-repair-in-milwaukee-wi/";}'
      'else{m.innerHTML="We may still cover your area. <a href=\\"/wi/contact-us/\\">Get in touch</a> and we will confirm.";}}<\/script>')

    closer=('<style>.twx2-closer img.twx2-pair--band{height:104px!important;width:auto!important}.twx2-closer{margin-bottom:0}@media(max-width:700px){.twx2-closer img.twx2-pair--band{height:56px!important}}</style>'
      '<div class="twx2-closer"><img class="twx2-pair--band twx2-l" src="'+ICONL+'" alt="" data-no-lazy="1"><span>Ready for an exact quote?</span>'
      '<a class="twx2-btn twx2-btn--navy" href="https://twinsgaragedoors.com/wi/door-builder/">Design Your Door</a>'
      '<a class="twx2-btn twx2-btn--gold" href="'+tel_href+'">Call '+tel_disp+'</a>'
      '<img class="twx2-pair--band twx2-r" src="'+ICONR+'" alt="" data-no-lazy="1"></div>')

    napline=('<div style="background:var(--tw-soft);padding:0 16px 18px;margin-bottom:110px;text-align:center;font-family:Montserrat,\'Avenir Next\',\'Helvetica Neue\',Arial,sans-serif;font-size:12.5px;color:#5b6b7e">'
      'Twins Garage Doors, '+nap["st"]+', '+nap["loc"]+', WI '+nap["zip"]+' &#183; '+tel_disp+' &#183; <a href="'+url.replace("garage-door-cost-in-","").replace("-wi/","-wi/") if False else url+'" style="color:#5b6b7e">'+city+' garage door services</a></div>')
    # simpler nap (avoid broken link logic)
    hub = "/wi/garage-door-repair-in-milwaukee-wi/" if city=="Milwaukee" else "/wi/garage-door-installation/"
    napline=('<div style="background:var(--tw-soft);padding:0 16px 18px;margin-bottom:110px;text-align:center;font-family:Montserrat,\'Avenir Next\',\'Helvetica Neue\',Arial,sans-serif;font-size:12.5px;color:#5b6b7e">'
      'Twins Garage Doors, '+nap["st"]+', '+nap["loc"]+', WI '+nap["zip"]+' &#183; '+tel_disp+' &#183; <a href="'+hub+'" style="color:#3a4a63;font-weight:700">See '+city+' garage door services</a></div>')

    MODELS=[
      sec(html(hero), html(ld)),
      sec(html(answer)),
      sec(html(ribbon)),
      sec(html(price), pad=(8,40)),
      sec(html(affects)),
      sec(html(financing)),
      sec(html(navy)),
      sec(html(faqwrap)),
      sec(html(zip)),
      sec(html(closer)),
      sec(html(napline)),
    ]
    return MODELS

pages={
 "madison":{"city":"Madison","tel_disp":"(608) 420-2377","tel_href":"tel:+16084202377",
   "nap":{"st":"2921 Landmark Pl, Ste 206","loc":"Madison","zip":"53713","area":["Madison WI"]},
   "url":"https://twinsgaragedoors.com/wi/garage-door-cost-in-madison-wi/"},
 "milwaukee":{"city":"Milwaukee","tel_disp":"(414) 800-9271","tel_href":"tel:+14148009271",
   "nap":{"st":"11220 W Burleigh St Ste 100","loc":"Wauwatosa","zip":"53222","area":["Milwaukee WI","Wauwatosa WI"]},
   "url":"https://twinsgaragedoors.com/wi/garage-door-cost-in-milwaukee-wi/"},
}
for key,p in pages.items():
    MODELS=build(p["city"],p["tel_disp"],p["tel_href"],p["nap"],p["url"])
    blob=json.dumps(MODELS,ensure_ascii=False)
    # guards
    assert "—" not in blob and "–" not in blob, "dash!"
    js="window.__twxSaved='not-started';\ntry{\nvar MODELS="+blob+";\n$e.run('document/elements/empty',{force:true});\nvar root=elementor.documents.getCurrent().container;\nMODELS.forEach(function(m,i){$e.run('document/elements/create',{container:root,model:m,options:{at:i}});});\nwindow.__twxSaved='pending';\n$e.run('document/save/default').then(function(){window.__twxSaved='ok';}).catch(function(err){window.__twxSaved='ERR:'+err.message;});\n'built:'+root.children.length;\n}catch(e){'EX:'+e.message}"
    open(f"/private/tmp/claude-501/-Users-daniel-twins-dashboard/9437a4d8-b6c0-4c86-b840-17b467a14889/scratchpad/cost-{key}.js","w").write(js)
    print(f"cost-{key}.js: {len(MODELS)} sections, {len(js)} bytes, valid JSON")
