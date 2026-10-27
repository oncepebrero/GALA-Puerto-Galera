#!/usr/bin/env python3
"""
GALÁ placeholder image renderer.

Generates ORIGINAL, procedurally rendered coastal scenes used as temporary
placeholders (hero + category images). They are NOT photographs of any real
attraction and must not be presented as such. Replace them with licensed,
verified photography (see README, "Replacing placeholder images").

Usage:  python3 tools/render_scenes.py            (needs numpy, scipy, pillow)
Output: assets/img/hero/ and assets/img/placeholders/
"""
import os, math
import numpy as np
from PIL import Image, ImageDraw, ImageFilter
from scipy import ndimage as ndi

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT_HERO = os.path.join(ROOT, 'assets/img/hero')
OUT_PH = os.path.join(ROOT, 'assets/img/placeholders')
os.makedirs(OUT_HERO, exist_ok=True); os.makedirs(OUT_PH, exist_ok=True)


def fbm(h, w, base=(4, 6), octaves=6, persistence=0.52, seed=1):
    """Fractal value noise in [0,1] built from bicubic-resized random grids."""
    rng = np.random.default_rng(seed)
    out = np.zeros((h, w), np.float32); amp = 1.0; tot = 0.0
    bh, bw = base
    for _ in range(octaves):
        g = rng.random((max(2, int(bh)), max(2, int(bw)))).astype(np.float32)
        im = Image.fromarray(g, mode='F').resize((w, h), Image.BICUBIC)
        out += amp * np.asarray(im); tot += amp
        amp *= persistence; bh *= 2; bw *= 2
    out /= tot
    out = (out - out.min()) / (out.max() - out.min() + 1e-6)
    return out


def noise1d(n, base=5, octaves=6, persistence=0.55, seed=3):
    rng = np.random.default_rng(seed)
    out = np.zeros(n, np.float32); amp = 1; tot = 0; b = base
    for _ in range(octaves):
        g = rng.random(max(2, int(b))).astype(np.float32)
        x = np.linspace(0, len(g) - 1, n)
        out += amp * np.interp(x, np.arange(len(g)), g) if False else amp * ndi.zoom(g, n / len(g), order=3)[:n]
        tot += amp; amp *= persistence; b *= 2
    out /= tot
    return (out - out.min()) / (out.max() - out.min() + 1e-6)


def smooth(a, b, x):
    t = np.clip((x - a) / (b - a + 1e-9), 0, 1)
    return t * t * (3 - 2 * t)


def mix(a, b, t):
    t = t[..., None] if (isinstance(t, np.ndarray) and t.ndim == 2 and a.ndim == 3) else t
    return a * (1 - t) + b * t


def col(r, g, b):
    return np.array([r, g, b], np.float32) / 255.0


# --------------------------------------------------------------------------
# Palm drawing (supersampled PIL layer)
# --------------------------------------------------------------------------
def draw_palm(layer, crown, trunk_pts, fronds, scale=1.0, seed=7, dark=(14, 44, 24), rim=(120, 160, 60)):
    rnd = np.random.default_rng(seed)
    d = ImageDraw.Draw(layer)
    # trunk
    for i in range(len(trunk_pts) - 1):
        x0, y0 = trunk_pts[i]; x1, y1 = trunk_pts[i + 1]
        t = i / max(1, len(trunk_pts) - 1)
        wdt = int((26 - 12 * t) * scale)
        d.line([x0, y0, x1, y1], fill=(58, 44, 32, 255), width=max(2, wdt))
        d.line([x0 + wdt * .25, y0, x1 + wdt * .25, y1], fill=(96, 78, 56, 255), width=max(1, wdt // 3))
    cx, cy = crown
    for (ang, length, droop) in fronds:
        n = 46
        pts = []
        for k in range(n + 1):
            t = k / n
            x = cx + math.cos(ang) * length * t
            y = cy + math.sin(ang) * length * t + droop * length * t * t
            pts.append((x, y))
        d.line(pts, fill=dark + (255,), width=max(2, int(6 * scale)))
        for k in range(3, n):
            t = k / n
            x, y = pts[k]
            dx = pts[k + 1][0] - pts[k][0]; dy = pts[k + 1][1] - pts[k][1]
            L = math.hypot(dx, dy) + 1e-6
            nx, ny = -dy / L, dx / L
            ll = length * 0.20 * (1 - 0.55 * t) * (0.85 + 0.3 * rnd.random()) * (math.sin(t * math.pi) * 0.55 + 0.45)
            for sgn in (-1, 1):
                ex = x + (nx * sgn + dx / L * 0.55) * ll
                ey = y + (ny * sgn + dy / L * 0.55) * ll + ll * 0.35
                shade = rim if (sgn < 0) else dark
                c = tuple(int(shade[j] * (0.7 + 0.5 * rnd.random())) for j in range(3)) + (255,)
                d.line([x, y, ex, ey], fill=c, width=max(1, int(3 * scale)))


# --------------------------------------------------------------------------
# Main coastal renderer
# --------------------------------------------------------------------------
def render_coast(W, H, seed=11, horizon=0.44, sun=(0.78, 0.30), palette='day', mountains=True,
                 headland=True, palms=True, shore_slope=0.34, shore_base=0.99, boats=0, sparkle=1.0):
    rng = np.random.default_rng(seed)
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    X = xx / W; Y = yy / H
    hy = horizon * H
    sunx, suny = sun[0] * W, sun[1] * H

    if palette == 'day':
        sky_top, sky_hz, sun_col = col(34, 96, 176), col(206, 230, 242), col(255, 232, 190)
        cloud_lit, cloud_sh = col(255, 250, 244), col(150, 172, 200)
        haze = col(196, 222, 232); sea_deep, sea_mid, sea_shallow = col(8, 70, 118), col(14, 150, 168), col(150, 232, 214)
        sand_c = col(232, 210, 168); green_dark, green_lit = col(22, 66, 36), col(92, 132, 52)
    else:  # golden hour
        sky_top, sky_hz, sun_col = col(70, 96, 150), col(250, 196, 140), col(255, 214, 140)
        cloud_lit, cloud_sh = col(255, 214, 170), col(112, 110, 150)
        haze = col(246, 196, 150); sea_deep, sea_mid, sea_shallow = col(22, 52, 96), col(52, 120, 140), col(190, 214, 190)
        sand_c = col(236, 196, 146); green_dark, green_lit = col(26, 48, 40), col(120, 120, 60)

    # ---------------- sky
    t = np.clip(yy / max(hy, 1), 0, 1)
    sky = sky_top * (1 - t[..., None] ** 0.8) + sky_hz * (t[..., None] ** 0.8)
    d2 = ((xx - sunx) ** 2 + ((yy - suny) * 1.1) ** 2) / (W * 0.45) ** 2
    sky += sun_col * (np.exp(-d2 * 9) * 0.38 + np.exp(-d2 * 160) * 0.30)[..., None]
    cn = fbm(H, W, base=(3, 7), octaves=7, seed=seed + 1)
    cn2 = fbm(H, W, base=(5, 11), octaves=6, seed=seed + 2)
    dens = cn * 0.8 + cn2 * 0.3 - 0.18 * (1 - t) * 0 + (1 - t) * 0.0
    cover = smooth(0.47, 0.70, dens) * smooth(0.0, 0.10, t + 0.02) * (1 - 0.35 * smooth(0.88, 1.0, t))
    # light from sun side: difference with shifted copy
    sh = np.roll(np.roll(dens, int(-0.012 * W * np.sign(sunx - W / 2)), axis=1), 6, axis=0)
    shade = np.clip((dens - sh) * 9 + 0.42, 0, 1)
    cloud_rgb = cloud_sh * (1 - shade[..., None]) + cloud_lit * shade[..., None]
    cloud_rgb = cloud_rgb * (0.85 + 0.15 * cn2[..., None])
    img = sky * (1 - cover[..., None]) + cloud_rgb * cover[..., None]

    # ---------------- far mountains
    if mountains:
        def ridge(base, amp, seed_, peak=None, width=0.5, rough=0.55):
            n = noise1d(W, base=5, octaves=7, persistence=rough, seed=seed_)
            prof = amp * (0.35 * n + 0.65 * (np.exp(-((np.linspace(0, 1, W) - peak) ** 2) / (2 * width ** 2)) if peak is not None else 0.5))
            return base - prof
        layers = [
            dict(base=hy + 2, amp=0.17 * H, seed=seed + 20, peak=0.30, width=0.20, haze=0.62, c=col(104, 150, 150)),
            dict(base=hy + 2, amp=0.12 * H, seed=seed + 21, peak=0.62, width=0.22, haze=0.42, c=col(66, 116, 96)),
        ]
        for L in layers:
            r = ridge(L['base'], L['amp'], L['seed'], L['peak'], L['width'])
            mask = (yy < L['base']) & (yy > r[None, :].repeat(H, 0)[0:H, :]) if False else (yy > r[None, :]) & (yy <= L['base'])
            tex = fbm(H, W, base=(40, 70), octaves=3, seed=L['seed'] + 5)
            depth = np.clip((yy - r[None, :]) / (L['amp'] + 1), 0, 1)
            g = L['c'] * (0.82 + 0.35 * tex[..., None]) * (1.12 - 0.35 * depth[..., None])
            g = g * (1 - L['haze']) + haze * L['haze']
            # soft edge
            edge = smooth(0, 2.0, yy - r[None, :])
            m = (mask * edge)[..., None]
            img = img * (1 - m) + g * m

    # ---------------- sea
    shore_y = H * (shore_base - shore_slope * (smooth(0.0, 1.0, X) ** 0.85)) + 10 * (fbm(H, W, base=(2, 6), octaves=3, seed=seed + 30)[0] - 0.5) * 6
    shore_y = shore_y.astype(np.float32)
    shore2d = np.broadcast_to(shore_y[None, :] if shore_y.ndim == 1 else shore_y, (H, W))
    # shore_y computed as 2D (X is 2D) — take first row to keep 1D profile
    prof = shore_y[0]
    prof = ndi.gaussian_filter1d(prof + (noise1d(W, base=7, octaves=5, seed=seed + 31) - 0.5) * 0.03 * H, 6)
    shore2d = prof[None, :].repeat(H, 0)
    water = (yy > hy) & (yy < shore2d)
    u = np.clip((shore2d - yy) / np.maximum(shore2d - hy, 1), 0, 1)          # 0 at shore, 1 at horizon
    depth = np.clip(1 - np.exp(-u * 3.6), 0, 1)
    sea = np.where((depth < 0.5)[..., None], 0, 0).astype(np.float32)
    shallow_t = smooth(0.0, 0.5, depth)[..., None]
    deep_t = smooth(0.35, 1.0, depth)[..., None]
    sea = sea_shallow * (1 - shallow_t) + sea_mid * shallow_t
    sea = sea * (1 - deep_t) + sea_deep * deep_t
    # seabed patches (reef / seagrass) in shallows
    patch = fbm(H, W, base=(6, 10), octaves=5, seed=seed + 40)
    pm = smooth(0.50, 0.68, patch) * (1 - smooth(0.25, 0.7, depth))
    sea = sea * (1 - 0.45 * pm[..., None]) + col(20, 108, 98) * 0.45 * pm[..., None]
    # perspective ripples
    z = 1.0 / (np.maximum(yy - hy, 0) + 35.0)
    tile = fbm(1024, 1024, base=(10, 10), octaves=6, persistence=0.6, seed=seed + 50)
    cx_ = (xx * z * 520.0) % 1024; cy_ = (z * 90000.0) % 1024
    rip = ndi.map_coordinates(tile, [cy_, cx_], order=1, mode='wrap')
    swell_tile = fbm(512, 2048, base=(30, 6), octaves=4, persistence=0.55, seed=seed + 52)
    swell = ndi.map_coordinates(swell_tile, [(z * 30000.0) % 512, (xx * z * 160.0) % 2048], order=1, mode='wrap')
    sea *= (0.86 + 0.34 * (rip - 0.5) * (0.5 + 0.9 * u) + 0.30 * (swell - 0.5) * (0.35 + u))[..., None]
    # fresnel / sky reflection toward horizon
    refl = (u ** 2.6) * 0.62
    skyrefl = sky_hz * 0.85 + sky_top * 0.15
    sea = sea * (1 - refl[..., None]) + skyrefl * refl[..., None]
    # sun glitter
    gl_tile = fbm(1024, 1024, base=(60, 60), octaves=3, seed=seed + 51)
    gl = ndi.map_coordinates(gl_tile, [(z * 210000.0) % 1024, (xx * z * 1300.0) % 1024], order=1, mode='wrap')
    spread = (0.045 + 0.30 * (1 - u) ** 1.2) * W
    gmask = np.exp(-((xx - sunx) ** 2) / (2 * spread ** 2)) * smooth(0.04, 0.5, u) * (1 - smooth(0.95, 1.0, u))
    glit = smooth(0.80, 0.93, gl) * gmask * sparkle
    sea += sun_col * glit[..., None] * 1.7
    sea += sun_col * (np.exp(-((xx - sunx) ** 2) / (2 * (0.03 * W) ** 2)) * smooth(0.82, 1.0, u) * 0.35)[..., None]

    # horizon sea fades to haze
    sea = sea * (1 - 0.35 * smooth(0.9, 1.0, u)[..., None]) + haze * 0.35 * smooth(0.9, 1.0, u)[..., None]
    img = np.where(water[..., None], sea, img)

    # ---------------- near headland (right side)
    if headland:
        rx = np.linspace(0, 1, W)
        hn = noise1d(W, base=6, octaves=7, persistence=0.58, seed=seed + 60)
        top = H * (0.60 - 0.30 * smooth(0.52, 1.02, rx) ** 0.9) - 0.05 * H * hn * smooth(0.55, 1.0, rx)
        top = np.where(rx < 0.52, hy + H * 0.2, top)
        base_y = hy + (H - hy) * 0.20 + 0.01 * H * np.sin(rx * 9)
        hm = (yy > top[None, :]) & (yy < base_y[None, :]) & (X > 0.54)
        tex = fbm(H, W, base=(60, 100), octaves=4, persistence=0.6, seed=seed + 61)
        tex2 = fbm(H, W, base=(14, 24), octaves=4, seed=seed + 62)
        dd = np.clip((yy - top[None, :]) / (0.28 * H), 0, 1)
        g = green_dark * (0.65 + 0.9 * tex[..., None]) * (0.9 + 0.3 * tex2[..., None])
        g = g * (1 - 0.35 * (1 - dd[..., None])) + green_lit * 0.55 * (smooth(0.55, 0.9, tex2) * (1 - dd))[..., None]
        streak = fbm(H, W, base=(10, 200), octaves=4, persistence=0.6, seed=seed + 63)
        g = g * (0.80 + 0.45 * streak[..., None])
        g = g * 0.64 + haze * 0.36
        edge = smooth(0, 2.0, yy - top[None, :]) * smooth(0, 2.0, base_y[None, :] - yy)
        m = (hm * edge)[..., None]
        img = img * (1 - m) + g * m
        # little white rocks / surf at headland base
        surf = np.exp(-((yy - base_y[None, :]) ** 2) / (2 * 3.0 ** 2)) * smooth(0.54, 0.6, X) * (0.4 + 0.6 * rip)
        img += (surf * 0.5)[..., None] * col(255, 255, 255) * hm.any()

    # ---------------- foam, wet sand, sand
    dy = shore2d - yy
    ed = fbm(H, W, base=(10, 30), octaves=5, seed=seed + 70)
    lace = dy + (ed - 0.5) * 34
    foam1 = np.exp(-((lace - 6) ** 2) / (2 * 6.5 ** 2))
    foam2 = np.exp(-((lace - 40) ** 2) / (2 * 9 ** 2)) * smooth(0.45, 0.75, ed) * 0.55
    foam3 = np.exp(-((lace - 90) ** 2) / (2 * 14 ** 2)) * smooth(0.55, 0.85, fbm(H, W, base=(8, 22), octaves=4, seed=seed + 71)) * 0.35
    foam = np.clip(foam1 * 0.95 + foam2 + foam3, 0, 1) * (dy > -6)
    img = img * (1 - foam[..., None] * 0.9) + col(250, 252, 252) * foam[..., None] * 0.9

    sand = (yy >= shore2d)
    sg = fbm(H, W, base=(8, 14), octaves=5, seed=seed + 80)
    fine = fbm(H, W, base=(500, 800), octaves=1, seed=seed + 81)
    sc = sand_c * (0.90 + 0.18 * sg[..., None]) * (0.94 + 0.12 * fine[..., None])
    wet = np.exp(-np.maximum(yy - shore2d, 0) / 55.0)
    wetcol = sc * 0.62 + skyrefl * 0.16
    sc = sc * (1 - wet[..., None] * 0.9) + wetcol * wet[..., None] * 0.9
    # soft sand ripples / tide marks
    tide = np.exp(-((yy - shore2d - 150 - 20 * ed) ** 2) / (2 * 3.5 ** 2)) * 0.10
    sc -= tide[..., None] * 0.0
    sc = sc * (1 - 0.18 * smooth(0.8, 1.0, Y)[..., None]) + 0.0
    img = np.where(sand[..., None], sc, img)
    # edge antialias between sand & water handled by foam

    # ---------------- boats on sea (small outriggers) for 'sea' scenes
    pil = Image.fromarray((np.clip(img, 0, 1) * 255).astype(np.uint8))
    if boats:
        bd = ImageDraw.Draw(pil, 'RGBA')
        for i in range(boats):
            bx = int(W * (0.18 + 0.62 * rng.random())); by = int(hy + (H * 0.12) * (0.4 + rng.random()))
            s = 0.6 + (by - hy) / (H * 0.2)
            bd.polygon([(bx - 30 * s, by), (bx + 34 * s, by), (bx + 22 * s, by + 7 * s), (bx - 22 * s, by + 7 * s)], fill=(236, 236, 230, 255))
            bd.line([bx - 40 * s, by + 10 * s, bx + 42 * s, by + 10 * s], fill=(70, 50, 36, 255), width=max(1, int(2 * s)))
            bd.line([bx + 2 * s, by, bx + 2 * s, by - 20 * s], fill=(90, 70, 50, 255), width=max(1, int(1.5 * s)))
            bd.polygon([(bx + 3 * s, by - 20 * s), (bx + 26 * s, by - 2 * s), (bx + 3 * s, by - 2 * s)], fill=(250, 244, 230, 255))

    # ---------------- palms (foreground framing)
    if palms:
        # defocused foliage framing the top-left corner (photographic depth-of-field cue)
        SS = 1
        layer = Image.new('RGBA', (W, H), (0, 0, 0, 0))
        crown = (int(0.02 * W), int(-0.03 * H))
        fr = [(math.radians(a), L * W, d) for a, L, d in
              [(10, .30, .30), (30, .32, .45), (55, .27, .55), (80, .24, .5), (110, .20, .35), (-5, .26, .15), (22, .34, .25), (68, .22, .4)]]
        draw_palm(layer, crown, [crown, crown], fr, scale=2.6, seed=seed, dark=(10, 36, 20), rim=(70, 110, 40))
        layer = layer.filter(ImageFilter.GaussianBlur(13))
        a_ = np.asarray(layer).astype(np.float32)
        a_[..., 3] *= 0.92
        layer = Image.fromarray(a_.astype(np.uint8))
        pil = pil.convert('RGBA'); pil.alpha_composite(layer); pil = pil.convert('RGB')

    arr = np.asarray(pil).astype(np.float32) / 255.0
    return post(arr, seed)


def post(arr, seed=1, bloom=0.28, vignette=0.22, grain=0.014, sat=1.08):
    H, W, _ = arr.shape
    # bloom
    hi = np.clip(arr - 0.78, 0, 1)
    bl = np.stack([ndi.gaussian_filter(hi[..., c], W * 0.012) for c in range(3)], -1)
    arr = arr + bloom * bl * 2.2
    # soft lens: slight overall softness
    soft = np.stack([ndi.gaussian_filter(arr[..., c], 0.8) for c in range(3)], -1)
    arr = arr * 0.55 + soft * 0.45
    # tone curve + saturation + gentle teal shadows
    arr = np.clip(arr, 0, 1)
    arr = arr ** 0.96
    arr = arr * 0.92 + 0.08 * arr * arr * (3 - 2 * arr) * 1.0
    g = arr.mean(-1, keepdims=True)
    arr = g + (arr - g) * sat
    arr[..., 2] += 0.012 * (1 - g[..., 0]); arr[..., 0] -= 0.006 * (1 - g[..., 0])
    # vignette
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    r = np.sqrt(((xx - W / 2) / (W / 2)) ** 2 + ((yy - H / 2) / (H / 2)) ** 2)
    arr *= (1 - vignette * smooth(0.55, 1.45, r))[..., None]
    # film grain (luma-weighted)
    rng = np.random.default_rng(seed + 999)
    n = rng.normal(0, grain, (H, W, 1)).astype(np.float32) + rng.normal(0, grain * 0.5, (H, W, 3)).astype(np.float32)
    arr += n * (0.6 + 0.8 * (1 - np.abs(arr.mean(-1, keepdims=True) - 0.5) * 2))
    return Image.fromarray((np.clip(arr, 0, 1) * 255).astype(np.uint8))


# --------------------------------------------------------------------------
# Underwater placeholder
# --------------------------------------------------------------------------
def render_reef(W, H, seed=5):
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    X = xx / W; Y = yy / H
    top, mid, bot = col(110, 220, 220), col(14, 130, 160), col(4, 36, 74)
    t = Y[..., None]
    img = top * (1 - np.clip(t * 2.2, 0, 1)) + mid * np.clip(t * 2.2, 0, 1)
    img = img * (1 - np.clip((t - .35) * 1.6, 0, 1)) + bot * np.clip((t - .35) * 1.6, 0, 1)
    # light shafts
    sh = fbm(H, W, base=(2, 22), octaves=4, seed=seed)
    slant = ndi.map_coordinates(sh, [yy, (xx + yy * 0.35) % W], order=1, mode='wrap')
    rays = smooth(0.50, 0.85, slant) * (1 - Y) ** 1.4
    img += col(190, 255, 240) * (rays[..., None] * 0.35)
    # caustic shimmer near the top
    c = fbm(H, W, base=(16, 28), octaves=5, seed=seed + 1)
    caus = smooth(0.62, 0.8, c) * (1 - np.clip(Y * 1.6, 0, 1))
    img += col(220, 255, 250) * (caus[..., None] * 0.22)
    # sea floor + coral heads
    floor = noise1d(W, base=4, octaves=6, persistence=0.6, seed=seed + 2)
    fy = H * (0.78 - 0.16 * floor)
    fm = yy > fy[None, :]
    tex = fbm(H, W, base=(30, 50), octaves=5, seed=seed + 3)
    fcol = col(70, 98, 96) * (0.7 + 0.6 * tex[..., None])
    img = np.where(fm[..., None], fcol * (1 - 0.35 * smooth(0.0, 0.25, (yy - fy[None, :]) / H)[..., None]), img)
    # corals: blobs
    rng = np.random.default_rng(seed + 4)
    pil = Image.fromarray((np.clip(img, 0, 1) * 255).astype(np.uint8))
    d = ImageDraw.Draw(pil, 'RGBA')
    palette = [(230, 120, 90), (240, 170, 70), (170, 90, 150), (90, 170, 120), (210, 90, 120)]
    for i in range(70):
        cx = rng.random() * W; base = fy[int(min(W - 1, cx))] + rng.random() * 0.1 * H
        r = (0.015 + 0.04 * rng.random()) * W
        c0 = palette[rng.integers(len(palette))]
        for k in range(7):
            ox = (rng.random() - .5) * r * 1.4; oy = -rng.random() * r * 1.2
            rr = r * (0.35 + 0.5 * rng.random())
            a = int(120 + 100 * rng.random())
            d.ellipse([cx + ox - rr, base + oy - rr, cx + ox + rr, base + oy + rr], fill=c0 + (a,))
    pil = pil.filter(ImageFilter.GaussianBlur(2.2))
    # tiny fish
    d = ImageDraw.Draw(pil, 'RGBA')
    for i in range(34):
        fx = rng.random() * W * .9 + W * .05; fy2 = H * (0.28 + 0.32 * rng.random()); s = 5 + 10 * rng.random()
        d.polygon([(fx, fy2), (fx + s * 2.2, fy2 - s * .7), (fx + s * 2.2, fy2 + s * .7)], fill=(255, 214, 120, 190))
        d.polygon([(fx + s * 2.0, fy2), (fx + s * 3.0, fy2 - s * .8), (fx + s * 3.0, fy2 + s * .8)], fill=(255, 214, 120, 150))
    pil = pil.filter(ImageFilter.GaussianBlur(1.0))
    return post(np.asarray(pil).astype(np.float32) / 255.0, seed, bloom=0.18, vignette=0.30, grain=0.016)


# --------------------------------------------------------------------------
# Misty forested hills (nature placeholder)
# --------------------------------------------------------------------------
def render_mist(W, H, seed=9):
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    Y = yy / H
    sky = col(214, 232, 232) * (1 - Y[..., None]) + col(244, 246, 238) * Y[..., None]
    img = sky.copy()
    n_layers = 6
    for i in range(n_layers):
        k = i / (n_layers - 1)
        r = noise1d(W, base=4 + i, octaves=7, persistence=0.58, seed=seed + i * 3)
        base = H * (0.34 + 0.11 * i)
        prof = base - H * (0.22 - 0.02 * i) * r
        mask = yy > prof[None, :]
        tex = fbm(H, W, base=(30 + 12 * i, 50 + 20 * i), octaves=4, persistence=0.6, seed=seed + 40 + i)
        c = col(120, 160, 140) * (1 - k) + col(18, 62, 34) * k
        g = c * (0.72 + 0.55 * tex[..., None])
        mist = 0.78 * (1 - k) ** 1.2
        g = g * (1 - mist) + col(226, 238, 232) * mist
        img = np.where(mask[..., None], g, img)
        # mist band between layers
        band = np.exp(-((yy - (base + 0.03 * H)) ** 2) / (2 * (0.035 * H) ** 2)) * (0.30 * (1 - k * .6))
        img = img * (1 - band[..., None]) + col(236, 244, 240) * band[..., None]
    return post(img.astype(np.float32), seed, bloom=0.12, vignette=0.2, grain=0.012, sat=1.05)


def save(img, path, w=None, q=82):
    if w and img.width != w:
        img = img.resize((w, int(img.height * w / img.width)), Image.LANCZOS)
    img.save(path, quality=q, optimize=True, progressive=True)
    print('wrote', path, img.size, os.path.getsize(path) // 1024, 'KB')


if __name__ == '__main__':
    hero = render_coast(2560, 1440, seed=11)
    save(hero, os.path.join(OUT_HERO, 'puerto-galera-hero.jpg'), 2400, 80)
    save(hero, os.path.join(OUT_HERO, 'puerto-galera-hero-1200.jpg'), 1200, 76)
    save(render_coast(1600, 1000, seed=21, horizon=0.30, sun=(0.3, 0.2), headland=False, palms=False, mountains=False, shore_slope=0.16, shore_base=0.86, sparkle=.7),
         os.path.join(OUT_PH, 'placeholder-beach.jpg'), 1400, 78)
    save(render_reef(1600, 1000), os.path.join(OUT_PH, 'placeholder-diving.jpg'), 1400, 78)
    save(render_mist(1600, 1000), os.path.join(OUT_PH, 'placeholder-nature.jpg'), 1400, 78)
    save(render_coast(1600, 1000, seed=31, horizon=0.5, palette='golden', sun=(0.5, 0.42), headland=True, palms=False, mountains=True, shore_slope=0.0, shore_base=1.4, boats=3),
         os.path.join(OUT_PH, 'placeholder-culture.jpg'), 1400, 78)
    save(render_coast(1600, 1000, seed=41, horizon=0.46, sun=(0.2, 0.25), headland=False, palms=False, mountains=True, shore_slope=0.0, shore_base=1.4, boats=5),
         os.path.join(OUT_PH, 'placeholder-recreation.jpg'), 1400, 78)
