---
name: Meeple Playful Tactile
colors:
  surface: '#fcf9f2'
  surface-dim: '#dcdad3'
  surface-bright: '#fcf9f2'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f6f3ec'
  surface-container: '#f0eee7'
  surface-container-high: '#ebe8e1'
  surface-container-highest: '#e5e2db'
  on-surface: '#1c1c18'
  on-surface-variant: '#44474c'
  inverse-surface: '#31312c'
  inverse-on-surface: '#f3f0ea'
  outline: '#74777d'
  outline-variant: '#c4c6cc'
  surface-tint: '#525f71'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#0f1c2c'
  on-primary-container: '#778598'
  inverse-primary: '#bac8dc'
  secondary: '#855300'
  on-secondary: '#ffffff'
  secondary-container: '#fea619'
  on-secondary-container: '#684000'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#002113'
  on-tertiary-container: '#009668'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d6e4f9'
  primary-fixed-dim: '#bac8dc'
  on-primary-fixed: '#0f1c2c'
  on-primary-fixed-variant: '#3a4859'
  secondary-fixed: '#ffddb8'
  secondary-fixed-dim: '#ffb95f'
  on-secondary-fixed: '#2a1700'
  on-secondary-fixed-variant: '#653e00'
  tertiary-fixed: '#6ffbbe'
  tertiary-fixed-dim: '#4edea3'
  on-tertiary-fixed: '#002113'
  on-tertiary-fixed-variant: '#005236'
  background: '#fcf9f2'
  on-background: '#1c1c18'
  surface-variant: '#e5e2db'
typography:
  display-hero:
    fontFamily: Rubik
    fontSize: 48px
    fontWeight: '800'
    lineHeight: 56px
    letterSpacing: -0.02em
  display-hero-mobile:
    fontFamily: Rubik
    fontSize: 36px
    fontWeight: '800'
    lineHeight: 42px
    letterSpacing: -0.01em
  headline-lg:
    fontFamily: Rubik
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Rubik
    fontSize: 26px
    fontWeight: '700'
    lineHeight: 32px
  headline-md:
    fontFamily: Rubik
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 30px
  headline-sm:
    fontFamily: Rubik
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 26px
  title-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 18px
    fontWeight: '700'
    lineHeight: 24px
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '500'
    lineHeight: 24px
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
  body-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
  label-lg:
    fontFamily: Rubik
    fontSize: 15px
    fontWeight: '700'
    lineHeight: 20px
    letterSpacing: 0.02em
  label-md:
    fontFamily: Rubik
    fontSize: 13px
    fontWeight: '700'
    lineHeight: 16px
    letterSpacing: 0.03em
  label-sm:
    fontFamily: Rubik
    fontSize: 11px
    fontWeight: '700'
    lineHeight: 14px
    letterSpacing: 0.04em
rounded:
  sm: 0.5rem
  DEFAULT: 1rem
  md: 1.5rem
  lg: 2rem
  xl: 3rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-desktop: 1.5rem
  margin: 1rem
  margin-desktop: 2rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2.25rem
---

## Brand & Style

This design system expresses a cheerful, tactile tabletop gaming universe tailored for casual memory and puzzle players. Rooted in the visual joy of physical board games, dice rolling, and meeple figurines, the aesthetic balances arcade exuberance with clean digital ergonomics. 

The design language fuses **Tactile / Toyetic Skeuomorphism** with contemporary casual-game flat illustration:
- **Physicality and "Squish":** Interactive objects feel tangible, modeled after smooth molded plastic meeples, lacquered wooden tokens, and chunky rounded dice.
- **Tone and Voice:** Encouraging, vibrant, delightfully responsive, and universally accessible across age groups.
- **Visual Weight:** Deep, anchoring midnight navy provides structural depth and high contrast, letting sunny ambers, emeralds, and candy rubies pop like game tokens on a custom tabletop mat.

## Colors

The palette draws inspiration directly from premium board game components: deep midnight cardstock, golden winner medallions, and colorful player pieces.

### Primary & Foundation
- **Midnight Board (`#0D1B2A`):** The dominant ink and structural tone. Used for deep interactive surfaces, rich typography, thick game board outlines, and night-mode canvases.
- **Tabletop Cream (`#FBF8F1`):** The primary light canvas background. Replaces stark clinical white with the warmth of unbleached linen game mats.
- **Cardstock White (`#FFFFFF`):** Reserved for active face-up tiles, floating action cards, dialog modals, and high-contrast tactile elements.

### Brand Accents
- **Meeple Gold (`#F59E0B`) & Sunset Blaze (`#F97316`):** The primary engagement pair. Used for critical action buttons, stars, high-score callouts, and combo multipliers.
- **Emerald Win (`#10B981`):** Indicates correct memory pairs, completion progress, and level unlocks.
- **Ruby Heart (`#EF4444`):** Designates missed pairs, timer pressure, and hazard alerts.
- **Sky Token (`#0EA5E9`):** Secondary interactive indicators, informational badges, and turn-order rings.

## Typography

The typography unites **Rubik** and **Plus Jakarta Sans** to establish a bouncy yet legible reading environment.

- **Display & Headlines (Rubik):** With rounded terminals and sturdy geometric frames, Rubik reinforces the curved forms of dice and meeples. Weights are kept heavy (`700` and `800`) to evoke arcade headers and board game rulebook titles.
- **Body & Information (Plus Jakarta Sans):** A friendly, warm humanist sans with open apertures. Ensures immediate scanning of game instructions, time limits, and inventory descriptions without optical clutter.
- **Labels & Microcopy (Rubik):** Heavy uppercase or title-cased labels mimic stamped wooden meeples and embossed game cards.

## Layout & Spacing

Layouts follow a structured, playfield-centric grid designed around responsive game boards and tactile HUD (Heads-Up Display) overlays.

- **Game Board Grid:** A fluid symmetrical grid designed for card-pairing matrices (2x2 up to 6x6). Card aspect ratios remain strictly `1:1` or `3:4` with equalized gutters to preserve muscle memory during rapid tapping.
- **HUD and Panels:** Anchored top status bar (timer, turn counters, streak badges) and sticky bottom controller docking for thumb navigation.
- **Rhythm & Padding:** Built on a strict 4px base with generous component padding (`space-md` to `space-lg`) to prevent mis-clicks during fast-paced memory gameplay.

## Elevation & Depth

Visual hierarchy does not rely on sterile generic blurs; instead, it utilizes **tactile toybox elevation** combining offset drop cushions and directional lighting:

- **Plinth Shadows (Tactile 3D Buttons):** Interactive buttons and cards feature a solid 0px-blur bottom border/offset shadow (`0 4px 0 #D97706` under amber buttons, `0 4px 0 #08111B` under navy elements). On press or hover, the offset collapses to `0 1px 0`, creating an authentic mechanical button click.
- **Tile Lift (Memory Cards):** Face-down cards sit with a soft ambient contact shadow (`0 6px 12px -2px rgba(13, 27, 42, 0.12)`). Flipped or selected cards gain an energetic lift (`0 12px 24px -4px rgba(13, 27, 42, 0.20)`) accompanied by a subtle scale bounce (`scale(1.05)`).
- **Modals and Drawers:** Elevated game-over boards and store overlays employ high-contrast backdrop isolation with a `rgba(13, 27, 42, 0.65)` scrim, framing cards with thick rounded midnight outlines (`border: 3px solid #0D1B2A`).

## Shapes

The design system embraces generous, pill-shaped forms reminiscent of extruded wooden pawns and smooth molded tokens:

- **Pills and Capsules (`rounded-full`):** Standard for buttons, score indicators, streak pills, and category toggles.
- **Smooth Game Cards (`rounded-2xl` / 1.5rem):** Memory tiles and modal panels possess large rounded corners that mimic curved linen cardstock, preventing sharp visual boundaries.
- **Avatars & Meeples:** Enclosed in continuous squircle or circular frames with thick `3px` midnight strokes.

## Components

### Buttons
- **Primary Pill (Meeple Action):** Solid `#F59E0B` fill with `#0D1B2A` heavy typography (`label-lg`). Finished with a solid `0 4px 0 #D97706` base lip that compresses upon `:active`. Fully rounded ends (`9999px`).
- **Secondary Midnight:** `#0D1B2A` background, Cardstock White label, with a `0 4px 0 #050B12` base lip.
- **Icon / Dice Actions:** Perfect circles or rounded squares with inset iconography and gentle glossy top-edge highlight lines.

### Memory Cards & Tiles
- **Card Back:** Rich `#0D1B2A` or `#F59E0B` background decorated with a rhythmic repeating geometric meeple/dice micro-pattern, rounded `1.25rem` corners, and a clean white inset safety stroke.
- **Card Face:** Crisp `#FFFFFF` card face with an elevated character icon, dice pip, or meeple emblem centered. Success pairs trigger an `#10B981` outer glow ring.

### Score & Streak Badges (Chips)
- Pill-shaped badges featuring dual-tone layouts: a circular icon node (e.g. fire streak, timer clock) nested on the left, paired with bold numeric readouts on a light cream or pastel-tinted bed.

### Inputs & Toggles
- **Input Fields:** Generously padded text inputs with a soft `#FBF8F1` background, `#0D1B2A` text, and a distinct `2px solid #E2D9C8` boundary that shifts to `#F59E0B` upon focus.
- **Checkboxes & Radios:** Styled as chunky circular buttons or dice tokens that pop with a checkmark badge and tactile scale micro-animation when selected.

### Modals & Dialogs (The Trophy Board)
- Contained inside an opaque `#FFFFFF` floating card with a chunky `#0D1B2A` border (`3px`) and ribbon header banners for victories, star calculations, and reward unlocks.