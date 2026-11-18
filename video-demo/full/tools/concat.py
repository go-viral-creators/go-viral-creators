import json, subprocess
OUT="/tmp/claude-0/seg"; plan=json.load(open(f"{OUT}/plan.json"))
R=24
plan[0]["gsf"]=0; plan[0]["df"]=0
for k in range(1,len(plan)):
    p=plan[k]; df=max(1,round(p["d_in"]*R)); bf=round((p["gs"]+p["d_in"]/2)*R)
    p["df"]=df; p["gsf"]=bf-df//2; plan[k-1]["gef"]=p["gsf"]+df
plan[-1]["gef"]=round(186.744*R)
inputs=[]; fc=[]
for k,p in enumerate(plan):
    inputs+=["-i",f"{OUT}/seg{k:02d}.mp4"]
    N=p["gef"]-p["gsf"]
    fc.append(f"[{k}:v]trim=end_frame={N},tpad=stop_mode=clone:stop=6,trim=end_frame={N},setpts=PTS-STARTPTS,fps=24,settb=1/24[s{k}]")
prev="s0"
for k in range(1,len(plan)):
    p=plan[k]
    # sanity: transition must fit in overlap
    assert plan[k-1]["gef"]-p["gsf"]==p["df"], (k, plan[k-1]["gef"]-p["gsf"], p["df"])
    trans={"cut":"fade","fade":"fade","fadewhite":"fadewhite","fadeblack":"fadeblack"}[p["t_in"]]
    fc.append(f"[{prev}][s{k}]xfade=transition={trans}:duration={p['df']/R:.6f}:offset={p['gsf']/R:.6f}[x{k}]")
    prev=f"x{k}"
fc.append(f"[{prev}]format=yuv420p[v]")
cmd=["ffmpeg","-v","error","-y",*inputs,"-filter_complex",";".join(fc),"-map","[v]","-r","24","-c:v","libx264","-crf","14","-preset","medium","-an","/home/user/go-viral-creators/video-demo/full/assets/base.mp4"]
subprocess.run(cmd,check=True,stdin=subprocess.DEVNULL)
print(subprocess.run(["ffprobe","-v","error","-show_entries","format=duration","-of","csv=p=0","/home/user/go-viral-creators/video-demo/full/assets/base.mp4"],capture_output=True,text=True).stdout, "expected", plan[-1]["gef"]/R)
