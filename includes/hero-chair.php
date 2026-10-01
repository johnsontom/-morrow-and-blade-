<?php declare(strict_types=1); ?>
<svg class="hero-chair" viewBox="0 0 460 560" xmlns="http://www.w3.org/2000/svg" role="presentation" focusable="false">
  <defs>
    <linearGradient id="mbcBack" x1="0.06" y1="0" x2="0.94" y2="1">
      <stop offset="0" stop-color="#5d5548"/>
      <stop offset="0.24" stop-color="#413b31"/>
      <stop offset="0.58" stop-color="#292520"/>
      <stop offset="0.86" stop-color="#191714"/>
      <stop offset="1" stop-color="#100f0d"/>
    </linearGradient>
    <linearGradient id="mbcSeatTop" x1="0.08" y1="0" x2="0.92" y2="1">
      <stop offset="0" stop-color="#6a6152"/>
      <stop offset="0.3" stop-color="#4a4438"/>
      <stop offset="0.68" stop-color="#2e2a22"/>
      <stop offset="1" stop-color="#1a1815"/>
    </linearGradient>
    <linearGradient id="mbcSeatFront" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#332c23"/>
      <stop offset="0.42" stop-color="#1d1a15"/>
      <stop offset="1" stop-color="#0a0908"/>
    </linearGradient>
    <linearGradient id="mbcArmTop" x1="0.1" y1="0" x2="0.9" y2="1">
      <stop offset="0" stop-color="#6f6555"/>
      <stop offset="0.5" stop-color="#4a4336"/>
      <stop offset="1" stop-color="#2a251e"/>
    </linearGradient>
    <linearGradient id="mbcArmFront" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#38301f"/>
      <stop offset="0.5" stop-color="#221d15"/>
      <stop offset="1" stop-color="#0d0c0a"/>
    </linearGradient>
    <linearGradient id="mbcBrass" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#f9ecc9"/>
      <stop offset="0.22" stop-color="#e6c684"/>
      <stop offset="0.54" stop-color="#bd8d3d"/>
      <stop offset="0.82" stop-color="#8b6526"/>
      <stop offset="1" stop-color="#634417"/>
    </linearGradient>
    <linearGradient id="mbcBrassFace" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#6b4c1c"/>
      <stop offset="0.15" stop-color="#d9b672"/>
      <stop offset="0.36" stop-color="#f8eac4"/>
      <stop offset="0.6" stop-color="#c8973f"/>
      <stop offset="0.84" stop-color="#886324"/>
      <stop offset="1" stop-color="#573d14"/>
    </linearGradient>
    <linearGradient id="mbcChrome" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#121110"/>
      <stop offset="0.12" stop-color="#413f3b"/>
      <stop offset="0.26" stop-color="#b0ada5"/>
      <stop offset="0.38" stop-color="#f2f0e8"/>
      <stop offset="0.5" stop-color="#8b8880"/>
      <stop offset="0.66" stop-color="#333130"/>
      <stop offset="0.84" stop-color="#1c1b19"/>
      <stop offset="1" stop-color="#0a0a09"/>
    </linearGradient>
    <radialGradient id="mbcDisc" cx="0.32" cy="0.2" r="0.95">
      <stop offset="0" stop-color="#8e8b84"/>
      <stop offset="0.25" stop-color="#585653"/>
      <stop offset="0.55" stop-color="#2f2e2c"/>
      <stop offset="0.84" stop-color="#1a1918"/>
      <stop offset="1" stop-color="#0a0a09"/>
    </radialGradient>
    <linearGradient id="mbcDiscRim" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#3a3835"/>
      <stop offset="0.35" stop-color="#1a1918"/>
      <stop offset="1" stop-color="#060605"/>
    </linearGradient>
    <linearGradient id="mbcKey" x1="0.1" y1="0" x2="0.9" y2="1">
      <stop offset="0" stop-color="#fff1d6" stop-opacity="0.28"/>
      <stop offset="0.42" stop-color="#ffdcaa" stop-opacity="0.05"/>
      <stop offset="1" stop-color="#ffd9a0" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="mbcRim" x1="0.25" y1="0.15" x2="1" y2="0.92">
      <stop offset="0" stop-color="#ffd9a0" stop-opacity="0"/>
      <stop offset="0.7" stop-color="#ffd9a0" stop-opacity="0"/>
      <stop offset="1" stop-color="#fff2da" stop-opacity="0.42"/>
    </linearGradient>
    <radialGradient id="mbcHalo">
      <stop offset="0" stop-color="#c89b4a" stop-opacity="0.15"/>
      <stop offset="1" stop-color="#c89b4a" stop-opacity="0"/>
    </radialGradient>
    <radialGradient id="mbcShadow">
      <stop offset="0" stop-color="#000000" stop-opacity="0.85"/>
      <stop offset="0.5" stop-color="#000000" stop-opacity="0.34"/>
      <stop offset="1" stop-color="#000000" stop-opacity="0"/>
    </radialGradient>
    <filter id="mbcSoft" x="-40%" y="-80%" width="180%" height="260%"><feGaussianBlur stdDeviation="10"/></filter>
    <filter id="mbcSoftTight" x="-50%" y="-80%" width="200%" height="260%"><feGaussianBlur stdDeviation="5"/></filter>
    <filter id="mbcGrain" x="0" y="0" width="100%" height="100%">
      <feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="4" seed="13" result="noise"/>
      <feColorMatrix in="noise" type="saturate" values="0"/>
    </filter>
    <clipPath id="mbcBody">
      <rect x="120" y="130" width="220" height="200" rx="54"/>
      <rect x="176" y="40" width="108" height="76" rx="36"/>
      <rect x="94" y="278" width="272" height="106" rx="48"/>
      <rect x="30" y="232" width="98" height="60" rx="26"/>
      <rect x="332" y="232" width="98" height="60" rx="26"/>
    </clipPath>
  </defs>

  <ellipse cx="230" cy="330" rx="214" ry="238" fill="url(#mbcHalo)"/>
  <ellipse cx="230" cy="520" rx="180" ry="32" fill="url(#mbcShadow)" filter="url(#mbcSoft)"/>
  <ellipse cx="230" cy="510" rx="120" ry="18" fill="#000000" opacity="0.5" filter="url(#mbcSoftTight)"/>

  <g>
    <ellipse cx="230" cy="503" rx="154" ry="43" fill="url(#mbcDiscRim)"/>
    <ellipse cx="230" cy="495" rx="154" ry="43" fill="url(#mbcDisc)"/>
    <ellipse cx="230" cy="495" rx="154" ry="43" fill="none" stroke="#c69a44" stroke-opacity="0.45" stroke-width="2.2"/>
    <ellipse cx="230" cy="495" rx="126" ry="34" fill="none" stroke="#000000" stroke-opacity="0.5" stroke-width="3"/>
    <ellipse cx="230" cy="493" rx="126" ry="34" fill="none" stroke="#ffffff" stroke-opacity="0.08" stroke-width="1.5"/>
    <ellipse cx="150" cy="482" rx="48" ry="14" fill="#ffffff" opacity="0.1" filter="url(#mbcSoft)"/>
    <path d="M118 474a154 43 0 0 0 226 0" fill="none" stroke="#ffd9a0" stroke-opacity="0.4" stroke-width="3.5" stroke-linecap="round"/>
  </g>

  <rect x="200" y="344" width="60" height="32" rx="10" fill="url(#mbcBrass)"/>
  <rect x="184" y="362" width="92" height="24" rx="12" fill="url(#mbcBrass)"/>
  <rect x="184" y="362" width="92" height="9" rx="4.5" fill="#ffeec6" opacity="0.42"/>
  <rect x="198" y="372" width="64" height="120" rx="15" fill="url(#mbcChrome)"/>
  <rect x="207" y="376" width="9" height="112" rx="4.5" fill="#ffffff" opacity="0.3"/>
  <rect x="245" y="376" width="5" height="112" rx="2.5" fill="#000000" opacity="0.4"/>

  <rect x="120" y="130" width="220" height="202" rx="54" fill="url(#mbcBack)"/>
  <rect x="120" y="130" width="220" height="202" rx="54" fill="none" stroke="#e8c98a" stroke-opacity="0.2" stroke-width="2"/>
  <ellipse cx="186" cy="162" rx="54" ry="22" fill="#ffffff" opacity="0.12" filter="url(#mbcSoft)"/>
  <path d="M230 158v148" stroke="#000000" stroke-opacity="0.5" stroke-width="4" stroke-linecap="round"/>
  <path d="M233 158v148" stroke="#ffffff" stroke-opacity="0.06" stroke-width="1.5" stroke-linecap="round"/>
  <path d="M140 306c0 14 12 22 30 22h120c18 0 30-8 30-22" fill="none" stroke="#000000" stroke-opacity="0.42" stroke-width="6" filter="url(#mbcSoftTight)"/>

  <rect x="192" y="108" width="14" height="46" rx="7" fill="url(#mbcBrassFace)"/>
  <rect x="254" y="108" width="14" height="46" rx="7" fill="url(#mbcBrassFace)"/>
  <rect x="176" y="40" width="108" height="76" rx="36" fill="url(#mbcBack)"/>
  <rect x="176" y="40" width="108" height="76" rx="36" fill="none" stroke="#e8c98a" stroke-opacity="0.22" stroke-width="2"/>
  <ellipse cx="208" cy="60" rx="32" ry="12" fill="#ffffff" opacity="0.13" filter="url(#mbcSoft)"/>
  <path d="M176 102c0 8 6 12 14 12h80c8 0 14-4 14-12" fill="none" stroke="#000000" stroke-opacity="0.4" stroke-width="4" filter="url(#mbcSoftTight)"/>

  <ellipse cx="230" cy="350" rx="134" ry="34" fill="#000000" opacity="0.7" filter="url(#mbcSoftTight)"/>
  <rect x="94" y="316" width="272" height="70" rx="34" fill="url(#mbcSeatFront)"/>
  <path d="M104 352a126 26 0 0 0 252 0" fill="none" stroke="#000000" stroke-opacity="0.55" stroke-width="8" filter="url(#mbcSoftTight)"/>
  <rect x="94" y="278" width="272" height="56" rx="28" fill="url(#mbcSeatTop)"/>
  <ellipse cx="184" cy="296" rx="78" ry="18" fill="#ffffff" opacity="0.12" filter="url(#mbcSoft)"/>
  <path d="M96 314h268" stroke="#000000" stroke-opacity="0.5" stroke-width="3"/>
  <path d="M100 310h260" stroke="#e8c98a" stroke-opacity="0.2" stroke-width="1.5"/>
  <rect x="94" y="278" width="272" height="56" rx="28" fill="none" stroke="#e8c98a" stroke-opacity="0.16" stroke-width="2"/>

  <rect x="104" y="252" width="16" height="60" rx="8" fill="url(#mbcBrassFace)"/>
  <rect x="340" y="252" width="16" height="60" rx="8" fill="url(#mbcBrassFace)"/>
  <rect x="30" y="252" width="98" height="42" rx="21" fill="url(#mbcArmFront)"/>
  <rect x="30" y="252" width="98" height="42" rx="21" fill="none" stroke="#000000" stroke-opacity="0.45" stroke-width="2"/>
  <rect x="32" y="232" width="94" height="28" rx="14" fill="url(#mbcArmTop)"/>
  <ellipse cx="72" cy="242" rx="32" ry="8" fill="#ffffff" opacity="0.14"/>
  <rect x="32" y="232" width="94" height="28" rx="14" fill="none" stroke="#e8c98a" stroke-opacity="0.22" stroke-width="2"/>
  <rect x="332" y="252" width="98" height="42" rx="21" fill="url(#mbcArmFront)"/>
  <rect x="332" y="252" width="98" height="42" rx="21" fill="none" stroke="#000000" stroke-opacity="0.45" stroke-width="2"/>
  <rect x="334" y="232" width="94" height="28" rx="14" fill="url(#mbcArmTop)"/>
  <ellipse cx="374" cy="242" rx="32" ry="8" fill="#ffffff" opacity="0.14"/>
  <rect x="334" y="232" width="94" height="28" rx="14" fill="none" stroke="#e8c98a" stroke-opacity="0.22" stroke-width="2"/>

  <g clip-path="url(#mbcBody)">
    <rect x="0" y="0" width="460" height="560" fill="url(#mbcKey)"/>
    <rect x="0" y="0" width="460" height="560" fill="url(#mbcRim)"/>
  </g>
  <g clip-path="url(#mbcBody)" opacity="0.13" style="mix-blend-mode:overlay">
    <rect x="0" y="0" width="460" height="560" filter="url(#mbcGrain)"/>
  </g>
</svg>
<svg class="hero-scissors" viewBox="0 0 140 230" xmlns="http://www.w3.org/2000/svg" role="presentation" focusable="false">
  <defs>
    <linearGradient id="mbScBrass" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#f4dfab"/>
      <stop offset="0.5" stop-color="#c89b4a"/>
      <stop offset="1" stop-color="#8a6323"/>
    </linearGradient>
    <linearGradient id="mbScSteel" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#f8f5ee"/>
      <stop offset="1" stop-color="#b3ada0"/>
    </linearGradient>
  </defs>
  <g fill="none" stroke-linecap="round">
    <path d="M58 150 42 24" stroke="url(#mbScSteel)" stroke-width="11"/>
    <path d="M82 150 98 24" stroke="url(#mbScSteel)" stroke-width="11"/>
    <circle cx="46" cy="188" r="24" stroke="url(#mbScBrass)" stroke-width="9"/>
    <circle cx="94" cy="188" r="24" stroke="url(#mbScBrass)" stroke-width="9"/>
  </g>
  <circle cx="70" cy="148" r="8" fill="#c89b4a"/>
</svg>
