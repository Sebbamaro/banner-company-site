#!/usr/bin/env python3
"""Prepare a static, non-collecting GitHub Pages preview; never ship PHP/config."""
import argparse
import re
import shutil
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--base', default='/banner-company-site/')
args = parser.parse_args()
if not re.fullmatch(r'/[A-Za-z0-9_-]+/', args.base):
    parser.error('base must be /repository-name/')
subprocess.run(['python3', 'bin/build.py'], cwd=root, check=True)
output = root / 'dist-pages'
if output.exists():
    shutil.rmtree(output)
output.mkdir()
for source in (root / 'public').rglob('*'):
    if not source.is_file():
        continue
    relative = source.relative_to(root / 'public')
    if source.suffix.lower() not in {'.html', '.css', '.webp', '.avif', '.png', '.jpg', '.jpeg', '.svg'}:
        continue
    target = output / relative
    target.parent.mkdir(parents=True, exist_ok=True)
    if source.suffix == '.html':
        content = source.read_text()
        content = re.sub(r'<script\b[^>]*>.*?</script>', '', content, flags=re.S)
        content = re.sub(r'<link rel="canonical"[^>]*>', '', content)
        content = re.sub(r'<meta property="og:url"[^>]*>', '', content)
        content = content.replace('</head>', '<meta name="robots" content="noindex, nofollow"></head>')
        content = re.sub(r'(href|src)="/(?!/)', lambda m: m[1] + '="' + args.base, content)
        content = content.replace('<form id="callback">', '<div id="callback"><p class="notice">Это предпросмотр сайта. Форма отключена: заявки не сохраняются и уведомления не отправляются. Не вводите персональные данные.</p>')
        content = content.replace('</form>', '</div>')
        content = re.sub(r'<(input|textarea|button)\b', r'<\1 disabled', content)
        content = content.replace('<main id="main">', '<main id="main"><div class="wrap"><p class="notice">Предпросмотр · данные компании временные · приём заявок отключён</p></div>')
        target.write_text(content)
    else:
        shutil.copyfile(source, target)
(output / '.nojekyll').touch()
(output / 'robots.txt').write_text('User-agent: *\nDisallow: /\n')
print('GitHub Pages preview built in dist-pages')
