# Google Maps Setup Instructions

## Overview
The new Smart Bus Stop functionality uses Google Maps APIs to automatically detect and display bus stops along your route.

## Required APIs
1. **Google Maps JavaScript API** - For displaying the map
2. **Google Places API** - For finding bus stops and transit stations
3. **Google Directions API** - For calculating routes
4. **Google Geocoding API** - For address lookups
5. **Google Geometry Library** - For distance calculations

## Setup Steps

### 1. Get Google Maps API Key

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the following APIs:
   - Maps JavaScript API
   - Places API
   - Directions API
   - Geocoding API
4. Create credentials (API Key)
5. Restrict your API key (recommended for production):
   - HTTP referrers: Add your domain (e.g., `yourdomain.com/*`)
   - API restrictions: Select the APIs listed above

### 2. Configure Your Application

1. Open your `.env` file
2. Add your Google Maps API key:
   ```
   GOOGLE_MAPS_API_KEY=YOUR_ACTUAL_API_KEY_HERE
   ```
3. Save the file

### 3. Test the Feature


1. Log in as an Operator
2. Go to "Routes" menu
3. You should now see the new interface with Google Maps
4. Follow the 3-step process:
   - Step 1: Enter route information
   - Step 2: Select Origin and Destination (use Google autocomplete)
   - Step 3: Click detected bus stop markers on the map to add them

## Features

### Smart Bus Stop Detection
- Automatically finds all bus stops, transit stations, and transport points along your route
- Filters to show only stops between origin and destination
- Visual markers on Google Maps

### Click-to-Add Interface
- Click any bus stop marker on the map to add it to your route
- Selected markers turn green
- Stops are automatically ordered
- Click again to remove a stop

### Automatic Data Capture
- Stop name from Google Places
- Exact latitude/longitude coordinates
- Stop order automatically assigned
- No manual data entry required

### Duplicate Prevention
- System prevents adding the same stop twice to a route
- Each stop is identified by unique Google Place ID

### Real-time Visualization
- See the full route on Google Maps
- Route line shows the driving path
- Origin (dark green), Destination (red), intermediate stops (gray)


## Fallback Mode

If you don't have a Google Maps API key configured, the system will automatically fall back to the old interface using OpenStreetMap (free, no API key required).

## Cost Considerations

Google Maps APIs have a free tier:
- **$200 free credit per month** (enough for ~28,000 map loads or ~40,000 place searches)
- After free tier, costs apply per API call

For a small to medium transport operation, the free tier should be sufficient.

## Troubleshooting

### "Map not loading"
- Check that `GOOGLE_MAPS_API_KEY` is set in `.env`
- Verify the API key is active in Google Cloud Console
- Check browser console for specific error messages

### "No bus stops detected"
- Try a different route with more well-known locations
- Ensure origin and destination are in Kigali area
- Check that Places API is enabled for your key

### "API key errors"
- Verify all required APIs are enabled in Google Cloud Console
- Check API key restrictions aren't blocking requests
- Ensure billing is enabled (required even for free tier)

## Security Note

Never commit your actual API key to version control. Always use environment variables (`.env` file).
