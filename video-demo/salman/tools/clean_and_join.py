"""Remove the AI watermark from every Flow clip, keep its own audio, and join all clips in order.
Every clip is normalised to 1920x1080 / 24 fps / yuv420p and AAC 48 kHz stereo so the join is a lossless stream copy."""
import subprocess, json, sys, os
import numpy as np
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__))); import unwm
RAW="/tmp/claude-0/sb/raw"; OUT=sys.argv[1] if len(sys.argv)>1 else "/tmp/claude-0/sb/clean"
os.makedirs(OUT,exist_ok=True)
clips=sorted(f for f in os.listdir(RAW) if f.endswith(".mp4"))
def probe(p):
    j=json.loads(subprocess.run(["ffprobe","-v","error","-select_streams","v:0","-show_entries","stream=width,height:format=duration","-of","json",p],capture_output=True,text=True).stdout)
    return j["streams"][0]["width"],j["streams"][0]["height"],float(j["format"]["duration"])
for c in clips:
    out=f"{OUT}/{c}"
    if os.path.exists(out): continue
    src=f"{RAW}/{c}"; W,H,D=probe(src); res=1080 if H==1080 else 720
    dec=subprocess.Popen(["ffmpeg","-v","error","-i",src,"-an","-f","rawvideo","-pix_fmt","rgb24","-"],stdout=subprocess.PIPE,stdin=subprocess.DEVNULL)
    enc=subprocess.Popen(["ffmpeg","-v","error","-y","-f","rawvideo","-pix_fmt","rgb24","-s",f"{W}x{H}","-r","24","-i","-","-i",src,
        "-map","0:v","-map","1:a","-vf","scale=1920:1080:flags=lanczos,format=yuv420p","-c:v","libx264","-crf","14","-preset","medium","-r","24",
        "-af","aresample=48000,aformat=channel_layouts=stereo","-c:a","aac","-b:a","192k","-ar","48000","-shortest","-movflags","+faststart",out],stdin=subprocess.PIPE)
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
    print("done",c,n,"frames",flush=True)
with open(f"{OUT}/list.txt","w") as f:
    for c in clips: f.write(f"file '{OUT}/{c}'\n")
subprocess.run(["ffmpeg","-v","error","-y","-f","concat","-safe","0","-i",f"{OUT}/list.txt","-c","copy","-movflags","+faststart",f"{OUT}/ALL_CLIPS_JOINED.mp4"],check=True,stdin=subprocess.DEVNULL)
print("joined")
