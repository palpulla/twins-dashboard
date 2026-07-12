<style>
#twins-callbar{display:none;}
@media(max-width:768px){
  #twins-callbar{display:flex;position:fixed;bottom:0;left:0;right:0;z-index:99999;gap:8px;padding:10px 12px calc(10px + env(safe-area-inset-bottom,0px));background:#0b2249;box-shadow:0 -2px 10px rgba(0,0,0,.25);}
  #twins-callbar a{flex:1;text-align:center;font-weight:700;font-size:16px;padding:12px 8px;border-radius:8px;text-decoration:none;}
  #twins-callbar .twins-call{background:#ffc524;color:#0b2249;}
  #twins-callbar .twins-book{background:#fff;color:#0b2249;border:2px solid #ffc524;}
  body{padding-bottom:70px !important;}
}
</style>
<div id="twins-callbar">
  <a class="twins-call" href="tel:+16084202377">&#128222; Call Now</a>
  <a class="twins-book" href="https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true">Book Online</a>
</div>
<!-- runtime phone unifier: Milwaukee pages -> (414) 800-9271, all other /wi -> (608) 420-2377 tracking line.
     Rewrites both anchors and visible text nodes. Replaces the old 833->888 swap. Snippet 6657 (GHL number pool) deactivated 2026-07-10. -->
<script>
(function(){var v=document.querySelector('meta[name="viewport"]');if(v){v.setAttribute("content","width=device-width, initial-scale=1");}})();
</script>
<script>
(function(){
  function run(){
    var mke=/milwaukee/i.test(location.pathname);
    var disp=mke?"(414) 800-9271":"(608) 420-2377";
    var tel=mke?"tel:+14148009271":"tel:+16084202377";
    var cb=document.querySelector("#twins-callbar .twins-call");if(cb){cb.setAttribute("href",tel);}
    document.querySelectorAll('a[href*="8338332010"],a[href*="833-2010"],a[href*="6088888785"],a[href*="888-8785"],a[href*="16088888785"]').forEach(function(a){a.setAttribute("href",tel);});
    var test=/(\(?833\)?[ .-]?833[ .-]?2010)|(\(?608\)?[ .-]?888[ .-]?8785)/;
    var w=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT,{acceptNode:function(n){var p=n.parentNode?n.parentNode.nodeName:"";if(p==="SCRIPT"||p==="STYLE")return NodeFilter.FILTER_REJECT;return test.test(n.nodeValue||"")?NodeFilter.FILTER_ACCEPT:NodeFilter.FILTER_REJECT;}});
    var nodes=[],x;while(x=w.nextNode())nodes.push(x);
    nodes.forEach(function(n){n.nodeValue=n.nodeValue.replace(/\(?833\)?[ .-]?833[ .-]?2010/g,disp).replace(/\(?608\)?[ .-]?888[ .-]?8785/g,disp);});
  }
  if(document.readyState!=="loading"){run();}else{document.addEventListener("DOMContentLoaded",run);}
  setTimeout(run,1200);
})();
</script>
