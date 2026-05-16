import json
import pandas as pd
import xgboost as xgb
from datetime import datetime

# LOAD MODEL
model = xgb.XGBClassifier()
model.load_model(r"ml\asf_zone_modelnew.json")

zone_labels = {
    0: "free",
    1: "surveillance",
    2: "buffer",
    3: "infected"
}

with open(r"ml\input.json", "r") as f:
    input_data = json.load(f)

results = []

for row in input_data:

    future_date = datetime.strptime(
        row["future_date"],
        "%Y-%m-%d"
    )

    features = pd.DataFrame([{
        "latitude": row["latitude"],
        "longitude": row["longitude"],
        "year": future_date.year,
        "month": future_date.month,
        "day": future_date.day
    }])

    # MODEL PREDICTION
    prediction = int(model.predict(features)[0])

    # -----------------------------------
    # CONVERT NUMBER -> STRING HERE
    # -----------------------------------

    zone_type = zone_labels.get(prediction, "free")

    results.append({
        "city": row["city"],
        "barangay": row["barangay"],
        "latitude": row["latitude"],
        "longitude": row["longitude"],
        "zone_type": zone_type
    })

# -----------------------------------
# SAVE OUTPUT
# -----------------------------------

with open(r"ml\predictions.json", "w") as f:
    json.dump(results, f, indent=4)

print("Predictions complete")