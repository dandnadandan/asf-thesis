import json

print("Loading ph-location.json...")
with open(r'c:\xampp\htdocs\asf\PH-Address-API-main\ph-location.json', 'r', encoding='utf-8') as f:
    locations = json.load(f)

calabarzon_cities = set()

# Get all locations with Region 4A code (04)
for city in locations.get('city', []):
    if city.get('region_code') == '04':
        name = city['name'].upper().replace('CITY OF ', '').replace(' CITY', '').strip()
        calabarzon_cities.add(name)

# Some special standardizations
aliases = {
    "DASMARIÑAS": ["DASMARINAS"],
    "BIÑAN": ["BINAN"],
    "BACOOR": ["BACOOR CITY"],
    "LOS BAÑOS": ["LOS BANOS"],
    "TRECE MARTIRES": ["TRECE MARTIRES CITY"],
    "CAVITE CITY": ["CAVITE"],
    "BATANGAS CITY": ["BATANGAS"],
    "LUCENA CITY": ["LUCENA"],
    "SAN PABLO CITY": ["SAN PABLO"],
    "LIPA CITY": ["LIPA"],
    "SANTA ROSA": ["STA. ROSA", "STA ROSA"],
    "SAN JOSE": ["SAN JOSE, BATANGAS"]
}

for main, alt_list in aliases.items():
    if main in calabarzon_cities:
        for alt in alt_list:
            calabarzon_cities.add(alt)
    for alt in alt_list:
        calabarzon_cities.add(alt)

print(f"Found {len(calabarzon_cities)} municipalities in CALABARZON bounds.")

print("Loading calabarzon-municipalities.geojson...")
with open(r'c:\xampp\htdocs\asf\assets\data\calabarzon-municipalities.geojson', 'r', encoding='utf-8') as f:
    geojson = json.load(f)

filtered_features = []
for feature in geojson.get('features', []):
    props = feature.get('properties', {})
    raw_name = props.get('shapeName') or props.get('ADM3_EN') or props.get('name') or ''
    name = raw_name.upper().replace('CITY OF ', '').replace(' CITY', '').strip()
    
    if name in calabarzon_cities:
        filtered_features.append(feature)

print(f"Filtered JSON from {len(geojson.get('features', []))} down to {len(filtered_features)} CALABARZON features.")

geojson['features'] = filtered_features
with open(r'c:\xampp\htdocs\asf\assets\data\calabarzon-municipalities.geojson', 'w', encoding='utf-8') as f:
    json.dump(geojson, f)

print("Saved cleanly filtered CALABARZON geojson!")
