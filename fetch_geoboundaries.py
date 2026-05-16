import requests
import json

api_url = "https://www.geoboundaries.org/api/current/gbOpen/PHL/ADM3/"
try:
    print("Calling geoBoundaries API for PHL ADM3 (Municipalities)...")
    r = requests.get(api_url)
    if r.status_code == 200:
        data = r.json()
        geojson_url = data.get('simplifiedGeometryGeoJSON')
        
        if geojson_url:
            print(f"Downloading from {geojson_url} ...")
            dl = requests.get(geojson_url)
            if dl.status_code == 200:
                with open(r'c:\xampp\htdocs\asf\assets\data\calabarzon-municipalities.geojson', 'w', encoding='utf-8') as f:
                    f.write(dl.text)
                print("Saved geoBoundaries successfully! Coastlines are usually clipped here.")
            else:
                print(f"Failed download: {dl.status_code}")
        else:
            print(f"No GeoJSON URL in response: {data}")
    else:
        # If ADM3 doesn't exist, try ADM2 (Provinces or Municipalities depending on their mapping)
        print(f"ADM3 failed with {r.status_code}. Trying ADM2...")
        r2 = requests.get("https://www.geoboundaries.org/api/current/gbOpen/PHL/ADM2/")
        data2 = r2.json()
        geojson_url2 = data2.get('simplifiedGeometryGeoJSON')
        if geojson_url2:
            print(f"Downloading ADM2 from {geojson_url2} ...")
            dl2 = requests.get(geojson_url2)
            with open(r'c:\xampp\htdocs\asf\assets\data\calabarzon-municipalities.geojson', 'w', encoding='utf-8') as f:
                f.write(dl2.text)
            print("Saved ADM2 geoBoundaries successfully!")
except Exception as e:
    print(f"Error: {e}")
