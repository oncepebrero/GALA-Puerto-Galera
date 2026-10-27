#!/usr/bin/env python3
"""
GALÁ scene library: paints an ORIGINAL illustration that matches each place's description
(wrecks for wrecks, a cave for caves, a lighthouse for the lighthouse, and so on).
These are artwork, not photographs of the real places. Drop a real photo at
assets/img/places/{slug}.jpg to replace any of them.

Usage: python3 tools/render_places.py [slug ...]     (no args = every place that has no real photo)
Needs: numpy scipy pillow
"""
import os, sys, math, re
import numpy as np
from PIL import Image, ImageDraw, ImageFilter
from scipy import ndimage as ndi
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from render_scenes import fbm, noise1d, smooth, col, post, render_coast, draw_palm

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, 'assets/img/places')
W, H = 1400, 875
RS = np.random.default_rng


def to_pil(a): return Image.fromarray((np.clip(a, 0, 1) * 255).astype(np.uint8))
def to_arr(p): return np.asarray(p.convert('RGB')).astype(np.float32) / 255.0
def grid(): return np.mgrid[0:H, 0:W].astype(np.float32)
def layer(): return Image.new('RGBA', (W, H), (0, 0, 0, 0))
def comp(base, lay, blur=0):
    if blur: lay = lay.filter(ImageFilter.GaussianBlur(blur))
    b = base.convert('RGBA'); b.alpha_composite(lay); return b.convert('RGB')
def lerp(a, b, t): return a * (1 - t) + b * t
def rgb(c): return tuple(int(x) for x in c)


# ---------------------------------------------------------------- underwater
def water(seed, top=(120, 226, 224), mid=(10, 128, 166), bot=(3, 34, 72), rays=0.35, murk=0.0):
    yy, xx = grid(); Y = (yy / H)[..., None]
    t = np.clip(Y * 2.0, 0, 1); b = np.clip((Y - .32) * 1.5, 0, 1)
    img = lerp(lerp(col(*top), col(*mid), t), col(*bot), b)
    sh = fbm(H, W, base=(2, 20), octaves=4, seed=seed)
    sl = ndi.map_coordinates(sh, [yy, (xx + yy * .3) % W], order=1, mode='wrap')
    r = smooth(.5, .86, sl) * (1 - Y[..., 0]) ** 1.3
    img = img + col(200, 255, 240) * (r[..., None] * rays)
    cc = fbm(H, W, base=(14, 24), octaves=5, seed=seed + 1)
    img = img + col(220, 255, 250) * (smooth(.64, .8, cc) * (1 - np.clip(Y[..., 0] * 1.7, 0, 1)) * .18)[..., None]
    if murk: img = lerp(img, col(40, 120, 120), murk)
    return to_pil(img)


def seabed(img, seed, base=.78, relief=.14, c1=(196, 176, 130), c2=(86, 96, 92)):
    yy, xx = grid()
    n = noise1d(W, base=4, octaves=6, persistence=.6, seed=seed)
    fy = H * (base - relief * n)
    m = yy > fy[None, :]
    tex = fbm(H, W, base=(30, 50), octaves=5, seed=seed + 2)
    sandy = lerp(col(*c1) * .82, col(*c1), smooth(.3, .8, tex)[..., None])
    sandy *= (.92 + .14 * tex[..., None])
    sandy = lerp(sandy, col(5, 50, 80), np.clip((yy - fy[None, :]) / (H * .35), 0, 1)[..., None] * .35)
    a = to_arr(img); edge = smooth(0, 3, yy - fy[None, :])
    a = a * (1 - (m * edge)[..., None]) + sandy * (m * edge)[..., None]
    return to_pil(a), fy.astype(int)


PAL = [(238, 120, 86), (246, 176, 72), (178, 92, 160), (96, 176, 128), (222, 96, 126), (252, 218, 110), (90, 150, 210)]


def branching(d, x, y, ln, ang, depth, color, w):
    if depth == 0 or ln < 4: return
    x2 = x + math.cos(ang) * ln; y2 = y + math.sin(ang) * ln
    d.line([x, y, x2, y2], fill=color, width=max(1, int(w)))
    for da in (-.5, .45):
        branching(d, x2, y2, ln * .72, ang + da + (RS(int(x + y)).random() - .5) * .3, depth - 1, color, w * .72)


def table_coral(d, x, y, w, color):
    d.rectangle([x - w * .05, y - w * .35, x + w * .05, y], fill=rgb(np.array(color) * .55) + (255,))
    for k in range(4):
        ww = w * (1 - k * .12); yy_ = y - w * .35 - k * w * .035
        c = rgb(np.array(color) * (.85 + .1 * k)) + (255,)
        d.ellipse([x - ww / 2, yy_ - ww * .09, x + ww / 2, yy_ + ww * .09], fill=c)


def brain(d, x, y, r, color):
    d.ellipse([x - r, y - r * .8, x + r, y + r * .8], fill=color + (255,))
    rr = RS(int(x))
    for k in range(int(r / 3)):
        a = rr.random() * 6.28; rad = rr.random() * r * .8
        px, py = x + math.cos(a) * rad, y + math.sin(a) * rad * .8
        d.arc([px - 6, py - 4, px + 6, py + 4], 0, 200, fill=rgb(np.array(color) * .65) + (255,), width=2)


def fan(d, x, y, h, color):
    for k in range(-6, 7):
        d.line([x, y, x + k * h * .1, y - h], fill=color + (255,), width=2)
    for k in range(1, 7):
        yy_ = y - h * k / 7
        d.line([x - (7 - k) * h * .05 - 6, yy_, x + (7 - k) * h * .05 + 6, yy_], fill=color + (190,), width=1)
    d.line([x, y, x, y + h * .15], fill=rgb(np.array(color) * .6) + (255,), width=4)


def fish(d, x, y, s, color, dirn=1, stripe=None, tailc=None):
    body = [(x + dirn * s * i, y + math.sin(i * math.pi / 3) * s * .38 * (1 if i < 3 else .6) * (-1 if False else 1)) for i in []]
    d.ellipse([x - s * 1.5, y - s * .6, x + s * 1.5, y + s * .6], fill=color + (255,))
    tx = x - dirn * s * 1.4
    d.polygon([(tx, y), (tx - dirn * s * 1.0, y - s * .7), (tx - dirn * s * 1.0, y + s * .7)], fill=(tailc or color) + (255,))
    d.polygon([(x - s * .3, y - s * .55), (x + s * .5, y - s * 1.0), (x + s * .7, y - s * .5)], fill=rgb(np.array(color) * .85) + (255,))
    if stripe:
        for k in (-.3, .3): d.line([x + k * s, y - s * .55, x + k * s, y + s * .55], fill=stripe + (255,), width=max(1, int(s * .22)))
    d.ellipse([x + dirn * s * .9 - 1.5, y - s * .2 - 1.5, x + dirn * s * .9 + 1.5, y - s * .2 + 1.5], fill=(10, 20, 30, 255))


def school(d, cx, cy, spread, n, s, color, seed, dirn=1, stripe=None, tight=1.0):
    r = RS(seed)
    for i in range(n):
        a = r.normal(0, spread); b = r.normal(0, spread * .55 * tight)
        fish(d, cx + a, cy + b, s * (.8 + .4 * r.random()), tuple(int(c * (.85 + .3 * r.random())) for c in color), dirn, stripe)


def shark(d, x, y, s, dirn=1, col_=(70, 92, 112)):
    pts = [(0, 0), (.35, -.16), (.65, -.2), (.9, -.12), (1.0, 0), (.9, .1), (.6, .16), (.3, .14)]
    P = [(x + dirn * (p[0] - .5) * s * 4, y + p[1] * s * 4) for p in pts]
    d.polygon(P, fill=col_ + (255,))
    d.polygon([(x - dirn * s * 2, y), (x - dirn * s * 2.9, y - s * .9), (x - dirn * s * 2.5, y), (x - dirn * s * 2.9, y + s * .6)], fill=col_ + (255,))
    d.polygon([(x - dirn * s * .2, y - s * .8), (x + dirn * s * .5, y - s * 1.6), (x + dirn * s * .7, y - s * .8)], fill=col_ + (255,))
    d.polygon([(x + dirn * s * .3, y + s * .6), (x - dirn * s * .3, y + s * 1.4), (x - dirn * s * .5, y + s * .6)], fill=rgb(np.array(col_) * .8) + (255,))


def turtle(d, x, y, s, dirn=1):
    d.ellipse([x - s * 1.4, y - s * .9, x + s * 1.4, y + s * .9], fill=(88, 104, 56, 255))
    d.ellipse([x - s * .9, y - s * .55, x + s * .9, y + s * .55], fill=(124, 138, 74, 255))
    hx0, hx1 = sorted([x + dirn * s * 1.2, x + dirn * s * 1.9]); d.ellipse([hx0, y - s * .3, hx1, y + s * .3], fill=(110, 124, 70, 255))
    d.polygon([(x - s * .2, y - s * .7), (x + dirn * s * .9, y - s * 2.0), (x + dirn * s * .4, y - s * .6)], fill=(98, 112, 62, 255))
    d.polygon([(x - s * .2, y + s * .7), (x + dirn * s * .9, y + s * 2.0), (x + dirn * s * .4, y + s * .6)], fill=(98, 112, 62, 255))
    d.polygon([(x - dirn * s * 1.2, y), (x - dirn * s * 1.9, y - s * .3), (x - dirn * s * 1.9, y + s * .3)], fill=(98, 112, 62, 255))


def bubbles(img, seed, n=40):
    d = ImageDraw.Draw(img, 'RGBA'); r = RS(seed)
    for _ in range(n):
        x = r.random() * W; y = r.random() * H; s = 2 + r.random() * 6
        d.ellipse([x - s, y - s, x + s, y + s], outline=(255, 255, 255, 120), width=1)
    return img


def reef_corals(base, fy, seed, density=60, kinds=('b', 't', 'm', 'f'), tint=None, scale=1.0):
    lay = layer(); d = ImageDraw.Draw(lay); r = RS(seed)
    for i in range(density):
        x = r.random() * W; y = fy[int(min(W - 1, max(0, x)))] + r.random() * H * .12
        k = r.choice(kinds); c = PAL[r.integers(len(PAL))]
        if tint: c = tuple(int(a * .6 + b * .4) for a, b in zip(c, tint))
        sz = (30 + r.random() * 70) * scale * (.6 + (y / H))
        if k == 'b':
            for j in range(3): branching(d, x + (j - 1) * 8, y, sz * .55, -1.57 + (j - 1) * .5, 4, c + (255,), 4 * scale)
        elif k == 't': table_coral(d, x, y, sz * 1.5, c)
        elif k == 'm': brain(d, x, y, sz * .5, c)
        else: fan(d, x, y, sz * 1.1, c)
    return comp(base, lay, blur=1.2)


def finish(img, bloom=.1, vig=.28, grain=.014, sat=1.08):
    return post(to_arr(img), 3, bloom=bloom, vignette=vig, grain=grain, sat=sat)


# ---------------------------------------------------------------- dive scenes
def sc_reef(seed, beginner=True, clam=False, tint=None, anth=True):
    img = water(seed, rays=.4 if beginner else .3)
    img, fy = seabed(img, seed + 3, base=.8, relief=.1)
    img = reef_corals(img, fy, seed + 5, density=70 if beginner else 50, tint=tint)
    lay = layer(); d = ImageDraw.Draw(lay)
    if clam:
        r = RS(seed)
        for i in range(5):
            x = W * (.16 + .17 * i + r.random() * .05); y = fy[int(x)] + H * (.04 + .06 * r.random()); s = 60 + 50 * r.random()
            d.ellipse([x - s, y - s * .55, x + s, y + s * .35], fill=(70, 80, 70, 255))
            for k in range(26):
                a = k / 26 * math.pi; px = x + math.cos(a) * s * .9; py = y - s * .15 + math.sin(a) * s * -.1 + math.sin(k * 1.3) * 6
                d.ellipse([px - 11, py - 7, px + 11, py + 7], fill=(20 + k * 3 % 90, 120 + k * 5 % 100, 190 - k * 3 % 70, 255))
            d.ellipse([x - s * .85, y - s * .3, x + s * .85, y + s * .08], outline=(30, 150, 190, 255), width=5)
    if anth:
        for j in range(3):
            school(d, W * (.25 + .25 * j), H * (.38 + .06 * j), 90, 38, 8, (255, 150 + 20 * j, 70), seed + j, dirn=1 if j % 2 else -1)
    img = comp(img, lay, blur=.9)
    return finish(bubbles(img, seed))


def sc_clam(seed): return sc_reef(seed, clam=True, anth=False)


def sc_drift(seed, macro=False):
    img = water(seed, top=(90, 200, 220), mid=(8, 112, 160), bot=(4, 40, 86), rays=.2)
    img, fy = seabed(img, seed + 3, base=.84, relief=.06, c1=(206, 190, 150))
    a = to_arr(img); yy, xx = grid()
    st = fbm(H, W, base=(60, 3), octaves=3, seed=seed + 9)
    streak = smooth(.62, .8, st) * .22
    a += col(220, 255, 255) * streak[..., None] * (1 - yy / H)[..., None]
    img = to_pil(a)
    lay = layer(); d = ImageDraw.Draw(lay)
    school(d, W * .3, H * .35, 190, 70, 8, (210, 220, 235), seed, dirn=-1, tight=.8)
    school(d, W * .68, H * .5, 140, 40, 10, (255, 200, 90), seed + 4, dirn=-1, stripe=(40, 70, 130))
    lay = lay.filter(ImageFilter.GaussianBlur(1.2))
    m = lay.filter(ImageFilter.BoxBlur(1))
    k = np.zeros((1, 31), np.float32); k[0, :] = 1 / 31
    mot = np.asarray(lay).astype(np.float32)
    mot = np.stack([ndi.convolve(mot[..., c], k) for c in range(4)], -1)
    lay2 = Image.fromarray(np.clip(mot, 0, 255).astype(np.uint8)); out = comp(img, lay, 0); out = comp(out, lay2, 0)
    if macro:
        d2 = ImageDraw.Draw(out, 'RGBA')
        for i in range(5):
            x = W * (.12 + .2 * i); y = fy[int(x)] + 20
            d2.ellipse([x - 22, y - 8, x + 22, y + 8], fill=PAL[i % 7] + (255,))
            for k2 in range(4): d2.line([x - 10 + k2 * 7, y - 6, x - 10 + k2 * 7 + 3, y - 22], fill=(255, 240, 200, 255), width=2)
    return finish(out)


def sc_wall(seed, holes=False, big=False):
    yy, xx = grid()
    img = water(seed, top=(60, 170, 200), mid=(6, 90, 150), bot=(2, 26, 66), rays=.18)
    a = to_arr(img)
    edge = W * (.5 + .12 * noise1d(H, base=4, octaves=5, seed=seed)[:, None].repeat(W, 1))
    wallm = xx < edge
    tex = fbm(H, W, base=(8, 4), octaves=6, seed=seed + 2)
    rock = lerp(col(70, 84, 84), col(168, 146, 116), tex[..., None]) * (.7 + .5 * (1 - xx / W)[..., None])
    rock = lerp(rock, col(8, 60, 98), np.clip((xx / edge), 0, 1)[..., None] ** 3 * .6)
    a = a * (1 - (wallm * smooth(0, 5, edge - xx))[..., None]) + rock * (wallm * smooth(0, 5, edge - xx))[..., None]
    img = to_pil(a)
    lay = layer(); d = ImageDraw.Draw(lay); r = RS(seed)
    for i in range(34):
        y = r.random() * H; x = edge[int(min(H - 1, y)), 0] - 4 - r.random() * 20
        c = PAL[r.integers(len(PAL))]
        if r.random() < .55: fan(d, x - 20, y, 70 + r.random() * 90, (230, 60 + int(r.random() * 40), 70))
        else: d.ellipse([x - 22, y - 8, x + 4, y + 8], fill=c + (255,))
    if holes:
        cx, cy = W * .26, H * .5
        d.ellipse([cx - 112, cy - 142, cx + 112, cy + 142], fill=(30, 40, 46, 255)); d.ellipse([cx - 92, cy - 120, cx + 92, cy + 120], fill=(30, 150, 205, 255)); d.ellipse([cx - 50, cy - 70, cx + 50, cy + 60], fill=(110, 220, 230, 255))
    school(d, W * .74, H * .4, 120, 55, 7, (255, 170, 70), seed + 2, dirn=-1)
    if big:
        shark(d, W * .76, H * .66, 24, -1, (74, 96, 118))
    img = comp(img, lay, blur=.9)
    return finish(bubbles(img, seed, 20), vig=.34)


def sc_cave(seed, shark_=False):
    yy, xx = grid()
    img = water(seed, top=(60, 190, 220), mid=(10, 120, 170), bot=(4, 50, 90), rays=.5)
    cx, cy = W * .5, H * .48
    rx, ry = W * .24, H * .32
    dd = ((xx - cx) / rx) ** 2 + ((yy - cy) / ry) ** 2
    tex = fbm(H, W, base=(6, 10), octaves=6, seed=seed + 2)
    rock = lerp(col(14, 22, 28), col(70, 76, 74), tex[..., None])
    inner = smooth(1.05, .55, dd)[..., None]
    a = to_arr(img)
    out = rock * (.4 + .5 * (1 - np.clip(dd, 0, 3) / 3)[..., None] * 0)
    cavelight = lerp(col(10, 40, 60), col(100, 230, 230), (smooth(1.0, 0, dd) ** 1.5)[..., None])
    full = lerp(rock * .85, cavelight, inner)
    full = full * (.8 + .4 * tex[..., None])
    img = to_pil(full)
    lay = layer(); d = ImageDraw.Draw(lay); r = RS(seed)
    for i in range(18):
        x = W * (.1 + .8 * r.random()); y = H * (.6 + .38 * r.random()); c = PAL[r.integers(7)]
        d.ellipse([x - 16, y - 7, x + 16, y + 7], fill=tuple(int(q * .5) for q in c) + (255,))
    if shark_:
        shark(d, cx, cy + 40, 26, 1, (30, 44, 56)); shark(d, cx - 160, cy + 110, 18, -1, (26, 40, 52))
    else:
        school(d, cx, cy, 70, 40, 6, (255, 200, 90), seed, dirn=1)
    img = comp(img, lay, blur=1.0)
    return finish(bubbles(img, seed, 25), vig=.4)


def sc_wreck(seed, small=False, double=False):
    yy, xx = grid()
    img = water(seed, top=(70, 190, 210), mid=(8, 100, 150), bot=(2, 32, 70), rays=.3)
    img, fy = seabed(img, seed + 3, base=.84, relief=.05)
    lay = layer(); d = ImageDraw.Draw(lay)
    def hull(cx, cy, s, ang, c):
        ca, sa = math.cos(ang), math.sin(ang)
        def T(p): return (cx + (p[0] * ca - p[1] * sa) * s, cy + (p[0] * sa + p[1] * ca) * s)
        body = [(-3.2, .9), (-3.2, -.9), (1.8, -.9), (3.5, -1.7), (3.3, .3), (1.9, .95)]
        d.polygon([T(p) for p in body], fill=c + (255,))
        d.polygon([T(p) for p in [(-3.2, .5), (-3.2, .95), (1.9, .95), (2.4, .5)]], fill=(34, 40, 44, 255))
        for k in range(-6, 5):
            d.line([T((k * .5, -.9)), T((k * .5, .9))], fill=(30, 24, 20, 255), width=3)
        d.rectangle([T((-1.4, -2.0))[0], T((-1.4, -2.0))[1], T((.4, -.9))[0], T((.4, -.9))[1]], fill=rgb(np.array(c) * .85) + (255,))
        d.rectangle([T((-.9, -2.6))[0], T((-.9, -2.6))[1], T((-.1, -2.0))[0], T((-.1, -2.0))[1]], fill=rgb(np.array(c) * .7) + (255,))
        d.line([T((-3.0, -.9)), T((2.0, -.9))], fill=(60, 40, 30, 255), width=5)
        d.line([T((1.0, -.9)), T((1.1, -3.4))], fill=(50, 40, 36, 255), width=6)
        for k in range(10):
            xk = -3 + k * .65; d.line([T((xk, -.9)), T((xk + .1, -1.5))], fill=(70, 50, 40, 255), width=3)
    hull(W * .5, H * .68, 85 if small else 150, -.09, (96, 68, 52))
    if double: hull(W * .2, H * .82, 55, .18, (84, 72, 62))
    # encrusting corals + anthias
    r = RS(seed)
    for i in range(70):
        x = W * (.2 + .6 * r.random()); y = H * (.56 + .22 * r.random()); c = PAL[r.integers(7)]
        d.ellipse([x - 8, y - 4, x + 8, y + 4], fill=c + (170,))
    school(d, W * .5, H * .4, 120, 60, 7, (255, 150, 80), seed, dirn=1)
    img = comp(img, lay, blur=.9)
    img = reef_corals(img, fy, seed + 8, density=26, scale=.8)
    return finish(bubbles(img, seed, 25), vig=.34)


def sc_pelagic(seed, kind='turtle'):
    img = water(seed, top=(80, 200, 224), mid=(8, 100, 168), bot=(2, 30, 84), rays=.42)
    img, fy = seabed(img, seed + 3, base=.88, relief=.07)
    img = reef_corals(img, fy, seed + 6, density=40, scale=.9)
    lay = layer(); d = ImageDraw.Draw(lay)
    if kind == 'turtle': turtle(d, W * .52, H * .42, 44, 1)
    elif kind == 'sharks': shark(d, W * .5, H * .38, 30, 1); shark(d, W * .26, H * .5, 18, -1)
    elif kind == 'mix':
        turtle(d, W * .66, H * .38, 30, -1); shark(d, W * .25, H * .34, 22, 1)
    school(d, W * .32, H * .56, 150, 80, 7, (220, 232, 245), seed, dirn=-1)
    school(d, W * .74, H * .6, 90, 40, 8, (255, 200, 80), seed + 5, dirn=1, stripe=(60, 90, 150))
    img = comp(img, lay, blur=1.0)
    return finish(bubbles(img, seed, 30))


def sc_atoll(seed):
    yy, xx = grid(); cx, cy = W * .5, H * .5
    d_ = np.sqrt(((xx - cx) / 1.35) ** 2 + (yy - cy) ** 2)
    R = H * .33
    n = fbm(H, W, base=(8, 12), octaves=5, seed=seed)
    ring = np.exp(-((d_ - R) ** 2) / (2 * (H * .065) ** 2))
    lag = smooth(R * .95, R * .25, d_)
    deepwater = col(4, 62, 110); shallow = col(40, 200, 200); sand = col(230, 214, 170); reef = col(40, 120, 90)
    base = lerp(deepwater, col(20, 120, 160), smooth(R * 2.2, R * 1.1, d_)[..., None])
    base = lerp(base, shallow, (smooth(R * 1.25, R * .9, d_) * .9)[..., None])
    base = lerp(base, lerp(shallow, col(16, 150, 190), lag[..., None] * .8), (smooth(R * .96, R * .6, d_))[..., None])
    base = lerp(base, lerp(reef, sand, n[..., None] * .8), (ring * .85)[..., None])
    foam = np.exp(-((d_ - R * 1.12) ** 2) / (2 * 5 ** 2)) * smooth(.4, .7, n)
    base = lerp(base, col(255, 255, 255), foam[..., None] * .7)
    return finish(to_pil(np.clip(base, 0, 1)), bloom=.05, vig=.2)


def sc_hill(seed):
    img = water(seed, rays=.4)
    img, fy = seabed(img, seed + 3, base=.9, relief=.04)
    lay = layer(); d = ImageDraw.Draw(lay); r = RS(seed)
    for i in range(160):
        a = r.random() * math.pi; rad = r.random()
        x = W * .5 + math.cos(a) * W * .26 * rad * 1.3; y = H * .88 - math.sin(a) * H * .5 * (1 - rad * .6)
        c = PAL[r.integers(7)]
        d.ellipse([x - 22, y - 12, x + 22, y + 12], fill=c + (255,))
    img = comp(img, lay, blur=2.0)
    lay = layer(); d = ImageDraw.Draw(lay)
    school(d, W * .5, H * .3, 160, 110, 7, (255, 180, 70), seed, dirn=1)
    school(d, W * .3, H * .5, 80, 40, 6, (230, 235, 245), seed + 2, dirn=-1)
    img = comp(img, lay, blur=.9)
    return finish(bubbles(img, seed, 30))


def sc_sweetlips(seed):
    img = water(seed, rays=.4)
    img, fy = seabed(img, seed + 3, base=.82, relief=.1)
    img = reef_corals(img, fy, seed + 5, density=50)
    lay = layer(); d = ImageDraw.Draw(lay); r = RS(seed)
    for i in range(14):
        x = W * (.1 + .8 * r.random()); y = H * (.3 + .3 * r.random())
        fish(d, x, y, 22 + 8 * r.random(), (250, 220, 90), 1 if r.random() > .4 else -1, stripe=(60, 60, 70), tailc=(250, 220, 90))
    img = comp(img, lay, blur=.8)
    return finish(bubbles(img, seed, 28))


# ---------------------------------------------------------------- beach variations
def draw_banca(d, x, y, s, c=(240, 240, 232), stripe=(30, 130, 190)):
    d.polygon([(x - 90 * s, y - 6 * s), (x + 100 * s, y - 14 * s), (x + 80 * s, y + 20 * s), (x - 70 * s, y + 20 * s)], fill=c + (255,))
    d.polygon([(x - 78 * s, y + 6 * s), (x + 92 * s, y - 2 * s), (x + 80 * s, y + 20 * s), (x - 70 * s, y + 20 * s)], fill=stripe + (255,))
    for ox in (-36, 38):
        d.line([x + ox * s, y - 4 * s, x + (ox - 6) * s, y - 40 * s], fill=(110, 78, 50, 255), width=max(2, int(4 * s)))
    d.line([x - 60 * s, y - 36 * s, x + 70 * s, y - 40 * s], fill=(110, 78, 50, 255), width=max(2, int(4 * s)))
    d.ellipse([x - 70 * s, y - 46 * s, x + 80 * s, y - 34 * s], fill=(236, 226, 190, 255))


def sc_beach_boats(seed):
    base = render_coast(W, H, seed=seed, horizon=.42, sun=(.3, .22), headland=True, palms=True, sparkle=.8, shore_slope=.2, shore_base=.8)
    lay = layer(); d = ImageDraw.Draw(lay)
    draw_banca(d, W * .3, H * .86, 1.35); draw_banca(d, W * .7, H * .8, 1.0, stripe=(230, 90, 70)); draw_banca(d, W * .55, H * .9, 1.6, stripe=(250, 190, 60))
    sh = layer(); sd = ImageDraw.Draw(sh)
    for (x, y, s) in [(W * .3, H * .86, 1.35), (W * .7, H * .8, 1.0), (W * .55, H * .9, 1.6)]:
        sd.ellipse([x - 100 * s, y + 14 * s, x + 110 * s, y + 34 * s], fill=(40, 30, 20, 90))
    img = comp(base, sh, 6); img = comp(img, lay, .7)
    return finish(img, bloom=.12, vig=.2)


def sc_beach_picnic(seed):
    base = render_coast(W, H, seed=seed, horizon=.4, sun=(.7, .22), headland=False, palms=True, sparkle=.8, shore_slope=.1, shore_base=.78)
    lay = layer(); d = ImageDraw.Draw(lay); sh = layer(); sd = ImageDraw.Draw(sh)
    for (x, y, c) in [(W * .24, H * .84, (255, 90, 80)), (W * .62, H * .78, (255, 190, 50)), (W * .84, H * .9, (30, 160, 190))]:
        sd.ellipse([x - 120, y + 10, x + 150, y + 50], fill=(40, 30, 20, 90))
        d.line([x, y - 130, x, y + 10], fill=(90, 70, 50, 255), width=5)
        for k in range(8):
            a0 = math.pi + k * math.pi / 8
            d.pieslice([x - 110, y - 190, x + 110, y - 70], math.degrees(a0), math.degrees(a0 + math.pi / 8), fill=(c if k % 2 == 0 else (255, 250, 240)) + (255,))
    mats = [(W * .38, H * .92, (255, 120, 90)), (W * .74, H * .86, (60, 170, 200))]
    for (x, y, c) in mats:
        d.polygon([(x - 90, y), (x + 90, y), (x + 120, y + 30), (x - 120, y + 30)], fill=c + (255,))
        d.ellipse([x - 20, y + 6, x + 20, y + 20], fill=(250, 240, 220, 255))
    img = comp(base, sh, 6); img = comp(img, lay, .7)
    return finish(img, bloom=.1, vig=.2)


def sc_beach_cove(seed):
    img = render_coast(W, H, seed=seed, horizon=.5, sun=(.2, .2), headland=True, palms=True, sparkle=.5, shore_slope=.3, shore_base=.96)
    return finish(img, bloom=.08, vig=.18)


def sc_beach_quiet(seed):
    img = render_coast(W, H, seed=seed, horizon=.4, sun=(.82, .26), headland=False, palms=True, sparkle=.9, shore_slope=.22, shore_base=.92)
    return finish(img, bloom=.1, vig=.18)


# ---------------------------------------------------------------- culture
def sky_grad(top, hz, sunp=None, seed=1, clouds=True):
    yy, xx = grid(); t = np.clip(yy / H / .62, 0, 1)[..., None]
    a = lerp(col(*top), col(*hz), t ** .9)
    if sunp:
        d2 = ((xx - sunp[0] * W) ** 2 + (yy - sunp[1] * H) ** 2) / (W * .4) ** 2
        a = a + col(255, 220, 160) * (np.exp(-d2 * 8) * .5 + np.exp(-d2 * 90) * .4)[..., None]
    if clouds:
        c = fbm(H, W, base=(3, 7), octaves=6, seed=seed)
        cv = smooth(.52, .75, c) * (1 - smooth(.3, .66, yy / H)) * .7
        a = lerp(a, col(255, 252, 246), cv[..., None])
    return a


def sc_lighthouse(seed):
    yy, xx = grid()
    a = sky_grad((36, 108, 190), (206, 232, 244), (.78, .3), seed)
    hy = H * .5
    sea = lerp(col(150, 220, 214), col(10, 80, 130), np.clip((yy - hy) / (H * .3), 0, 1)[..., None] ** .6)
    sea = sea * (.92 + .16 * fbm(H, W, base=(60, 6), octaves=4, seed=seed + 1)[..., None])
    a = np.where((yy > hy)[..., None], sea, a)
    img = to_pil(a)
    n = noise1d(W, base=5, octaves=6, seed=seed + 3)
    top = H * (.66 - .12 * smooth(.9, .2, np.linspace(0, 1, W)) - .04 * n)
    m = yy > top[None, :]
    tex = fbm(H, W, base=(30, 40), octaves=4, seed=seed + 4)
    grass = lerp(col(34, 100, 44), col(110, 150, 60), tex[..., None])
    rock = lerp(col(74, 70, 66), col(120, 110, 96), tex[..., None])
    land = lerp(grass, rock, smooth(.76, .98, yy / H)[..., None] * .8)
    a = to_arr(img); a = np.where(m[..., None], land, a)
    img = to_pil(a)
    d = ImageDraw.Draw(img, 'RGBA')
    bx, by = W * .34, top[int(W * .34)] + 8
    hgt = H * .5; wb = 64; wt = 38
    for k in range(6):
        y0 = by - hgt * k / 6; y1 = by - hgt * (k + 1) / 6
        w0 = wb - (wb - wt) * k / 6; w1 = wb - (wb - wt) * (k + 1) / 6
        d.polygon([(bx - w0, y0), (bx + w0, y0), (bx + w1, y1), (bx - w1, y1)], fill=(244, 244, 240, 255) if k % 2 == 0 else (206, 52, 48, 255))
    d.polygon([(bx + wb * .45, by), (bx + wb, by), (bx + wt, by - hgt), (bx + wt * .45, by - hgt)], fill=(0, 0, 0, 40))
    d.rectangle([bx - 54, by - hgt - 10, bx + 54, by - hgt], fill=(40, 46, 56, 255))
    d.rectangle([bx - 32, by - hgt - 70, bx + 32, by - hgt - 10], fill=(255, 238, 170, 255), outline=(40, 46, 56, 255), width=4)
    d.polygon([(bx - 44, by - hgt - 70), (bx + 44, by - hgt - 70), (bx, by - hgt - 116)], fill=(206, 52, 48, 255))
    d.rectangle([bx - 14, by - 90, bx + 14, by], fill=(60, 44, 38, 255))
    return finish(img.filter(ImageFilter.GaussianBlur(.5)), bloom=.12, vig=.2)


def sc_church(seed):
    yy, xx = grid()
    a = sky_grad((60, 130, 205), (220, 238, 246), (.2, .2), seed)
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA')
    d.rectangle([0, H * .8, W, H], fill=(190, 180, 160, 255))
    cx = W * .5
    d.rectangle([cx - 330, H * .36, cx + 330, H * .82], fill=(246, 240, 228, 255))
    d.rectangle([cx - 150, H * .12, cx + 150, H * .82], fill=(250, 245, 235, 255))
    d.polygon([(cx - 170, H * .14), (cx + 170, H * .14), (cx, H * .04)], fill=(226, 218, 204, 255))
    d.rectangle([cx - 28, H * .01, cx + 28, H * .05], fill=(226, 218, 204, 255))
    d.rectangle([cx - 6, H * -.02, cx + 6, H * .04], fill=(226, 218, 204, 255))
    for ox in (-95, 95):
        d.rectangle([cx + ox - 40, H * .2, cx + ox + 40, H * .34], fill=(226, 218, 204, 255)); d.pieslice([cx + ox - 40, H * .15, cx + ox + 40, H * .25], 180, 360, fill=(40, 40, 50, 255))
        d.ellipse([cx + ox - 26, H * .22, cx + ox + 26, H * .32], fill=(60, 56, 70, 255)); d.rectangle([cx + ox - 26, H * .27, cx + ox + 26, H * .32], fill=(60, 56, 70, 255))
    d.ellipse([cx - 28, H * .4, cx + 28, H * .48], fill=(80, 100, 150, 255), outline=(200, 190, 170, 255), width=4)
    d.rectangle([cx - 54, H * .56, cx + 54, H * .82], fill=(110, 78, 56, 255)); d.pieslice([cx - 54, H * .5, cx + 54, H * .62], 180, 360, fill=(110, 78, 56, 255))
    for ox in (-230, -170, 170, 230):
        d.rectangle([cx + ox - 22, H * .5, cx + ox + 22, H * .72], fill=(90, 120, 150, 255)); d.pieslice([cx + ox - 22, H * .46, cx + ox + 22, H * .56], 180, 360, fill=(90, 120, 150, 255))
    for k in range(5):
        d.rectangle([cx - 130 - k * 14, H * .82 + k * 10, cx + 130 + k * 14, H * .82 + k * 10 + 10], fill=(214, 204, 184, 255))
    for ox in (-480, 470):
        d.rectangle([cx + ox - 8, H * .44, cx + ox + 8, H * .84], fill=(96, 70, 44, 255))
        for fr in range(8):
            ang = -math.pi + fr * math.pi / 7
            pts = [(cx + ox, H * .44)] + [(cx + ox + math.cos(ang) * L, H * .44 + math.sin(ang) * L * .5 + (L ** 2) * .0016) for L in range(30, 240, 30)]
            d.line(pts, fill=(30, 100, 50, 255), width=6)
    img = img.filter(ImageFilter.GaussianBlur(.7))
    return finish(img, bloom=.1, vig=.24)


def sc_grotto(seed):
    yy, xx = grid()
    tex = fbm(H, W, base=(10, 14), octaves=6, seed=seed)
    rock = lerp(col(38, 42, 40), col(124, 118, 104), tex[..., None])
    dd = np.sqrt(((xx - W * .5) / (W * .22)) ** 2 + ((yy - H * .62) / (H * .36)) ** 2)
    niche = (dd < 1) | ((yy > H * .62) & (np.abs(xx - W * .5) < W * .22))
    inner = lerp(col(20, 22, 30), col(250, 226, 170), (1 - np.clip(dd, 0, 1))[..., None] ** 1.5 * .55)
    a = np.where(niche[..., None], inner, rock)
    a = a * (.8 + .3 * tex[..., None])
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA'); cx = W * .5
    d.polygon([(cx - 34, H * .8), (cx + 34, H * .8), (cx + 44, H * .54), (cx + 22, H * .36), (cx - 22, H * .36), (cx - 44, H * .54)], fill=(248, 248, 252, 255))
    d.ellipse([cx - 20, H * .22, cx + 20, H * .34], fill=(250, 250, 255, 255))
    d.polygon([(cx - 36, H * .3), (cx + 36, H * .3), (cx + 62, H * .62), (cx - 62, H * .62)], fill=(150, 190, 235, 200))
    d.arc([cx - 30, H * .17, cx + 30, H * .25], 180, 360, fill=(255, 235, 150, 255), width=4)
    d.rectangle([cx - 150, H * .8, cx + 150, H * .84], fill=(210, 206, 196, 255))
    r = RS(seed)
    for i in range(34):
        x = cx - 140 + r.random() * 280; y = H * .78 + r.random() * 10; c = [(250, 250, 250), (255, 120, 150), (255, 210, 80), (255, 140, 60)][i % 4]
        d.ellipse([x - 8, y - 8, x + 8, y + 8], fill=c + (255,))
    for ox in (-110, 110):
        d.rectangle([cx + ox - 4, H * .72, cx + ox + 4, H * .8], fill=(240, 236, 220, 255)); d.ellipse([cx + ox - 6, H * .69, cx + ox + 6, H * .73], fill=(255, 200, 80, 255))
    lay = layer(); ld = ImageDraw.Draw(lay)
    for (x0, side) in [(-60, 1), (W + 60, -1)]:
        for fr in range(12):
            ang = (fr / 12) * 1.4 + .1; L = 380 + 80 * r.random()
            pts = [(x0, H * (.1 + .5 * r.random()))]
            pts = [(x0 + side * math.cos(ang + k * .08) * k * 34, H * .2 + math.sin(ang * 2 + k * .3) * 40 + k * 22 * (1 if fr % 2 else .4)) for k in range(12)]
            ld.line(pts, fill=(24, 90, 44, 255), width=12)
    img = comp(img, lay, 5)
    return finish(img, bloom=.2, vig=.4)


def sc_muelle(seed):
    yy, xx = grid()
    a = sky_grad((54, 80, 150), (252, 190, 130), (.72, .5), seed)
    hy = H * .5
    sea = lerp(col(250, 196, 140), col(26, 70, 120), np.clip((yy - hy) / (H * .3), 0, 1)[..., None] ** .7)
    sea = sea * (.9 + .2 * fbm(H, W, base=(70, 6), octaves=4, seed=seed + 1)[..., None])
    gl = np.exp(-((xx - W * .72) ** 2) / (2 * (W * .06) ** 2)) * smooth(.5, .9, fbm(H, W, base=(90, 8), octaves=3, seed=seed + 5)) * (yy > hy) * .8
    sea = sea + col(255, 230, 170) * gl[..., None]
    a = np.where((yy > hy)[..., None], sea, a)
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA')
    d.polygon([(0, hy), (W * .3, hy - 18), (W * .5, hy)], fill=(40, 62, 70, 255))
    d.polygon([(W * .6, hy), (W * .9, hy - 40), (W, hy - 10), (W, hy)], fill=(36, 56, 64, 255))
    # promenade + pier
    d.polygon([(0, H * .84), (W, H * .8), (W, H), (0, H)], fill=(104, 86, 74, 255))
    for k in range(14): d.line([(W * k / 14, H * .84), (W * k / 14 * 1.2 - 100, H)], fill=(80, 64, 54, 255), width=3)
    d.polygon([(W * .38, H * .8), (W * .44, H * .8), (W * .5, hy + 20), (W * .47, hy + 20)], fill=(92, 70, 52, 255))
    for k in range(7):
        t = k / 7; x = W * (.4 + .09 * t); y = H * (.8 - .26 * t)
        d.line([x, y, x, y - 30 + 20 * t], fill=(50, 38, 30, 255), width=4)
    for x in (W * .12, W * .86):
        d.line([x, H * .84, x, H * .56], fill=(30, 36, 44, 255), width=7); d.ellipse([x - 22, H * .5, x + 22, H * .58], fill=(255, 224, 150, 255))
    draw_banca(d, W * .2, hy + 40, .6, stripe=(240, 130, 70)); draw_banca(d, W * .62, hy + 55, .8, stripe=(30, 130, 190)); draw_banca(d, W * .8, hy + 36, .45)
    d.rectangle([W * .02, H * .82, W * .26, H * .87], fill=(150, 90, 60, 255))
    return finish(img.filter(ImageFilter.GaussianBlur(.6)), bloom=.28, vig=.3)


def sc_memorial(seed):
    yy, xx = grid()
    a = sky_grad((40, 70, 130), (255, 186, 120), (.5, .52), seed)
    hy = H * .56
    sea = lerp(col(255, 190, 130), col(18, 56, 100), np.clip((yy - hy) / (H * .25), 0, 1)[..., None] ** .6)
    sea = sea * (.92 + .16 * fbm(H, W, base=(70, 6), octaves=4, seed=seed + 1)[..., None])
    a = np.where((yy > hy)[..., None], sea, a)
    ground = lerp(col(70, 90, 56), col(96, 110, 70), fbm(H, W, base=(20, 30), octaves=4, seed=seed + 2)[..., None])
    a = np.where((yy > H * .76)[..., None], ground, a)
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA'); cx = W * .5
    d.polygon([(cx - 120, H * .84), (cx + 120, H * .84), (cx + 90, H * .74), (cx - 90, H * .74)], fill=(150, 144, 134, 255))
    d.rectangle([cx - 110, H * .84, cx + 110, H * .88], fill=(110, 104, 96, 255))
    d.rectangle([cx - 18, H * .2, cx + 18, H * .74], fill=(24, 24, 28, 255)); d.rectangle([cx - 100, H * .32, cx + 100, H * .38], fill=(24, 24, 28, 255))
    for (ox, s) in [(-300, -1), (300, 1)]:
        x = cx + ox; y = H * .78
        d.polygon([(x - 90 * s, y - 30), (x + 90 * s, y - 40), (x + 90 * s, y - 10), (x - 90 * s, y)], fill=(30, 34, 38, 255))
        d.ellipse([x + 70 * s - 14, y - 46, x + 70 * s + 14, y - 8], fill=(18, 20, 24, 255))
        for wx in (-50, 40): d.ellipse([x + wx - 32, y - 2, x + wx + 32, y + 56], fill=(60, 44, 34, 255)); d.ellipse([x + wx - 14, y + 16, x + wx + 14, y + 44], fill=(110, 90, 70, 255))
    d.ellipse([cx - 160, H * .86, cx + 160, H * .93], fill=(0, 0, 0, 50))
    return finish(img.filter(ImageFilter.GaussianBlur(.6)), bloom=.25, vig=.32)


def sc_museum(seed):
    yy, xx = grid()
    wall = lerp(col(30, 24, 22), col(70, 54, 42), fbm(H, W, base=(4, 4), octaves=4, seed=seed)[..., None])
    spot = np.exp(-(((xx - W * .5) / (W * .42)) ** 2 + ((yy - H * .5) / (H * .5)) ** 2) * 1.2)
    a = wall * (.25 + 1.1 * spot[..., None])
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA')
    d.rectangle([W * .08, H * .66, W * .92, H * .72], fill=(40, 30, 26, 255)); d.rectangle([W * .1, H * .72, W * .9, H], fill=(24, 18, 16, 255))
    d.rectangle([W * .12, H * .22, W * .88, H * .66], fill=(255, 240, 210, 24), outline=(220, 200, 160, 150), width=3)
    glaze = [(208, 220, 214), (60, 120, 160), (190, 150, 90), (230, 226, 210), (70, 140, 120)]
    xs = [W * .2, W * .36, W * .52, W * .68, W * .82]
    for i, x in enumerate(xs):
        c = glaze[i % 5]; h = H * (.2 + .06 * (i % 3)); y = H * .66
        d.ellipse([x - 52, y - h, x + 52, y - h + 40], fill=rgb(np.array(c) * .8) + (255,))
        d.polygon([(x - 28, y - h + 30), (x + 28, y - h + 30), (x + 70, y - h * .45), (x + 40, y), (x - 40, y), (x - 70, y - h * .45)], fill=c + (255,))
        d.polygon([(x - 50, y - h * .5), (x + 50, y - h * .5), (x + 56, y - h * .38), (x - 56, y - h * .38)], fill=rgb(np.array(c) * .6) + (200,))
        d.ellipse([x - 38, y - h + 8, x + 38, y - h + 34], fill=rgb(np.array(c) * 1.05) + (255,))
        d.ellipse([x - 20, y - 8, x + 20, y + 6], fill=(0, 0, 0, 90))
    for x in (W * .26, W * .6):
        d.rectangle([x - 40, H * .1, x + 40, H * .18], fill=(200, 170, 110, 255)); d.rectangle([x - 34, H * .12, x + 34, H * .16], fill=(60, 40, 30, 255))
    return finish(img.filter(ImageFilter.GaussianBlur(.8)), bloom=.3, vig=.45)


def sc_gallery(seed):
    yy, xx = grid()
    wall = lerp(col(240, 234, 222), col(214, 208, 196), fbm(H, W, base=(3, 3), octaves=3, seed=seed)[..., None])
    a = np.where((yy < H * .78)[..., None], wall, lerp(col(130, 96, 70), col(160, 120, 88), fbm(H, W, base=(2, 30), octaves=3, seed=seed)[..., None]))
    a = a * (.84 + .3 * np.exp(-(((xx - W * .5) / (W * .5)) ** 2)))[..., None]
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA'); r = RS(seed)
    frames = [(.06, .16, .22, .5), (.3, .22, .2, .36), (.54, .12, .2, .56), (.78, .2, .17, .42)]
    pals = [[(250, 190, 60), (30, 150, 180), (230, 90, 80)], [(30, 100, 150), (240, 230, 210), (60, 170, 120)], [(240, 120, 90), (250, 210, 100), (60, 60, 120)], [(90, 180, 150), (250, 150, 120), (40, 90, 140)]]
    for (fx, fy, fw, fh), pl in zip(frames, pals):
        x0, y0, x1, y1 = W * fx, H * fy, W * (fx + fw), H * (fy + fh)
        d.rectangle([x0 + 8, y0 + 12, x1 + 8, y1 + 12], fill=(0, 0, 0, 50))
        d.rectangle([x0 - 10, y0 - 10, x1 + 10, y1 + 10], fill=(46, 36, 30, 255)); d.rectangle([x0, y0, x1, y1], fill=(246, 240, 226, 255))
        for k in range(7):
            cx_ = x0 + r.random() * (x1 - x0); cy_ = y0 + r.random() * (y1 - y0); rr = 20 + r.random() * 70
            d.ellipse([cx_ - rr, cy_ - rr, cx_ + rr, cy_ + rr], fill=pl[k % 3] + (200,))
        d.polygon([(x0, y1), (x0, y1 - (y1 - y0) * .3), (x0 + (x1 - x0) * .5, y1 - (y1 - y0) * .1), (x1, y1 - (y1 - y0) * .4), (x1, y1)], fill=(30, 110, 130, 220))
        d.rectangle([x0 + (x1 - x0) * .4, y1 + 22, x0 + (x1 - x0) * .6, y1 + 30], fill=(60, 50, 44, 255))
    d.rectangle([W * .35, H * .74, W * .65, H * .79], fill=(40, 30, 26, 255)); d.rectangle([W * .37, H * .79, W * .39, H * .88], fill=(40, 30, 26, 255)); d.rectangle([W * .61, H * .79, W * .63, H * .88], fill=(40, 30, 26, 255))
    return finish(img.filter(ImageFilter.GaussianBlur(.5)), bloom=.1, vig=.3)


# ---------------------------------------------------------------- nature
def canopy(a, seed, y0, dark=1.0):
    yy, xx = grid()
    tex = fbm(H, W, base=(40, 60), octaves=5, persistence=.6, seed=seed)
    g = lerp(col(16, 56, 28), col(104, 150, 54), tex[..., None] ** 1.5) * dark
    return g


def sc_trail(seed):
    yy, xx = grid()
    a = sky_grad((60, 140, 210), (196, 230, 240), (.8, .2), seed)
    hy = H * .4
    sea = lerp(col(100, 214, 206), col(10, 110, 150), np.clip((yy - hy) / (H * .25), 0, 1)[..., None] ** .6)
    a = np.where((yy > hy)[..., None], sea, a)
    n = noise1d(W, base=5, octaves=6, seed=seed + 2)
    top = H * (.46 - .06 * n)
    far = (yy > top[None, :]) & (yy < H * .52) & (xx > W * .55)
    a = np.where(far[..., None], lerp(canopy(a, seed, 0), haze_col(), .5), a)
    near = yy > H * .5
    ground = canopy(a, seed + 4, 0, 1.0) * (.8 + .5 * (yy / H)[..., None])
    a = np.where(near[..., None], ground, a)
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA')
    # path winding to the viewpoint
    d.polygon([(W * .34, H), (W * .66, H), (W * .535, H * .55), (W * .465, H * .55)], fill=(184, 150, 106, 255))
    d.polygon([(W * .42, H), (W * .58, H), (W * .52, H * .58), (W * .48, H * .58)], fill=(210, 178, 128, 255))
    for k in range(7):
        t = k / 7; y = H * (.6 + .38 * t * t); hw = W * (.02 + .12 * t * t)
        d.line([W * .5 - hw, y, W * .5 + hw, y], fill=(130, 100, 70, 120), width=2)
    lay = layer(); ld = ImageDraw.Draw(lay)
    for (x0, side) in [(-40, 1), (W + 40, -1)]:
        for fr in range(11):
            pts = [(x0 + side * math.cos(.2 + fr * .12 + k * .09) * k * 30, H * .05 + math.sin(fr + k * .4) * 40 + k * 24) for k in range(13)]
            ld.line(pts, fill=(14, 70, 36, 255), width=14)
    img = comp(img, lay, 4)
    return finish(img, bloom=.12, vig=.28)


def haze_col(): return col(150, 190, 180)


def sc_river(seed):
    yy, xx = grid()
    a = canopy(None, seed, 0, .75)
    sky = np.exp(-(((xx - W * .5) / (W * .22)) ** 2 + ((yy - H * .12) / (H * .16)) ** 2)) * .8
    a = lerp(a, col(220, 245, 240), sky[..., None])
    cx = W * (.5 + .1 * np.sin(yy / H * 4)); half = W * (.06 + .3 * (yy / H) ** 1.6)
    riv = np.abs(xx - cx) < half
    fl = fbm(H, W, base=(30, 6), octaves=4, seed=seed + 2)
    rv = lerp(col(40, 150, 140), col(140, 220, 200), (smooth(.4, .8, fl) * (yy / H))[..., None])
    a = np.where(riv[..., None], rv, a)
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA'); r = RS(seed)
    for i in range(40):
        y = H * (.4 + .6 * r.random()); x = W * (.5 + .1 * math.sin(y / H * 4)) + (r.random() - .5) * (W * (.06 + .3 * (y / H) ** 1.6) * 2.4)
        s = 14 + 60 * (y / H) * r.random() + 10
        g = int(120 + r.random() * 60); d.ellipse([x - s, y - s * .6, x + s, y + s * .6], fill=(g, g - 8, g - 16, 255)); d.ellipse([x - s * .8, y - s * .6, x + s * .5, y], fill=(g + 30, g + 22, g + 10, 255))
        d.ellipse([x - s * 1.1, y + s * .35, x + s * 1.1, y + s * .7], fill=(255, 255, 255, 70))
    return finish(img.filter(ImageFilter.GaussianBlur(.6)), bloom=.14, vig=.34)


def sc_pythoncave(seed):
    yy, xx = grid(); cx, cy = W * .5, H * .66
    dd = ((xx - cx) / (W * .26)) ** 2 + ((yy - cy) / (H * .34)) ** 2
    tex = fbm(H, W, base=(8, 10), octaves=6, seed=seed)
    rock = lerp(col(34, 30, 28), col(150, 132, 106), tex[..., None])
    fol = canopy(None, seed + 3, 0, .8)
    top = yy < H * .16
    a = np.where(top[..., None], fol, rock)
    a = a * (.8 + .4 * tex[..., None])
    opening = dd < 1
    inner = lerp(col(2, 3, 6), col(200, 170, 120), (smooth(1, .8, dd) * .35 + (np.exp(-(((xx - cx) / 90) ** 2 + ((yy - H * .88) / 80) ** 2))) * .6)[..., None])
    a = np.where(opening[..., None], inner, a)
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA'); r = RS(seed)
    for i in range(24):
        x = cx + (r.random() - .5) * W * .48; ytop = H * .32 + 40 * math.sin(i) ** 2 + abs(x - cx) * .22
        L = 40 + r.random() * 130 * (1 - abs(x - cx) / (W * .3))
        if L < 14: continue
        d.polygon([(x - 13, ytop), (x + 13, ytop), (x, ytop + L)], fill=(94, 84, 74, 255)); d.polygon([(x + 2, ytop), (x + 13, ytop), (x, ytop + L)], fill=(60, 52, 46, 255))
    for i in range(12):
        x = cx + (r.random() - .5) * W * .38; y = H * (.9 + .06 * r.random()); d.polygon([(x - 12, y), (x + 12, y), (x, y - 40 - 40 * r.random())], fill=(70, 62, 56, 255))
    return finish(img.filter(ImageFilter.GaussianBlur(.7)), bloom=.1, vig=.46)


def sc_hotspring(seed, sea=False):
    yy, xx = grid()
    a = canopy(None, seed, 0, .85)
    if sea:
        a = np.where((yy < H * .34)[..., None], lerp(col(150, 220, 224), col(20, 130, 170), np.clip(yy / (H * .34), 0, 1)[..., None]), a)
    pool = (((xx - W * .5) / (W * .42)) ** 2 + ((yy - H * .72) / (H * .2)) ** 2) < 1
    water_c = lerp(col(150, 238, 226), col(40, 168, 176), (fbm(H, W, base=(10, 20), octaves=4, seed=seed + 1))[..., None])
    a = np.where(pool[..., None], water_c, a)
    rim = (((xx - W * .5) / (W * .46)) ** 2 + ((yy - H * .72) / (H * .24)) ** 2 < 1) & ~pool
    rockc = lerp(col(80, 74, 66), col(160, 150, 134), fbm(H, W, base=(30, 30), octaves=5, seed=seed + 3)[..., None])
    a = np.where(rim[..., None], rockc, a)
    st = fbm(H, W, base=(5, 8), octaves=5, seed=seed + 7)
    steam = smooth(.45, .8, st) * np.exp(-(((xx - W * .5) / (W * .34)) ** 2 + ((yy - H * .5) / (H * .24)) ** 2))
    a = lerp(a, col(246, 250, 250), (steam * .8)[..., None])
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA')
    for i in range(7):
        x = W * (.1 + .13 * i); y = H * .95
        d.polygon([(x, y), (x - 40, y - 150), (x + 10, y - 60), (x + 50, y - 160), (x + 40, y)], fill=(18, 90, 48, 255))
    return finish(img.filter(ImageFilter.GaussianBlur(.7)), bloom=.28, vig=.34)


# ---------------------------------------------------------------- recreation
def sc_zip(seed):
    yy, xx = grid()
    a = sky_grad((54, 130, 210), (200, 232, 244), (.2, .25), seed)
    hy = H * .5
    sea = lerp(col(110, 220, 210), col(8, 100, 150), np.clip((yy - hy) / (H * .2), 0, 1)[..., None] ** .6)
    a = np.where((yy > hy)[..., None], sea, a)
    can = canopy(None, seed + 2, 0, 1)
    n = noise1d(W, base=6, octaves=6, seed=seed + 3)
    top = H * (.62 - .1 * n)
    a = np.where((yy > top[None, :])[..., None], can * (.8 + .4 * (yy / H)[..., None]), a)
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA')
    d.line([W * .06, H * .26, W * .9, H * .5], fill=(40, 44, 50, 255), width=4)
    d.line([W * .06, H * .29, W * .9, H * .53], fill=(40, 44, 50, 130), width=2)
    for (x, y) in [(W * .06, H * .26), (W * .9, H * .5)]:
        d.rectangle([x - 12, y - 10, x + 12, H * .8], fill=(90, 70, 52, 255))
    t = .55; x = W * (.06 + .84 * t); y = H * (.26 + .24 * t)
    d.rectangle([x - 16, y - 6, x + 16, y + 6], fill=(220, 70, 60, 255)); d.line([x, y + 6, x, y + 60], fill=(40, 40, 50, 255), width=3)
    d.ellipse([x - 12, y + 52, x + 12, y + 76], fill=(250, 190, 60, 255)); d.polygon([(x - 18, y + 76), (x + 18, y + 76), (x + 10, y + 120), (x - 10, y + 120)], fill=(30, 140, 190, 255))
    d.line([x - 26, y + 60, x + 26, y + 40], fill=(30, 140, 190, 255), width=6)
    return finish(img.filter(ImageFilter.GaussianBlur(.6)), bloom=.12, vig=.28)


def sc_partyboat(seed):
    yy, xx = grid()
    a = sky_grad((40, 60, 140), (255, 168, 112), (.82, .5), seed)
    hy = H * .46
    sea = lerp(col(255, 176, 130), col(10, 100, 150), np.clip((yy - hy) / (H * .24), 0, 1)[..., None] ** .55)
    sea = sea * (.92 + .16 * fbm(H, W, base=(70, 6), octaves=4, seed=seed + 1)[..., None])
    a = np.where((yy > hy)[..., None], sea, a)
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA'); cx = W * .45; wy = H * .66
    d.polygon([(cx - 380, wy - 30), (cx + 330, wy - 50), (cx + 270, wy + 40), (cx - 330, wy + 40)], fill=(248, 248, 244, 255))
    d.polygon([(cx - 350, wy + 4), (cx + 300, wy - 8), (cx + 270, wy + 40), (cx - 330, wy + 40)], fill=(30, 120, 190, 255))
    d.rectangle([cx - 250, wy - 120, cx + 200, wy - 36], fill=(236, 240, 246, 255)); d.rectangle([cx - 230, wy - 104, cx + 180, wy - 70], fill=(70, 120, 170, 255))
    d.rectangle([cx - 190, wy - 190, cx + 120, wy - 120], fill=(250, 250, 250, 255)); d.rectangle([cx - 190, wy - 196, cx + 130, wy - 186], fill=(230, 80, 90, 255))
    for k in range(16):
        x = cx - 330 + k * 44; y = wy - 150 + 10 * math.sin(k)
        d.ellipse([x - 6, y - 6, x + 6, y + 6], fill=[(255, 220, 90, 255), (255, 120, 150, 255), (120, 230, 255, 255)][k % 3])
    d.line([cx - 330, wy - 150, cx - 190, wy - 190], fill=(255, 255, 255, 120), width=2)
    d.polygon([(cx + 240, wy - 20), (cx + 330, wy + 20), (cx + 360, wy + 60), (cx + 330, wy + 60), (cx + 230, wy + 10)], fill=(250, 80, 120, 255))
    d.rectangle([cx + 120, wy - 150, cx + 128, wy - 60], fill=(150, 150, 150, 255))
    d.ellipse([cx - 420, wy + 38, cx + 380, wy + 70], fill=(255, 255, 255, 110))
    return finish(img.filter(ImageFilter.GaussianBlur(.6)), bloom=.3, vig=.3)


def sc_sports(seed):
    yy, xx = grid()
    a = sky_grad((60, 140, 210), (214, 236, 244), (.8, .2), seed)
    img = to_pil(a); d = ImageDraw.Draw(img, 'RGBA')
    d.rectangle([0, H * .6, W, H], fill=(110, 140, 100, 255))
    d.polygon([(W * .06, H * .82), (W * .94, H * .82), (W * .86, H * .66), (W * .14, H * .66)], fill=(60, 110, 160, 255))
    for k in range(6):
        d.line([W * (.14 + .144 * k), H * .66, W * (.06 + .176 * k), H * .82], fill=(240, 240, 240, 255), width=3)
    d.rectangle([W * .06, H * .2, W * .94, H * .26], fill=(220, 70, 60, 255))
    d.polygon([(W * .02, H * .26), (W * .98, H * .26), (W * .94, H * .32), (W * .06, H * .32)], fill=(170, 50, 46, 255))
    for x in (W * .08, W * .5, W * .92): d.rectangle([x - 12, H * .26, x + 12, H * .84], fill=(70, 80, 90, 255))
    d.rectangle([W * .4, H * .36, W * .6, H * .44], fill=(250, 250, 250, 255))
    d.line([W * .5, H * .44, W * .5, H * .5], fill=(230, 100, 40, 255), width=6); d.ellipse([W * .46, H * .49, W * .54, H * .52], outline=(230, 100, 40, 255), width=5)
    d.rectangle([W * .1, H * .84, W * .9, H * .88], fill=(160, 150, 130, 255))
    return finish(img.filter(ImageFilter.GaussianBlur(.6)), bloom=.1, vig=.26)


def sc_mist(seed):
    from render_scenes import render_mist
    img = render_mist(W, H, seed=seed)
    return img


# ---------------------------------------------------------------- registry
SCENES = {
    # beaches without a supplied photo
    'cely-beach': lambda: sc_beach_quiet(21),
    'calumpang-beach': lambda: sc_beach_boats(31),
    'centro-beach': lambda: sc_beach_picnic(41),
    'palangan-munti-beach': lambda: sc_beach_cove(51),
    # diving
    'coral-garden': lambda: sc_reef(61, True),
    'giant-clam-garden': lambda: sc_clam(62),
    'manila-channel': lambda: sc_drift(63, macro=True),
    'sinandigan-wall': lambda: sc_wall(64, big=True),
    'coral-cove': lambda: sc_reef(65, False, tint=(120, 200, 190)),
    'boulders': lambda: sc_cave(66),
    'dungon-point': lambda: sc_wall(67, big=True),
    'ernies-cave': lambda: sc_cave(68),
    'fish-bowl': lambda: sc_hill(69),
    'hole-in-the-wall': lambda: sc_wall(70, holes=True),
    'japanese-wreck': lambda: sc_wreck(71),
    'kilima-drift': lambda: sc_drift(72),
    'sabang-wrecks': lambda: sc_wreck(73, double=True),
    'shark-cave': lambda: sc_cave(74, shark_=True),
    'st-christopher': lambda: sc_wreck(75, small=True),
    'sweetlips-corner': lambda: sc_sweetlips(76),
    'the-atoll': lambda: sc_atoll(77),
    'the-hill': lambda: sc_hill(78),
    'the-verde-island-passage': lambda: sc_pelagic(79, 'mix'),
    'the-wall': lambda: sc_wall(80),
    'turtle-rock': lambda: sc_pelagic(81, 'turtle'),
    'west-escarceo': lambda: sc_wall(82, big=False),
    'wreck-point': lambda: sc_wreck(83, small=True),
    # culture
    'van-vianen-art-gallery': lambda: sc_gallery(91),
    'muelle-cultural-and-heritage-park': lambda: sc_muelle(92),
    'escarceo-point-lighthouse': lambda: sc_lighthouse(93),
    'birhen-maria-ina-ng-kalikasan-grotto': lambda: sc_grotto(94),
    'immaculate-conception-church': lambda: sc_church(95),
    'black-rice-and-the-cross-with-two-canyons': lambda: sc_memorial(96),
    'excavation-museum': lambda: sc_museum(97),
    # community listings: landscape only, no people or cultural material
    'ambang-mangyan-community': lambda: sc_mist(101),
    'iraya-mangyan-village': lambda: sc_mist(102),
    'baclayan-mangyan-settlement': lambda: sc_mist(103),
    # nature
    'medio-island-trail': lambda: sc_trail(111),
    'permida-paraoayan': lambda: sc_river(112),
    'python-cave': lambda: sc_pythoncave(113),
    'villaflor-hot-spring': lambda: sc_hotspring(114),
    'tabinay-hot-spring': lambda: sc_hotspring(115, sea=True),
    # recreation
    'x-treme-sports-and-adventure': lambda: sc_zip(121),
    'hype-genesis': lambda: sc_partyboat(122),
    'timbugan-cockpit-sports-center': lambda: sc_sports(123),
}

if __name__ == '__main__':
    only = sys.argv[1:]
    for slug, fn in SCENES.items():
        if only and slug not in only: continue
        img = fn()
        img = img.resize((1400, 875), Image.LANCZOS) if img.size != (1400, 875) else img
        img.save(os.path.join(OUT, slug + '.jpg'), quality=80, optimize=True, progressive=True)
        sm = img.copy(); sm.thumbnail((640, 640), Image.LANCZOS); sm.save(os.path.join(OUT, slug + '-sm.jpg'), quality=76, optimize=True, progressive=True)
        print('rendered', slug, flush=True)
