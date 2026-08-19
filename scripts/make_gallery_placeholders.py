"""Generate the placeholder art for the demo before/after gallery.

These are deliberately abstract illustrations, not photographs: publishing
invented "results" as if they were real patient outcomes would be misleading,
and no stock photo can honestly stand in for a clinical result. The pairs
differ enough (texture, tone, clarity) for the comparison slider to read as a
before/after during a demo, and the clinic replaces them with their own
consented photography.

Run: python3 scripts/make_gallery_placeholders.py
"""
import pathlib

OUT = pathlib.Path(__file__).resolve().parent.parent / "public" / "gallery-demo"

# key -> (before bg, after bg, ink) — kept inside the site palette.
CASES = {
    "laser":     ("#efe2e4", "#fbf3f2", "#9a4a63"),
    "acne":      ("#e9e3dc", "#fbf6f1", "#a9743f"),
    "glow":      ("#e6e1e8", "#f7f3f8", "#6d4a71"),
    "filler":    ("#e6dfe1", "#faf2f3", "#9a4a63"),
    "pigment":   ("#e5e6e0", "#f6f7f2", "#4a6e5f"),
    "hair":      ("#e4e0dd", "#f8f5f2", "#7a5a46"),
}

TPL = """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 750" width="600" height="750" role="img" aria-label="{label} illustration">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="0.4" y2="1">
      <stop offset="0%" stop-color="{bg}"/>
      <stop offset="100%" stop-color="{bg2}"/>
    </linearGradient>
    <radialGradient id="glow" cx="0.5" cy="0.42" r="0.55">
      <stop offset="0%" stop-color="#ffffff" stop-opacity="{glow}"/>
      <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
    </radialGradient>
    <filter id="soft"><feGaussianBlur stdDeviation="{blur}"/></filter>
  </defs>

  <rect width="600" height="750" fill="url(#bg)"/>

  <!-- Head-and-shoulders silhouette: enough to read as a portrait plate
       without pretending to be a person. -->
  <g fill="{ink}" fill-opacity="{sil}">
    <circle cx="300" cy="300" r="132"/>
    <path d="M300 440 c -118 0 -196 74 -206 190 h 412 c -10 -116 -88 -190 -206 -190 z"/>
  </g>

  <rect width="600" height="750" fill="url(#glow)"/>

  <!-- Texture marks: dense and scattered on the before plate, almost gone on
       the after plate. -->
  <g fill="{ink}" fill-opacity="{tex}" filter="url(#soft)">
    {marks}
  </g>

  <g font-family="'Jost', system-ui, sans-serif" fill="{ink}">
    <text x="40" y="60" font-size="18" letter-spacing="6" fill-opacity="0.75">{label}</text>
    <text x="40" y="716" font-size="12" letter-spacing="3" fill-opacity="0.45">SAMPLE ILLUSTRATION</text>
  </g>
</svg>
"""

# Fixed offsets rather than random, so re-running produces identical files.
SPOTS = [
    (232, 250, 13), (352, 236, 9), (289, 322, 15), (247, 372, 8), (356, 352, 12),
    (312, 268, 7), (268, 300, 10), (338, 400, 9), (222, 320, 6), (372, 300, 8),
    (300, 214, 6), (262, 420, 11), (348, 452, 7), (240, 208, 5), (330, 200, 6),
]


def plate(kind, bg, bg2, ink):
    before = kind == "before"
    spots = SPOTS if before else SPOTS[:3]
    marks = "\n    ".join(
        f'<ellipse cx="{x}" cy="{y}" rx="{r}" ry="{max(4, r - 2)}"/>' for x, y, r in spots
    )
    return TPL.format(
        label="BEFORE" if before else "AFTER",
        bg=bg, bg2=bg2, ink=ink,
        glow="0.10" if before else "0.55",
        blur="3.5" if before else "6",
        sil="0.22" if before else "0.15",
        tex="0.30" if before else "0.07",
        marks=marks,
    )


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    for key, (before_bg, after_bg, ink) in CASES.items():
        (OUT / f"{key}-before.svg").write_text(plate("before", before_bg, "#ded5d2", ink))
        (OUT / f"{key}-after.svg").write_text(plate("after", after_bg, "#ffffff", ink))
    print(f"wrote {len(CASES) * 2} files to {OUT}")


main()
