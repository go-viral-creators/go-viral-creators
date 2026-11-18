import sys, json
SP=sys.argv[1]
# face anchors in source px: (cx, cy, face_width), image size
F={"boy_up":(440,210,176,(880,997)),"boy_bow":(306,260,183,(918,1071)),"girl_smile2":(673,505,404,(1011,1060)),
   "boy_sun2":(573,358,322,(1074,1080)),"boy_cry2":(538,330,264,(1099,1068)),"girl_bridge_cut":(825,329,237,(1292,1736)),"girl_coat3":(607,267,310,(1001,1080))}
def person(name,x,y,tw,flip=False,fade_px=520,extra="",cls="p",topfade=0,hfade=False):
    cx,cy,fw,(W,H)=F[name]; s=tw/fw; h=H*s; w=W*s
    left = x-(W-cx if flip else cx)*s; top=y-cy*s
    fb=(cy*s)+fade_px*0.55
    tf=f"transparent 0, #000 {topfade}px, " if topfade else "#000 0, "
    mask=f"-webkit-mask-image: linear-gradient(to bottom, {tf}#000 {fb:.0f}px, transparent {fb+fade_px*0.35:.0f}px)"
    mask+= (", linear-gradient(to right, transparent 0, #000 14%, #000 80%, transparent 97%); -webkit-mask-composite: source-in; mask-composite: intersect;" if hfade else ";")
    tr="transform: scaleX(-1);" if flip else ""
    img=f'<img class="{cls}" src="img/{name}.png" style="position:absolute; height:{h:.0f}px; left:{left:.0f}px; top:{top:.0f}px; {mask} {tr} {extra}">'
    return f'<div class="{cls}w" style="position:absolute;inset:0">{img}</div>' if cls=="p" else img
T='<div class="a title">तेरा इंतज़ार है</div><div class="a singer">BAD DEVIL</div>'
def page(style,body): return f'<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="base.css"><style>{style}</style></head><body><div class="stage">{body}<div class="grain"></div></div></body></html>'
D={}
# E1 Moon
D["e1"]=page("""
.sky{position:absolute;inset:0;background:radial-gradient(ellipse 70% 80% at 50% 25%,#3b3a8a 0%,#1b1a52 40%,#0a0a24 75%,#04040e 100%);}
.stars{position:absolute;inset:0;background-image:radial-gradient(1.5px 1.5px at 10% 20%,#fff,transparent),radial-gradient(1px 1px at 30% 8%,#fff,transparent),radial-gradient(1.5px 1.5px at 70% 14%,#fff,transparent),radial-gradient(1px 1px at 88% 30%,#fff,transparent),radial-gradient(1px 1px at 55% 5%,#fff,transparent),radial-gradient(1.2px 1.2px at 20% 45%,#fff,transparent),radial-gradient(1px 1px at 80% 50%,#fff,transparent),radial-gradient(1.4px 1.4px at 42% 30%,#fff,transparent);opacity:.8}
.moon{position:absolute;left:640px;top:150px;width:300px;margin-left:-150px;filter:drop-shadow(0 0 50px rgba(220,230,255,.8)) drop-shadow(0 0 140px rgba(150,170,255,.6));}
.halo{position:absolute;left:640px;top:300px;width:900px;height:900px;margin:-450px;background:radial-gradient(circle,rgba(180,190,255,.25),transparent 60%);}
.pw{filter:drop-shadow(0 0 24px rgba(150,170,255,.35));}
.shade{position:absolute;left:0;right:0;bottom:0;height:320px;background:linear-gradient(to top,rgba(4,4,14,.95),rgba(4,4,14,.6) 50%,transparent);}
.title{left:0;width:100%;bottom:92px;text-align:center;font-size:148px;color:#f6f7ff;text-shadow:0 0 2px #000,0 6px 0 #14123a,0 0 26px rgba(170,180,255,.95),0 0 70px rgba(120,130,255,.6);}
.singer{left:0;width:100%;bottom:40px;text-align:center;font-size:44px;color:#d8dcff;text-shadow:0 3px 0 #000,0 0 14px rgba(150,160,255,.9);}
""",'<div class="sky"></div><div class="stars"></div><div class="halo"></div><img class="moon" src="img/moon.png">'
 +person("boy_up",300,330,205)+person("girl_bridge_cut",985,330,190)+'<div class="shade"></div>'+T)
# E2 Golden memory
D["e2"]=page("""
.bg{position:absolute;inset:0;background:radial-gradient(ellipse 80% 90% at 50% 40%,#ffcf7a 0%,#f08a3c 35%,#9a3a2a 70%,#3a1410 100%);}
.bok{position:absolute;inset:0;background-image:radial-gradient(circle 40px at 15% 25%,rgba(255,240,200,.45),transparent 70%),radial-gradient(circle 60px at 82% 18%,rgba(255,230,180,.4),transparent 70%),radial-gradient(circle 30px at 50% 12%,rgba(255,250,220,.55),transparent 70%),radial-gradient(circle 50px at 92% 60%,rgba(255,220,170,.35),transparent 70%),radial-gradient(circle 45px at 6% 70%,rgba(255,220,170,.35),transparent 70%),radial-gradient(circle 25px at 38% 30%,rgba(255,255,230,.5),transparent 70%),radial-gradient(circle 35px at 64% 26%,rgba(255,240,200,.45),transparent 70%);}
.flare{position:absolute;left:640px;top:250px;width:700px;height:700px;margin:-350px;background:radial-gradient(circle,rgba(255,250,225,.75),rgba(255,210,140,.25) 30%,transparent 60%);}
.pw{filter:drop-shadow(0 0 26px rgba(255,200,120,.55));}
.shade{position:absolute;left:0;right:0;bottom:0;height:320px;background:linear-gradient(to top,rgba(50,15,5,.92),rgba(50,15,5,.5) 50%,transparent);}
.title{left:0;width:100%;bottom:92px;text-align:center;font-size:148px;color:#fff;text-shadow:0 0 2px #5a1a00,0 4px 0 #9a3200,0 8px 0 #5a1a00,0 0 40px rgba(255,220,140,.95);}
.singer{left:0;width:100%;bottom:40px;text-align:center;font-size:44px;color:#fff3dc;text-shadow:0 3px 0 #5a1a00,0 0 14px rgba(255,180,90,.9);}
""",'<div class="bg"></div><div class="flare"></div><div class="bok"></div>'
 +person("boy_sun2",330,330,205,extra="clip-path: inset(0 22% 0 0);")+person("girl_smile2",950,330,190,flip=True,hfade=True,fade_px=270)+'<div class="shade"></div>'+T)
# E3 Flatline
D["e3"]=page("""
.bg{position:absolute;inset:0;background:radial-gradient(ellipse 70% 80% at 50% 45%,#2a0508 0%,#120204 55%,#050102 100%);}
.grid{position:absolute;inset:0;background-image:linear-gradient(rgba(255,40,60,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(255,40,60,.07) 1px,transparent 1px);background-size:40px 40px;}
svg{position:absolute;inset:0}
.pw{filter:drop-shadow(0 0 22px rgba(255,40,60,.35));}
.shade{position:absolute;left:0;right:0;bottom:0;height:320px;background:linear-gradient(to top,rgba(5,1,2,.95),rgba(5,1,2,.55) 50%,transparent);}
.title{left:0;width:100%;bottom:92px;text-align:center;font-size:148px;color:#fff;text-shadow:0 0 2px #000,0 6px 0 #3a0006,0 0 22px #ff2a3d,0 0 60px #c40018;}
.singer{left:0;width:100%;bottom:40px;text-align:center;font-size:44px;color:#ff5a68;text-shadow:0 3px 0 #000,0 0 14px rgba(255,40,60,.9);}
""",'<div class="bg"></div><div class="grid"></div>'
 +person("boy_bow",330,300,135)+person("girl_bridge_cut",975,330,190)
 +'<svg viewBox="0 0 1280 720"><defs><filter id="r" x="-20%" y="-50%" width="140%" height="200%"><feGaussianBlur stdDeviation="5" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs><polyline filter="url(#r)" points="0,470 470,470 510,470 535,400 560,540 590,330 620,520 645,470 1280,470" fill="none" stroke="#ff3347" stroke-width="5" stroke-linejoin="round"/></svg>'
 +'<div class="shade"></div>'+T)
# E4 Rain glass
D["e4"]=page("""
.bg{position:absolute;inset:0;background:radial-gradient(ellipse 80% 80% at 50% 40%,#1f5f7a 0%,#0e3348 45%,#061520 100%);}
.bok{position:absolute;inset:0;background-image:radial-gradient(circle 35px at 20% 30%,rgba(255,190,90,.35),transparent 70%),radial-gradient(circle 28px at 75% 22%,rgba(120,220,255,.35),transparent 70%),radial-gradient(circle 40px at 55% 60%,rgba(255,170,80,.25),transparent 70%),radial-gradient(circle 30px at 88% 70%,rgba(120,220,255,.3),transparent 70%);filter:blur(2px)}
.frame{position:absolute;left:628px;top:0;width:24px;height:720px;background:linear-gradient(90deg,#03090f,#122636 40%,#03090f);box-shadow:0 0 30px rgba(0,0,0,.8);}
.rain{position:absolute;inset:0;background-image:radial-gradient(3px 4px at 12% 18%,rgba(220,245,255,.55),transparent),radial-gradient(2px 3px at 31% 66%,rgba(220,245,255,.5),transparent),radial-gradient(3px 5px at 47% 12%,rgba(220,245,255,.55),transparent),radial-gradient(2px 3px at 66% 44%,rgba(220,245,255,.5),transparent),radial-gradient(3px 4px at 83% 15%,rgba(220,245,255,.55),transparent),radial-gradient(2px 4px at 92% 52%,rgba(220,245,255,.5),transparent),radial-gradient(2px 3px at 7% 58%,rgba(220,245,255,.5),transparent),radial-gradient(3px 4px at 58% 80%,rgba(220,245,255,.5),transparent);background-size:260px 230px;}
.streak{position:absolute;inset:0;background:repeating-linear-gradient(95deg,transparent 0 34px,rgba(210,240,255,.08) 34px 35px);}
.pw{filter:drop-shadow(0 0 22px rgba(120,220,255,.3));}
.shade{position:absolute;left:0;right:0;bottom:0;height:320px;background:linear-gradient(to top,rgba(3,10,16,.95),rgba(3,10,16,.55) 50%,transparent);}
.title{left:0;width:100%;bottom:92px;text-align:center;font-size:148px;background:linear-gradient(180deg,#ffffff 0%,#c9f3ff 45%,#5fd0ff 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;filter:drop-shadow(0 0 2px #000) drop-shadow(0 6px 0 #04202e) drop-shadow(0 0 24px rgba(90,210,255,.75));}
.singer{left:0;width:100%;bottom:40px;text-align:center;font-size:44px;color:#e6f9ff;text-shadow:0 3px 0 #000,0 0 14px rgba(90,210,255,.9);}
""",'<div class="bg"></div><div class="bok"></div>'
 +person("boy_cry2",315,330,205)+person("girl_coat3",965,325,210,topfade=60)+'<div class="frame"></div><div class="streak"></div><div class="rain"></div><div class="shade"></div>'+T)
# E5 Torn photo
D["e5"]=page("""
.bg{position:absolute;inset:0;background:radial-gradient(ellipse 80% 80% at 70% 40%,#1c2433 0%,#0c111a 55%,#05070b 100%);}
.photo{position:absolute;left:40px;top:40px;width:560px;height:520px;background:#f2e2c4;transform:rotate(-5deg);box-shadow:0 20px 50px rgba(0,0,0,.7);clip-path:polygon(0 0,100% 0,96% 8%,100% 16%,94% 26%,99% 36%,93% 47%,98% 58%,92% 69%,97% 80%,93% 90%,98% 100%,0 100%);}
.inner{position:absolute;left:24px;top:24px;right:0;bottom:24px;overflow:hidden;background:radial-gradient(ellipse at 50% 40%,#ffd99a,#d9894a 70%,#7a3a20);}
.inner img{position:absolute;filter:sepia(.35) saturate(1.1);}
.rain{position:absolute;inset:0;background:repeating-linear-gradient(100deg,transparent 0 22px,rgba(200,220,255,.10) 22px 23px);}
.pw{filter:drop-shadow(0 0 26px rgba(120,160,255,.35));}
.shade{position:absolute;left:0;right:0;bottom:0;height:320px;background:linear-gradient(to top,rgba(4,5,8,.95),rgba(4,5,8,.55) 50%,transparent);}
.title{left:0;width:100%;bottom:92px;text-align:center;font-size:148px;background:linear-gradient(180deg,#fff7d6 0%,#ffd36b 40%,#e08a1c 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;filter:drop-shadow(0 0 2px #000) drop-shadow(0 0 2px #000) drop-shadow(0 7px 0 #2a1200) drop-shadow(0 0 26px rgba(255,160,50,.6));}
.singer{left:0;width:100%;bottom:40px;text-align:center;font-size:44px;color:#fff;text-shadow:0 3px 0 #000,0 0 14px rgba(255,190,90,.8);}
""",'<div class="bg"></div><div class="photo"><div class="inner">'
 +person("girl_smile2",270,250,190,flip=True,fade_px=2000,cls="q")+'</div></div>'
 +'<div class="rain"></div>'+person("boy_up",965,330,205)+'<div class="shade"></div>'+T)
for k,v in D.items(): open(f"{SP}/t3/{k}.html","w").write(v)
print("ok")
