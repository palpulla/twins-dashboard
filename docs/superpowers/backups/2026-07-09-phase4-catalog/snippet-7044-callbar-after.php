<!-- 2026-07-09 scoping change: snippet BODY below is UNCHANGED (byte-identical to
     snippet-7044-callbar-before.php). The fix was applied via WPCode UI, not code:
     Smart Conditional Logic = ENABLED, Show this snippet if [Page URL] [Contains] "-lp".
     Old #twins-callbar now renders only on landing pages; #twx2-stickybar (snippet 7050)
     is the sole mobile bar everywhere else. If restoring from this file, re-create that
     Conditional Logic rule in the WPCode editor (admin.php?page=wpcode-snippet-manager&snippet_id=7044). -->
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
  <a class="twins-call" href="tel:+16088888785">&#128222; Call Now</a>
  <a class="twins-book" href="https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true">Book Online</a>
</div>
<script>
(function(){
  var v=document.querySelector('meta[name="viewport"]');
  if(v){v.setAttribute('content','width=device-width, initial-scale=1');}
})();
</script>
