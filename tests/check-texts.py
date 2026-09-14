"""Vergleicht Pakettexte und wörtliche Pflichttexte mit dem Originalauftrag."""
import json
import re
import sys
from pathlib import Path

root = Path(__file__).resolve().parents[1]
auftrag = Path(sys.argv[1]).read_text()
block = auftrag.split('### Sperrliste.', 1)[1].split('**Ebenfalls verboten:', 1)[0]
blocked = re.findall(r'`([^`]+)`', block)
readme = (root / 'readme.txt').read_text()
pot = (root / 'languages/editorial-accessibility-checks.pot').read_text()
source_texts = []
for match in re.finditer(r'^msgid (".*")(?:\n(".*"))*', pot, re.M):
    parts = re.findall(r'^".*"$|(?<=msgid )".*"', match.group(), re.M)
    source_texts.append(''.join(json.loads(part) for part in parts))
visible = '\n'.join(source_texts) + '\n' + readme + '\n' + (root / 'LICENSE').read_text()
# Der vorgeschriebene Haftungssatz nennt WCAG, EAA, BFSG und ADA in der VERNEINUNG und ist
# damit als einziger Text von der Sperrliste ausgenommen. Er wird vor der Suche vollstaendig
# herausgeschnitten, damit ein Treffer ausserhalb dieses Satzes wieder ein echter Treffer ist.
AUSNAHME = (
    'This plugin does not determine, certify or guarantee conformance with WCAG, '
    'the European Accessibility Act, the German BFSG, the ADA or any other standard '
    'or law. Whether your website meets legal requirements is a question only you and '
    'your advisers can answer.'
)
if AUSNAHME not in visible:
    print('FEHLT: der vorgeschriebene Haftungssatz')
    sys.exit(1)
visible = visible.replace(AUSNAHME, '')
hits = [word for word in blocked if word.casefold() in visible.casefold()]
quotes = [' '.join(line[2:] for line in block.splitlines()) for block in re.findall(r'(?:^> .*\n?)+', auftrag, re.M)]
missing = [quote for quote in quotes if quote not in source_texts]
print('Sperrliste: ' + str(len(hits)) + ' Treffer')
print('Wörtliche Pflichttexte in PHP/POT: ' + str(len(quotes) - len(missing)) + '/' + str(len(quotes)))
for quote in missing:
    print('FEHLT: ' + quote)
print('Readme-Tags: ' + re.search(r'^Tags: (.*)$', readme, re.M).group(1))
if hits:
    print('Treffer: ' + ', '.join(hits))
sys.exit(1 if hits or missing else 0)
