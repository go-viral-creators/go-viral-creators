"""Write the HyperFrames motion-graphics layer for Salman Barga."""
import html, os
P=os.path.join(os.path.dirname(os.path.abspath(__file__)),"..","hf"); END=155.4
B,T="b","t"
# (start, end, text, position, hook)
L=[(2.5,6.0,"Love You कहके मने वो हाँ कर गया",T,0),
(12.5,15.3,"भोली सी सूरत दिल पे यो छा गई",B,0),
(15.3,17.9,"एक झलक में जान पे आ गयी",B,0),
(17.9,22.9,"सांवला सा छोरा दिल न लूट गया",B,0),
(23.2,26.9,"लम्बा ठाड्डा छोरा, कती सलमान बरगा",B,1),
(27.0,33.4,"सिद्धि सद्दी छोरी नै, पागल कर गया",B,0),
(33.6,38.9,"लम्बा ठाड्डा छोरा, कती सलमान बरगा",B,1),
(41.8,44.6,"सांवला है रंग, पर दिल सोणा है",B,0),
(44.6,47.2,"मुझे तो अब उसका ही होणा है",B,0),
(47.3,50.0,"यूपी का लड़का, हरियाणा की छोरी",B,0),
(50.0,52.4,"प्यार में उसके पागल हो री",B,0),
(52.5,57.6,"पता नी कौन सा जादू वो कर गया",T,0),
(60.3,62.9,"तेरे जैसी ना कोई, तू मान मेरी जान",B,0),
(62.9,65.4,"मेरी सुबह बना दे तेरी एक मुस्कान",B,0),
(65.5,68.1,"तेरी यादों में बीते मेरे दिन और शाम",B,0),
(68.2,70.8,"मेरे दिल की धड़कन पे बस तेरा नाम",B,0),
(70.8,73.5,"तेरे प्यार का पिया दे तू बस एक जाम",B,0),
(73.5,76.1,"तेरे नाम मैं कर दूँ मेरी जिंदगी तमाम",B,0),
(76.2,79.1,"मैं मुकरता ना फेर जो दे दूँ जबान",B,0),
(79.1,84.3,"मैं हूँ तेरे साथ, तू ना हो परेशान",B,0),
(84.6,87.8,"साथ तेरे रहने की, कसम मैंने खा राखी",B,0),
(87.9,90.4,"तेरी सेफ्टी खत्तिर, या बॉडी बना राखी",B,0),
(90.5,93.3,"तू मेरी और मैं तेरा, जन्मों सूं साथी",B,0),
(93.4,97.0,"रहना चाहूँ तेरे साथ, अपना बता बाकी",B,0),
(98.0,102.8,"तेरे बिना इब रहणा कौनी रे",B,0),
(103.0,108.3,"मरजूँगी पर किसी का होणा कौनी रे",B,0),
(108.6,113.6,"तेरे बिना इब रहणा कौनी रे",B,0),
(113.8,118.4,"मरजूँगी पर किसी का होणा कौनी रे",B,0),
(118.9,124.3,"अफ़सर किंग, तू मेरी जान बरगा",B,0),
(124.4,126.4,"लम्बा... लम्बा... लम्बा...",B,1),
(126.5,130.9,"लम्बा ठाड्डा छोरा, कती सलमान बरगा",B,1),
(131.0,136.0,"सिद्धि सद्दी छोरी नै, पागल करगा",B,0),
(136.3,141.2,"लम्बा ठाड्डा छोरा, कती सलमान बरगा",T,1),
(143.0,146.7,"लम्बा ठाड्डा छोरा, कती सलमान बरगा",T,1)]
divs=[]; js=[]
for i,(s,e,t,pos,hook) in enumerate(L):
    words=" ".join(f'<span class="w">{html.escape(w)}</span>' for w in t.split(" "))
    cls="line "+("top" if pos==T else "bot")+(" hook" if hook else "")
    divs.append(f'    <div id="ly{i}" class="clip {cls}" data-start="{s}" data-duration="{round(e-s,2)}" data-track-index="0"><div class="in" id="ly{i}-in">{words}</div></div>')
    if hook:
        js.append(f'      tl.fromTo("#ly{i}-in .w",{{opacity:0,scale:1.6,y:-10}},{{opacity:1,scale:1,y:0,duration:0.35,ease:"back.out(2.2)",stagger:0.1}},{s});')
    else:
        js.append(f'      tl.fromTo("#ly{i}-in .w",{{opacity:0,y:18,filter:"blur(6px)"}},{{opacity:1,y:0,filter:"blur(0px)",duration:0.4,ease:"power2.out",stagger:0.06}},{s});')
    js.append(f'      tl.fromTo("#ly{i}-in",{{opacity:1}},{{opacity:0,duration:0.3,ease:"power1.in"}},{round(e-0.32,2)});')
open(f"{P}/compositions/lyrics.html","w").write(f'''<template>
  <style>
    @font-face {{ font-family: "Yatra One"; src: url("assets/fonts/YatraOne-Regular.ttf"); }}
    #root {{ position: absolute; inset: 0; pointer-events: none; }}
    .line {{ position: absolute; left: 0; width: 100%; text-align: center; }}
    .bot {{ bottom: 52px; }} .top {{ top: 46px; }}
    .in {{ display: inline-block; padding: 0 70px; font-family: "Yatra One", serif; font-size: 52px; line-height: 1.35; color: #ffffff;
      text-shadow: 0 0 2px #2a0018, 0 3px 0 rgba(60,0,30,.85), 0 0 18px rgba(0,0,0,.75), 0 0 34px rgba(255,60,150,.45); }}
    .hook .in {{ font-size: 64px; color: #ffe27a; text-shadow: 0 0 2px #3a1400, 0 4px 0 #7a2a00, 0 0 22px rgba(0,0,0,.7), 0 0 40px rgba(255,170,40,.7); }}
    .w {{ display: inline-block; }}
  </style>
  <div id="root" data-composition-id="lyrics" data-width="1920" data-height="1080">
{chr(10).join(divs)}
  </div>
  <script>
    (function () {{
      const tl = gsap.timeline({{ paused: true }});
{chr(10).join(js)}
      window.__timelines["lyrics"] = tl;
    }})();
  </script>
</template>
''')
# fx: grain, vignette, fades, flashes, hearts, confetti
hearts=[]; conf=[]
import random
r=random.Random(11)
for k in range(22):
    hearts.append((r.uniform(80,1840),r.uniform(0.7,1.6),r.uniform(0,3.6),r.choice(["#ff5fa8","#ff8fc5","#ffd1e6","#ff3d7f"])))
for k in range(70):
    conf.append((r.uniform(0,1920),r.uniform(-300,-20),r.uniform(0,1.2),r.choice(["#ffd23f","#ff4f9a","#3fd0ff","#7dff6b","#ffffff","#ff8a3d"]),r.uniform(-540,540),r.uniform(10,20)))
hd="\n".join(f'    <div class="heart" data-layout-ignore id="h{i}" style="left:{x:.0f}px;transform:scale({s:.2f});color:{c}">&#10084;</div>' for i,(x,s,d,c) in enumerate(hearts))
hj="\n".join(f'      tl.fromTo("#h{i}",{{y:0}},{{y:-760,duration:3.4,ease:"power1.out"}},{47.6+d:.2f}); tl.fromTo("#h{i}",{{opacity:0}},{{opacity:1,duration:0.5}},{47.6+d:.2f}); tl.fromTo("#h{i}",{{opacity:1}},{{opacity:0,duration:0.8,immediateRender:false}},{47.6+d+2.6:.2f});' for i,(x,s,d,c) in enumerate(hearts))
cd="\n".join(f'    <div class="cf" id="c{i}" style="left:{x:.0f}px;top:{y:.0f}px;width:{w:.0f}px;height:{w*0.45:.0f}px;background:{c}"></div>' for i,(x,y,d,c,rot,w) in enumerate(conf))
cj="\n".join(f'      tl.fromTo("#c{i}",{{y:0,rotation:0,opacity:1}},{{y:{1400+r.uniform(-100,200):.0f},x:{r.uniform(-160,160):.0f},rotation:{rot:.0f},duration:{3.2+r.uniform(0,1.2):.2f},ease:"power1.in"}},{131.0+d:.2f});' for i,(x,y,d,c,rot,w) in enumerate(conf))
open(f"{P}/compositions/fx.html","w").write(f'''<template>
  <style>
    #root {{ position: absolute; inset: 0; overflow: hidden; pointer-events: none; }}
    #grain {{ position: absolute; inset: -320px; opacity: .045; background: url("assets/img/noise.png"); background-size: 512px 512px; }}
    #vig {{ position: absolute; inset: 0; background: radial-gradient(ellipse 80% 78% at 50% 50%, rgba(0,0,0,0) 60%, rgba(0,0,0,.42) 100%); }}
    #shadeB {{ position: absolute; left:0; right:0; bottom:0; height: 260px; background: linear-gradient(to top, rgba(0,0,0,.42), rgba(0,0,0,0)); }}
    #shadeT {{ position: absolute; left:0; right:0; top:0; height: 220px; background: linear-gradient(to bottom, rgba(0,0,0,.38), rgba(0,0,0,0)); opacity: 0; }}
    #flashP {{ position: absolute; inset: 0; background: radial-gradient(ellipse 70% 70% at 50% 40%, #ffd0ec, rgba(255,80,170,.65) 55%, rgba(120,0,90,.2)); opacity: 0; mix-blend-mode: screen; }}
    #flashW {{ position: absolute; inset: 0; background: #fff8e6; opacity: 0; }}
    #black {{ position: absolute; inset: 0; background: #000; opacity: 1; }}
    .heart {{ position: absolute; bottom: -80px; font-size: 54px; opacity: 0; text-shadow: 0 0 18px currentColor; }}
    .cf {{ position: absolute; border-radius: 2px; }}
  </style>
  <div id="root" data-composition-id="fx" data-width="1920" data-height="1080">
    <div id="shadeB"></div><div id="shadeT"></div>
{hd}
{cd}
    <div id="grain"></div><div id="vig"></div>
    <div id="flashP"></div><div id="flashW"></div><div id="black"></div>
  </div>
  <script>
    (function () {{
      const tl = gsap.timeline({{ paused: true }});
      tl.fromTo("#grain",{{x:0,y:0}},{{x:-293,y:-187,duration:{END},ease:"steps({int(END*12)})"}},0);
      tl.fromTo("#black",{{opacity:1}},{{opacity:0,duration:1.6,ease:"power1.out"}},0);
      tl.fromTo("#black",{{opacity:0}},{{opacity:1,duration:1.8,ease:"power1.in",immediateRender:false}},{END-1.85});
      // top shade only while lyrics sit at the top
      [[2.4,6.1],[52.4,57.7],[136.2,146.8]].forEach(([a,b])=>{{ tl.fromTo("#shadeT",{{opacity:0}},{{opacity:1,duration:0.3,immediateRender:false}},a); tl.fromTo("#shadeT",{{opacity:1}},{{opacity:0,duration:0.3,immediateRender:false}},b); }});
      // rap entry pink flash and chorus white flash
      tl.fromTo("#flashP",{{opacity:0}},{{opacity:0.85,duration:0.06,immediateRender:false}},57.76); tl.fromTo("#flashP",{{opacity:0.85}},{{opacity:0,duration:0.5,immediateRender:false}},57.82);
      tl.fromTo("#flashW",{{opacity:0}},{{opacity:0.8,duration:0.05,immediateRender:false}},131.0); tl.fromTo("#flashW",{{opacity:0.8}},{{opacity:0,duration:0.45,immediateRender:false}},131.05);
{hj}
{cj}
      window.__timelines["fx"] = tl;
    }})();
  </script>
</template>
''')
def title(cid, show, hide, top):
    hid = "" if hide is None else f'tl.fromTo(["#{cid}-t","#{cid}-s"],{{opacity:1}},{{opacity:0,duration:0.6,immediateRender:false}},{hide});'
    return f'''<template>
  <style>
    @font-face {{ font-family: "Yatra One"; src: url("assets/fonts/YatraOne-Regular.ttf"); }}
    @font-face {{ font-family: "Bebas"; src: url("assets/fonts/BebasNeue-Regular.ttf"); }}
    #root {{ position: absolute; inset: 0; pointer-events: none; }}
    #{cid}-t {{ position: absolute; left: 0; width: 100%; top: {top}px; text-align: center; font-family: "Yatra One", serif; font-size: 150px; line-height: 1.15; color: #ffe27a;
      text-shadow: 0 0 3px #3a1400, 0 6px 0 #8a2c00, 0 10px 0 #4a1500, 0 0 50px rgba(255,170,50,.85), 0 0 110px rgba(255,90,160,.5); }}
    #{cid}-s {{ position: absolute; left: 0; width: 100%; top: {top+190}px; text-align: center; font-family: "Bebas", sans-serif; font-size: 52px; letter-spacing: .5em; color: #fff;
      text-shadow: 0 3px 0 #000, 0 0 20px rgba(255,80,160,.9); }}
  </style>
  <div id="root" data-composition-id="{cid}" data-width="1920" data-height="1080">
    <div id="{cid}-t">सलमान बरगा</div>
    <div id="{cid}-s">BAD DEVIL</div>
  </div>
  <script>
    (function () {{
      const tl = gsap.timeline({{ paused: true }});
      tl.fromTo("#{cid}-t",{{opacity:0,scale:1.5,filter:"blur(12px)"}},{{opacity:1,scale:1,filter:"blur(0px)",duration:0.8,ease:"back.out(1.8)"}},{show});
      tl.fromTo("#{cid}-s",{{opacity:0,y:14}},{{opacity:1,y:0,duration:0.6,ease:"power2.out"}},{show+0.6});
      {hid}
      window.__timelines["{cid}"] = tl;
    }})();
  </script>
</template>
'''
open(f"{P}/compositions/intro.html","w").write(title("intro",0.4,5.5,40))
open(f"{P}/compositions/outro.html","w").write(title("outro",1.2,None,40))
punch=[57.76,58.56,59.68,88.9,124.5,125.2,125.9,126.5,127.8,131.0,136.3,143.06]
pj="\n".join(f'      tl.fromTo("#base",{{scale:1}},{{scale:1.06,duration:0.07,ease:"power2.out",immediateRender:false}},{t}); tl.fromTo("#base",{{scale:1.06}},{{scale:1,duration:0.4,ease:"power2.out",immediateRender:false}},{t+0.07:.2f});' for t in punch)
open(f"{P}/index.html","w").write(f'''<!doctype html>
<html lang="hi">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=1920, height=1080" />
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/gsap.min.js"></script>
    <style>
      * {{ margin: 0; padding: 0; box-sizing: border-box; }}
      html, body {{ margin: 0; width: 1920px; height: 1080px; overflow: hidden; background: #000; }}
      #root {{ position: relative; width: 100%; height: 100%; background: #000; overflow: hidden; }}
      #base {{ position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }}
    </style>
  </head>
  <body>
    <div id="root" data-composition-id="main" data-start="0" data-duration="{END}" data-width="1920" data-height="1080">
      <video id="base" class="clip" src="assets/base.mp4" muted playsinline data-start="0" data-duration="{END}" data-track-index="0"></video>
      <div id="lyrics" class="clip" data-composition-id="lyrics" data-composition-src="compositions/lyrics.html" data-start="0" data-duration="{END}" data-track-index="3" data-width="1920" data-height="1080" data-track-kind="captions" style="z-index:5"></div>
      <div id="fx" class="clip" data-composition-id="fx" data-composition-src="compositions/fx.html" data-start="0" data-duration="{END}" data-track-index="1" data-width="1920" data-height="1080" data-track-kind="graphics" style="z-index:4"></div>
      <div id="intro" class="clip" data-composition-id="intro" data-composition-src="compositions/intro.html" data-start="6.0" data-duration="6.4" data-track-index="2" data-width="1920" data-height="1080" data-track-kind="graphics" style="z-index:6"></div>
      <div id="outro" class="clip" data-composition-id="outro" data-composition-src="compositions/outro.html" data-start="147.6" data-duration="{round(END-147.6,2)}" data-track-index="2" data-width="1920" data-height="1080" data-track-kind="graphics" style="z-index:6"></div>
      <audio id="song" class="clip" src="assets/mix.mp3" data-start="0" data-duration="{END}" data-volume="1" data-track-index="5"></audio>
    </div>
    <script>
      window.__timelines = window.__timelines || {{}};
      const tl = gsap.timeline({{ paused: true }});
{pj}
      window.__timelines["main"] = tl;
      tl.seek(0);
    </script>
  </body>
</html>
''')
print("written")
