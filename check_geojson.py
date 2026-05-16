import json

try:
    with open(r'c:\xampp\htdocs\asf\assets\data\calabarzon-municipalities.geojson', 'r', encoding='utf-8') as f:
        data = json.load(f)
        if 'features' in data and len(data['features']) > 0:
            properties = data['features'][0]['properties']
            print("\nKeys:", list(properties.keys()))
            print("\nFirst feature properties:", properties)
except Exception as e:
    print("Error:", e)
