import html
P="/home/user/go-viral-creators/video-demo/full"
END=186.744
L=[(14.2,21.4,"है इल्तिज़ा... कि अपनी बाहों में रख ले ज़रा"),
(23.4,30.4,"है क्या पता... कल हो ना हो हम यहाँ"),
(31.2,34.8,"तेरा इंतज़ार है"),
(35.6,39.4,"कह दे हाँ, प्यार है"),
(40.0,44.2,"तेरे बिना जीना, हो, हो"),
(44.4,47.9,"अब तो बेकार है..."),
(48.4,53.0,"तेरा इंतज़ार है, कह दे हाँ, प्यार है"),
(53.0,57.3,"तेरे बिना जीना, अब तो बेकार है"),
(57.4,61.7,"तेरा इंतज़ार है, कह दे हाँ, प्यार है"),
(61.7,67.0,"तेरे बिना जीना, अब तो बेकार है"),
(84.8,89.6,"चल चलते ग़म भरते, क्या जीना मर-मर के"),
(89.7,94.0,"तारे हम अंबर के, गुज़रे कुछ कर धर के"),
(94.1,98.3,"हर पल ये हलचल है, क्यूँ तेरी कल-कल है"),
(98.4,102.9,"दिल मेरा पागल है, तू कितनी चंचल है"),
(103.0,110.6,"है इल्तिज़ा मेरी, ले इम्तिहाँ मेरी"),
(111.0,117.2,"बस इतना तू कह दे जहाँ, ना है परवाह तेरी"),
(117.3,125.2,"दीवानेपन में तेरे हाँ इतना तो कर जाऊँगा"),
(125.9,133.8,"तू आए या ना आए, तेरी राह में मर जाऊँगा"),
(136.0,143.6,"ओ... ओ... ओ..."),
(144.9,149.2,"तेरा इंतज़ार है, कह दे हाँ, प्यार है"),
(149.3,153.4,"तेरे बिना जीना, अब तो बेकार है"),
(153.5,157.8,"तेरा इंतज़ार है, कह दे हाँ, प्यार है"),
(157.9,163.4,"तेरे बिना जीना, अब तो बेकार है"),
(164.2,167.4,"रोते ना सही, दो आँसू ही गिरा देते"),
(167.5,170.4,"रिश्ता ना निभाते, प्यार ही निभा देते"),
(170.5,173.9,"तुमने छोड़ा है हमें बेवफ़ा कहकर"),
(174.0,175.6,"ये बुरा लगा हमको..."),
(175.7,178.0,"हमें बेवफ़ा न कहते"),
(178.1,180.2,"कोई और इल्ज़ाम लगा देते"),
(180.3,183.8,"कोई और इल्ज़ाम लगा देते...")]
HOOK="तेरा इंतज़ार है"
lines=[]; js=[]
for i,(s,e,t) in enumerate(L):
    d=round(e-s,2); words=t.split(" ")
    spans=[]; hook=t.startswith(HOOK); hw=len(HOOK.split(" "))
    for j,w in enumerate(words):
        cls="ly-w ly-gold" if (hook and j<hw) else "ly-w"
        spans.append(f'<span class="{cls}">{html.escape(w)}</span>')
    lines.append(f'      <div id="ly{i}" class="clip ly-line" data-start="{s}" data-duration="{d}" data-track-index="0"><div class="ly-in" id="ly{i}-in">{" ".join(spans)}</div></div>')
    js.append(f'      tl.fromTo("#ly{i}-in .ly-w", {{ opacity: 0, y: 16, filter: "blur(8px)" }}, {{ opacity: 1, y: 0, filter: "blur(0px)", duration: 0.45, ease: "power2.out", stagger: 0.07 }}, {s});')
    js.append(f'      tl.fromTo("#ly{i}-in", {{ opacity: 1 }}, {{ opacity: 0, duration: 0.35, ease: "power1.in" }}, {round(e-0.4,2)});')
lyr=f'''<template>
  <style>
    @font-face {{ font-family: "Tiro Devanagari Hindi"; src: url("assets/fonts/TiroDevanagariHindi.ttf"); font-weight: 400; }}
    #root {{ position: absolute; inset: 0; pointer-events: none; }}
    .ly-line {{ position: absolute; left: 0; width: 100%; bottom: 112px; text-align: center; }}
    .ly-in {{ display: inline-block; padding: 0 60px; font-family: "Tiro Devanagari Hindi", serif; font-size: 54px; line-height: 1.3; color: #fff7ec;
      text-shadow: 0 2px 6px rgba(0,0,0,0.9), 0 0 28px rgba(0,0,0,0.75); }}
    .ly-w {{ display: inline-block; }}
    .ly-gold {{ color: #ffd79a; text-shadow: 0 2px 6px rgba(0,0,0,0.9), 0 0 26px rgba(255,170,70,0.65); }}
  </style>
  <div id="root" data-composition-id="lyrics" data-width="1920" data-height="1080">
{chr(10).join(lines)}
  </div>
  <script>
    (function () {{
      const tl = gsap.timeline({{ paused: true }});
{chr(10).join(js)}
      window.__timelines["lyrics"] = tl;
    }})();
  </script>
</template>
'''
open(f"{P}/compositions/lyrics.html","w").write(lyr)

fx=f'''<template>
  <style>
    #root {{ position: absolute; inset: 0; overflow: hidden; pointer-events: none; }}
    #fx-grain {{ position: absolute; inset: -320px; opacity: 0.06; background: url("assets/img/noise.png"); background-size: 512px 512px; }}
    #fx-vig {{ position: absolute; inset: 0; background: radial-gradient(ellipse 75% 72% at 50% 50%, rgba(0,0,0,0) 55%, rgba(0,0,0,0.55) 100%); }}
    #fx-shade {{ position: absolute; left: 0; bottom: 0; width: 100%; height: 360px; background: linear-gradient(to top, rgba(0,0,0,0.55), rgba(0,0,0,0)); }}
    .fx-bar {{ position: absolute; left: 0; width: 100%; height: 70px; background: #000; }}
    #fx-bar-t {{ top: 0; }} #fx-bar-b {{ bottom: 0; }}
    #fx-flash {{ position: absolute; inset: 0; background: radial-gradient(ellipse 80% 70% at 50% 30%, #f4f8ff, rgba(200,220,255,0.7) 50%, rgba(160,190,255,0.2)); opacity: 0; }}
    #fx-black {{ position: absolute; inset: 0; background: #000; opacity: 0; }}
  </style>
  <div id="root" data-composition-id="fx" data-width="1920" data-height="1080">
    <div id="fx-shade"></div>
    <div id="fx-grain"></div>
    <div id="fx-vig"></div>
    <div id="fx-flash"></div>
    <div id="fx-black"></div>
    <div id="fx-bar-t" class="fx-bar"></div>
    <div id="fx-bar-b" class="fx-bar"></div>
  </div>
  <script>
    (function () {{
      const tl = gsap.timeline({{ paused: true }});
      tl.fromTo("#fx-grain", {{ x: 0, y: 0 }}, {{ x: -293, y: -187, duration: {END}, ease: "steps({int(END*12)})" }}, 0);
      // open from black
      tl.fromTo("#fx-black", {{ opacity: 1 }}, {{ opacity: 0, duration: 1.4, ease: "power1.out" }}, 0);
      // lightning on the bridge cut (storm begins)
      tl.fromTo("#fx-flash", {{ opacity: 0 }}, {{ opacity: 0.85, duration: 0.06, ease: "none" }}, 117.28);
      tl.fromTo("#fx-flash", {{ opacity: 0.85 }}, {{ opacity: 0, duration: 0.5, ease: "power2.out", immediateRender: false }}, 117.34);
      tl.fromTo("#fx-flash", {{ opacity: 0 }}, {{ opacity: 0.45, duration: 0.05, ease: "none", immediateRender: false }}, 132.2);
      tl.fromTo("#fx-flash", {{ opacity: 0.45 }}, {{ opacity: 0, duration: 0.45, ease: "power2.out", immediateRender: false }}, 132.25);
      // final fade to black
      tl.fromTo("#fx-black", {{ opacity: 0 }}, {{ opacity: 1, duration: 1.4, ease: "power1.in", immediateRender: false }}, {END-1.45});
      window.__timelines["fx"] = tl;
    }})();
  </script>
</template>
'''
open(f"{P}/compositions/fx.html","w").write(fx)

def titles(cid, start, top, show, hide, lx=0, lw=1920):
    return f'''<template>
  <style>
    @font-face {{ font-family: "Tiro Devanagari Hindi"; src: url("assets/fonts/TiroDevanagariHindi.ttf"); font-weight: 400; }}
    #root {{ position: absolute; inset: 0; pointer-events: none; }}
    #{cid}-art {{ position: absolute; left: {lx}px; width: {lw}px; top: {top}px; text-align: center; color: rgba(235,242,255,0.9);
      font-family: "Inter", sans-serif; font-size: 30px; font-weight: 700; letter-spacing: 0.62em; text-shadow: 0 2px 12px rgba(0,0,0,0.8); }}
    #{cid}-title {{ position: absolute; left: {lx}px; width: {lw}px; top: {top+50}px; text-align: center; color: #ffe0b0;
      font-family: "Tiro Devanagari Hindi", serif; font-size: 156px; line-height: 1.2;
      text-shadow: 0 2px 10px rgba(0,0,0,0.7), 0 0 44px rgba(255,170,80,0.6), 0 0 120px rgba(255,140,60,0.35); }}
    #{cid}-line {{ position: absolute; left: {lx+lw//2}px; top: {top+262}px; width: 220px; height: 2px; margin-left: -110px; background: rgba(255,205,150,0.8); }}
    #{cid}-sub {{ position: absolute; left: {lx}px; width: {lw}px; top: {top+286}px; text-align: center; color: rgba(215,228,245,0.85);
      font-family: "Inter", sans-serif; font-size: 28px; font-weight: 400; letter-spacing: 0.5em; text-shadow: 0 2px 10px rgba(0,0,0,0.8); }}
  </style>
  <div id="root" data-composition-id="{cid}" data-width="1920" data-height="1080">
    <div id="{cid}-art">BAD DEVIL</div>
    <div id="{cid}-title">तेरा इंतज़ार है</div>
    <div id="{cid}-line"></div>
    <div id="{cid}-sub">MUSIC VIDEO</div>
  </div>
  <script>
    (function () {{
      const tl = gsap.timeline({{ paused: true }});
      tl.fromTo("#{cid}-art", {{ opacity: 0, scale: 1.12 }}, {{ opacity: 1, scale: 1, duration: 1.2, ease: "power2.out" }}, {show});
      tl.fromTo("#{cid}-title", {{ opacity: 0, scale: 1.08, filter: "blur(14px)" }}, {{ opacity: 1, scale: 1, filter: "blur(0px)", duration: 1.4, ease: "power3.out" }}, {show+0.9});
      tl.fromTo("#{cid}-line", {{ scaleX: 0 }}, {{ scaleX: 1, duration: 0.8, ease: "power3.out" }}, {show+1.8});
      tl.fromTo("#{cid}-sub", {{ opacity: 0, y: 8 }}, {{ opacity: 1, y: 0, duration: 0.8, ease: "power2.out" }}, {show+2.1});
      {"" if hide is None else f'tl.fromTo(["#{cid}-art","#{cid}-title","#{cid}-line","#{cid}-sub"], {{ opacity: 1 }}, {{ opacity: 0, duration: 0.9, ease: "power1.in", immediateRender: false }}, {hide});'}
      window.__timelines["{cid}"] = tl;
    }})();
  </script>
</template>
'''
open(f"{P}/compositions/intro.html","w").write(titles("intro",0,330,0.9,5.9))
open(f"{P}/compositions/outro.html","w").write(titles("outro",0,150,0.3,None,1010,860))

idx=f'''<!doctype html>
<html lang="hi">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=1920, height=1080" />
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/gsap.min.js"></script>
    <style>
      * {{ margin: 0; padding: 0; box-sizing: border-box; }}
      html, body {{ margin: 0; width: 1920px; height: 1080px; overflow: hidden; background: #000; }}
      #root {{ position: relative; width: 100%; height: 100%; background: #000; }}
      #base {{ position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }}
    </style>
  </head>
  <body>
    <div id="root" data-composition-id="main" data-start="0" data-duration="{END}" data-width="1920" data-height="1080">
      <video id="base" class="clip" src="assets/base.mp4" muted playsinline data-start="0" data-duration="{END}" data-track-index="0"></video>
      <div id="fx" class="clip" data-composition-id="fx" data-composition-src="compositions/fx.html" data-start="0" data-duration="{END}" data-track-index="1" data-width="1920" data-height="1080" data-track-kind="graphics" style="z-index:5"></div>
      <div id="intro" class="clip" data-composition-id="intro" data-composition-src="compositions/intro.html" data-start="0" data-duration="7.2" data-track-index="2" data-width="1920" data-height="1080" data-track-kind="graphics" style="z-index:6"></div>
      <div id="outro" class="clip" data-composition-id="outro" data-composition-src="compositions/outro.html" data-start="179.6" data-duration="{round(END-179.6,3)}" data-track-index="2" data-width="1920" data-height="1080" data-track-kind="graphics" style="z-index:6"></div>
      <div id="lyrics" class="clip" data-composition-id="lyrics" data-composition-src="compositions/lyrics.html" data-start="0" data-duration="{END}" data-track-index="3" data-width="1920" data-height="1080" data-track-kind="captions" style="z-index:4"></div>
      <audio id="song" class="clip" src="assets/song.mp3" data-start="0" data-duration="{END}" data-volume="1" data-fade-out="1.2" data-track-index="5"></audio>
    </div>
    <script>
      window.__timelines = window.__timelines || {{}};
      const tl = gsap.timeline({{ paused: true }});
      window.__timelines["main"] = tl;
      tl.seek(0);
    </script>
  </body>
</html>
'''
open(f"{P}/index.html","w").write(idx)
print("written")
