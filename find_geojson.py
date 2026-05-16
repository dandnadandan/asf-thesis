import requests

url = "https://api.github.com/repos/faeldon/philippines-json-maps/git/trees/master?recursive=1"
try:
    r = requests.get(url)
    data = r.json()
    paths = [x['path'] for x in data.get('tree', []) if 'municipality' in x['path'].lower()]
    for p in paths:
        if p.endswith('.geojson'):
            print(p)
except Exception as e:
    print(e)
