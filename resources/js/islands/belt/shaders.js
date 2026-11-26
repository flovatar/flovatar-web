/**
 * GLSL of the /islands belt scene (resources/js/islands/belt/scene.js). Plain strings (no loader), three r134
 * ShaderMaterial conventions (position/uv/normal attributes and the camera matrices are declared by three).
 * Colours are written straight in display space (the renderer does no tone mapping / encoding).
 */

const NOISE = /* glsl */`
float hash31(vec3 p) {
    p = fract(p * 0.3183099 + 0.1);
    p *= 17.0;
    return fract(p.x * p.y * p.z * (p.x + p.y + p.z));
}
float vnoise(vec3 x) {
    vec3 i = floor(x);
    vec3 f = fract(x);
    f = f * f * (3.0 - 2.0 * f);
    return mix(mix(mix(hash31(i), hash31(i + vec3(1.0, 0.0, 0.0)), f.x),
                   mix(hash31(i + vec3(0.0, 1.0, 0.0)), hash31(i + vec3(1.0, 1.0, 0.0)), f.x), f.y),
               mix(mix(hash31(i + vec3(0.0, 0.0, 1.0)), hash31(i + vec3(1.0, 0.0, 1.0)), f.x),
                   mix(hash31(i + vec3(0.0, 1.0, 1.0)), hash31(i + vec3(1.0, 1.0, 1.0)), f.x), f.y), f.z);
}
float fbm(vec3 p) {
    float a = 0.5;
    float s = 0.0;
    for (int i = 0; i < OCTAVES; i++) {
        s += a * vnoise(p);
        p = p * 2.03 + vec3(1.7, 9.2, 3.1);
        a *= 0.5;
    }
    return s;
}
`;

// ---------------------------------------------------------------------------------------------------- planet

export const PLANET_VERT = /* glsl */`
varying vec3 vObj;
varying vec3 vNormalW;
varying vec3 vWorld;
void main() {
    vObj = position;
    vNormalW = normalize(mat3(modelMatrix) * normal);
    vec4 w = modelMatrix * vec4(position, 1.0);
    vWorld = w.xyz;
    gl_Position = projectionMatrix * viewMatrix * w;
}
`;

/**
 * Cloud deck: domain-warped value noise stretched along the latitude (bands), drifting in the noise domain so it never
 * winds up. Storm cells (uStorm[i] = object-space direction + intensity) light the clouds from inside: a wide soft
 * glow shaped by the cloud structure plus a small hot core. Nothing here changes the whole disc at once.
 */
export const PLANET_FRAG = /* glsl */`
precision highp float;
#define STORMS 8
uniform float uTime;
uniform vec3 uSun;
uniform vec3 uSunObj; // sun direction in the planet's object space
uniform vec4 uStorm[STORMS];
uniform vec3 uCity; // object-space direction of Nova City, under the clouds
varying vec3 vObj;
varying vec3 vNormalW;
varying vec3 vWorld;
${NOISE}
// Nova City is never shown: only window-sized warm dots inside a ragged footprint, and the caller lets them through
// only where the cloud deck is thin AND a lightning flash is lighting that spot. Dots fade out before they alias.
float cityDots(vec3 p, float k) {
    vec3 g = p * k;
    vec3 f = fract(g) - 0.5;
    float h = hash31(floor(g));
    float px = max(fwidth(g.x), max(fwidth(g.y), fwidth(g.z)));
    return smoothstep(0.30, 0.07, length(f)) * step(0.60, h) * (0.55 + 0.45 * h) * (1.0 - smoothstep(0.40, 1.0, px));
}
float cityLights(vec3 p) {
    float district = smoothstep(0.55, 0.90, dot(p, uCity) + (vnoise(p * 6.0) - 0.5) * 0.34);
    if (district <= 0.001) { return 0.0; }
    float blocks = 0.35 + 0.65 * smoothstep(0.35, 0.65, vnoise(p * 23.0));
    return district * blocks * (cityDots(p, 64.0) + cityDots(p, 150.0) * 0.8);
}
void main() {
    vec3 p = normalize(vObj);
    vec3 q = vec3(p.x, p.y * 2.4, p.z);
    float t = uTime;
    float w = fbm(q * 1.5 + vec3(0.0, 0.0, t * 0.021));
    vec3 qq = q * 2.6 + vec3(w * 2.2, w * 0.7, -w * 1.6) + vec3(t * 0.012, 0.0, t * 0.004);
    float n = fbm(qq);
    float bands = 0.5 + 0.5 * sin(p.y * 11.0 + w * 5.0 + n * 2.0);
    float c = clamp(n * 0.82 + bands * 0.24 - 0.06, 0.0, 1.0);

    vec3 deep = vec3(0.070, 0.035, 0.200);
    vec3 indigo = vec3(0.235, 0.105, 0.540);
    vec3 violet = vec3(0.455, 0.290, 0.840);
    vec3 tops = vec3(0.760, 0.740, 1.000);
    vec3 smogC = vec3(0.150, 0.640, 0.600);
    vec3 pink = vec3(0.900, 0.200, 0.740);
    vec3 col = mix(deep, indigo, smoothstep(0.22, 0.50, c));
    col = mix(col, violet, smoothstep(0.44, 0.68, c));
    col = mix(col, tops, smoothstep(0.62, 0.90, c) * 0.85);
    // polluted veins between the cloud tops, and thin pink jet streams
    float smog = smoothstep(0.50, 0.78, fbm(q * 1.05 + vec3(7.3, 1.1, t * 0.010)));
    col = mix(col, smogC, smog * 0.30 * (1.0 - smoothstep(0.45, 0.80, c)));
    col = mix(col, pink, smoothstep(0.90, 1.0, bands) * 0.16 * smoothstep(0.30, 0.60, c));

    vec3 N = normalize(vNormalW);
    vec3 V = normalize(cameraPosition - vWorld);
    float ndl = dot(N, normalize(uSun));
    float light = smoothstep(-0.30, 0.65, ndl);
#ifdef RELIEF
    // cloud relief: the deck is a bit darker where the next cloud towards the sun is higher
    float n2 = fbm(qq + vec3(uSunObj.x, uSunObj.y * 2.4, uSunObj.z) * 0.16);
    light *= clamp(1.0 + (n - n2) * 3.2, 0.55, 1.35);
#endif
    // the night side keeps a dim violet sheen (thick atmosphere), so the storms always have clouds to light up
    vec3 lit = col * (0.19 + 0.95 * light) + vec3(0.020, 0.010, 0.050) * (1.0 - light);

    // lightning inside the clouds
    float structure = 0.30 + 1.25 * smoothstep(0.30, 0.80, c);
    vec3 flash = vec3(0.0);
    float glimpse = 0.0;
    for (int i = 0; i < STORMS; i++) {
        float d = 1.0 - dot(p, uStorm[i].xyz);
        float wide = exp(-d * 55.0);
        float core = exp(-d * 520.0);
        flash += uStorm[i].w * (vec3(0.62, 0.66, 1.00) * wide * structure * 0.85 + vec3(0.95, 0.93, 1.00) * core * 0.7);
        glimpse += uStorm[i].w * exp(-d * 30.0);
    }
    lit += flash;
    // a glimpse of what is under the clouds: warm lights through the gaps, only while lightning is on them
    float gap = 1.0 - smoothstep(0.24, 0.50, c);
    lit += vec3(1.00, 0.72, 0.36) * cityLights(p) * gap * min(glimpse, 1.0) * 1.7;

    // limb: the thick atmosphere scatters towards the rim
    float rim = pow(1.0 - clamp(dot(N, V), 0.0, 1.0), 2.6);
    lit += vec3(0.560, 0.360, 1.000) * rim * (0.16 + 0.62 * light);
    gl_FragColor = vec4(min(lit, vec3(1.0)), 1.0);
}
`;

// ---------------------------------------------------------------------------------------------------- atmosphere

export const ATMO_VERT = /* glsl */`
varying vec3 vWorld;
void main() {
    vec4 w = modelMatrix * vec4(position, 1.0);
    vWorld = w.xyz;
    gl_Position = projectionMatrix * viewMatrix * w;
}
`;

/** Rim glow outside the disc, from the impact parameter of the view ray (planet at the origin). Additive. */
export const ATMO_FRAG = /* glsl */`
precision highp float;
uniform vec3 uSun;
uniform float uRadius;
varying vec3 vWorld;
void main() {
    vec3 ro = cameraPosition;
    vec3 rd = normalize(vWorld - ro);
    float tca = dot(-ro, rd);
    vec3 closest = ro + rd * tca;
    float x = (length(closest) - uRadius) / uRadius;
    float glow = (exp(-max(x, 0.0) * 11.0) * 0.85 + exp(-max(x, 0.0) * 3.2) * 0.16) * smoothstep(-0.012, 0.004, x);
    float lit = 0.30 + 0.70 * smoothstep(-0.45, 0.55, dot(normalize(closest), normalize(uSun)));
    vec3 col = mix(vec3(0.930, 0.240, 0.780), vec3(0.560, 0.480, 1.000), exp(-max(x, 0.0) * 9.0));
    gl_FragColor = vec4(col * glow * lit, 1.0);
}
`;

// ---------------------------------------------------------------------------------------------------- sky

export const BAKE_VERT = /* glsl */`
varying vec2 vUv;
void main() {
    vUv = uv;
    gl_Position = vec4(position.xy, 0.0, 1.0);
}
`;

/** Nebula baked once into an equirect texture (same direction mapping as THREE.SphereGeometry uvs). */
export const NEBULA_FRAG = /* glsl */`
precision highp float;
varying vec2 vUv;
${NOISE}
void main() {
    float phi = vUv.x * 6.28318530718;
    float theta = (1.0 - vUv.y) * 3.14159265359;
    vec3 d = vec3(-cos(phi) * sin(theta), cos(theta), sin(phi) * sin(theta));
    float w = fbm(d * 1.3 + 4.0);
    float n = fbm(d * 2.1 + vec3(w * 1.8, -w, w * 0.6));
    float n2 = fbm(d * 3.7 + vec3(11.0, 3.0, -7.0) + w);
    // a tilted band (the belt's home sector) carries most of the dust
    vec3 axis = normalize(vec3(0.35, 0.90, 0.25));
    float band = exp(-pow(dot(d, axis) * 2.3, 2.0));
    vec3 col = vec3(0.045, 0.016, 0.105);
    col += vec3(0.255, 0.090, 0.576) * smoothstep(0.42, 0.85, n) * (0.22 + 0.55 * band);
    col += vec3(0.937, 0.165, 0.757) * smoothstep(0.58, 0.95, n2) * 0.13 * (0.25 + band);
    col += vec3(0.569, 0.890, 0.976) * smoothstep(0.66, 0.98, n * n2 * 2.2) * 0.10 * band;
    // unresolved star dust
    float s = hash31(floor(d * 310.0));
    col += vec3(0.80, 0.84, 1.0) * smoothstep(0.9965, 1.0, s) * 0.45;
    gl_FragColor = vec4(col, 1.0);
}
`;

export const STARS_VERT = /* glsl */`
attribute vec4 aStar; // size px, twinkle phase, twinkle speed, tint
uniform float uTime;
uniform float uDpr;
uniform float uTwinkle;
varying float vTint;
varying float vGain;
void main() {
    vTint = aStar.w;
    vGain = 0.72 + 0.28 * mix(1.0, sin(uTime * aStar.z + aStar.y), uTwinkle);
    vec4 mv = viewMatrix * vec4(position, 0.0);
    gl_Position = (projectionMatrix * vec4(mv.xyz, 1.0)).xyww;
    gl_PointSize = aStar.x * uDpr;
}
`;

export const STARS_FRAG = /* glsl */`
precision mediump float;
varying float vTint;
varying float vGain;
void main() {
    vec2 c = gl_PointCoord - 0.5;
    float a = smoothstep(0.5, 0.05, length(c));
    vec3 col = vTint < 0.6 ? vec3(1.0, 1.0, 1.0) : (vTint < 0.78 ? vec3(0.68, 0.84, 0.97) : (vTint < 0.92 ? vec3(1.0, 0.93, 0.56) : vec3(0.98, 0.62, 0.90)));
    gl_FragColor = vec4(col * a * vGain, 1.0);
}
`;

// ---------------------------------------------------------------------------------------------------- orbits

/** Shared by islands, glows and belt rocks. aP/aQ: scaled in-plane basis, aKep: e, mean anomaly at t = 0, mean motion. */
const ORBIT = /* glsl */`
attribute vec3 aP;
attribute vec3 aQ;
attribute vec3 aKep;
uniform float uTime;
vec3 orbitPosition() {
    float e = aKep.x;
    float M = aKep.y + aKep.z * uTime;
    float E = M + e * sin(M);
    for (int k = 0; k < 3; k++) {
        E -= (E - e * sin(E) - M) / (1.0 - e * cos(E));
    }
    return aP * (cos(E) - e) + aQ * sin(E);
}
`;

/**
 * Islands: camera-facing instanced quads (3:4), one draw call per atlas page.
 * aLook = (height, rarity 0..2, seed, cell index or -1 = no art yet), aState = 0 normal, 1 highlighted,
 * 2 filtered out (small + dark), 3 hidden (its near-LOD sprite is drawn instead).
 */
export const ISLAND_VERT = /* glsl */`
${ORBIT}
attribute vec4 aLook;
attribute float aState;
uniform vec4 uGrid;     // cols, rows, cell width in uv, cell height in uv
uniform float uPxWorld; // world units per pixel at view depth 1
uniform float uMinPx;
uniform float uHoverPx;
uniform float uSway;
varying vec2 vUv;
varying vec2 vQuad;
varying float vState;
varying float vRarity;
varying float vCell;
varying float vHaze;
void main() {
    vec3 c = orbitPosition();
    vec4 mv = viewMatrix * vec4(c, 1.0);
    float st = aState;
    float h = aLook.x * (st > 1.5 ? 0.55 : (st > 0.5 ? 1.16 : 1.0));
    h = max(h, (st > 0.5 && st < 1.5 ? uHoverPx : uMinPx * (st > 1.5 ? 0.6 : 1.0)) * uPxWorld * -mv.z);
    if (st > 2.5) { h = 0.0; }
    float sway = sin(uTime * 0.31 + aLook.z * 6.2832) * 0.045 * uSway;
    vec2 o = vec2(position.x * 0.75, position.y) * h;
    mv.xy += vec2(o.x * cos(sway) - o.y * sin(sway), o.x * sin(sway) + o.y * cos(sway));
    gl_Position = projectionMatrix * mv;
    float idx = max(aLook.w, 0.0);
    float col = mod(idx, uGrid.x);
    float row = floor(idx / uGrid.x + 0.0001);
    vUv = vec2((col + uv.x) * uGrid.z, 1.0 - (row + 1.0 - uv.y) * uGrid.w);
    vQuad = uv;
    vState = st;
    vRarity = aLook.y;
    vCell = aLook.w;
    vHaze = smoothstep(60.0, 260.0, -mv.z) * 0.5;
}
`;

export const ISLAND_FRAG = /* glsl */`
precision highp float;
uniform sampler2D uMap;
uniform float uHasMap;
uniform float uCoverage; // 1 = MSAA alpha-to-coverage, 0 = alpha test
uniform vec3 uHaze;
varying vec2 vUv;
varying vec2 vQuad;
varying float vState;
varying float vRarity;
varying float vCell;
varying float vHaze;
void main() {
    vec3 rgb;
    float a;
    if (uHasMap > 0.5 && vCell > -0.5) {
        vec4 t = texture2D(uMap, vUv); // premultiplied
        a = t.a;
        rgb = a > 0.003 ? t.rgb / a : vec3(0.0);
    } else {
        // no render yet: a small bare rock in the rarity colour
        vec2 q = (vQuad - vec2(0.5, 0.42)) * vec2(1.5, 2.6);
        float d = length(q + vec2(0.0, max(q.y, 0.0) * -0.35));
        a = 1.0 - smoothstep(0.42, 0.47, d);
        vec3 tint = vRarity < 0.5 ? vec3(0.70, 0.72, 0.86) : (vRarity < 1.5 ? vec3(0.95, 0.88, 0.36) : vec3(0.86, 0.32, 0.90));
        rgb = tint * (0.30 + 0.45 * smoothstep(0.5, -0.3, q.y + q.x * 0.4));
    }
    if (vState > 1.5) {
        rgb = mix(vec3(dot(rgb, vec3(0.299, 0.587, 0.114))), rgb, 0.2) * 0.30;
    } else if (vState > 0.5) {
        rgb = min(rgb * 1.10 + 0.03, vec3(1.0));
    }
    rgb = mix(rgb, uHaze, vHaze);
    if (uCoverage > 0.5) {
        if (a < 0.06) discard;
    } else {
        if (a < 0.5) discard;
        a = 1.0;
    }
    gl_FragColor = vec4(rgb, a);
}
`;

/** Rarity halo behind each island (additive, depth-tested, never writes depth). */
export const GLOW_VERT = /* glsl */`
${ORBIT}
attribute vec4 aLook;
attribute float aState;
uniform float uPxWorld;
uniform float uMinPx;
uniform float uHoverPx;
uniform vec3 uGain; // intensity per rarity
varying vec2 vQuad;
varying vec3 vColor;
varying float vGain;
void main() {
    vec3 c = orbitPosition();
    vec4 mv = viewMatrix * vec4(c, 1.0);
    float st = aState;
    float h = max(aLook.x, uMinPx * uPxWorld * -mv.z);
    float gain = aLook.y < 0.5 ? uGain.x : (aLook.y < 1.5 ? uGain.y : uGain.z);
    if (st > 0.5 && st < 1.5) { gain = max(gain * 2.0, 0.55); h = max(h * 1.16, uHoverPx * uPxWorld * -mv.z); }
    if (st > 1.5 && st < 2.5) { gain = 0.0; }
    float size = gain > 0.001 ? h * 2.1 : 0.0;
    mv.xy += position.xy * size + vec2(0.0, -0.10 * h);
    mv.z -= h * 0.35;
    gl_Position = projectionMatrix * mv;
    vQuad = position.xy * 2.0;
    vColor = aLook.y < 0.5 ? vec3(0.80, 0.86, 1.00) : (aLook.y < 1.5 ? vec3(1.00, 0.933, 0.314) : vec3(0.922, 0.235, 0.949));
    vGain = gain;
}
`;

export const GLOW_FRAG = /* glsl */`
precision mediump float;
varying vec2 vQuad;
varying vec3 vColor;
varying float vGain;
void main() {
    float r = length(vQuad);
    float g = smoothstep(1.0, 0.0, r);
    gl_FragColor = vec4(vColor * g * g * vGain, 1.0);
}
`;

/** Unterraformed rocks of the belt: tiny round points on the same kind of orbits. */
export const ROCK_VERT = /* glsl */`
${ORBIT}
attribute vec2 aRock; // size, shade
uniform float uDpr;
uniform float uPxWorld;
varying float vShade;
void main() {
    vec4 mv = viewMatrix * vec4(orbitPosition(), 1.0);
    gl_Position = projectionMatrix * mv;
    gl_PointSize = clamp(aRock.x / (uPxWorld * -mv.z), 1.0, 7.0) * uDpr;
    vShade = aRock.y * (1.0 - smoothstep(70.0, 280.0, -mv.z) * 0.6);
}
`;

export const ROCK_FRAG = /* glsl */`
precision mediump float;
varying float vShade;
void main() {
    vec2 c = gl_PointCoord - 0.5;
    if (dot(c, c) > 0.25) discard;
    float l = 0.55 + 0.45 * smoothstep(0.35, -0.35, c.x + c.y);
    gl_FragColor = vec4(vec3(0.44, 0.40, 0.60) * vShade * l, 1.0);
}
`;

// ---------------------------------------------------------------------------------------------------- lightning bolt

/** Screen-space ribbon along a polyline (aPrev / aNext neighbours, aSide = (-1|1, 0..1 along the bolt)). */
export const BOLT_VERT = /* glsl */`
attribute vec3 aPrev;
attribute vec3 aNext;
attribute vec2 aSide;
uniform vec2 uViewport;
uniform float uWidth;
varying float vAcross;
varying float vAlong;
void main() {
    mat4 m = projectionMatrix * modelViewMatrix;
    vec4 cur = m * vec4(position, 1.0);
    vec4 prev = m * vec4(aPrev, 1.0);
    vec4 next = m * vec4(aNext, 1.0);
    vec2 a = prev.xy / max(prev.w, 0.0001) * uViewport;
    vec2 b = next.xy / max(next.w, 0.0001) * uViewport;
    vec2 dir = b - a;
    float len = length(dir);
    dir = len > 0.0001 ? dir / len : vec2(1.0, 0.0);
    vec2 normal = vec2(-dir.y, dir.x);
    float width = uWidth * mix(1.0, 0.35, aSide.y);
    cur.xy += normal * aSide.x * width / uViewport * cur.w;
    gl_Position = cur;
    vAcross = aSide.x;
    vAlong = aSide.y;
}
`;

export const BOLT_FRAG = /* glsl */`
precision mediump float;
uniform float uPower;
varying float vAcross;
varying float vAlong;
void main() {
    float x = abs(vAcross);
    float core = smoothstep(0.38, 0.0, x);
    float halo = smoothstep(1.0, 0.0, x);
    vec3 col = vec3(0.60, 0.52, 1.00) * halo * halo * 0.55 + vec3(0.96, 0.95, 1.00) * core;
    gl_FragColor = vec4(col * uPower * mix(1.0, 0.55, vAlong), 1.0);
}
`;

// ---------------------------------------------------------------------------------------------------- crystal spire

/** Tip of a purple crystal spire that now and then pokes through the cloud tops (the rest stays under the deck). */
export const SPIRE_VERT = /* glsl */`
varying vec3 vNormalV;
varying float vUp;
void main() {
    vNormalV = normalize(normalMatrix * normal);
    vUp = uv.y;
    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
}
`;

export const SPIRE_FRAG = /* glsl */`
precision mediump float;
uniform float uFlash;
varying vec3 vNormalV;
varying float vUp;
void main() {
    float facet = 0.5 + 0.5 * dot(normalize(vNormalV), normalize(vec3(-0.5, 0.6, 0.62)));
    vec3 col = mix(vec3(0.200, 0.075, 0.520), vec3(0.800, 0.640, 1.000), facet * facet);
    col += vec3(0.55, 0.50, 1.0) * uFlash * 0.5;
    col *= 0.45 + 0.75 * vUp;
    gl_FragColor = vec4(col, 1.0);
}
`;
