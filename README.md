# Statistics for Strava

A data-driven training platform that leverages Strava's API to provide advanced analytics and generate personalized training plans using statistical modeling and exercise science principles.

## Features

- 📊 Advanced activity analytics and visualizations
- 📈 Training load calculation using exponential decay algorithm
- 🎯 Race time predictions using modified Riegel formula
- 📅 Periodized training plans based on sports science principles
- 📱 Responsive Material-UI design

## Technical Implementation

### Core Algorithms

#### Training Load Calculation
```javascript
load = Σ(activity_effort * e^(-days_ago/7))
```
- Exponential decay algorithm with 7-day half-life constant
- Activity effort calculated as: `(distance_km * (heart_rate/150))`
- Normalized to account for different activity types and intensities

#### Race Time Prediction
```javascript
T2 = T1 * (D2/D1)^1.06  // Riegel formula
adjustment_factor = {
  'finish': 1.05,  // Conservative
  'pr': 0.95,      // Aggressive
  'compete': 0.90   // Very aggressive
}
```
- Distance tolerance: ±2% for GPS accuracy
- Performance weighting: Recent 6 months prioritized
- Goal-based adjustments applied to base prediction

#### Training Plan Periodization
- Build Phase (70%): Progressive overload with 10% weekly cap
- Peak Phase (20%): Volume maintenance with intensity increase
- Taper Phase (10%): Strategic volume reduction (40% over period)

## Setup Instructions

### Prerequisites
- Node.js (v14+)
- npm or yarn
- Strava API access

### 1. Strava API Setup
1. Go to [Strava API Settings](https://www.strava.com/settings/api)
2. Create new application
3. Note your Client ID and Client Secret
4. Set Authorization Callback Domain:
   - Development: `localhost:3000`
   - Production: Your domain

### 2. Local Development Setup
```bash
# Clone repository
git clone https://github.com/yourusername/statistics-for-strava.git
cd statistics-for-strava

# Install dependencies
npm install

# Create and configure environment variables
cp .env.example .env
# Edit .env with your Strava API credentials

# Start development server
npm start
```

### 3. Production Deployment

#### Vercel Deployment (Recommended)
1. Fork this repository
2. Create account on [Vercel](https://vercel.com)
3. New Project → Import Git Repository
4. Configure environment variables:
   - `REACT_APP_STRAVA_CLIENT_ID`
   - `REACT_APP_STRAVA_CLIENT_SECRET`
   - `REACT_APP_REDIRECT_URI` (your-domain.com/callback)
5. Deploy

#### Manual Deployment
```bash
# Build production bundle
npm run build

# Serve using your preferred hosting solution
# Example with serve package:
npm install -g serve
serve -s build
```

## Architecture

### Data Flow
```
Strava API → Activity Processing → Statistical Analysis → Plan Generation
```

### Key Components
- `TrainingPlan.js`: Core plan generation logic
- `Analytics.js`: Statistical analysis and visualizations
- `Records.js`: Performance tracking and PR detection
- `stravaService.js`: API integration and data fetching

### State Management
- React Context for global settings
- Local state for component-specific data
- Strava OAuth handling for authentication

## Demo

[Add your deployed version link here]

## Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

## License

MIT License - See LICENSE file for details

## Acknowledgments

- Built with [React](https://reactjs.org/)
- UI components from [Material-UI](https://mui.com/)
- Data from [Strava API](https://developers.strava.com/)

## Contact

Your Name - [your.email@example.com](mailto:your.email@example.com)

Project Link: [https://github.com/yourusername/statistics-for-strava](https://github.com/yourusername/statistics-for-strava)

