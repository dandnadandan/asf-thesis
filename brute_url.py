import requests
import json

urls = []
for year in ['2023', '2019']:
    for name in ['municipality', 'municipalities', 'municipality-city', 'municipalities-city', 'municipality-cities', 'city-municipality']:
        urls.append(f"https://raw.githubusercontent.com/faeldon/philippines-json-maps/master/{year}/geojson/{name}/lowres/{name}.geojson")
        urls.append(f"https://raw.githubusercontent.com/faeldon/philippines-json-maps/master/{year}/geojson/{name}/lowres/{name}.json")
        urls.append(f"https://raw.githubusercontent.com/faeldon/philippines-json-maps/master/{year}/geojson/municipality/lowres/{name}.geojson")

headers = {'User-Agent': 'Mozilla/5.0'}

found = False
for url in urls:
    print(f"Trying {url} ...")
    r = requests.head(url, headers=headers)
    if r.status_code == 200:
        print(f"FOUND: {url}")
        print("Downloading...")
        dl = requests.get(url, headers=headers)
        with open(r'c:\xampp\htdocs\asf\assets\data\calabarzon-municipalities.geojson', 'w', encoding='utf-8') as f:
            f.write(dl.text)
        print("Successfully saved!")
        found = True
        break

if not found:
    print("Could not find the exact URL.")
