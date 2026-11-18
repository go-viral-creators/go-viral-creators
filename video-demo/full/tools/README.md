# Tera Intezaar Hai: full music video pipeline

1. `build_base.py`: reads the 22 Flow clips (renamed `c01..c22.mp4`), removes the AI sparkle
   watermark with reverse alpha blending (`unwm.py` + `alpha_*.npy`), trims/speeds each shot
   to its slot in the song, and writes 1080p/24fps segments. The shot list (`SHOTS`) is the edit.
2. `concat.py`: joins the segments with frame-exact `xfade` transitions into `assets/base.mp4`.
   All clip audio is dropped.
3. `gen_full.py`: writes the HyperFrames motion-graphics layer (intro and end titles, synced
   Hindi lyrics, grain, vignette, letterbox, lightning flashes) and `index.html`.
4. Render: `hyperframes render -f 24 -o renders/tera_intezaar_hai_full.mp4` from `video-demo/full`.

`plan.json` is the computed timing of every shot (global start/end, source in-point, speed).
Clip mapping: S7=c16, S8=c21, S1=c15, S2=c12, S3=c22, S4=c18, S5=c14, S6=c11, S9=c17,
S10=c19, S11=c13, S12=c09, S13=c06, S14=c07, S15=c10, S16=c08, S17=c03, S18=c02, S19=c01,
S20=c05, S21=c04 (split to skip a burned-in "Main yahin hoon" subtitle).
