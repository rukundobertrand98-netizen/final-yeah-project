# Google Maps Setup for Smart Route Creation

## Quick Setup Guide

### Step 1: Get Google Maps API Key (Free)
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable these APIs:
   - **Maps JavaScript API**
   - **Geocoding API** 
   - **Directions API**
4. Create API Key credentials
5. Copy your API key

### Step 2: Configure Your Application
1. Open `.env` file in your project root
2. Find the line: `GOOGLE_MAPS_API_KEY=`
3. Add your API key: `GOOGLE_MAPS_API_KEY=your_actual_key_here`
4. Save the file
5. Run: `php artisan config:clear`

### Step 3: Test the Feature
1. Log in as Operator
2. Go to "Routes" menu  
3. You should now see Google Maps integration!

## What You Get

### ✅ With Google Maps API Key:
- **Live Google Maps** with satellite/street view
- **Smart autocomplete** for locations (powered by Google)
- **Accurate routing** using Google Directions API
- **Professional interface** with real-time location detection

### ✅ Without API Key (Fallback):
- **OpenStreetMap** (free, no signup needed)
- **Basic autocomplete** using Nominatim
- **Route calculation** using OSRM
- **All core features work** - just different map provider

## How to Use

1. **Enter Route Information** - Name, code, price, duration
2. **Select Origin** - Type location name, click suggestion
3. **Select Destination** - Type location name, click suggestion  
4. **See Route on Map** - Automatic route calculation and display
5. **Add Bus Stops** - Continue adding stops along the route (original functionality)
6. **Save Route** - Complete route with origin, destination, and stops

## Cost Information

- **Free Tier**: $200/month Google Cloud credit
- **Covers**: ~28,000 map loads + ~40,000 geocoding requests
- **Perfect for**: Small to medium bus operations
- **No charges** until you exceed free limits

## Troubleshooting

**Map not loading?**
- Check `.env` has `GOOGLE_MAPS_API_KEY` set
- Verify API key is active in Google Cloud Console
- Check browser console (F12) for errors

**Still using old interface?**
- Clear cache: `php artisan config:clear`
- Hard refresh browser (Ctrl+F5)

**Location search not working?**
- Check Geocoding API is enabled
- Verify API key has proper permissions

## Features

- ✅ **Origin/Destination first** (not based on first/last bus stop)
- ✅ **Live Google Maps** integration  
- ✅ **Smart location detection** with autocomplete
- ✅ **Route visualization** on map
- ✅ **Markers for origin/destination** 
- ✅ **Fallback support** (works without API key)
- ✅ **All existing bus stop functionality** preserved

The system automatically detects if you have Google Maps configured and uses the best available option!