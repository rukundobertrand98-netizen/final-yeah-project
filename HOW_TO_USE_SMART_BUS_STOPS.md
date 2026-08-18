# How to Use Smart Bus Stop Selection

## Overview
The new Smart Bus Stop feature makes it incredibly easy to create routes by automatically detecting all bus stops along your chosen path using Google Maps.

## Step-by-Step Guide

### Step 1: Enter Route Information
1. Enter a **Route Name** (e.g., "Nyabugogo to Kimironko Express")
2. Enter a **Route Code** (e.g., "KBS-101")
3. Enter **Ticket Price** in RWF (e.g., 600)
4. Click **"Next: Select Origin & Destination"**

### Step 2: Select Origin and Destination
1. Click in the **Origin** field
2. Start typing the starting location (e.g., "Nyabugogo")
3. Select from Google's autocomplete suggestions
4. Click in the **Destination** field
5. Start typing the ending location (e.g., "Kimironko")
6. Select from Google's autocomplete suggestions
7. Click **"Load Route & Detect Bus Stops"**

### Step 3: Select Bus Stops
The system will now:
- Draw the route on Google Maps
- Automatically detect all bus stops, transit stations along the route
- Display markers for each detected stop
- Show two lists: Available Stops and Selected Stops

#### To Add a Bus Stop:
**Option 1:** Click the marker on the map
**Option 2:** Click the stop in the "Available Bus Stops" list

The marker will turn **green** and appear in your "Selected Stops" list.

#### To Remove a Bus Stop:
**Option 1:** Click the green marker on the map again
**Option 2:** Click "Remove" button in the "Selected Stops" list

The marker will return to **gray** color.

#### Stop Order
- Stops are automatically ordered 1, 2, 3... in the sequence you add them
- Stop order is recalculated automatically when you remove stops
- You should select stops in the order the bus will visit them

### Step 4: Save the Route
1. Review your selected stops in the right panel
2. Ensure you have at least **2 stops** selected
3. Click **"Save Route with Selected Stops"**

Done! Your route is now created with all bus stops automatically configured.

## Key Features

### ✅ Automatic Detection
The system searches for:
- Official bus stops
- Transit stations
- Transport hubs
- Public transport points

### ✅ Smart Filtering
Only shows stops that are:
- Located along or very close to the route
- Between origin and destination
- Relevant to public transport

### ✅ No Manual Entry
You don't need to enter:
- Stop coordinates (latitude/longitude)
- Stop order numbers
These are captured automatically when you click a marker.

### ✅ Duplicate Prevention
The system won't let you add the same stop twice to a route.

### ✅ Visual Feedback
- **Gray markers** = Available stops
- **Green markers** = Selected stops
- **Green route line** = The driving path
- **Numbers in list** = Stop order (1, 2, 3...)

## Tips for Best Results

1. **Use well-known landmarks** as origin/destination for better autocomplete results
2. **Select stops in order** - add them in the sequence the bus will visit them
3. **Review the map** - ensure the detected stops make sense for your route
4. **Add major stops first** - you can always edit the route later to add more

## What If No Stops Are Detected?

If the system doesn't find bus stops:
1. Try using more specific location names
2. Check that your origin and destination are correct
3. The route might be in an area with few registered transit points
4. You can still use the old manual entry method by asking your admin

## Manual Stop Name Override

While you cannot manually enter coordinates (they're auto-detected for accuracy), you CAN customize the stop name after creation by editing the route later.

## Need Help?

Contact your system administrator if:
- The map doesn't load
- No stops are being detected on known bus routes
- You need to add a stop that isn't detected
