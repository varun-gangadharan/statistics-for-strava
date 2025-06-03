import React, { useEffect, useState } from 'react';
import {
  Grid,
  Paper,
  Typography,
  Box,
  Card,
  CardContent,
  CircularProgress,
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
} from 'recharts';
import stravaService from '../services/stravaService';
import { useNavigate } from 'react-router-dom';
import { useSettings } from '../context/SettingsContext';

const StatCard = ({ title, value, unit }) => (
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
    </CardContent>
  </Card>
);

const Dashboard = () => {
  const [stats, setStats] = useState(null);
  const [activities, setActivities] = useState([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const { convertDistance, getDistanceUnit, convertElevation, getElevationUnit } = useSettings();

  useEffect(() => {
    const fetchData = async () => {
      try {
        const athleteData = await stravaService.getAthlete();
        const statsData = await stravaService.getStats(athleteData.id);
        setStats(statsData);
        
        const activitiesData = await stravaService.getActivities(1, 7);
        
        // Aggregate activities by day
        const dailyActivities = activitiesData.reduce((acc, activity) => {
          const date = new Date(activity.start_date_local).toLocaleDateString(undefined, { weekday: 'short' });
          if (!acc[date]) {
            acc[date] = {
              name: date,
              distance: 0,
              activities: 0,
            };
          }
          acc[date].distance += activity.distance / 1000;
          acc[date].activities += 1;
          return acc;
        }, {});

        // Convert to array and sort by date
        const processedActivities = Object.values(dailyActivities)
          .map(day => ({
            ...day,
            distance: convertDistance(day.distance),
          }));
        
        setActivities(processedActivities);
      } catch (error) {
        console.error('Error fetching dashboard data:', error);
        if (error.message === 'No access token available') {
          navigate('/login');
        }
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [navigate, convertDistance]);

  if (loading || !stats) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '80vh' }}>
        <CircularProgress />
      </Box>
    );
  }

  const recentRuns = stats.recent_run_totals;
  const allRuns = stats.all_run_totals;

  return (
    <Box sx={{ flexGrow: 1, mt: 4 }}>
      <Typography variant="h4" gutterBottom>
        Dashboard
      </Typography>
      
      <Grid container spacing={3}>
        <Grid item xs={12} md={3}>
          <StatCard 
            title="Recent Distance" 
            value={convertDistance(recentRuns.distance / 1000)} 
            unit={getDistanceUnit()}
          />
        </Grid>
        <Grid item xs={12} md={3}>
          <StatCard 
            title="Recent Activities" 
            value={recentRuns.count} 
            unit="runs"
          />
        </Grid>
        <Grid item xs={12} md={3}>
          <StatCard 
            title="Total Distance" 
            value={convertDistance(allRuns.distance / 1000)} 
            unit={getDistanceUnit()}
          />
        </Grid>
        <Grid item xs={12} md={3}>
          <StatCard 
            title="Total Time" 
            value={Math.round(allRuns.moving_time / 3600)} 
            unit="hours"
          />
        </Grid>

        <Grid item xs={12} md={8}>
          <Paper sx={{ p: 2 }}>
            <Typography variant="h6" gutterBottom>
              Recent Activities
            </Typography>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={activities}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="name" />
                <YAxis />
                <Tooltip 
                  formatter={(value, name, props) => [
                    `${value} ${getDistanceUnit()}${props.payload.activities > 1 ? ` (${props.payload.activities} activities)` : ''}`
                  ]}
                />
                <Bar 
                  dataKey="distance" 
                  name={`Distance (${getDistanceUnit()})`} 
                  fill="#FF5722" 
                />
              </BarChart>
            </ResponsiveContainer>
          </Paper>
        </Grid>

        <Grid item xs={12} md={4}>
          <Paper sx={{ p: 2, height: '100%' }}>
            <Typography variant="h6" gutterBottom>
              Weekly Progress
            </Typography>
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={activities}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="name" />
                <YAxis />
                <Tooltip 
                  formatter={(value, name, props) => [
                    `${value} ${getDistanceUnit()}${props.payload.activities > 1 ? ` (${props.payload.activities} activities)` : ''}`
                  ]}
                />
                <Line
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
      </Grid>
    </Box>
  );
};

export default Dashboard; 