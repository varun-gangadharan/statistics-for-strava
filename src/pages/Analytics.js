import React, { useEffect, useState } from 'react';
import {
  Grid,
  Paper,
  Typography,
  Box,
  CircularProgress,
  Card,
  CardContent,
  ToggleButtonGroup,
  ToggleButton,
} from '@mui/material';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  LineChart,
  Line,
  PieChart,
  Pie,
  Cell,
  ScatterChart,
  Scatter,
  ZAxis,
  Legend,
} from 'recharts';
import { useNavigate } from 'react-router-dom';
import stravaService from '../services/stravaService';
import { useSettings } from '../context/SettingsContext';

const COLORS = ['#FF5722', '#4CAF50', '#2196F3', '#FFC107', '#9C27B0'];

const StatCard = ({ title, value, unit, subtitle }) => (
  <Card sx={{ height: '100%' }}>
    <CardContent>
      <Typography color="text.secondary" gutterBottom>
        {title}
      </Typography>
      <Typography variant="h4" component="div">
        {value}
        <Typography component="span" variant="body1" sx={{ ml: 1 }}>
          {unit}
        </Typography>
      </Typography>
      {subtitle && (
        <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
          {subtitle}
        </Typography>
      )}
    </CardContent>
  </Card>
);

const Analytics = () => {
  const [timeRange, setTimeRange] = useState('year');
  const [activities, setActivities] = useState([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const { convertDistance, getDistanceUnit, convertElevation, getElevationUnit } = useSettings();

  useEffect(() => {
    const fetchData = async () => {
      try {
        const activitiesData = await stravaService.getActivities(1, 100);
        setActivities(activitiesData);
      } catch (error) {
        console.error('Error fetching analytics data:', error);
        if (error.message === 'No access token available') {
          navigate('/login');
        }
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [navigate]);

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '80vh' }}>
        <CircularProgress />
      </Box>
    );
  }

  // Aggregate activities by day
  const dailyActivities = activities.reduce((acc, activity) => {
    const date = new Date(activity.start_date_local).toLocaleDateString();
    if (!acc[date]) {
      acc[date] = {
        date,
        distance: 0,
        elevation: 0,
        time: 0,
        calories: 0,
        activities: 0,
        heartrate: [],
      };
    }
    acc[date].distance += activity.distance / 1000;
    acc[date].elevation += activity.total_elevation_gain;
    acc[date].time += activity.moving_time;
    acc[date].calories += activity.calories || 0;
    acc[date].activities += 1;
    if (activity.average_heartrate) {
      acc[date].heartrate.push(activity.average_heartrate);
    }
    return acc;
  }, {});

  // Calculate lifetime stats
  const totalDistance = activities.reduce((sum, activity) => sum + activity.distance, 0) / 1000;
  const totalElevation = activities.reduce((sum, activity) => sum + activity.total_elevation_gain, 0);
  const totalTime = activities.reduce((sum, activity) => sum + activity.moving_time, 0);

  // Remove calories calculation since it's not reliable
  const processedData = Object.values(dailyActivities).map(day => ({
    date: day.date,
    distance: convertDistance(day.distance),
    elevation: convertElevation(day.elevation),
    time: day.time / 3600, // Convert to hours
    activities: day.activities,
    heartrate: day.heartrate.length > 0 
      ? Math.round(day.heartrate.reduce((a, b) => a + b) / day.heartrate.length)
      : null
  }));

  // Activity type distribution
  const activityTypes = activities.reduce((acc, activity) => {
    acc[activity.type] = (acc[activity.type] || 0) + 1;
    return acc;
  }, {});

  const pieData = Object.entries(activityTypes).map(([name, value]) => ({
    name,
    value,
  }));

  // Monthly distance data (aggregated by day)
  const monthlyData = Object.values(dailyActivities).reduce((acc, day) => {
    const date = new Date(day.date);
    const monthYear = date.toLocaleString('default', { month: 'short', year: '2-digit' });
    acc[monthYear] = (acc[monthYear] || 0) + day.distance;
    return acc;
  }, {});

  const monthlyChartData = Object.entries(monthlyData)
    .map(([name, distance]) => ({
      name,
      distance: convertDistance(distance),
    }))
    .slice(-12);

  // Time of day distribution
  const timeDistribution = activities.reduce((acc, activity) => {
    const hour = new Date(activity.start_date_local).getHours();
    const timeSlot = hour < 6 ? 'Night (0-6)'
      : hour < 12 ? 'Morning (6-12)'
      : hour < 18 ? 'Afternoon (12-18)'
      : 'Evening (18-24)';
    acc[timeSlot] = (acc[timeSlot] || 0) + 1;
    return acc;
  }, {});

  const timeDistributionData = Object.entries(timeDistribution).map(([name, value]) => ({
    name,
    value,
  }));

  // Heart Rate Zones Analysis
  const hrZones = activities.reduce((acc, activity) => {
    if (activity.average_heartrate) {
      const zone = activity.average_heartrate < 130 ? 'Easy (< 130)'
        : activity.average_heartrate < 150 ? 'Aerobic (130-150)'
        : activity.average_heartrate < 170 ? 'Tempo (150-170)'
        : 'High Intensity (170+)';
      acc[zone] = (acc[zone] || 0) + 1;
    }
    return acc;
  }, {});

  const hrZonesData = Object.entries(hrZones).map(([name, value]) => ({
    name,
    value,
  }));

  // Training Load Analysis
  const trainingLoadData = Object.values(dailyActivities)
    .slice(-14) // Last 14 days
    .map(day => {
      // Calculate training load based on time and intensity (using HR if available)
      const avgHR = day.heartrate.length > 0 
        ? day.heartrate.reduce((sum, hr) => sum + hr, 0) / day.heartrate.length 
        : 0;
      const intensity = avgHR > 0 ? avgHR / 180 : 0.8; // Default to moderate intensity if no HR
      const load = (day.time / 3600) * intensity * 100;
      
      return {
        date: day.date,
        load: Math.round(load),
        distance: convertDistance(day.distance),
        activities: day.activities,
      };
    });

  // Effort vs Recovery Analysis (Scatter plot)
  const effortData = activities
    .filter(activity => activity.average_heartrate && activity.moving_time > 0)
    .map(activity => ({
      duration: activity.moving_time / 60, // minutes
      heartrate: activity.average_heartrate,
      distance: convertDistance(activity.distance / 1000),
    }));

  return (
    <Box sx={{ flexGrow: 1, mt: 4 }}>
      <Typography variant="h4" gutterBottom>
        Advanced Analytics
      </Typography>

      <Grid container spacing={3}>
        {/* Lifetime Stats */}
        <Grid item xs={12} md={4}>
          <StatCard
            title="Lifetime Distance"
            value={convertDistance(totalDistance)}
            unit={getDistanceUnit()}
            subtitle="Total distance covered"
          />
        </Grid>
        <Grid item xs={12} md={4}>
          <StatCard
            title="Total Elevation"
            value={convertElevation(totalElevation)}
            unit={getElevationUnit()}
            subtitle="Total climbing"
          />
        </Grid>
        <Grid item xs={12} md={4}>
          <StatCard
            title="Total Time"
            value={Math.round(totalTime / 3600)}
            unit="hours"
            subtitle="Time in motion"
          />
        </Grid>

        {/* Monthly Distance Trend */}
        <Grid item xs={12} md={8}>
          <Paper sx={{ p: 2 }}>
            <Typography variant="h6" gutterBottom>
              Monthly Distance Trend
            </Typography>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={monthlyChartData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="name" />
                <YAxis />
                <Tooltip />
                <Legend />
                <Bar dataKey="distance" name={`Distance (${getDistanceUnit()})`} fill="#FF5722" />
              </BarChart>
            </ResponsiveContainer>
          </Paper>
        </Grid>

        {/* Activity Type Distribution */}
        <Grid item xs={12} md={4}>
          <Paper sx={{ p: 2 }}>
            <Typography variant="h6" gutterBottom>
              Activity Types
            </Typography>
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie
                  data={pieData}
                  dataKey="value"
                  nameKey="name"
                  cx="50%"
                  cy="50%"
                  outerRadius={80}
                  label
                >
                  {pieData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
                <Legend />
              </PieChart>
            </ResponsiveContainer>
          </Paper>
        </Grid>

        {/* Training Load */}
        <Grid item xs={12} md={6}>
          <Paper sx={{ p: 2 }}>
            <Typography variant="h6" gutterBottom>
              Training Load (14-day trend)
            </Typography>
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={trainingLoadData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="date" />
                <YAxis yAxisId="left" />
                <YAxis yAxisId="right" orientation="right" />
                <Tooltip />
                <Legend />
                <Line
                  yAxisId="left"
                  type="monotone"
                  dataKey="load"
                  name="Training Load"
                  stroke="#FF5722"
                  strokeWidth={2}
                />
                <Line
                  yAxisId="right"
                  type="monotone"
                  dataKey="distance"
                  name={`Distance (${getDistanceUnit()})`}
                  stroke="#4CAF50"
                  strokeWidth={2}
                />
              </LineChart>
            </ResponsiveContainer>
          </Paper>
        </Grid>

        {/* Heart Rate Zones */}
        <Grid item xs={12} md={6}>
          <Paper sx={{ p: 2 }}>
            <Typography variant="h6" gutterBottom>
              Heart Rate Zones Distribution
            </Typography>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={hrZonesData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="name" />
                <YAxis />
                <Tooltip />
                <Bar dataKey="value" name="Activities" fill="#2196F3" />
              </BarChart>
            </ResponsiveContainer>
          </Paper>
        </Grid>

        {/* Effort Analysis */}
        <Grid item xs={12}>
          <Paper sx={{ p: 2 }}>
            <Typography variant="h6" gutterBottom>
              Effort Analysis (Duration vs Heart Rate)
            </Typography>
            <ResponsiveContainer width="100%" height={400}>
              <ScatterChart>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis 
                  dataKey="duration" 
                  name="Duration" 
                  unit=" min"
                  type="number"
                />
                <YAxis 
                  dataKey="heartrate" 
                  name="Heart Rate" 
                  unit=" bpm"
                />
                <ZAxis 
                  dataKey="distance" 
                  name="Distance" 
                  unit={` ${getDistanceUnit()}`}
                />
                <Tooltip cursor={{ strokeDasharray: '3 3' }} />
                <Legend />
                <Scatter 
                  name="Activities" 
                  data={effortData} 
                  fill="#FF5722"
                />
              </ScatterChart>
            </ResponsiveContainer>
          </Paper>
        </Grid>
      </Grid>
    </Box>
  );
};

export default Analytics; 