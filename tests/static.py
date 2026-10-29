from pathlib import Path
from html.parser import HTMLParser
import json, subprocess
R=Path(__file__).resolve().parents[1];P=R/'public'
class Page(HTMLParser):
 def __init__(self):super().__init__();self.h1=0;self.links=[]
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='h1':self.h1+=1
  if tag=='a':self.links.append(a.get('href',''))
for f in P.rglob('*.html'):
 p=Page();p.feed(f.read_text());assert p.h1==1,f
 for link in p.links:
  if link.startswith('/'):
   target=link.split('#')[0]
   assert not target or (P/target.lstrip('/')/'index.html').is_file(),(f,link)
assert 'tel:' not in (P/'index.html').read_text()
assert '/raboty/' not in (P/'index.html').read_text()
original=(R/'config/portfolio.json').read_text()
try:
 # Local fixture is removed in finally and never part of production output.
 fixture=P/'assets/images/work/test.svg';fixture.write_text('<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"/>')
 (R/'config/portfolio.json').write_text(json.dumps([{'published':True,'title':'TEST','description':'TEST','images':[{'src':'/assets/images/work/test.svg','alt':'TEST','width':20,'height':20}]}]))
 subprocess.run(['python3','bin/build.py'],cwd=R,check=True)
 assert '/raboty/' in (P/'index.html').read_text()
 assert (P/'raboty/index.html').is_file()
finally:
 (R/'config/portfolio.json').write_text(original);fixture.unlink(missing_ok=True)
 subprocess.run(['python3','bin/build.py'],cwd=R,check=True)
assert not (P/'raboty/index.html').exists()
print('Static pages, internal links and automatic portfolio toggle passed')
