import numpy as np, os
_D=os.path.dirname(os.path.abspath(__file__))
from PIL import Image, ImageFilter
BOX={1080:(1672,826,148,148),720:(1114,550,100,100)}
def prep(a):
    a=a.copy(); a[a<0.06]=0
    core=a>0.25; a[core]=np.median(a[core])
    star=(a>0.04).astype(np.uint8)*255
    m=Image.fromarray(star)
    dil=np.asarray(m.filter(ImageFilter.MaxFilter(7)),np.float32)/255
    ero=np.asarray(m.filter(ImageFilter.MinFilter(5)),np.float32)/255
    ring=np.clip(dil-ero,0,1)
    ring=np.asarray(Image.fromarray((ring*255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(1.5)),np.float32)/255
    ring=np.clip(ring*1.6,0,1)
    return a, ring
P={r:prep(np.load(os.path.join(_D,f"alpha_{r}.npy"))) for r in (1080,720)}
def fix(frame,res):
    x,y,w,h=BOX[res]; a,ring=P[res]; a3=a[...,None]
    reg=frame[y:y+h,x:x+w].astype(np.float32)
    b=np.clip((reg-255*a3)/(1-a3),0,255)
    bl=np.asarray(Image.fromarray(b.astype(np.uint8)).filter(ImageFilter.GaussianBlur(3)),np.float32)
    r=ring[...,None]
    out=b*(1-r)+bl*r
    frame[y:y+h,x:x+w]=np.clip(out,0,255).astype(np.uint8)
    return frame
