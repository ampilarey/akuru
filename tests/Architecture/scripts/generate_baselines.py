#!/usr/bin/env python3
"""Regenerate architecture baseline files after fixing violations (baseline may only shrink)."""
import re
from pathlib import Path

DOMAINS = [
    'Identity', 'People', 'Academics', 'ExamsGrades', 'Hifz', 'Admissions',
    'Finance', 'HR', 'Commerce', 'Library', 'Courses', 'Offerings', 'Progress',
    'Pronunciation', 'Media', 'Notifications', 'Portal', 'Website', 'Settings',
]
ALLOWED_LAYERS = {'Contracts', 'DTOs', 'Events', 'Actions'}


def home_domain(path: Path) -> str | None:
    parts = path.parts
    if 'Domains' not in parts:
        return None
    i = parts.index('Domains')
    d = parts[i + 1]
    if d == 'Academics' and i + 2 < len(parts) and parts[i + 2] == 'Legacy':
        return 'Academics'
    return d


def rel(path: Path) -> str:
    return str(path).replace('\\', '/')


def scan():
    files = list(Path('app/Domains').rglob('*.php'))
    files += list(Path('app/Support').rglob('*.php'))
    rule1, rule2, rule5 = set(), set(), set()
    for path in files:
        home = home_domain(path)
        if not home:
            continue
        text = path.read_text()
        r = rel(path)
        for m in re.finditer(r'use App\\Domains\\([^\\]+)\\([^\\]+)\\([^;]+);', text):
            other, layer, rest = m.group(1), m.group(2), m.group(3)
            if other == 'Academics' and layer == 'Legacy':
                other = 'Academics'
            if other != home:
                fqcn = f'App\\Domains\\{m.group(1)}\\{layer}\\{rest}'
                if layer == 'Models':
                    rule1.add(r)
                if layer not in ALLOWED_LAYERS:
                    rule2.add(f'{r} -> {fqcn}')
        for other in DOMAINS:
            if other == home:
                continue
            if re.search(rf'App\\Domains\\{other}\\Models\\', text):
                rule1.add(r)
        if home != 'Hifz' and re.search(r'App\\Domains\\Hifz\\', text):
            rule5.add(r)
    rule4 = sorted({
        rel(p) for p in Path('app/Domains').rglob('Http/Controllers/**/*.php')
        if 'DB::' in p.read_text()
    })
    return sorted(rule1), sorted(rule2), rule4, sorted(rule5)


def write(name: str, items: list, header: str) -> None:
    out = Path(__file__).resolve().parents[1] / 'Baselines' / name
    lines = [
        '<?php',
        '',
        f'// {header}',
        '// Baseline may only shrink when violations are fixed — never grow.',
        f'// Baseline count: {len(items)}',
        '',
        'return [',
    ]
    for item in items:
        lines.append(f"    '{item}',")
    lines.append('];')
    lines.append('')
    out.write_text('\n'.join(lines))


if __name__ == '__main__':
    r1, r2, r4, r5 = scan()
    write('cross_domain_models.php', r1, "PHASE_0_CHECKLIST §0.5 rule 1: cross-domain Models imports.")
    write('cross_domain_non_contract.php', r2, "PHASE_0_CHECKLIST §0.5 rule 2: cross-domain non-contract references.")
    write('controllers_using_db_facade.php', r4, "PHASE_0_CHECKLIST §0.5 rule 4: DB:: in domain controllers.")
    write('hifz_referenced_outside_hifz.php', r5, "PHASE_0_CHECKLIST §0.5 rule 5: Hifz referenced outside Hifz domain.")
    print(f'r1={len(r1)} r2={len(r2)} r4={len(r4)} r5={len(r5)}')
