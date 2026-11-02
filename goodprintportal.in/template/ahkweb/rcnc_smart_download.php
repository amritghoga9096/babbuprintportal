<?php
/**
 * ================================================================
 *   RC NOCHIP — DOWNLOAD BUTTON VISUAL STATE ONLY
 *   (NO handler logic, NO preventDefault, NO AJAX/fetch/blob)
 *
 *   This matches the EXACT same pattern used by rc_get_list which
 *   NEVER has the "need refresh for first click" bug.
 *   Browser default anchor click does ALL the download work.
 *   This module ONLY adds optional cosmetic visual feedback:
 *     - 12s processing class when user clicks a matching download link
 *     - auto-removes class after 12s (no stuck state even if JS fails)
 *
 *   Matching targets:
 *     Any <a href="...rc_nochip_pdf_download.php?id=N..."> (new list pattern)
 *     Any <a onclick*="rcncSmartDownload"> (legacy pattern)
 *     Any [data-rcnc-btn-id]
 * ================================================================
 */
if (!defined('AHK_RCNC_SMART_MOUNTED')) { define('AHK_RCNC_SMART_MOUNTED', true); }
?>

<style>
/* ============================================================
   COSMETIC VISUAL STATE ONLY (no behaviour changes)
   ============================================================ */
a.rcnc-vis-processing,
[data-rcnc-btn-id].rcnc-vis-processing{
  pointer-events:auto !important; /* do NOT block repeat clicks */
  opacity:0.76 !important;
  filter:saturate(0.9);
  cursor:progress !important;
  user-select:none !important;
  box-shadow:0 0 0 2px rgba(16,185,129,.35) inset !important;
}
a.rcnc-vis-processing .rcnc-spinner,
[data-rcnc-btn-id].rcnc-vis-processing .rcnc-spinner{ display:inline-block !important; }
.rcnc-spinner{
  display:none; width:16px; height:16px; border-radius:50%;
  border:2.5px solid rgba(255,255,255,.38);
  border-top-color:#fff !important;
  animation:rcncSpin 0.85s linear infinite;
  margin-right:6px; vertical-align:middle;
}
@keyframes rcncSpin{ to{ transform: rotate(360deg); } }
a.rcnc-vis-done,
[data-rcnc-btn-id].rcnc-vis-done{
  box-shadow:0 0 0 3px rgba(74,222,128,.26) inset !important;
}
</style>

<script>
/* ================================================================
 *  CAPTURE-PHASE WINDOW CLICK LISTENER
 *  No document.ready dependency, no DataTable dependency —
 *  listener is attached synchronously when this <script> executes,
 *  BEFORE the user ever gets to click a button.
 * ================================================================ */
(function(){
  var VIS_CLASS = 'rcnc-vis-processing';
  var DONE_CLASS = 'rcnc-vis-done';
  var SPINNER_CLASS = 'rcnc-spinner';

  function matchesDownloadAnchor(el){
    if(!el) return null;
    try{
      var a = el;
      if(a.tagName && String(a.tagName).toUpperCase() !== 'A'){
        if(a.closest){ a = a.closest('a, [data-rcnc-btn-id]') || a; }
      }
      if(!a) return null;
      /* pattern 1: new list button → href points to rc_nochip_pdf_download.php */
      if(a.getAttribute){
        var href = a.getAttribute('href') || '';
        if(typeof href === 'string' && href.indexOf('rc_nochip_pdf_download.php') >= 0) return a;
        /* pattern 2: old preview buttons → explicit onclick or marker */
        var onclick = a.getAttribute('onclick') || '';
        if(typeof onclick === 'string' && onclick.indexOf('rcncSmartDownload') >= 0) return a;
        var marker = a.getAttribute('data-rcnc-btn-id');
        if(marker) return a;
      }
    }catch(e){}
    return null;
  }

  function ensureSpinnerOn(anchor){
    try{
      if(!anchor || !anchor.querySelector) return;
      if(anchor.querySelector('.' + SPINNER_CLASS)) return;
      var sp = document.createElement('span');
      sp.className = SPINNER_CLASS;
      if(anchor.firstChild){ try{ anchor.insertBefore(sp, anchor.firstChild); }catch(e){ try{ anchor.appendChild(sp); }catch(e2){} } }
      else { try{ anchor.appendChild(sp); }catch(e){} }
    }catch(e){}
  }

  function applyVisual(anchor){
    try{
      if(!anchor || !anchor.classList) return;
      ensureSpinnerOn(anchor);
      /* remove any prior done class */
      try{ anchor.classList.remove(DONE_CLASS); }catch(e){}
      /* add processing visual */
      try{ anchor.classList.add(VIS_CLASS); }catch(e){}
      /* after 3 seconds assume done and flash green */
      setTimeout(function(){
        try{ anchor.classList.remove(VIS_CLASS); anchor.classList.add(DONE_CLASS); }catch(e){}
        setTimeout(function(){
          try{ anchor.classList.remove(DONE_CLASS); }catch(e){}
        }, 2000);
      }, 3200);
      /* safety cleanup at 12s in case timers delayed */
      setTimeout(function(){
        try{ anchor.classList.remove(VIS_CLASS); anchor.classList.remove(DONE_CLASS); }catch(e){}
      }, 12000);
    }catch(e){}
  }

  /* CAPTURE-PHASE listener: runs BEFORE bubbling, regardless of
     document.ready / DataTable / jQuery state. This is the key
     fix for the "first click only works after refresh" bug:
     our visual code fires IMMEDIATELY at window-capture level
     and never depends on any deferred callback. */
  try{
    window.addEventListener('click', function(ev){
      try{
        var tgt = (ev && ev.target) ? ev.target : null;
        if(!tgt) return;
        var anc = matchesDownloadAnchor(tgt);
        if(anc){
          /* apply visual feedback (no preventDefault → browser default click handles download) */
          applyVisual(anc);
        }
      }catch(eInt){ try{ console.log('[rcnc] listener err', eInt); }catch(eSilent){} }
    }, true /* capture phase */);
  }catch(eOuter){}

  /* Legacy global alias kept for backwards compatibility with any
     old onclick="return rcncSmartDownload(this, event);" in preview
     page — but NO logic. Let browser click through normally. */
  try{
    window.RCNC = window.RCNC || {};
    window.RCNC.smart = {
      download: function(el, ev){
        try{
          var tgt = el || (ev && ev.target) || null;
          var anc = matchesDownloadAnchor(tgt);
          if(anc) applyVisual(anc);
        }catch(e){}
        /* return true so browser default anchor click proceeds */
        return true;
      }
    };
    window.rcncSmartDownload = function(el, ev){ return (window.RCNC && RCNC.smart) ? RCNC.smart.download(el, ev) : true; };
  }catch(eAlias){}
})();
</script>
