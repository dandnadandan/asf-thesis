import json
import time
import sys
import subprocess

# ensure requests is installed
try:
    import requests
except ImportError:
    subprocess.check_call([sys.executable, "-m", "pip", "install", "requests"])
    import requests

cities = [
    "Batangas City", "Lipa", "Tanauan",
    "Calamba", "San Pablo City", "Santa Rosa", 
    "Tagaytay", "Dasmariñas", "Imus", "Bacoor",
    "Antipolo", "Lucena", "Tayabas",
    "San Juan, Batangas", "Bauan", "San Jose, Batangas", "Calaca", "Lemery"
]

features = []
headers = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
    'Accept': 'application/json'
}

for city in cities:
    search_term = city.replace(' ', '+')
    if "Batangas" not in city:
        search_term += ",+Region+IV-A"
        
    url = f"https://nominatim.openstreetmap.org/search?q={search_term},+Philippines&polygon_geojson=1&format=json"
    
    try:
        r = requests.get(url, headers=headers)
        if r.status_code == 200:
            data = r.json()
            if data:
                # Find first valid polygon
                found = False
                for item in data:
                    if 'geojson' in item and item['geojson']['type'] in ['Polygon', 'MultiPolygon']:
                        features.append({
                            "type": "Feature",
                            "properties": {"name": city.split(',')[0].upper()},
                            "geometry": item['geojson']
                        })
                        print(f"Success: {city}")
                        found = True
                        break
                if not found:
                    print(f"No polygon found: {city}")
            else:
                print(f"No data: {city}")
        else:
            print(f"Error {r.status_code}: {city}")
    except Exception as e:
        print(f"Failed {city}: {e}")
        
    time.sleep(1.5) # rate limit

geojson = {"type": "FeatureCollection", "features": features}

with open(r'c:\xampp\htdocs\asf\assets\data\calabarzon-municipalities.geojson', 'w', encoding='utf-8') as f:
    json.dump(geojson, f)

print(f"Done! Saved {len(features)} real city boundaries.")
