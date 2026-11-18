import subprocess, json, sys, os, numpy as np
sys.path.insert(0,"/tmp/claude-0"); import unwm
CL="/tmp/claude-0/clips"; OUT="/tmp/claude-0/seg"
END=186.744
# (scene, clip, desired_src_start, cut_in_time, transition_in_type, transition_in_dur)
SHOTS=[
 ("S7","c16",0.0,   0.0,  None,0),
 ("S8","c21",0.4,   7.6,  "fade",1.0),
 ("S1","c15",0.0,  14.2,  "fade",0.5),
 ("S2","c12",2.0,  23.2,  "fadewhite",0.6),
 ("S3","c22",0.3,  27.0,  "fade",0.35),
 ("S4","c18",1.0,  31.2,  "cut",0),
 ("S5","c14",1.2,  35.5,  "cut",0),
 ("S6","c11",0.0,  40.0,  "cut",0),
 ("S9","c17",0.2,  48.2,  "cut",0),
 ("S10","c19",0.7, 57.4,  "cut",0),
 ("S11","c13",0.8, 63.9,  "fade",0.4),
 ("S12","c09",0.0, 70.0,  "fadewhite",0.8),
 ("S13","c06",0.5, 77.9,  "fade",0.6),
 ("S14","c07",0.0, 84.8,  "cut",0),
 ("S15","c10",0.0, 94.1,  "cut",0),
 ("S16","c08",0.3,103.0,  "fade",0.7),
 ("S17","c03",0.0,117.3,  "cut",0),
 ("S18","c02",2.2,132.2,  "cut",0),
 ("S19","c01",0.0,144.9,  "fadeblack",0.6),
 ("S6r","c11",3.5,159.8,  "cut",0),
 ("S20","c05",0.3,164.2,  "fadeblack",1.0),
 ("S21","c04",2.6,176.0,  "fade",1.2),
 ("S21b","c04",10.85,181.9, "fade",0.5),
]
F=1/24
def dur(p): return float(subprocess.run(["ffprobe","-v","error","-show_entries","format=duration","-of","csv=p=0",p],capture_output=True,text=True,stdin=subprocess.DEVNULL).stdout)
def res(p):
    w,h=subprocess.run(["ffprobe","-v","error","-select_streams","v:0","-show_entries","stream=width,height","-of","csv=p=0",p],capture_output=True,text=True,stdin=subprocess.DEVNULL).stdout.strip().split(",")
    return int(w),int(h)
plan=[]
for i,(sc,c,s0,b,tt,td) in enumerate(SHOTS):
    td = F if tt=="cut" else td
    nb = SHOTS[i+1][3] if i+1<len(SHOTS) else END
    ntt = SHOTS[i+1][4] if i+1<len(SHOTS) else None
    ntd = (F if ntt=="cut" else SHOTS[i+1][5]) if i+1<len(SHOTS) else 0
    gs = b - (td/2 if tt else 0); ge = nb + ntd/2
    L = ge-gs
    p=f"{CL}/{c}.mp4"; D=dur(p)-0.05
    src = s0 - (td/2 if tt else 0)
    speed=1.0
    if src<0: src=0
    if sc in ("S21b",) and src + L > D:
        speed=(D-src)/L
    elif src + L*speed > D:
        # try earlier start first, then slow down
        src=max(0, D-L)
        if src + L > D: speed=(D-src)/L
    plan.append(dict(scene=sc,clip=c,gs=round(gs,4),ge=round(ge,4),L=round(L,4),src=round(src,3),speed=round(speed,4),t_in=tt,d_in=td))
json.dump(plan,open(f"{OUT}/plan.json","w"),indent=1)
for p in plan: print(p)
if "--plan" in sys.argv: sys.exit()
for k,p in enumerate(plan):
    path=f"{CL}/{p['clip']}.mp4"; W,H=res(path); r=1080 if H==1080 else 720
    out=f"{OUT}/seg{k:02d}.mp4"
    if os.path.exists(out): continue
    srcT=p["L"]*p["speed"]+0.2
    dec=subprocess.Popen(["ffmpeg","-v","error","-ss",str(p["src"]),"-t",f"{srcT:.3f}","-i",path,"-an","-f","rawvideo","-pix_fmt","rgb24","-"],stdout=subprocess.PIPE,stdin=subprocess.DEVNULL)
    vf=f"setpts=PTS/{p['speed']}"+(",framerate=fps=24" if p['speed']<0.999 else ",fps=24")+",scale=1920:1080:flags=lanczos,format=yuv420p"
    enc=subprocess.Popen(["ffmpeg","-v","error","-y","-f","rawvideo","-pix_fmt","rgb24","-s",f"{W}x{H}","-r","24","-i","-","-vf",vf,"-t",f"{p['L']:.4f}","-c:v","libx264","-crf","13","-preset","fast","-an",out],stdin=subprocess.PIPE)
    fs=W*H*3; n=0
    while True:
        buf=dec.stdout.read(fs)
        if len(buf)<fs: break
        f=np.frombuffer(buf,np.uint8).reshape(H,W,3).copy(); unwm.fix(f,r)
        try: enc.stdin.write(f.tobytes())
        except BrokenPipeError: break
        n+=1
    try: enc.stdin.close()
    except BrokenPipeError: pass
    enc.wait(); dec.stdout.close(); dec.kill(); dec.wait()
    print("done",k,p["scene"],n,"frames",flush=True)
