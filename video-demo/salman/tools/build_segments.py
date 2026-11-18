"""Salman Barga: trims, speeds and watermark removal per shot. SHOTS is the edit."""
import subprocess, json, sys, os
import numpy as np
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__))); import unwm
RAW="/tmp/claude-0/sb/raw"; OUT="/tmp/claude-0/sb/seg"; END=155.4; R=24
# clip, src_in, src_out, global_start, transition_in, transition_dur
SHOTS=[
 ("01_proposal", 0.0, 12.6,   0.0, None,0),
 ("02_cafe",     0.0, 14.85, 13.0, "fadewhite",0.6),
 ("03_photo",    1.6, 7.3,   28.0, "cut",0),
 ("04_salman",   3.7, 9.2,   33.6, "fadewhite",0.5),
 ("05_amma",     0.0, 7.9,   39.0, "fadewhite",0.5),
 ("06_bed_front",0.5, 5.9,   47.3, "fadeblack",0.5),
 ("07_bed_phone",0.4, 5.9,   52.5, "cut",0),
 ("08_rap",      0.0, 10.0,  57.8, "cut",0),
 ("09_jacket",   0.0, 10.0,  72.0, "fade",0.4),
 ("10_gym",      0.0, 10.0,  84.6, "cut",0),
 ("11_balcony",  0.0, 10.0,  97.5, "fade",0.7),
 ("12_rishta",   0.0, 10.0, 108.4, "fade",0.4),
 ("13_entry",    4.2, 10.0, 118.8, "fade",0.4),
 ("04_salman",  10.0, 14.8, 126.4, "fadewhite",0.3),
 ("14_dance_A",  0.0, 8.0,  131.0, "cut",0),
 ("15_dance_B",  0.0, 8.0,  139.0, "fade",0.4),
 ("16_ending",   0.0, 8.0,  147.0, "fade",0.8),
]
def probe(p):
    o=subprocess.run(["ffprobe","-v","error","-select_streams","v:0","-show_entries","stream=width,height:format=duration","-of","json",p],capture_output=True,text=True).stdout
    j=json.loads(o); return j["streams"][0]["width"],j["streams"][0]["height"],float(j["format"]["duration"])
plan=[]
for i,(c,a,b,gs,tt,td) in enumerate(SHOTS):
    ge=SHOTS[i+1][3] if i+1<len(SHOTS) else END
    din=(1/R if tt=="cut" else td) if i>0 else 0
    dout=0
    if i+1<len(SHOTS): dout=1/R if SHOTS[i+1][4]=="cut" else SHOTS[i+1][5]
    core=ge-gs; sp=(b-a)/core
    W,H,D=probe(f"{RAW}/{c}.mp4")
    s0=a-din/2*sp; s1=b+dout/2*sp
    pad0=max(0,-s0)/sp; pad1=max(0,s1-(D-0.05))/sp
    s0=max(0,s0); s1=min(D-0.05,s1)
    L=(s1-s0)/sp
    plan.append(dict(i=i,clip=c,gs=gs,ge=ge,din=din,tin=tt,speed=round(sp,4),s0=round(s0,3),s1=round(s1,3),pad0=round(pad0,3),pad1=round(pad1,3),L=round(L,3),W=W,H=H))
json.dump(plan,open(f"{OUT}/plan.json","w"),indent=1)
for p in plan: print(p["i"],p["clip"],p["gs"],"->",p["ge"],"speed",p["speed"],"src",p["s0"],p["s1"],"pad",p["pad0"],p["pad1"])
if "--plan" in sys.argv: sys.exit()
for p in plan:
    out=f"{OUT}/seg{p['i']:02d}.mp4"
    if os.path.exists(out): continue
    W,H=p["W"],p["H"]; res=1080 if H==1080 else 720
    dec=subprocess.Popen(["ffmpeg","-v","error","-ss",str(p["s0"]),"-t",f"{p['s1']-p['s0']+0.1:.3f}","-i",f"{RAW}/{p['clip']}.mp4","-an","-f","rawvideo","-pix_fmt","rgb24","-"],stdout=subprocess.PIPE,stdin=subprocess.DEVNULL)
    sp=p["speed"]
    vf=f"setpts=PTS/{sp}"+(",framerate=fps=24:interp_start=15:interp_end=240" if sp<0.97 else ",fps=24")+",scale=1920:1080:flags=lanczos,format=yuv420p"
    enc=subprocess.Popen(["ffmpeg","-v","error","-y","-f","rawvideo","-pix_fmt","rgb24","-s",f"{W}x{H}","-r","24","-i","-","-vf",vf,"-t",f"{p['L']:.4f}","-c:v","libx264","-crf","13","-preset","fast","-an",out],stdin=subprocess.PIPE)
    fs=W*H*3; n=0
    while True:
        buf=dec.stdout.read(fs)
        if len(buf)<fs: break
        f=np.frombuffer(buf,np.uint8).reshape(H,W,3).copy(); unwm.fix(f,res)
        try: enc.stdin.write(f.tobytes())
        except BrokenPipeError: break
        n+=1
    try: enc.stdin.close()
    except BrokenPipeError: pass
    enc.wait(); dec.stdout.close(); dec.kill(); dec.wait()
    print("done",p["i"],p["clip"],n,flush=True)
