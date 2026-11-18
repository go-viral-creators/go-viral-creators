"""Synthesise light SFX and mix them under the song only where nobody sings."""
import numpy as np, subprocess, wave
SR=48000; END=155.4
rng=np.random.default_rng(7)
def env(n,a,r):
    e=np.ones(n); A=int(a*SR); Rl=int(r*SR); e[:A]=np.linspace(0,1,A); e[-Rl:]*=np.linspace(1,0,Rl)**2; return e
def sparkle(dur=1.6):
    n=int(dur*SR); out=np.zeros(n)
    for k in range(14):
        f=rng.uniform(2200,5200); st=int(rng.uniform(0,dur*0.7)*SR); L=int(0.5*SR); t=np.arange(L)/SR
        tone=np.sin(2*np.pi*f*t)*np.exp(-t*7)*rng.uniform(0.3,1)
        e=min(n,st+L); out[st:e]+=tone[:e-st]
    return out*env(n,0.05,0.4)*0.25
def whoosh(dur=0.9, up=True):
    n=int(dur*SR); x=rng.normal(0,1,n)
    # sweep a one-pole lowpass cutoff
    fc=np.linspace(300,4000,n) if up else np.linspace(4000,300,n)
    y=np.zeros(n); a=0.0
    for i in range(n):
        al=1-np.exp(-2*np.pi*fc[i]/SR); a+=al*(x[i]-a); y[i]=a
    shape=np.sin(np.linspace(0,np.pi,n))**1.5
    return y*shape*0.5
def firework(dur=2.2):
    n=int(dur*SR); t=np.arange(n)/SR
    boom=rng.normal(0,1,n)
    y=np.zeros(n); a=0
    for i in range(n): a+=0.02*(boom[i]-a); y[i]=a
    boom=y*np.exp(-t*5)*6
    crack=np.zeros(n)
    for k in range(60):
        st=int((0.25+rng.exponential(0.5))*SR)
        if st<n-200: crack[st:st+80]+=rng.normal(0,1,80)*np.exp(-np.arange(80)/15)*rng.uniform(0.2,0.7)*np.exp(-st/SR*1.2)
    return (boom+crack)*0.5
track=np.zeros(int(END*SR)+SR)
def put(sig,t,g):
    s=int(t*SR); track[s:s+len(sig)]+=sig*g
put(sparkle(1.8),0.35,0.9)          # intro shimmer (song starts singing at 2.5)
put(whoosh(0.9,True),9.9,0.55)      # proposal spin
put(sparkle(1.0),11.5,0.8)          # into the flashback white flash
put(whoosh(0.7,False),38.7,0.45)    # back from the daydream
put(sparkle(1.2),38.9,0.6)
for t,g in [(147.6,0.55),(148.9,0.45),(150.3,0.5),(151.6,0.4),(152.9,0.35)]: put(firework(2.2),t,g)   # ending fireworks
track=track[:int(END*SR)]
peak=np.abs(track).max(); track=track/peak*0.32   # keep effects well under the song
pcm=(np.clip(track,-1,1)*32767).astype(np.int16)
with wave.open("/tmp/claude-0/sb/sfx.wav","wb") as w: w.setnchannels(1); w.setsampwidth(2); w.setframerate(SR); w.writeframes(pcm.tobytes())
print("sfx ok")
