# Smart Bus Stop Feature - Visual Guide

## Interface Overview

### Step 1: Route Information Screen
```
┌─────────────────────────────────────────────────────────┐
│ 📋 Step 1: Route Information                           │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Route Name: [Nyabugogo to Kimironko           ]       │
│  Route Code: [KBS-101                          ]       │
│  Ticket Price: [600                            ] RWF   │
│                                                         │
│                         [Next: Select Origin & Dest →] │
└─────────────────────────────────────────────────────────┘
```

### Step 2: Origin & Destination Selection
```
┌─────────────────────────────────────────────────────────┐
│ 📍 Step 2: Select Origin & Destination                 │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Origin:       [Nyabugogo Bus Terminal         ] 🔍    │
│                 ↓ Google Autocomplete Suggestions      │
│                                                         │
│  Destination:  [Kimironko Market               ] 🔍    │
│                 ↓ Google Autocomplete Suggestions      │
│                                                         │
│  [← Back]  [Load Route & Detect Bus Stops →]          │
└─────────────────────────────────────────────────────────┘
```

### Step 3: Interactive Map with Bus Stops
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🚏 Step 3: Select Bus Stops Along Route                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌───────────────────────────────────────────────────────────┐    │
│  │                   🗺️ GOOGLE MAPS                         │    │
│  │                                                           │    │
│  │   🟢 Nyabugogo (Origin - Dark Green)                    │    │
│  │         │                                                 │    │
│  │         ● (Gray marker - Available stop)                 │    │
│  │         │                                                 │    │
│  │         🟢 (Green marker - Selected stop)                │    │
│  │         │                                                 │    │
│  │         ─── Green route line showing bus path ───        │    │
│  │         │                                                 │    │
│  │         ● (Gray marker - Available stop)                 │    │
│  │         │                                                 │    │
│  │   🔴 Kimironko (Destination - Red)                       │    │
│  │                                                           │    │
│  └───────────────────────────────────────────────────────────┘    │
│                                                                     │
│  ┌─────────────────────────┐  ┌──────────────────────────────┐   │
│  │ 🔍 Available Stops (15) │  │ ✅ Selected Stops (3)        │   │
│  ├─────────────────────────┤  ├──────────────────────────────┤   │
│  │ ○ Nyabugogo Station     │  │ 1. Nyabugogo Station    [❌] │   │
│  │   Main bus terminal     │  │    Main bus terminal         │   │
│  │              [Add]      │  │                              │   │
│  │                         │  │ 2. Kigali City Center   [❌] │   │
│  │ ○ Kigali City Center    │  │    Downtown stop             │   │
│  │   Downtown stop         │  │                              │   │
│  │              [Add]      │  │ 3. Kimironko Market     [❌] │   │
│  │                         │  │    Final destination         │   │
│  │ ○ Remera Taxi Park      │  │                              │   │
│  │   Transit hub           │  │                              │   │
│  │              [Add]      │  │                              │   │
│  └─────────────────────────┘  └──────────────────────────────┘   │
│                                                                     │
│  [← Back]  [💾 Save Route with Selected Stops]                    │
└─────────────────────────────────────────────────────────────────────┘
```

## Color Coding

### Map Markers
- **Dark Green (🟢)** = Origin stop
- **Red (🔴)** = Destination stop  
- **Gray (●)** = Available bus stop (not selected)
- **Green (🟢)** = Selected bus stop
- **Green Line (───)** = Driving route path

### Stop Lists
- **Left List** = All detected stops along route
- **Right List** = Stops you've selected (numbered in order)
- **White background** = Available
- **Green background** = Selected

## User Interactions

### Adding a Stop
1. **Click marker on map** OR **Click "Add" button in list**
   - Marker turns green
   - Stop appears in "Selected Stops" list
   - Gets next sequential number

### Removing a Stop
1. **Click green marker again** OR **Click "Remove" button**
   - Marker turns gray
   - Stop removed from "Selected Stops" list
   - Numbers recalculate automatically

## Loading States

### While Detecting Stops
```
┌─────────────────────────────────┐
│                                 │
│         ⟳ (Spinning)            │
│                                 │
│  Detecting bus stops along      │
│  route...                       │
│                                 │
└─────────────────────────────────┘
```

### When Complete
- Loading overlay disappears
- Map shows all detected stops
- Lists populate with stops
- Ready for selection

## Responsive Design

### Desktop (>1024px)
- Two-column layout for stop lists
- Full-size map (500px height)
- All controls visible

### Tablet (768px - 1024px)
- Stacked layout for stop lists
- Medium map (400px height)
- Touch-friendly buttons

### Mobile (<768px)
- Single column
- Compact map (300px height)
- Large touch targets
- Swipeable lists

## Error States

### No API Key
```
⚠️ Google Maps integration not configured.
   Please contact administrator.
   [Use Basic Entry Mode]
```

### No Stops Detected
```
ℹ️ No bus stops detected along this route.
   Try selecting different locations or
   contact support.
```

### Network Error
```
❌ Could not load route. Please check your
   internet connection and try again.
   [Retry]
```

## Success State

### After Saving
```
✅ Route created successfully!
   - 5 bus stops added
   - Route: Nyabugogo to Kimironko
   - Code: KBS-101
   
   [View Route] [Create Another]
```
