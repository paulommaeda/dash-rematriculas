#!/usr/bin/env python3
"""Gera o ZIP instalável e o manifesto usado pelo atualizador do WordPress."""
import argparse
import hashlib
import io
import json
from pathlib import Path
import re
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED

root = Path(__file__).resolve().parent.parent
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--replace', action='store_true', help='Refazer uma versão local ainda não publicada.')
args = parser.parse_args()
source = root / 'dashboard-rematriculas'
main = (source / 'dashboard-rematriculas.php').read_text()
version = re.search(r'^ \* Version: (\d+\.\d+\.\d+)$', main, re.M).group(1)
assert f"define('DRJ_VERSION', '{version}');" in main, 'Versões do cabeçalho e da constante diferentes.'
buffer = io.BytesIO()
with ZipFile(buffer, 'w', ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(source.rglob('*')):
        if path.is_symlink():
            raise SystemExit('Links simbólicos não podem fazer parte do pacote.')
        if not path.is_file():
            continue
        relative = path.relative_to(root).as_posix()
        entry = ZipInfo(relative, (1980, 1, 1, 0, 0, 0))
        entry.compress_type = ZIP_DEFLATED
        entry.external_attr = 0o100644 << 16
        archive.writestr(entry, path.read_bytes())
package = buffer.getvalue()
destination = root / 'dist' / f'dashboard-rematriculas-{version}.zip'
destination.parent.mkdir(exist_ok=True)
if destination.exists() and destination.read_bytes() != package and not args.replace:
    raise SystemExit('Esse número de versão já tem um ZIP diferente. Aumente a versão antes de publicar.')
destination.write_bytes(package)
manifest = {
    'name': 'Dashboard de Rematrículas Journey',
    'version': version,
    'download_url': f'https://raw.githubusercontent.com/paulommaeda/dash-rematriculas/main/dist/{destination.name}',
    'sha256': hashlib.sha256(package).hexdigest(),
    'requires': '6.0', 'requires_php': '7.4', 'tested': '7.1.4',
    'changelog': (root / 'CHANGELOG.md').read_text().strip(),
}
(root / 'update.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')
print(f'Pacote: {destination.relative_to(root)} ({len(package)} bytes)')
print(f'Versão: {version}; SHA-256: {manifest["sha256"]}')
