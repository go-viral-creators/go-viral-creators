"""Join segments with frame-exact xfade transitions into base.mp4 (no audio)."""
import json, subprocess, sys
OUT="/tmp/claude-0/sb/seg"; R=24; END=155.4
DST=sys.argv[1] if len(sys.argv)>1 else "/home/user/go-viral-creators/video-demo/salman/hf/assets/base.mp4"
plan=json.load(open(f"{OUT}/plan.json"))
plan[0]["gsf"]=0; plan[0]["df"]=0
for k in range(1,len(plan)):
    p=plan[k]; df=max(1,round(p["din"]*R)); bf=round(p["gs"]*R)
    p["df"]=df; p["gsf"]=bf-df//2; plan[k-1]["gef"]=p["gsf"]+df
plan[-1]["gef"]=round(END*R)
inp=[]; fc=[]
for k,p in enumerate(plan):
    inp+=["-i",f"{OUT}/seg{k:02d}.mp4"]; N=p["gef"]-p["gsf"]
    pre=round(p["pad0"]*R)
    fc.append(f"[{k}:v]tpad=start_mode=clone:start={pre}:stop_mode=clone:stop=30,trim=end_frame={N+3},setpts=PTS-STARTPTS,fps=24,settb=1/24[s{k}]")
prev="s0"
for k in range(1,len(plan)):
    p=plan[k]; tr={"cut":"fade","fade":"fade","fadewhite":"fadewhite","fadeblack":"fadeblack"}[p["tin"]]
    fc.append(f"[{prev}][s{k}]xfade=transition={tr}:duration={p['df']/R:.6f}:offset={p['gsf']/R:.6f}[x{k}]"); prev=f"x{k}"
fc.append(f"[{prev}]trim=end_frame={round(END*R)},format=yuv420p[v]")
subprocess.run(["ffmpeg","-v","error","-y",*inp,"-filter_complex",";".join(fc),"-map","[v]","-r","24","-c:v","libx264","-crf","14","-preset","medium","-an",DST],check=True,stdin=subprocess.DEVNULL)
print(subprocess.run(["ffprobe","-v","error","-show_entries","format=duration","-of","csv=p=0",DST],capture_output=True,text=True).stdout.strip(),"expected",round(END*R)/R)
