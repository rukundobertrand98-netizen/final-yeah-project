# Quick Start Checklist - Smart Bus Stops Feature

## ✅ Setup Checklist (One-Time)

### Step 1: Get Google Maps API Key
- [ ] Go to [Google Cloud Console](https://console.cloud.google.com/)
- [ ] Create new project (or select existing)
- [ ] Enable these APIs:
  - [ ] Maps JavaScript API
  - [ ] Places API
  - [ ] Directions API
  - [ ] Geocoding API
- [ ] Create API Key credential
- [ ] (Optional) Restrict API key to your domain
- [ ] Copy the API key

### Step 2: Configure Your Application
- [ ] Open `.env` file in your project root
- [ ] Find the line: `GOOGLE_MAPS_API_KEY=`
- [ ] Paste your API key after the `=` sign
- [ ] Save the file
- [ ] Clear cache: `php artisan config:clear`

### Step 3: Test the Feature
- [ ] Log in as Operator
- [ ] Go to "Routes" menu
- [ ] Verify you see the new 3-step interface
- [ ] Test creating a route with Google Maps

## ✅ Using the Feature (Every Time)

### Creating a New Route
1. [ ] Click "Routes" in operator menu
2. [ ] Fill in route name (e.g., "Nyabugogo to Kimironko")
3. [ ] Fill in route code (e.g., "KBS-101")
4. [ ] Enter ticket price (e.g., 600 RWF)
5. [ ] Click "Next"
6. [ ] Search and select Origin location
7. [ ] Search and select Destination location
8. [ ] Click "Load Route & Detect Bus Stops"
9. [ ] Wait for stops to load (5-10 seconds)
10. [ ] Click markers on map to select stops
11. [ ] Verify at least 2 stops are selected
12. [ ] Click "Save Route"

## ✅ Troubleshooting

### Map Not Loading
- [ ] Check `.env` has `GOOGLE_MAPS_API_KEY` set
- [ ] Run: `php artisan config:clear`
- [ ] Check browser console (F12) for errors
- [ ] Verify API key is active in Google Cloud Console

### No Stops Detected
- [ ] Try more well-known locations (e.g., "Nyabugogo Bus Terminal")
- [ ] Check Places API is enabled
- [ ] Verify route is in Kigali area
- [ ] Try shorter route distance

### "API Key Error" Message
- [ ] Verify all 4 APIs are enabled in Google Cloud Console
- [ ] Check billing is enabled (required for free tier)
- [ ] Verify API key hasn't been restricted too much
- [ ] Check API key quotas haven't been exceeded

### Markers Not Clickable
- [ ] Wait for loading to complete
- [ ] Zoom in on map to see markers more clearly
- [ ] Refresh the page and try again

## ✅ Best Practices

### For Accurate Results
- [ ] Use official place names (e.g., "Nyabugogo Bus Station" not "nyabugogo")
- [ ] Select stops in the order bus will visit them
- [ ] Include major stops/landmarks passengers know
- [ ] Verify stop names are recognizable

### For System Performance
- [ ] Don't create duplicate routes
- [ ] Test with short routes first
- [ ] Monitor API usage in Google Cloud Console
- [ ] Set up billing alerts

## ✅ Common Questions

**Q: Is this feature free?**
A: Google Maps has $200/month free tier (usually sufficient for small operations). After that, costs apply.

**Q: What if I don't want to use Google Maps?**
A: The system automatically falls back to the old OpenStreetMap interface if no API key is configured.

**Q: Can I edit routes later?**
A: Yes, click "Edit" on any route to modify stops using the same interface.

**Q: How many stops can I add?**
A: Minimum 2 stops required. No maximum limit, but routes with 5-15 stops are typical.

**Q: Will this work on mobile?**
A: Yes, the interface is fully responsive and works on tablets and phones.

**Q: What if detected stops have wrong names?**
A: Stop names come from Google's database. You can manually edit them later in the route editor.

**Q: Can I add custom stops not detected by Google?**
A: Currently no. This feature only shows stops detected by Google Maps. Contact admin for custom stops.

## ✅ Support Resources

- **Setup Guide:** See `GOOGLE_MAPS_SETUP.md`
- **User Guide:** See `HOW_TO_USE_SMART_BUS_STOPS.md`
- **Technical Details:** See `SMART_BUS_STOPS_IMPLEMENTATION.md`
- **Visual Guide:** See `FEATURE_VISUAL_GUIDE.md`

## ✅ Verification

After setup, you should see:
- ✅ New 3-step wizard interface
- ✅ Google Maps autocomplete for locations
- ✅ Interactive map with route line
- ✅ Clickable markers for bus stops
- ✅ Two lists: Available Stops and Selected Stops
- ✅ Real-time marker color changes
- ✅ Automatic stop ordering

If you see all of the above, congratulations! The feature is working correctly. 🎉

## Next Steps

1. Create your first route using the new interface
2. Train other operators on how to use it
3. Monitor Google Maps API usage
4. Provide feedback for future improvements
