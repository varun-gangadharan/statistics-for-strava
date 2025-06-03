import React, { useEffect, useState } from 'react';
import {
  Box,
  Typography,
  Paper,
  Grid,
  Avatar,
  List,
  ListItem,
  ListItemText,
  ListItemIcon,
  Divider,
  CircularProgress,
} from '@mui/material';
import {
  Speed as SpeedIcon,
  Timeline as TimelineIcon,
  Terrain as TerrainIcon,
  EmojiEvents as TrophyIcon,
  CalendarMonth as CalendarIcon,
} from '@mui/icons-material';
import stravaService from '../services/stravaService';
import { useNavigate } from 'react-router-dom';
import { useSettings } from '../context/SettingsContext';

const Profile = () => {
  const [athlete, setAthlete] = useState(null);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const { convertDistance, getDistanceUnit, convertElevation, getElevationUnit } = useSettings();

  useEffect(() => {
    const fetchData = async () => {
      try {
        const athleteData = await stravaService.getAthlete();
        setAthlete(athleteData);
        const statsData = await stravaService.getStats(athleteData.id);
        setStats(statsData);
      } catch (error) {
        console.error('Error fetching profile data:', error);
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

  if (!athlete || !stats) {
    return null;
  }

  const achievements = [
    stats.biggest_ride_distance > 50000 ? 'Half Century Rider' : null,
    stats.biggest_climb_elevation_gain > 1000 ? 'Mountain Goat' : null,
    stats.all_run_totals.count > 100 ? '100 Runs Club' : null,
    stats.all_ride_totals.distance > 1000000 ? '1000km Club' : null,
  ].filter(Boolean);

  return (
    <Box sx={{ flexGrow: 1, mt: 4 }}>
      <Grid container spacing={3}>
        <Grid item xs={12} md={4}>
          <Paper sx={{ p: 3, textAlign: 'center' }}>
            <Avatar
              src={athlete.profile}
              sx={{
                width: 150,
                height: 150,
                margin: '0 auto',
                mb: 2,
                border: 3,
                borderColor: 'primary.main',
              }}
            />
            <Typography variant="h5" gutterBottom>
              {athlete.firstname} {athlete.lastname}
            </Typography>
            <Typography color="text.secondary" gutterBottom>
              {athlete.city}, {athlete.country}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Member since {new Date(athlete.created_at).toLocaleDateString()}
            </Typography>
          </Paper>
        </Grid>

        <Grid item xs={12} md={8}>
          <Paper sx={{ p: 3 }}>
            <Typography variant="h6" gutterBottom>
              Statistics
            </Typography>
            <List>
              <ListItem>
                <ListItemIcon>
                  <TimelineIcon color="primary" />
                </ListItemIcon>
                <ListItemText
                  primary="Total Distance"
                  secondary={`${convertDistance(stats.all_run_totals.distance / 1000)} ${getDistanceUnit()}`}
                />
              </ListItem>
              <Divider />
              <ListItem>
                <ListItemIcon>
                  <TerrainIcon color="primary" />
                </ListItemIcon>
                <ListItemText
                  primary="Total Elevation Gain"
                  secondary={`${convertElevation(stats.all_run_totals.elevation_gain)} ${getElevationUnit()}`}
                />
              </ListItem>
              <Divider />
              <ListItem>
                <ListItemIcon>
                  <CalendarIcon color="primary" />
                </ListItemIcon>
                <ListItemText
                  primary="Total Activities"
                  secondary={stats.all_run_totals.count}
                />
              </ListItem>
              <Divider />
              <ListItem>
                <ListItemIcon>
                  <SpeedIcon color="primary" />
                </ListItemIcon>
                <ListItemText
                  primary="Recent Run Distance"
                  secondary={`${convertDistance(stats.recent_run_totals.distance / 1000)} ${getDistanceUnit()} in last 4 weeks`}
                />
              </ListItem>
            </List>
          </Paper>

          {achievements.length > 0 && (
            <Paper sx={{ p: 3, mt: 3 }}>
              <Typography variant="h6" gutterBottom>
                Achievements
              </Typography>
              <List>
                {achievements.map((achievement, index) => (
                  <ListItem key={index}>
                    <ListItemIcon>
                      <TrophyIcon color="secondary" />
                    </ListItemIcon>
                    <ListItemText primary={achievement} />
                  </ListItem>
                ))}
              </List>
            </Paper>
          )}
        </Grid>
      </Grid>
    </Box>
  );
};

export default Profile; 