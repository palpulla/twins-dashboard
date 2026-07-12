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
<script>
(function(){
  // CAP: unify stray phone numbers to the main line (833 pool split corrupts attribution)
  document.querySelectorAll('a[href*="8338332010"], a[href*="833-2010"]').forEach(function(a){
    a.setAttribute('href','tel:+16088888785');
    if(/833/.test(a.textContent)) a.textContent = a.textContent.replace(/\(?833\)?[ -.]?833[ -.]?2010/g,'(608) 888-8785');
  });
})();
</script>